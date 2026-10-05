<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>@yield('title', 'SNS閲覧数ダッシュボード')</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=1">
</head>
<body>
@auth
    <header class="topbar">
        <a class="brand" href="{{ route('dashboard') }}">SNS閲覧数ダッシュボード</a>
        <nav>
            <a href="{{ route('dashboard') }}" @class(['active' => request()->routeIs('dashboard')])>ダッシュボード</a>
            @if (auth()->user()->canSeeDetails())
                <a href="{{ route('manual.index') }}" @class(['active' => request()->routeIs('manual.*')])>手入力</a>
            @endif
            @if (auth()->user()->isAdmin())
                <a href="{{ route('admin.accounts.index') }}" @class(['active' => request()->routeIs('admin.accounts.*', 'connect.*')])>アカウント連携</a>
                <a href="{{ route('admin.brands.index') }}" @class(['active' => request()->routeIs('admin.brands.*')])>ブランド</a>
                <a href="{{ route('admin.members.index') }}" @class(['active' => request()->routeIs('admin.members.*')])>メンバー</a>
                <a href="{{ route('admin.logs') }}" @class(['active' => request()->routeIs('admin.logs')])>収集ログ</a>
            @endif
        </nav>
        <div class="user">
            <span>{{ auth()->user()->name }}({{ auth()->user()->role->label() }})</span>
            <form method="post" action="{{ route('logout') }}" class="inline">@csrf<button class="btn small">ログアウト</button></form>
        </div>
    </header>
@endauth
<main>
    @if (session('status'))<div class="flash ok">{{ session('status') }}</div>@endif
    @if (session('error'))<div class="flash err">{{ session('error') }}</div>@endif
    @if ($errors->any())
        <div class="flash err">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
    @endif
    @yield('content')
</main>
</body>
</html>
