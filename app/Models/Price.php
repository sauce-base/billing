<?php

namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Billing\Enums\BillingScheme;
use Modules\Billing\Enums\Currency;

class Price extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'provider_price_id',
        'currency',
        'amount',
        'billing_scheme',
        'interval',
        'interval_count',
        'metadata',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'interval_count' => 'integer',
            'currency' => Currency::class,
            'billing_scheme' => BillingScheme::class,
            'metadata' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
