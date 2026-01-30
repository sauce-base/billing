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
        return match ($this) {
            self::FREEMIUM => __('Free Plan'),
            self::SUBSCRIPTION => __('Subscription'),
            self::ONE_TIME => __('One-time Purchase'),
        };
    }

    /**
     * Get translation key for the product type description.
     */
    public function description(): string
    {
        return match ($this) {
            self::FREEMIUM => __('Free tier with limited features'),
            self::SUBSCRIPTION => __('Recurring payment plan (monthly/yearly)'),
            self::ONE_TIME => __('Single payment product (setup fees, credits, etc.)'),
        };
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
