@extends('layouts.app')
@section('title', 'メンバー')
@section('content')
@use('App\Enums\Role')
<h1>メンバー</h1>
<p class="muted">登録したGoogleアカウントだけがログインできます。運用担当は担当ブランドを選ぶとそのブランドだけ表示されます(未選択なら全ブランド)。</p>
<div class="card">
    <form method="post" action="{{ route('admin.members.store') }}" class="row">
        @csrf
        <label>Googleアカウント(メール)<input type="email" name="email" required></label>
        <label>表示名<input type="text" name="name" maxlength="100"></label>
        <label>ロール
            <select name="role">
                @foreach (Role::cases() as $role)<option value="{{ $role->value }}" @selected($role === Role::Viewer)>{{ $role->label() }}</option>@endforeach
            </select>
        </label>
        <label>担当ブランド(運用担当のみ)
            <select name="brands[]" multiple size="3">
                @foreach ($brands as $brand)<option value="{{ $brand->id }}">{{ $brand->name }}</option>@endforeach
            </select>
        </label>
        <button class="btn primary">登録</button>
    </form>
</div>
<p class="small muted">管理者: 全操作 / 運用担当: 内訳・投稿別の閲覧と手入力 / 閲覧者: 合計のみ閲覧</p>
<div class="table-wrap">
<table>
    <thead><tr><th>メール</th><th>表示名・ロール・担当ブランド・有効</th><th>最終ログイン</th><th></th></tr></thead>
    <tbody>
    @foreach ($members as $member)
        <tr>
            <td>{{ $member->email }}</td>
            <td>
                <form method="post" action="{{ route('admin.members.update', $member) }}" class="row">
                    @csrf @method('put')
                    <input type="text" name="name" value="{{ $member->name }}" maxlength="100" style="width:140px">
                    <select name="role">
                        @foreach (Role::cases() as $role)<option value="{{ $role->value }}" @selected($member->role === $role)>{{ $role->label() }}</option>@endforeach
                    </select>
                    <select name="brands[]" multiple size="2">
                        @foreach ($brands as $brand)<option value="{{ $brand->id }}" @selected($member->brands->contains($brand))>{{ $brand->name }}</option>@endforeach
                    </select>
                    <label style="flex-direction:row;align-items:center"><input type="checkbox" name="is_active" value="1" @checked($member->is_active)> 有効</label>
                    <button class="btn small">保存</button>
                </form>
            </td>
            <td class="small">{{ $member->last_login_at?->isoFormat('YYYY/M/D HH:mm') ?? '未ログイン' }}</td>
            <td>
                @unless ($member->is(auth()->user()))
                    <form method="post" action="{{ route('admin.members.destroy', $member) }}" class="inline" onsubmit="return confirm('{{ $member->email }} を削除しますか?')">
                        @csrf @method('delete')<button class="btn small danger">削除</button>
                    </form>
                @endunless
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>
@endsection
