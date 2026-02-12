<?php

namespace Modules\Billing\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Billing\Enums\SubscriptionStatus;
use Modules\Billing\Models\Subscription;

class SubscriptionUpdatedNotification extends Notification
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

        $mail = (new MailMessage)
            ->greeting("Hello {$notifiable->name},");

        if ($this->subscription->cancelled_at && $this->subscription->status === SubscriptionStatus::Active) {
            $endsAt = $this->subscription->ends_at?->format('F j, Y') ?? 'the end of your billing period';

            return $mail
                ->subject('Subscription Cancellation Scheduled')
                ->line("Your subscription to **{$productName}** has been scheduled for cancellation on **{$endsAt}**.")
                ->action('Manage Billing', route('settings.billing'))
                ->line('You can resume your subscription at any time before this date.');
        }

        return $mail
            ->subject('Subscription Past Due')
            ->line("Your subscription to **{$productName}** is past due. Please update your payment method.")
            ->action('Manage Billing', route('settings.billing'))
            ->line('Update your payment method to avoid service interruption.');
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
