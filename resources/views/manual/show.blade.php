@extends('layouts.app')
@section('title', '手入力 - '.$account->name)
@section('content')
<p class="small"><a href="{{ route('manual.index') }}">← 手入力の一覧</a></p>
<h1>{{ $account->brand->name }} / {{ $account->platform->label() }} / {{ $account->name }}</h1>

<div class="card">
    <form method="post" action="{{ route('manual.store', $account) }}" class="row">
        @csrf
        <label>フォーマット
            <select name="format">
                @foreach ($account->platform->formats() as $key => $label)
                    <option value="{{ $key }}" @selected(old('format') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label>期間の開始<input type="date" name="period_start" value="{{ old('period_start', now()->subMonthNoOverflow()->startOfMonth()->toDateString()) }}" required></label>
        <label>期間の終了<input type="date" name="period_end" value="{{ old('period_end', now()->subMonthNoOverflow()->endOfMonth()->toDateString()) }}" required></label>
        <label>{{ $account->platform->metricLabel() }}<input type="number" name="views" min="0" value="{{ old('views') }}" required></label>
        <label>メモ<input type="text" name="note" value="{{ old('note') }}" maxlength="255" placeholder="任意"></label>
        <button class="btn primary">登録</button>
    </form>
    <p class="small muted">期間の合計を入力します。ダッシュボードでは、集計期間と重なる日数で按分して合計に含めます。</p>
</div>

<h2>入力履歴</h2>
<div class="table-wrap">
<table>
    <thead><tr><th>期間</th><th>フォーマット</th><th class="num">{{ $account->platform->metricLabel() }}</th><th>メモ</th><th>入力者</th><th></th></tr></thead>
    <tbody>
    @forelse ($entries as $entry)
        <tr>
            <td>{{ \Illuminate\Support\Carbon::parse($entry->period_start)->isoFormat('YYYY/M/D') }} 〜 {{ \Illuminate\Support\Carbon::parse($entry->period_end)->isoFormat('YYYY/M/D') }}</td>
            <td>{{ $account->platform->formatLabel($entry->format) }}</td>
            <td class="num">{{ number_format($entry->views) }}</td>
            <td class="small">{{ $entry->note }}</td>
            <td class="small">{{ $entry->creator?->name }}</td>
            <td>
                <form method="post" action="{{ route('manual.destroy', $entry) }}" class="inline" onsubmit="return confirm('削除しますか?')">
                    @csrf @method('delete')<button class="btn small danger">削除</button>
                </form>
            </td>
        </tr>
    @empty
        <tr><td colspan="6" class="muted">まだ入力がありません。</td></tr>
    @endforelse
    </tbody>
</table>
</div>
{{ $entries->links('partials.pagination') }}
@endsection
