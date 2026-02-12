<?php

namespace Modules\Billing\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Billing\Enums\Currency;
use Modules\Billing\Models\Subscription;

class SubscriptionCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Subscription $subscription,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $price = $this->subscription->price;
        $productName = $price?->product?->name ?? 'our service';
        $amount = number_format(($price?->amount ?? 0) / 100, 2);
        $currency = $price?->currency?->value ?? Currency::default();
        $interval = $price?->interval ?? 'month';

        return (new MailMessage)
            ->subject("Welcome to {$productName}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your subscription to **{$productName}** is now active.")
            ->line("Plan: **{$productName}** — {$currency} {$amount}/{$interval}")
            ->action('Go to Dashboard', route('settings.billing'))
            ->line('Thank you for subscribing!');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'subscription_id' => $this->subscription->id,
        ];
    }
}
