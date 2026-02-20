<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Merchant extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_user_id',
        'name',
        'slug',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function providerCredentials(): HasMany
    {
        return $this->hasMany(MerchantProviderCredential::class);
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(MerchantApiKey::class);
    }

    public function activeStripeCredentials(): HasOne
    {
        return $this->hasOne(MerchantProviderCredential::class)->where('provider', 'stripe')->where('is_active', true);
    }

    public function activePaddleCredentials(): HasOne
    {
        return $this->hasOne(MerchantProviderCredential::class)->where('provider', 'paddle')->where('is_active', true);
    }
}
