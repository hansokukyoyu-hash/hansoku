<?php

namespace App\Services;

use App\Enums\Platform;
use App\Models\Account;
use App\Models\Brand;
use App\Models\DailyMetric;
use App\Models\ManualEntry;
use App\Models\Post;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * 期間と集計モードを受けて、ブランド × SNS の閲覧数を組み立てる。
 *
 * モード occurred(ア): 期間中に発生した閲覧数 = 日次値の合計 + 手入力分(期間の重なり日数で按分)
 * モード posted(イ):  期間中に投稿したものの閲覧数 = 投稿日が期間内の投稿の最新累計の合計(手入力分は含まない)
 */
class Dashboard
{
    public const MODE_OCCURRED = 'occurred';

    public const MODE_POSTED = 'posted';

    public function __construct(
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
        public readonly string $mode,
    ) {}

    public function previousStart(): CarbonImmutable
    {
        return $this->start->subDays($this->days());
    }

    public function previousEnd(): CarbonImmutable
    {
        return $this->start->subDay();
    }

    public function days(): int
    {
        return (int) $this->start->diffInDays($this->end) + 1;
    }

    /**
     * @return array{
     *   brands: list<array{brand: Brand, total: int, previous: int, accounts: list<array>}>,
     *   platforms: array<string, array{platform: Platform, total: int, previous: int}>,
     *   total: int,
     *   previous: int,
     * }
     */
    public function build(User $user): array
    {
        $order = array_flip(array_map(fn (Platform $p) => $p->value, Platform::cases()));
        $brands = $user->visibleBrands()->with('accounts')->get()->each(function (Brand $brand) use ($order) {
            $brand->setRelation('accounts', $brand->accounts->sortBy(fn (Account $a) => [$order[$a->platform->value], $a->id])->values());
        });
        $accounts = $brands->flatMap->accounts;

        $current = $this->viewsByAccountAndFormat($accounts, $this->start, $this->end);
        $previous = $this->viewsByAccountAndFormat($accounts, $this->previousStart(), $this->previousEnd());

        $platforms = [];
        foreach (Platform::cases() as $platform) {
            $platforms[$platform->value] = ['platform' => $platform, 'total' => 0, 'previous' => 0];
        }

        $brandRows = [];
        foreach ($brands as $brand) {
            $rows = [];
            foreach ($brand->accounts as $account) {
                $formats = $current[$account->id] ?? [];
                $total = $this->countable($account) ? array_sum($formats) : null;
                $prev = $this->countable($account) ? array_sum($previous[$account->id] ?? []) : null;

                $rows[] = [
                    'account' => $account,
                    'total' => $total,
                    'previous' => $prev,
                    'formats' => $formats,
                ];

                $platforms[$account->platform->value]['total'] += $total ?? 0;
                $platforms[$account->platform->value]['previous'] += $prev ?? 0;
            }

            $brandRows[] = [
                'brand' => $brand,
                'accounts' => $rows,
                'total' => array_sum(array_map(fn ($r) => $r['total'] ?? 0, $rows)),
                'previous' => array_sum(array_map(fn ($r) => $r['previous'] ?? 0, $rows)),
            ];
        }

        return [
            'brands' => $brandRows,
            'platforms' => array_filter($platforms, fn ($p) => $accounts->contains(fn (Account $a) => $a->platform === $p['platform'])),
            'total' => array_sum(array_column($brandRows, 'total')),
            'previous' => array_sum(array_column($brandRows, 'previous')),
        ];
    }

    /** モード「イ」では手入力アカウントは数えない(期間合計しか無く投稿日が分からないため) */
    public function countable(Account $account): bool
    {
        return $this->mode === self::MODE_OCCURRED || $account->isApi();
    }

    /** 期間内に投稿されたものを閲覧数の多い順に(投稿別の内訳) */
    public function topPosts(Account $account, int $limit = 20): Collection
    {
        return Post::query()
            ->where('account_id', $account->id)
            ->whereBetween('published_at', [$this->start->startOfDay(), $this->end->endOfDay()])
            ->orderByDesc('views')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  Collection<int, Account>  $accounts
     * @return array<int, array<string, int>> account_id => [format => views]
     */
    public function viewsByAccountAndFormat(Collection $accounts, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $ids = $accounts->pluck('id');
        $result = [];

        if ($this->mode === self::MODE_POSTED) {
            Post::query()
                ->selectRaw('account_id, format, SUM(views) as views')
                ->whereIn('account_id', $ids)
                ->whereBetween('published_at', [$start->startOfDay(), $end->endOfDay()])
                ->groupBy('account_id', 'format')
                ->get()
                ->each(function ($row) use (&$result) {
                    $result[$row->account_id][$row->format] = (int) $row->views;
                });

            return $result;
        }

        DailyMetric::query()
            ->selectRaw('account_id, format, SUM(views) as views')
            ->whereIn('account_id', $ids)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('account_id', 'format')
            ->get()
            ->each(function ($row) use (&$result) {
                $result[$row->account_id][$row->format] = (int) $row->views;
            });

        ManualEntry::query()
            ->whereIn('account_id', $ids)
            ->where('period_start', '<=', $end->toDateString())
            ->where('period_end', '>=', $start->toDateString())
            ->get()
            ->each(function (ManualEntry $entry) use (&$result, $start, $end) {
                $result[$entry->account_id][$entry->format] = ($result[$entry->account_id][$entry->format] ?? 0)
                    + self::prorate($entry, $start, $end);
            });

        return $result;
    }

    /** 手入力の期間合計を、集計期間と重なる日数で按分する */
    public static function prorate(ManualEntry $entry, CarbonImmutable $start, CarbonImmutable $end): int
    {
        $entryStart = CarbonImmutable::parse($entry->period_start)->startOfDay();
        $entryEnd = CarbonImmutable::parse($entry->period_end)->startOfDay();
        $entryDays = (int) $entryStart->diffInDays($entryEnd) + 1;

        $from = $entryStart->max($start->startOfDay());
        $to = $entryEnd->min($end->startOfDay());
        if ($from->gt($to)) {
            return 0;
        }

        $overlap = (int) $from->diffInDays($to) + 1;

        return (int) round($entry->views * $overlap / $entryDays);
    }

    /** 前期間比(%)。前期間が0なら null */
    public static function change(?int $current, ?int $previous): ?float
    {
        if ($current === null || ! $previous) {
            return null;
        }

        return round(($current - $previous) / $previous * 100, 1);
    }
}
