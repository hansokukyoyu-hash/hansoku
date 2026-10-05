@extends('layouts.app')
@section('title', 'ブランド')
@section('content')
<h1>ブランド</h1>
<div class="card">
    <form method="post" action="{{ route('admin.brands.store') }}" class="row">
        @csrf
        <label>ブランド名<input type="text" name="name" required maxlength="100"></label>
        <label>表示順<input type="number" name="sort_order" min="0" value="{{ $brands->count() + 1 }}" style="width:80px"></label>
        <button class="btn primary">追加</button>
    </form>
</div>
<h2>登録済み</h2>
<div class="table-wrap">
<table>
    <thead><tr><th>ブランド名・表示順</th><th class="num">アカウント数</th><th></th></tr></thead>
    <tbody>
    @forelse ($brands as $brand)
        <tr>
            <td>
                <form method="post" action="{{ route('admin.brands.update', $brand) }}" class="row">
                    @csrf @method('put')
                    <input type="text" name="name" value="{{ $brand->name }}" required maxlength="100">
                    <input type="number" name="sort_order" value="{{ $brand->sort_order }}" min="0" style="width:80px">
                    <button class="btn small">保存</button>
                </form>
            </td>
            <td class="num">{{ $brand->accounts_count }}</td>
            <td>
                <form method="post" action="{{ route('admin.brands.destroy', $brand) }}" class="inline" onsubmit="return confirm('ブランドと、紐づくアカウント・収集データをすべて削除します。よろしいですか?')">
                    @csrf @method('delete')<button class="btn small danger">削除</button>
                </form>
            </td>
        </tr>
    @empty
        <tr><td colspan="3" class="muted">まだブランドがありません。</td></tr>
    @endforelse
    </tbody>
</table>
</div>
@endsection
