<?php

namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPlanPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_plan_id',
        'provider_ids',
        'amount',
        'currency',
        'billing_interval',
        'billing_interval_count',
        'is_active',
    ];

    protected $casts = [
        'provider_ids' => 'array',
        'amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function getFormattedAmountAttribute(): string
    {
        return '$'.number_format($this->amount, 2);
    }

    /**
     * Get provider price ID for a specific provider.
     */
    public function getProviderPriceId(string $provider): ?string
    {
        return $this->provider_ids[$provider] ?? null;
    }

    /**
     * Set provider price ID for a specific provider.
     */
    public function setProviderPriceId(string $provider, string $priceId): void
    {
        $providerIds = $this->provider_ids ?? [];
        $providerIds[$provider] = $priceId;
        $this->provider_ids = $providerIds;
        $this->save();
    }
}
