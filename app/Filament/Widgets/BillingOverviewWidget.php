<?php

namespace Modules\Billing\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\Billing\Enums\Currency;
use Modules\Billing\Enums\PaymentStatus;
use Modules\Billing\Enums\SubscriptionStatus;
use Modules\Billing\Models\Payment;
use Modules\Billing\Models\Subscription;

class BillingOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        return [
            $this->mrrStat(),
            $this->activeSubscriptionsStat(),
            $this->totalTransactionsStat(),
        ];
    }

    private function mrrStat(): Stat
    {
        $mrr = (int) Subscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->with('price')
            ->get()
            ->sum(function (Subscription $subscription): float {
                $price = $subscription->price;

                if (! $price || ! $price->interval) {
                    return 0;
                }

                $divisor = match ($price->interval) {
                    'month' => $price->interval_count ?? 1,
                    'year'  => ($price->interval_count ?? 1) * 12,
                    default => 1,
                };

                return $price->amount / $divisor;
            });

        return Stat::make(__('MRR'), Currency::default()->formatAmount($mrr))
            ->description(__('Monthly Recurring Revenue'))
            ->descriptionIcon('heroicon-m-arrow-trending-up')
            ->color('success');
    }

    private function activeSubscriptionsStat(): Stat
    {
        $count = Subscription::where('status', SubscriptionStatus::Active)->count();

        return Stat::make(__('Active Subscriptions'), number_format($count))
            ->description(__('Currently active subscriptions'))
            ->descriptionIcon('heroicon-m-user-group')
            ->color('info');
    }

    private function totalTransactionsStat(): Stat
    {
        $count = Payment::where('status', PaymentStatus::Succeeded)->count();
        $total = (int) Payment::where('status', PaymentStatus::Succeeded)->sum('amount');

        return Stat::make(__('Total Transactions'), number_format($count))
            ->description(Currency::default()->formatAmount($total).' '.__('total revenue'))
            ->descriptionIcon('heroicon-m-credit-card')
            ->color('warning');
    }
}
