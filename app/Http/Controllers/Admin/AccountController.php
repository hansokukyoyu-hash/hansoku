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

        return back()->with('status', "「{$account->name}」".implode(' / ', $messages));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'brand_id' => ['required', 'exists:brands,id'],
            'platform' => ['required', Rule::enum(Platform::class)],
            'name' => ['required', 'string', 'max:100'],
            'input_method' => ['required', Rule::in([Account::METHOD_API, Account::METHOD_MANUAL])],
        ]);

        if ($data['input_method'] === Account::METHOD_API && ! Platform::from($data['platform'])->supportsApi()) {
            $data['input_method'] = Account::METHOD_MANUAL;
        }

        return $data;
    }
}
