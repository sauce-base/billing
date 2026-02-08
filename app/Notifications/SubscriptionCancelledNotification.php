<?php

namespace Modules\Billing\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Billing\Models\Subscription;

class SubscriptionCancelledNotification extends Notification
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
        $endsAt = $this->subscription->ends_at?->format('F j, Y') ?? 'the end of your billing period';

        return (new MailMessage)
            ->subject('Subscription Cancelled')
            ->greeting("Hello {$notifiable->name},")
            ->line("Your subscription to **{$productName}** has been cancelled.")
            ->line("You will continue to have access until **{$endsAt}**.")
            ->action('Manage Billing', route('billing.index'))
            ->line('We hope to see you again!');
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
