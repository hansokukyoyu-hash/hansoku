@extends('layouts.app')
@section('title', '収集ログ')
@section('content')
<h1>収集ログ</h1>
<div class="table-wrap">
<table>
    <thead><tr><th>開始</th><th>アカウント</th><th>種類</th><th>結果</th><th>内容</th><th class="num">所要</th></tr></thead>
    <tbody>
    @forelse ($logs as $log)
        <tr>
            <td class="small">{{ $log->started_at->isoFormat('M/D HH:mm:ss') }}</td>
            <td class="small">{{ $log->account?->brand?->name }} / {{ $log->account?->platform->label() }} / {{ $log->account?->name }}</td>
            <td class="small">{{ $log->job === 'stories' ? 'ストーリーズ' : '日次' }}</td>
            <td><span @class(['badge', 'active' => $log->status === 'success', 'error' => $log->status === 'failed'])>{{ ['success' => '成功', 'failed' => '失敗', 'running' => '実行中'][$log->status] ?? $log->status }}</span></td>
            <td class="small">{{ $log->message }}</td>
            <td class="num small">{{ $log->finished_at ? $log->started_at->diffInSeconds($log->finished_at).'秒' : '' }}</td>
        </tr>
    @empty
        <tr><td colspan="6" class="muted">まだ収集していません。</td></tr>
    @endforelse
    </tbody>
</table>
</div>
{{ $logs->links('partials.pagination') }}
@endsection
