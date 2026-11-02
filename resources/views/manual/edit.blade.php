@extends('layouts.app')
@section('title', '手入力の編集 - '.$account->name)
@section('content')
<p class="small"><a href="{{ route('manual.show', $account) }}">← 入力履歴に戻る</a></p>
<h1>{{ $account->brand->name }} / {{ $account->platform->label() }} / {{ $account->name }} の入力を編集</h1>

<div class="card">
    <form method="post" action="{{ route('manual.update', $entry) }}" class="row">
        @csrf @method('put')
        @include('manual._fields', ['entry' => $entry])
        <button class="btn primary">保存</button>
    </form>
    <p class="small muted">投稿本数を空欄にすると「未入力」になります。</p>
</div>
@endsection
