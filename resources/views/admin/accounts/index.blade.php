@extends('layouts.app')
@section('title', 'アカウント連携')
@section('content')
<div class="row" style="justify-content:space-between;align-items:center">
    <h1>アカウント連携</h1>
    <a class="btn primary" href="{{ route('admin.accounts.create') }}">アカウントを追加</a>
</div>
<p class="muted small">
    API連携のアカウントは「連携」を押し、そのアカウントを管理している人のSNSログインで許可します。
    連携後は自動で収集されます(ストーリーズは数時間おき、その他は1日1回)。
</p>
@forelse ($brands as $brand)
    <h2>{{ $brand->name }}</h2>
    <div class="table-wrap">
    <table>
        <thead><tr><th>SNS</th><th>アカウント</th><th>取得方法</th><th>状態</th><th>最終取得</th><th></th></tr></thead>
        <tbody>
        @forelse ($brand->accounts as $account)
            <tr>
                <td>{{ $account->platform->label() }}</td>
                <td>{{ $account->name }}</td>
                <td>@if ($account->isManual())<span class="badge manual">手入力</span>@else<span class="badge">API</span>@endif</td>
                <td class="small">
                    @if ($account->isManual())
                        <a href="{{ route('manual.show', $account) }}">入力画面</a>
                    @elseif (! $account->isConnected())
                        <span class="badge error">未連携</span>
                    @elseif ($account->status === 'error')
                        <span class="badge error">エラー</span> <span class="muted">{{ \Illuminate\Support\Str::limit($account->last_error, 120) }}</span>
                    @else
                        <span class="badge active">連携済み</span>
                    @endif
                </td>
                <td class="small">
                    @if ($account->isApi())
                        日次 {{ $account->daily_synced_at?->isoFormat('M/D HH:mm') ?? '—' }}
                        @if ($account->platform->hasStories())<br>ストーリーズ {{ $account->stories_synced_at?->isoFormat('M/D HH:mm') ?? '—' }}@endif
                    @endif
                </td>
                <td><div class="row" style="gap:6px">
                    @if ($account->isApi())
                        <a class="btn small" href="{{ route('admin.accounts.connect', $account) }}">{{ $account->isConnected() ? '再連携' : '連携' }}</a>
                        @if ($account->isConnected())
                            <form method="post" action="{{ route('admin.accounts.collect', $account) }}" class="inline">@csrf<button class="btn small">今すぐ収集</button></form>
                        @endif
                    @endif
                    <a class="btn small" href="{{ route('admin.accounts.edit', $account) }}">編集</a>
                    <form method="post" action="{{ route('admin.accounts.destroy', $account) }}" class="inline" onsubmit="return confirm('アカウントと収集データを削除します。よろしいですか?')">
                        @csrf @method('delete')<button class="btn small danger">削除</button>
                    </form>
                </div></td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">アカウントがありません。</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
@empty
    <div class="card muted">先に<a href="{{ route('admin.brands.index') }}">ブランド</a>を追加してください。</div>
@endforelse
@endsection
