<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BrandController extends Controller
{
    public function index(): View
    {
        return view('admin.brands', ['brands' => Brand::withCount('accounts')->orderBy('sort_order')->orderBy('id')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Brand::create($this->validated($request));

        return back()->with('status', 'ブランドを追加しました。');
    }

    public function update(Request $request, Brand $brand): RedirectResponse
    {
        $brand->update($this->validated($request));

        return back()->with('status', 'ブランドを更新しました。');
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        $brand->delete();

        return back()->with('status', 'ブランドを削除しました(紐づくアカウントとデータも削除されました)。');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]) + ['sort_order' => 0];
    }
}
