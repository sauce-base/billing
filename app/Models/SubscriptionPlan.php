<?php

namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'provider_ids',
        'features',
        'is_active',
    ];

    protected $casts = [
        'provider_ids' => 'array',
        'features' => 'array',
        'is_active' => 'boolean',
    ];

    public function prices(): HasMany
    {
        return $this->hasMany(SubscriptionPlanPrice::class);
    }

    public function monthlyPrice(): ?SubscriptionPlanPrice
    {
        return $this->prices()->where('billing_interval', 'month')->first();
    }

    public function yearlyPrice(): ?SubscriptionPlanPrice
    {
        return $this->prices()->where('billing_interval', 'year')->first();
    }

    /**
     * Get provider product ID for a specific provider.
     */
    public function getProviderProductId(string $provider): ?string
    {
        return $this->provider_ids[$provider] ?? null;
    }

    /**
     * Set provider product ID for a specific provider.
     */
    public function setProviderProductId(string $provider, string $productId): void
    {
        $providerIds = $this->provider_ids ?? [];
        $providerIds[$provider] = $productId;
        $this->provider_ids = $providerIds;
        $this->save();
    }
}
