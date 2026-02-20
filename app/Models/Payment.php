<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'provider',
        'type',
        'status',
        'external_id',
        'currency',
        'amount',
        'refunded_amount',
        'customer_email',
        'idempotency_key',
        'metadata',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }
}
