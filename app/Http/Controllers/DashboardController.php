<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Services\Dashboard;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $dashboard = $this->dashboard($request);
        $user = $request->user();

        return view('dashboard', [
            'dashboard' => $dashboard,
            'data' => $dashboard->build($user),
            'presets' => $this->presets(),
            'expandAll' => $request->boolean('expand') && $user->canSeeDetails(),
            'canSeeDetails' => $user->canSeeDetails(),
        ]);
    }

    public function csv(Request $request): StreamedResponse
    {
        $dashboard = $this->dashboard($request);
        $data = $dashboard->build($request->user());
        $filename = sprintf('sns-views_%s_%s_%s.csv', $dashboard->start->format('Ymd'), $dashboard->end->format('Ymd'), $dashboard->mode);

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // Excelで文字化けしないようBOMを付ける
            fputcsv($out, ['ブランド', 'SNS', 'アカウント', '取得方法', 'フォーマット', '閲覧数・再生回数', '前期間']);
            foreach ($data['brands'] as $brandRow) {
                foreach ($brandRow['accounts'] as $row) {
                    /** @var Account $account */
                    $account = $row['account'];
                    $method = $account->isApi() ? 'API' : '手入力';
                    fputcsv($out, [$brandRow['brand']->name, $account->platform->label(), $account->name, $method, '合計', $row['total'] ?? '', $row['previous'] ?? '']);
                    foreach ($account->platform->formats() as $key => $label) {
                        fputcsv($out, [$brandRow['brand']->name, $account->platform->label(), $account->name, $method, $label, $row['formats'][$key] ?? 0, '']);
                    }
                }
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function dashboard(Request $request): Dashboard
    {
        $validated = $request->validate([
            'start' => ['nullable', 'date'],
            'end' => ['nullable', 'date', 'after_or_equal:start'],
            'mode' => ['nullable', 'in:'.Dashboard::MODE_OCCURRED.','.Dashboard::MODE_POSTED],
        ]);

        $end = isset($validated['end']) ? CarbonImmutable::parse($validated['end']) : CarbonImmutable::yesterday();
        $start = isset($validated['start']) ? CarbonImmutable::parse($validated['start']) : $end->subDays(29);

        return new Dashboard($start->startOfDay(), $end->startOfDay(), $validated['mode'] ?? Dashboard::MODE_OCCURRED);
    }

    /** @return array<string, array{0: string, 1: string}> */
    private function presets(): array
    {
        $today = CarbonImmutable::today();

        return [
            '過去7日' => [$today->subDays(7)->toDateString(), $today->subDay()->toDateString()],
            '過去30日' => [$today->subDays(30)->toDateString(), $today->subDay()->toDateString()],
            '今月' => [$today->startOfMonth()->toDateString(), $today->toDateString()],
            '先月' => [$today->subMonthNoOverflow()->startOfMonth()->toDateString(), $today->subMonthNoOverflow()->endOfMonth()->toDateString()],
        ];
    }
}
