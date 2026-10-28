@extends('layouts.app')
@section('title', 'ログイン')
@section('content')
<div class="login">
    <div class="card">
        @if (file_exists(public_path('legal/app-icon.png')))
            <img src="{{ asset('legal/app-icon.png') }}" alt="" width="64" height="64" style="display:block;margin:0 auto 8px">
        @endif
        <h1>SNS閲覧数ダッシュボード</h1>
        <p class="muted">管理者に登録されたGoogleアカウントでログインしてください。</p>
        <p><a class="btn primary" href="{{ route('auth.google') }}">Googleでログイン</a></p>
        <p class="small muted"><a href="{{ asset('legal/privacy.html') }}">プライバシーポリシー / Privacy Policy</a> ・ <a href="{{ asset('legal/terms.html') }}">利用規約 / Terms of Service</a></p>
    </div>
</div>
@endsection
