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
        <label>対象月<input type="month" id="target-month" value="{{ now()->subMonthNoOverflow()->format('Y-m') }}"></label>
        <label>期間の開始<input type="date" name="period_start" id="period-start" value="{{ old('period_start', now()->subMonthNoOverflow()->startOfMonth()->toDateString()) }}" required></label>
        <label>期間の終了<input type="date" name="period_end" id="period-end" value="{{ old('period_end', now()->subMonthNoOverflow()->endOfMonth()->toDateString()) }}" required></label>
        <label>{{ $account->platform->metricLabel() }}<input type="number" name="views" min="0" value="{{ old('views') }}" required></label>
        <label>投稿本数<input type="number" name="posts" min="0" value="{{ old('posts') }}" placeholder="任意"></label>
        <label>メモ<input type="text" name="note" value="{{ old('note') }}" maxlength="255" placeholder="任意"></label>
        <button class="btn primary">登録</button>
    </form>
    <p class="small muted">期間の合計を入力します。対象月を選ぶと、期間がその月の1日〜末日になります。<br>
        閲覧数は、ダッシュボードで集計期間と重なる日数で按分して合計に含めます。投稿本数は按分せず、入力した期間が集計期間にすべて入るときだけ数えます(月次で入力し、月単位で見ると正確です)。空欄は「未入力」として扱います。</p>
</div>
<script>
    // 対象月を選ぶと、期間をその月の1日〜末日にする
    document.getElementById('target-month').addEventListener('change', function () {
        if (!this.value) return;
        const [y, m] = this.value.split('-').map(Number);
        const last = new Date(y, m, 0).getDate();
        document.getElementById('period-start').value = this.value + '-01';
        document.getElementById('period-end').value = this.value + '-' + String(last).padStart(2, '0');
    });
</script>

<h2>入力履歴</h2>
<div class="table-wrap">
<table>
    <thead><tr><th>期間</th><th>フォーマット</th><th class="num">{{ $account->platform->metricLabel() }}</th><th class="num">投稿本数</th><th>メモ</th><th>入力者</th><th></th></tr></thead>
    <tbody>
    @forelse ($entries as $entry)
        <tr>
            <td>{{ \Illuminate\Support\Carbon::parse($entry->period_start)->isoFormat('YYYY/M/D') }} 〜 {{ \Illuminate\Support\Carbon::parse($entry->period_end)->isoFormat('YYYY/M/D') }}</td>
            <td>{{ $account->platform->formatLabel($entry->format) }}</td>
            <td class="num">{{ number_format($entry->views) }}</td>
            <td class="num">{!! $entry->posts === null ? '<span class="muted">未入力</span>' : number_format($entry->posts) !!}</td>
            <td class="small">{{ $entry->note }}</td>
            <td class="small">{{ $entry->creator?->name }}</td>
            <td>
                <form method="post" action="{{ route('manual.destroy', $entry) }}" class="inline" onsubmit="return confirm('削除しますか?')">
                    @csrf @method('delete')<button class="btn small danger">削除</button>
                </form>
            </td>
        </tr>
    @empty
        <tr><td colspan="7" class="muted">まだ入力がありません。</td></tr>
    @endforelse
    </tbody>
</table>
</div>
{{ $entries->links('partials.pagination') }}
@endsection
