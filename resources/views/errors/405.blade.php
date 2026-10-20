@extends('layouts.app')
@section('title', 'ページを表示できませんでした')
@section('content')
<div class="card">
    <h1>ページを表示できませんでした</h1>
    <p>ボタンの送信をやり直そうとしたか、送信の途中で画面が切り替わった可能性があります。データには影響ありません。</p>
    <p><a class="btn primary" href="{{ route('dashboard') }}">ダッシュボードへ</a> <a class="btn" href="{{ route('admin.accounts.index') }}">アカウント連携へ</a></p>
</div>
@endsection
