<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Platform;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Brand;
use App\Services\CollectionRunner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountController extends Controller
{
    public function index(): View
    {
        return view('admin.accounts.index', [
            'brands' => Brand::with(['accounts' => fn ($q) => $q->orderBy('platform')])->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.accounts.form', ['account' => new Account, 'brands' => Brand::orderBy('sort_order')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $account = Account::create($this->validated($request) + ['status' => 'not_connected']);

        return redirect()->route('admin.accounts.index')->with('status', "「{$account->name}」を追加しました。".($account->isApi() ? '続けて「連携」を押してください。' : ''));
    }

    public function edit(Account $account): View
    {
        return view('admin.accounts.form', ['account' => $account, 'brands' => Brand::orderBy('sort_order')->get()]);
    }

    public function update(Request $request, Account $account): RedirectResponse
    {
        $account->update($this->validated($request));

        return redirect()->route('admin.accounts.index')->with('status', "「{$account->name}」を更新しました。");
    }

    public function destroy(Account $account): RedirectResponse
    {
        $account->delete();

        return redirect()->route('admin.accounts.index')->with('status', "「{$account->name}」を削除しました。");
    }

    /** 連携確認用に、そのアカウントだけ今すぐ収集する */
    public function collect(Account $account, CollectionRunner $runner): RedirectResponse
    {
        abort_unless($account->isConnected(), 422, '先に連携してください。');

        $jobs = [CollectionRunner::JOB_DAILY];
        if ($account->platform->hasStories()) {
            $jobs[] = CollectionRunner::JOB_STORIES;
        }

        $messages = [];
        foreach ($jobs as $job) {
            $log = $runner->run($account, $job);
            $messages[] = ($log->status === 'success' ? '成功' : '失敗').': '.$log->message;
        }

        return redirect()->route('admin.accounts.index')->with('status', "「{$account->name}」".implode(' / ', $messages));
    }

    /** 管理しているアカウントの一覧(Excel で開ける CSV) */
    public function export(): StreamedResponse
    {
        $order = array_flip(array_map(fn (Platform $p) => $p->value, Platform::cases()));
        $brands = Brand::with('accounts')->orderBy('sort_order')->orderBy('id')->get();

        return response()->streamDownload(function () use ($brands, $order) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // Excelで文字化けしないようBOMを付ける
            fputcsv($out, ['ブランド', 'アカウント名', 'SNS媒体名', 'プロフィールURL']);
            foreach ($brands as $brand) {
                foreach ($brand->accounts->sortBy(fn (Account $a) => [$order[$a->platform->value], $a->id]) as $account) {
                    fputcsv($out, [$brand->name, $account->name, $account->platform->label(), $account->profile_url ?? '']);
                }
            }
            fclose($out);
        }, 'sns-accounts_'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'brand_id' => ['required', 'exists:brands,id'],
            'platform' => ['required', Rule::enum(Platform::class)],
            'name' => ['required', 'string', 'max:100'],
            'input_method' => ['required', Rule::in([Account::METHOD_API, Account::METHOD_MANUAL])],
            'profile_url' => ['nullable', 'url:http,https', 'max:1024'],
        ]);

        if ($data['input_method'] === Account::METHOD_API && ! Platform::from($data['platform'])->supportsApi()) {
            $data['input_method'] = Account::METHOD_MANUAL;
        }

        return $data;
    }
}
