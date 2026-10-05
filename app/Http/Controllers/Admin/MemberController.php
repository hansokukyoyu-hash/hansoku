<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MemberController extends Controller
{
    public function index(): View
    {
        return view('admin.members', [
            'members' => User::with('brands')->orderBy('role')->orderBy('email')->get(),
            'brands' => Brand::orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $user = User::create([
            'email' => strtolower($data['email']),
            'name' => $data['name'] ?: $data['email'],
            'role' => $data['role'],
            'is_active' => true,
        ]);
        $user->brands()->sync($data['brands'] ?? []);

        return back()->with('status', "{$user->email} を登録しました。このGoogleアカウントでログインできます。");
    }

    public function update(Request $request, User $member): RedirectResponse
    {
        $data = $this->validated($request, $member);

        if ($member->is($request->user()) && ($data['role'] !== Role::Admin->value || ! $request->boolean('is_active'))) {
            return back()->with('error', '自分自身の管理者権限は外せません。');
        }

        $member->update([
            'name' => $data['name'] ?: $member->name,
            'role' => $data['role'],
            'is_active' => $request->boolean('is_active'),
        ]);
        $member->brands()->sync($data['brands'] ?? []);

        return back()->with('status', "{$member->email} を更新しました。");
    }

    public function destroy(Request $request, User $member): RedirectResponse
    {
        if ($member->is($request->user())) {
            return back()->with('error', '自分自身は削除できません。');
        }

        $member->delete();

        return back()->with('status', "{$member->email} を削除しました。");
    }

    private function validated(Request $request, ?User $member = null): array
    {
        return $request->validate([
            'email' => [$member ? 'nullable' : 'required', 'email', Rule::unique('users', 'email')->ignore($member)],
            'name' => ['nullable', 'string', 'max:100'],
            'role' => ['required', Rule::enum(Role::class)],
            'brands' => ['nullable', 'array'],
            'brands.*' => ['exists:brands,id'],
        ]);
    }
}
