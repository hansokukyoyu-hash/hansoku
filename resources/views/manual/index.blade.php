@extends('layouts.app')
@section('title', '手入力')
@section('content')
<h1>手入力</h1>
<p class="muted">APIで取得しないアカウント(Facebook個人プロフィール、TikTok、Xなど)の閲覧数を、各アプリで確認した数値で入力します。</p>
<div class="table-wrap">
<table>
    <thead><tr><th>ブランド</th><th>SNS</th><th>アカウント</th><th>入力済みの期間</th><th></th></tr></thead>
    <tbody>
    @forelse ($accounts as $account)
        @php($last = $account->manualEntries()->max('period_end'))
        <tr>
            <td>{{ $account->brand->name }}</td>
            <td>{{ $account->platform->label() }}</td>
            <td>{{ $account->name }}</td>
            <td class="small">{{ $last ? '〜'.\Illuminate\Support\Carbon::parse($last)->isoFormat('YYYY/M/D') : '未入力' }}</td>
            <td><a class="btn small" href="{{ route('manual.show', $account) }}">入力する</a></td>
        </tr>
    @empty
        <tr><td colspan="5" class="muted">手入力のアカウントはありません。</td></tr>
    @endforelse
    </tbody>
</table>
</div>
@endsection
