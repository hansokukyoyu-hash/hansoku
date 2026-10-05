@extends('layouts.app')
@section('title', 'ログイン')
@section('content')
<div class="login">
    <div class="card">
        <h1>SNS閲覧数ダッシュボード</h1>
        <p class="muted">管理者に登録されたGoogleアカウントでログインしてください。</p>
        <p><a class="btn primary" href="{{ route('auth.google') }}">Googleでログイン</a></p>
    </div>
</div>
@endsection
