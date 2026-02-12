<?php

namespace Modules\Billing\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\SubscriptionStatus;
use Modules\Billing\Models\Invoice;

class SettingsBillingController
{
    public function show(): Response
    {
        $customer = Auth::user()->billingCustomer;

        if (! $customer) {
            return Inertia::render('Billing::SettingsBilling', [
                'subscription' => null,
                'paymentMethod' => null,
                'invoices' => [],
                'billingPortalUrl' => route('billing.portal'),
            ]);
        }

        $subscription = $customer
            ->subscriptions()
            ->with(['price.product', 'paymentMethod'])
            ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::PastDue])
            ->latest()
            ->first();

        $defaultPaymentMethod = $customer
            ->paymentMethods()
            ->where('is_default', true)
            ->first();

        $invoices = Invoice::where('customer_id', $customer->id)
            ->whereIn('status', [InvoiceStatus::Paid, InvoiceStatus::Posted, InvoiceStatus::Unpaid])
            ->orderByDesc('paid_at')
            ->limit(20)
            ->get();

        return Inertia::render('Billing::SettingsBilling', [
            'subscription' => $subscription ? [
                'id' => $subscription->id,
                'status' => $subscription->status->value,
                'current_period_starts_at' => $subscription->current_period_starts_at?->toISOString(),
                'current_period_ends_at' => $subscription->current_period_ends_at?->toISOString(),
                'cancelled_at' => $subscription->cancelled_at?->toISOString(),
                'ends_at' => $subscription->ends_at?->toISOString(),
                'plan_name' => $subscription->price?->product?->name,
                'interval' => $subscription->price?->interval,
            ] : null,
            'paymentMethod' => $defaultPaymentMethod ? [
                'card_brand' => $defaultPaymentMethod->card_brand,
                'card_last_four' => $defaultPaymentMethod->card_last_four,
                'card_exp_month' => $defaultPaymentMethod->card_exp_month,
                'card_exp_year' => $defaultPaymentMethod->card_exp_year,
            ] : null,
            'invoices' => $invoices->map(fn ($invoice) => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'total' => $invoice->total,
                'currency' => $invoice->currency?->value,
                'status' => $invoice->status->value,
                'paid_at' => $invoice->paid_at?->toISOString(),
                'hosted_invoice_url' => $invoice->hosted_invoice_url,
            ])->values(),
            'billingPortalUrl' => route('billing.portal'),
        ]);
    }
}
