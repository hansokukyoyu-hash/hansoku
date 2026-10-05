<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Platform;
use App\Exceptions\SnsApiException;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Services\OAuth\GoogleOAuth;
use App\Services\OAuth\MetaOAuth;
use App\Services\OAuth\ThreadsOAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * 各SNSのログイン画面へ送り、戻ってきたら連携情報を保存する。
 * 戻り先URL(各SNSの開発者画面に登録する):
 *   Meta(Instagram・Facebook): {APP_URL}/connect/meta/callback
 *   Threads:                    {APP_URL}/connect/threads/callback
 *   YouTube:                    {APP_URL}/connect/google/callback
 */
class ConnectController extends Controller
{
    public function redirect(Request $request, Account $account): RedirectResponse
    {
        abort_unless($account->isApi() && $account->platform->supportsApi(), 422, 'このアカウントは手入力です。');

        $state = Str::random(40);
        $request->session()->put('connect', ['state' => $state, 'account_id' => $account->id]);

        return redirect()->away(match ($account->platform) {
            Platform::Instagram, Platform::Facebook => app(MetaOAuth::class)->authorizeUrl(route('connect.meta.callback'), $state),
            Platform::Threads => app(ThreadsOAuth::class)->authorizeUrl(route('connect.threads.callback'), $state),
            Platform::YouTube => app(GoogleOAuth::class)->authorizeUrl(route('connect.google.callback'), $state),
        });
    }

    public function metaCallback(Request $request, MetaOAuth $oauth): RedirectResponse
    {
        $account = $this->pendingAccount($request);

        try {
            $candidates = $oauth->exchange((string) $request->query('code'), route('connect.meta.callback'));
        } catch (SnsApiException $e) {
            return $this->failed($account, $e);
        }

        if ($account->platform === Platform::Instagram) {
            $candidates = array_values(array_filter($candidates, fn ($c) => $c['instagram_id'] !== null));
        }
        if ($candidates === []) {
            return redirect()->route('admin.accounts.index')->with('error', $account->platform === Platform::Instagram
                ? 'Facebookページに紐づいたInstagramプロアカウントが見つかりませんでした。InstagramをFacebookページに紐づけてから再度お試しください。'
                : '管理しているFacebookページが見つかりませんでした。');
        }

        $request->session()->put('connect.meta_candidates', $candidates);

        return redirect()->route('connect.meta.choose');
    }

    public function metaChoose(Request $request): View
    {
        $account = $this->pendingAccount($request, keep: true);

        return view('admin.accounts.choose', [
            'account' => $account,
            'candidates' => $request->session()->get('connect.meta_candidates', []),
        ]);
    }

    public function metaSave(Request $request): RedirectResponse
    {
        $account = $this->pendingAccount($request);
        $candidates = collect($request->session()->pull('connect.meta_candidates', []));
        $chosen = $candidates->firstWhere('page_id', $request->input('page_id'));
        abort_if($chosen === null, 422, '選択が正しくありません。');

        $account->fill([
            'external_id' => $account->platform === Platform::Instagram ? $chosen['instagram_id'] : $chosen['page_id'],
            'credentials' => ['access_token' => $chosen['page_token'], 'page_id' => $chosen['page_id']],
        ]);

        return $this->connected($request, $account, $account->platform === Platform::Instagram ? '@'.$chosen['instagram_username'] : $chosen['page_name']);
    }

    public function threadsCallback(Request $request, ThreadsOAuth $oauth): RedirectResponse
    {
        $account = $this->pendingAccount($request);

        try {
            $result = $oauth->exchange((string) $request->query('code'), route('connect.threads.callback'));
        } catch (SnsApiException $e) {
            return $this->failed($account, $e);
        }

        $account->fill([
            'external_id' => $result['user_id'],
            'credentials' => ['access_token' => $result['access_token'], 'expires_at' => $result['expires_at']],
        ]);

        return $this->connected($request, $account, '@'.$result['username']);
    }

    public function googleCallback(Request $request, GoogleOAuth $oauth): RedirectResponse
    {
        $account = $this->pendingAccount($request);

        try {
            $result = $oauth->exchange((string) $request->query('code'), route('connect.google.callback'));
        } catch (SnsApiException $e) {
            return $this->failed($account, $e);
        }

        $account->fill([
            'external_id' => $result['channel_id'],
            'credentials' => [
                'access_token' => $result['access_token'],
                'refresh_token' => $result['refresh_token'] ?? $account->credential('refresh_token'),
                'expires_at' => $result['expires_at'],
            ],
        ]);

        return $this->connected($request, $account, (string) $result['channel_title']);
    }

    private function pendingAccount(Request $request, bool $keep = false): Account
    {
        $pending = $request->session()->get('connect');
        abort_if($pending === null, 419, '連携の有効期限が切れました。もう一度「連携」を押してください。');

        if ($request->has('state') && ! hash_equals($pending['state'], (string) $request->query('state'))) {
            abort(419, '連携の確認に失敗しました。もう一度「連携」を押してください。');
        }
        if ($request->has('error')) {
            abort(400, '連携がキャンセルされました: '.$request->query('error_description', $request->query('error')));
        }

        return Account::findOrFail($pending['account_id']);
    }

    private function connected(Request $request, Account $account, string $label): RedirectResponse
    {
        $request->session()->forget('connect');
        $account->forceFill(['status' => 'active', 'last_error' => null])->save();

        return redirect()->route('admin.accounts.index')
            ->with('status', "「{$account->name}」を {$label} と連携しました。「今すぐ収集」で取得を確認できます。");
    }

    private function failed(Account $account, SnsApiException $e): RedirectResponse
    {
        report($e);
        session()->forget('connect');

        return redirect()->route('admin.accounts.index')->with('error', "「{$account->name}」の連携に失敗しました: {$e->getMessage()}");
    }
}
