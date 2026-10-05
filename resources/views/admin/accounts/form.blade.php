@extends('layouts.app')
@section('title', $account->exists ? 'アカウントを編集' : 'アカウントを追加')
@use('App\Enums\Platform')
@use('App\Models\Account')
@section('content')
<p class="small"><a href="{{ route('admin.accounts.index') }}">← アカウント連携</a></p>
<h1>{{ $account->exists ? 'アカウントを編集' : 'アカウントを追加' }}</h1>
<div class="card">
    <form method="post" action="{{ $account->exists ? route('admin.accounts.update', $account) : route('admin.accounts.store') }}" class="row">
        @csrf
        @if ($account->exists) @method('put') @endif
        <label>ブランド
            <select name="brand_id" required>
                @foreach ($brands as $brand)
                    <option value="{{ $brand->id }}" @selected(old('brand_id', $account->brand_id) == $brand->id)>{{ $brand->name }}</option>
                @endforeach
            </select>
        </label>
        <label>SNS
            <select name="platform" required>
                @foreach (Platform::cases() as $platform)
                    <option value="{{ $platform->value }}" @selected(old('platform', $account->platform?->value) === $platform->value)>{{ $platform->label() }}</option>
                @endforeach
            </select>
        </label>
        <label>表示名(例: ブランド公式)<input type="text" name="name" value="{{ old('name', $account->name) }}" required maxlength="100"></label>
        <label>取得方法
            <select name="input_method">
                <option value="{{ Account::METHOD_API }}" @selected(old('input_method', $account->input_method) === Account::METHOD_API)>API(自動取得)</option>
                <option value="{{ Account::METHOD_MANUAL }}" @selected(old('input_method', $account->input_method) === Account::METHOD_MANUAL)>手入力</option>
            </select>
        </label>
        <button class="btn primary">保存</button>
    </form>
    <ul class="small muted">
        <li>API(自動取得)が使えるのは、Instagramプロアカウント・Facebookページ・YouTube・Threads です。</li>
        <li>Facebook個人プロフィール、Instagram個人アカウント、TikTok、X は「手入力」を選んでください(APIを選んでも手入力として保存されます)。</li>
    </ul>
</div>
@endsection
