<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantApiKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'name',
        'key_prefix',
        'key_hash',
        'last_used_at',
        'expires_at',
        'revoked_at',
    ];

    protected $hidden = [
        'key_hash',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}
