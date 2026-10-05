@extends('layouts.app')
@section('title', '連携先を選ぶ')
@use('App\Enums\Platform')
@section('content')
<h1>「{{ $account->name }}」と連携する{{ $account->platform === Platform::Instagram ? 'Instagramアカウント' : 'Facebookページ' }}を選んでください</h1>
<div class="card">
    <form method="post" action="{{ route('connect.meta.save') }}">
        @csrf
        @foreach ($candidates as $c)
            <p>
                <label>
                    <input type="radio" name="page_id" value="{{ $c['page_id'] }}" required @checked($loop->first)>
                    @if ($account->platform === Platform::Instagram)
                        {{ '@'.$c['instagram_username'] }} <span class="muted small">(Facebookページ: {{ $c['page_name'] }})</span>
                    @else
                        {{ $c['page_name'] }}
                    @endif
                </label>
            </p>
        @endforeach
        <button class="btn primary">このアカウントと連携する</button>
    </form>
</div>
@endsection
