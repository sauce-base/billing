<?php

namespace Modules\Billing\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Billing\Enums\Currency;
use Modules\Billing\Models\Payment;

class PaymentFailedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Payment $payment,
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

        $amount = number_format($this->payment->amount / 100, 2);
        $currency = $this->payment->currency?->value ?? Currency::default();
        $failureMessage = $this->payment->failure_message;

        $message = (new MailMessage)
            ->subject('Payment Failed')
            ->greeting("Hello {$notifiable->name},")
            ->line("We were unable to process your payment of **{$currency} {$amount}**.");

        if ($failureMessage) {
            $message->line("Reason: {$failureMessage}");
        }

        return $message
            ->line('Please update your payment method to avoid service interruption.')
            ->action('Update Payment Method', route('settings.billing'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'payment_id' => $this->payment->id,
        ];
    }
}
