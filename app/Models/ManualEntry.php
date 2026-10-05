<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualEntry extends Model
{
    protected $fillable = ['account_id', 'format', 'period_start', 'period_end', 'views', 'note', 'created_by'];

    protected function casts(): array
    {
        return [
            'views' => 'integer',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
