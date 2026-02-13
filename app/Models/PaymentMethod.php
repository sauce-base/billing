<?php

namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $customer_id
 * @property string|null $provider_payment_method_id
 * @property string $type
 * @property string|null $card_brand
 * @property string|null $card_last_four
 * @property int|null $card_exp_month
 * @property int|null $card_exp_year
 * @property array<string, mixed>|null $metadata
 * @property bool $is_default
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'provider_payment_method_id',
        'type',
        'card_brand',
        'card_last_four',
        'card_exp_month',
        'card_exp_year',
        'metadata',
        'is_default',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'card_exp_month' => 'integer',
            'card_exp_year' => 'integer',
            'metadata' => 'array',
            'is_default' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
