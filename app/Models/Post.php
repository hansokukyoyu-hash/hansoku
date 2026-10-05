<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post extends Model
{
    protected $fillable = [
        'account_id', 'external_id', 'format', 'published_at', 'permalink',
        'thumbnail_url', 'caption', 'views', 'views_fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'views_fetched_at' => 'datetime',
            'views' => 'integer',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(PostSnapshot::class);
    }
}
