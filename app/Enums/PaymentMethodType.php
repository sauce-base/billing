<?php

namespace Modules\Billing\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum PaymentMethodType: string
{
    case Card = 'card';
    case PayPal = 'paypal';
    case SepaDebit = 'sepa_debit';
    case UsBankAccount = 'us_bank_account';
    case BacsDebit = 'bacs_debit';
    case Link = 'link';
    case CashApp = 'cashapp';
    case ApplePay = 'apple_pay';
    case GooglePay = 'google_pay';
    case Bancontact = 'bancontact';
    case Ideal = 'ideal';
    case Unknown = 'unknown';

    public function category(): string
    {
        return match ($this) {
            self::Card, self::ApplePay, self::GooglePay => 'card',
            self::SepaDebit, self::UsBankAccount, self::BacsDebit,
            self::Bancontact, self::Ideal => 'bank',
            self::PayPal, self::Link, self::CashApp => 'wallet',
            self::Unknown => 'unknown',
        };
    }
}
