<?php

namespace Modules\Billing\Exceptions;

class PaymentException extends \Exception
{
    public static function invalidWebhookSignature(): self
    {
        return new self('Invalid webhook signature');
    }

    public static function alreadySubscribed(): self
    {
        return new self('User already has an active subscription');
    }

    public static function cannotResumeExpiredSubscription(): self
    {
        return new self('Cannot resume an expired subscription');
    }

    public static function gatewayNotFound(string $name): self
    {
        return new self("Payment gateway [{$name}] not found.");
    }

    public static function gatewayNotEnabled(string $name): self
    {
        return new self("Payment gateway [{$name}] is not enabled.");
    }
}
