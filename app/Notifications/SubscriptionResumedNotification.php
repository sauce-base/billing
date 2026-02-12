<?php

namespace Modules\Billing\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Billing\Models\Subscription;

class SubscriptionResumedNotification extends Notification
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
        // TODO: translate

        $productName = $this->subscription->price?->product?->name ?? 'your plan';

        return (new MailMessage)
            ->subject('Subscription Resumed')
            ->greeting("Hello {$notifiable->name},")
            ->line("Your subscription to **{$productName}** has been resumed.")
            ->action('Manage Billing', route('settings.billing'))
            ->line('Welcome back!');
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
