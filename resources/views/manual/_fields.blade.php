{{-- 手入力の入力欄(新規登録と編集で共通)。$entry が null なら新規 --}}
@php
    $default = now()->subMonthNoOverflow();
    $start = old('period_start', $entry ? \Illuminate\Support\Carbon::parse($entry->period_start)->toDateString() : $default->copy()->startOfMonth()->toDateString());
    $end = old('period_end', $entry ? \Illuminate\Support\Carbon::parse($entry->period_end)->toDateString() : $default->copy()->endOfMonth()->toDateString());
@endphp
<label>フォーマット
    <select name="format">
        @foreach ($account->platform->formats() as $key => $label)
            <option value="{{ $key }}" @selected(old('format', $entry?->format) === $key)>{{ $label }}</option>
        @endforeach
    </select>
</label>
<label>対象月<input type="month" id="target-month" value="{{ substr($start, 0, 7) }}"></label>
<label>期間の開始<input type="date" name="period_start" id="period-start" value="{{ $start }}" required></label>
<label>期間の終了<input type="date" name="period_end" id="period-end" value="{{ $end }}" required></label>
<label>{{ $account->platform->metricLabel() }}<input type="number" name="views" min="0" value="{{ old('views', $entry?->views) }}" required></label>
<label>投稿本数<input type="number" name="posts" min="0" value="{{ old('posts', $entry?->posts) }}" placeholder="任意"></label>
<label>メモ<input type="text" name="note" value="{{ old('note', $entry?->note) }}" maxlength="255" placeholder="任意"></label>
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
