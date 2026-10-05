<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = ['post_id', 'date', 'views', 'fetched_at'];

    protected function casts(): array
    {
        return [
            'fetched_at' => 'datetime',
            'views' => 'integer',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
