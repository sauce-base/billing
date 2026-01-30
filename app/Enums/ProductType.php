<?php

namespace Modules\Billing\Enums;

use JsonSerializable;

enum ProductType: string implements JsonSerializable
{
    case FREEMIUM = 'freemium';
    case SUBSCRIPTION = 'subscription';
    case ONE_TIME = 'one_time';

    /**
     * Get all available product types.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get translation key for the product type label.
     */
    public function label(): string
    {
        return __("billing::billing.product_types.{$this->value}.label");
    }

    /**
     * Get translation key for the product type description.
     */
    public function description(): string
    {
        return __("billing::billing.product_types.{$this->value}.description");
    }

    public function jsonSerialize(): mixed
    {
        return [
            'value' => $this->value,
            'label' => $this->label(),
            'description' => $this->description(),
        ];
    }
}
