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
 *
 * 投稿本数(どちらのモードも同じ): API連携は投稿日が期間内の投稿の数。手入力は、入力した期間が
 * 集計期間にすべて入るものだけを数える(本数は按分すると端数が出るため)。月次の入力を月単位で見れば正確になる。
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
     *   platforms: array<string, array{platform: Platform, total: int, previous: int, posts: ?int, post_formats: array<string, int>, ads: int}>,
     *   posts: int,
     *   post_notes: array{partial: int, missing: int},
     *   ads: int,
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

        ['counts' => $postCounts, 'notes' => $postNotes] = $this->postCounts($accounts);
        $platforms = [];
        foreach (Platform::cases() as $platform) {
            $platforms[$platform->value] = [
                'platform' => $platform,
                'total' => 0,
                'previous' => 0,
                'posts' => null, // 本数が分かるアカウントが1つも無ければ null(未入力)
                'post_formats' => [],
                'ads' => 0,
            ];
        }

        $brandRows = [];
        foreach ($brands as $brand) {
            $rows = [];
            foreach ($brand->accounts as $account) {
                $formats = $current[$account->id] ?? [];
                $total = $this->countable($account) ? array_sum($formats) : null;
                $prev = $this->countable($account) ? array_sum($previous[$account->id] ?? []) : null;

                $postFormats = $postCounts[$account->id] ?? null;
                $posts = $postFormats === null ? null : array_sum($postFormats);

                $rows[] = [
                    'account' => $account,
                    'total' => $total,
                    'previous' => $prev,
                    'formats' => $formats,
                    'posts' => $posts,
                    'post_formats' => $postFormats ?? [],
                ];

                $p = &$platforms[$account->platform->value];
                if ($posts !== null) {
                    $p['posts'] = ($p['posts'] ?? 0) + $posts;
                    foreach ($postFormats as $format => $count) {
                        $p['post_formats'][$format] = ($p['post_formats'][$format] ?? 0) + $count;
                    }
                }
                unset($p);

                $platforms[$account->platform->value]['total'] += $total ?? 0;
                $platforms[$account->platform->value]['previous'] += $prev ?? 0;
                $platforms[$account->platform->value]['ads'] += $this->countable($account) ? ($formats['ad'] ?? 0) : 0;
            }

            $brandRows[] = [
                'brand' => $brand,
                'accounts' => $rows,
                'total' => array_sum(array_map(fn ($r) => $r['total'] ?? 0, $rows)),
                'previous' => array_sum(array_map(fn ($r) => $r['previous'] ?? 0, $rows)),
                'posts' => self::sumKnown(array_column($rows, 'posts')),
            ];
        }

        return [
            'brands' => $brandRows,
            'platforms' => array_filter($platforms, fn ($p) => $accounts->contains(fn (Account $a) => $a->platform === $p['platform'])),
            'total' => array_sum(array_column($brandRows, 'total')),
            'previous' => array_sum(array_column($brandRows, 'previous')),
            'posts' => array_sum(array_map('array_sum', $postCounts)),
            'post_notes' => $postNotes,
            'ads' => array_sum(array_column($platforms, 'ads')),
        ];
    }

    /**
     * 期間内の投稿本数(アカウントごと・フォーマットごと)。
     * 本数が分からないアカウント(手入力で、期間にすべて入る本数の入力が無い)は含めない。
     * notes.partial: 期間と一部だけ重なり本数に含めなかった手入力の件数
     * notes.missing: 期間にすべて入るが本数が未入力の手入力の件数
     *
     * @param  Collection<int, Account>  $accounts
     * @return array{counts: array<int, array<string, int>>, notes: array{partial: int, missing: int}}
     */
    public function postCounts(Collection $accounts): array
    {
        $counts = [];
        $notes = ['partial' => 0, 'missing' => 0];

        $apiIds = $accounts->filter(fn (Account $a) => $a->isApi())->pluck('id');
        foreach ($apiIds as $id) {
            $counts[$id] = [];
        }
        Post::query()
            ->selectRaw('account_id, format, COUNT(*) as posts')
            ->whereIn('account_id', $apiIds)
            ->whereBetween('published_at', [$this->start->startOfDay(), $this->end->endOfDay()])
            ->groupBy('account_id', 'format')
            ->get()
            ->each(function ($row) use (&$counts) {
                $counts[$row->account_id][$row->format] = (int) $row->posts;
            });

        $start = $this->start->toDateString();
        $end = $this->end->toDateString();
        ManualEntry::query()
            ->whereIn('account_id', $accounts->filter(fn (Account $a) => $a->isManual())->pluck('id'))
            ->where('period_start', '<=', $end)
            ->where('period_end', '>=', $start)
            ->get()
            ->each(function (ManualEntry $entry) use (&$counts, &$notes, $start, $end) {
                $contained = CarbonImmutable::parse($entry->period_start)->toDateString() >= $start
                    && CarbonImmutable::parse($entry->period_end)->toDateString() <= $end;

                if (! $contained) {
                    $notes['partial']++;
                } elseif ($entry->posts === null) {
                    $notes['missing']++;
                } else {
                    $counts[$entry->account_id][$entry->format] = ($counts[$entry->account_id][$entry->format] ?? 0) + $entry->posts;
                }
            });

        return ['counts' => $counts, 'notes' => $notes];
    }

    /** null(未入力)を除いて合計する。すべて null なら null */
    public static function sumKnown(array $values): ?int
    {
        $known = array_filter($values, fn ($v) => $v !== null);

        return $known === [] ? null : array_sum($known);
    }

    /**
     * 内訳に出すフォーマット。モード「ア」はアカウント全体の日別値の内訳(広告・通常動画などを含む)、
     * モード「イ」は投稿の種類。
     *
     * @return array<string, string>
     */
    public function breakdownFormats(Account $account): array
    {
        if ($this->mode === self::MODE_OCCURRED && $account->isApi()) {
            return $account->platform->viewFormats();
        }

        return $account->platform->formats();
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
