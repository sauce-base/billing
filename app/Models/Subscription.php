<?php

namespace Modules\Billing\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'subscription_plan_id',
        'subscription_plan_price_id',
        'provider',
        'provider_subscription_id',
        'provider_metadata',
        'status',
        'current_period_start',
        'current_period_end',
        'canceled_at',
        'ends_at',
    ];

    protected $casts = [
        'provider_metadata' => 'array',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'canceled_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function planPrice(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlanPrice::class, 'subscription_plan_price_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active' &&
               (! $this->ends_at || $this->ends_at->isFuture());
    }

    public function isCanceled(): bool
    {
        return $this->canceled_at !== null;
    }

    public function onGracePeriod(): bool
    {
        return $this->isCanceled() && $this->ends_at && $this->ends_at->isFuture();
    }

    /**
     * Get provider metadata value by key.
     */
    public function getProviderMetadata(string $key, mixed $default = null): mixed
    {
        return $this->provider_metadata[$key] ?? $default;
    }

    /**
     * Scope query to a specific provider.
     */
    public function scopeProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }
}
