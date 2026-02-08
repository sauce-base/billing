<?php

namespace Modules\Billing\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;

class PaymentSucceededNotification extends Notification
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
        $currency = $this->payment->currency?->value ?? 'USD';
        $isOneTime = $this->payment->subscription_id === null;
        $productName = $this->payment->price?->product?->name
            ?? $this->payment->subscription?->price?->product?->name
            ?? 'your subscription';

        $invoice = Invoice::where('payment_id', $this->payment->id)->first();
        $actionUrl = $invoice?->hosted_invoice_url ?? route('billing.index');
        $actionText = $invoice?->hosted_invoice_url ? 'View Invoice' : 'Go to Billing';

        return (new MailMessage)
            ->subject('Payment Received')
            ->greeting("Hello {$notifiable->name},")
            ->line("We've received your payment of **{$currency} {$amount}** for **{$productName}**".($isOneTime ? ' (one-time purchase)' : '').'.')
            ->action($actionText, $actionUrl)
            ->line('Thank you for your payment!');
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
