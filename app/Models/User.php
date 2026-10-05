<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'google_id', 'avatar', 'role', 'is_active', 'last_login_at'];

    protected $hidden = ['remember_token'];

    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(Brand::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    /** 内訳・投稿別まで見られるか */
    public function canSeeDetails(): bool
    {
        return $this->role !== Role::Viewer;
    }

    /** 見られるブランド(運用担当は担当ブランドのみ。担当未設定なら全ブランド) */
    public function visibleBrands(): Builder
    {
        $brands = Brand::query()->orderBy('sort_order')->orderBy('id');

        if ($this->role === Role::Operator && $this->brands()->exists()) {
            $brands->whereIn('brands.id', $this->brands()->pluck('brands.id'));
        }

        return $brands;
    }

    public function canManageAccount(Account $account): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if ($this->role !== Role::Operator) {
            return false;
        }

        return ! $this->brands()->exists() || $this->brands()->whereKey($account->brand_id)->exists();
    }
}
