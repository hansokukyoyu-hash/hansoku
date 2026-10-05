@extends('layouts.app')
@section('title', 'ダッシュボード')
@use('App\Services\Dashboard')
@php
    $params = ['start' => $dashboard->start->toDateString(), 'end' => $dashboard->end->toDateString(), 'mode' => $dashboard->mode, 'expand' => $expandAll ? 1 : null];
    $url = fn (array $over) => route('dashboard', array_filter(array_merge($params, $over), fn ($v) => $v !== null));
    $change = function (?int $current, ?int $previous) {
        $pct = Dashboard::change($current, $previous);
        if ($pct === null) {
            return '<span class="muted">—</span>';
        }
        $class = $pct > 0 ? 'up' : ($pct < 0 ? 'down' : 'muted');

        return '<span class="'.$class.'">'.($pct > 0 ? '+' : '').number_format($pct, 1).'%</span>';
    };
    $num = fn (?int $v) => $v === null ? '—' : number_format($v);
@endphp
@section('content')
<form method="get" action="{{ route('dashboard') }}" class="toolbar">
    <input type="hidden" name="mode" value="{{ $dashboard->mode }}">
    @if ($expandAll)<input type="hidden" name="expand" value="1">@endif
    <div class="row">
        <label>開始日<input type="date" name="start" value="{{ $params['start'] }}"></label>
        <label>終了日<input type="date" name="end" value="{{ $params['end'] }}"></label>
        <button class="btn primary">集計</button>
    </div>
    <div>
        <div class="small muted">期間の選び方</div>
        <div class="presets">
            @foreach ($presets as $label => [$s, $e])
                <a href="{{ $url(['start' => $s, 'end' => $e]) }}">{{ $label }}</a>
            @endforeach
        </div>
    </div>
    <div>
        <div class="small muted">集計モード</div>
        <div class="segmented">
            <a href="{{ $url(['mode' => Dashboard::MODE_OCCURRED]) }}" @class(['on' => $dashboard->mode === Dashboard::MODE_OCCURRED])>ア. 期間中に発生</a>
            <a href="{{ $url(['mode' => Dashboard::MODE_POSTED]) }}" @class(['on' => $dashboard->mode === Dashboard::MODE_POSTED])>イ. 期間中に投稿</a>
        </div>
    </div>
    @if ($canSeeDetails)
        <div>
            <div class="small muted">表示</div>
            <div class="segmented">
                <a href="{{ $url(['expand' => null]) }}" @class(['on' => ! $expandAll])>サマリー表示</a>
                <a href="{{ $url(['expand' => 1]) }}" @class(['on' => $expandAll])>内訳も全表示</a>
            </div>
        </div>
    @endif
    <div><a class="btn" href="{{ route('dashboard.csv', array_filter($params)) }}">CSV出力</a></div>
</form>

<p class="small muted">
    {{ $dashboard->start->isoFormat('YYYY/M/D(ddd)') }} 〜 {{ $dashboard->end->isoFormat('YYYY/M/D(ddd)') }}({{ $dashboard->days() }}日間)。
    前期間比は直前の同じ日数({{ $dashboard->previousStart()->isoFormat('M/D') }}〜{{ $dashboard->previousEnd()->isoFormat('M/D') }})と比べています。
    @if ($dashboard->mode === Dashboard::MODE_POSTED)
        モード「イ」は投稿日が期間内の投稿について、最新の累計閲覧数を合計します。手入力のアカウントは含みません。
    @else
        手入力分は、入力した期間と集計期間が重なる日数で按分しています。
    @endif
</p>

<div class="kpis">
    <div class="kpi total">
        <div class="label">全体合計</div>
        <div class="value">{{ $num($data['total']) }}</div>
        <div class="change">前期間比 {!! $change($data['total'], $data['previous']) !!}</div>
    </div>
    @foreach ($data['platforms'] as $p)
        <div class="kpi">
            <div class="label">{{ $p['platform']->label() }}({{ $p['platform']->metricLabel() }})</div>
            <div class="value">{{ $num($p['total']) }}</div>
            <div class="change">前期間比 {!! $change($p['total'], $p['previous']) !!}</div>
        </div>
    @endforeach
</div>

@if ($data['brands'] === [])
    <div class="card muted">
        まだブランドが登録されていません。
        @if (auth()->user()->isAdmin())<a href="{{ route('admin.brands.index') }}">ブランドを追加</a>してください。@endif
    </div>
