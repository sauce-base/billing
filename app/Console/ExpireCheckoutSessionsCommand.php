<?php

namespace Modules\Billing\Console;

use Illuminate\Console\Command;
use Modules\Billing\Enums\CheckoutSessionStatus;
use Modules\Billing\Models\CheckoutSession;

class ExpireCheckoutSessionsCommand extends Command
{
    protected $signature = 'billing:expire-checkout-sessions';

    protected $description = 'Mark expired pending checkout sessions as expired';

    public function handle(): int
    {
        $count = CheckoutSession::where('status', CheckoutSessionStatus::Pending)
            ->where('expires_at', '<', now())
            ->update(['status' => CheckoutSessionStatus::Expired]);

        $this->info("Marked {$count} checkout session(s) as expired.");

        return self::SUCCESS;
    }
}
