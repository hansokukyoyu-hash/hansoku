<?php

namespace App\Models;

use App\Enums\Platform;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasFactory;

    public const METHOD_API = 'api';

    public const METHOD_MANUAL = 'manual';

    protected $fillable = [
        'brand_id', 'platform', 'name', 'input_method', 'external_id', 'credentials',
        'status', 'last_error', 'daily_synced_at', 'stories_synced_at',
    ];

    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return [
            'platform' => Platform::class,
            'credentials' => 'encrypted:array',
            'daily_synced_at' => 'datetime',
            'stories_synced_at' => 'datetime',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function dailyMetrics(): HasMany
    {
        return $this->hasMany(DailyMetric::class);
    }

    public function manualEntries(): HasMany
    {
        return $this->hasMany(ManualEntry::class);
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(SyncLog::class);
    }

    public function isApi(): bool
    {
        return $this->input_method === self::METHOD_API;
    }

    public function isManual(): bool
    {
        return $this->input_method === self::METHOD_MANUAL;
    }

    public function isConnected(): bool
    {
        return $this->isApi() && $this->external_id !== null && ! empty($this->credentials['access_token']);
    }

    public function scopeCollectable(Builder $query): Builder
    {
        return $query->where('input_method', self::METHOD_API)
            ->whereNotNull('external_id')
            ->whereNotNull('credentials');
    }

    public function credential(string $key): mixed
    {
        return $this->credentials[$key] ?? null;
    }

    public function mergeCredentials(array $values): void
    {
        $this->credentials = array_merge($this->credentials ?? [], $values);
    }
}