@else
<div class="table-wrap">
<table>
    <thead>
        <tr>
            <th>ブランド / アカウント</th>
            <th>SNS</th>
            <th class="num">閲覧数・再生回数</th>
            <th class="num">前期間比</th>
            <th>最終更新</th>
        </tr>
    </thead>
    <tbody>
    @foreach ($data['brands'] as $brandRow)
        <tr class="brand-row">
            <td>{{ $brandRow['brand']->name }}</td>
            <td></td>
            <td class="num">{{ $num($brandRow['total']) }}</td>
            <td class="num">{!! $change($brandRow['total'], $brandRow['previous']) !!}</td>
            <td></td>
        </tr>
        @forelse ($brandRow['accounts'] as $row)
            @php($account = $row['account'])
            <tr>
                <td class="indent">
                    @if ($canSeeDetails)
                        <details @if ($expandAll) open @endif>
                            <summary>{{ $account->name }}</summary>
                            <div class="breakdown">
                                <table>
                                    <tr><th>フォーマット</th><th class="num">{{ $account->platform->metricLabel() }}</th></tr>
                                    @foreach ($account->platform->formats() as $key => $label)
                                        <tr>
                                            <td>{{ $label }}</td>
                                            <td class="num">{{ $dashboard->countable($account) ? number_format($row['formats'][$key] ?? 0) : '—' }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                                @if ($account->isApi())
                                    @php($posts = $dashboard->topPosts($account, 10))
                                    <div class="small muted" style="margin-top:8px">期間内に投稿されたもの(累計の多い順・上位10件)</div>
                                    @if ($posts->isEmpty())
                                        <div class="small muted">該当する投稿はありません。</div>
                                    @else
                                        <table class="posts">
                                            @foreach ($posts as $post)
                                                <tr>
                                                    <td>@if ($post->thumbnail_url)<img src="{{ $post->thumbnail_url }}" alt="" loading="lazy" referrerpolicy="no-referrer">@endif</td>
                                                    <td class="small">{{ $post->published_at?->isoFormat('M/D HH:mm') }}<br><span class="badge">{{ $account->platform->formatLabel($post->format) }}</span></td>
                                                    <td class="caption small">
                                                        @if ($post->permalink)<a href="{{ $post->permalink }}" target="_blank" rel="noopener noreferrer">{{ $post->caption ?: '投稿を開く' }}</a>@else{{ $post->caption }}@endif
                                                    </td>
                                                    <td class="num">{{ number_format($post->views) }}</td>
                                                </tr>
                                            @endforeach
                                        </table>
                                        <div class="small muted">集計時点: {{ $posts->max('views_fetched_at')?->isoFormat('M/D HH:mm') }}</div>
                                    @endif
                                @else
                                    <div class="small muted" style="margin-top:8px">手入力のアカウントは投稿別の内訳がありません。<a href="{{ route('manual.show', $account) }}">入力画面へ</a></div>
                                @endif
                            </div>
                        </details>
                    @else
                        {{ $account->name }}
                    @endif
                </td>
                <td>
                    {{ $account->platform->label() }}
                    @if ($account->isManual())<span class="badge manual">手入力</span>@endif
                </td>
                <td class="num">{{ $num($row['total']) }}</td>
                <td class="num">{!! $change($row['total'], $row['previous']) !!}</td>
                <td class="small">
                    @if ($account->isManual())
                        @php($lastEntry = $account->manualEntries()->max('period_end'))
                        {{ $lastEntry ? '〜'.\Illuminate\Support\Carbon::parse($lastEntry)->isoFormat('M/D').' まで入力済み' : '未入力' }}
                    @elseif (! $account->isConnected())
                        <span class="badge error">未連携</span>
                    @elseif ($account->status === 'error')
                        <span class="badge error" title="{{ $account->last_error }}">取得エラー</span>
                    @else
                        {{ collect([$account->daily_synced_at, $account->stories_synced_at])->filter()->max()?->isoFormat('M/D HH:mm') ?? '未取得' }}
                    @endif
                </td>
            </tr>
        @empty
            <tr><td class="indent muted" colspan="5">アカウントが登録されていません。</td></tr>
        @endforelse
    @endforeach
    </tbody>
</table>
</div>
@endif
@endsection
