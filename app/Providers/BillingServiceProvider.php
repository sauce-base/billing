<?php

namespace Modules\Billing\Providers;

use App\Models\User;
use App\Providers\ModuleServiceProvider;
use Illuminate\Support\Facades\Gate;
use Modules\Billing\Contracts\PaymentGatewayInterface;
use Modules\Billing\Models\Customer;
use Modules\Billing\Services\BillingService;
use Modules\Billing\Services\PaymentGatewayManager;

class BillingServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Billing';

    protected string $nameLower = 'billing';

    protected array $providers = [
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->singleton(PaymentGatewayManager::class);

        $this->app->bind(PaymentGatewayInterface::class, function ($app) {
            return $app->make(PaymentGatewayManager::class)->driver();
        });

        $this->app->singleton(BillingService::class);
    }

    public function boot(): void
    {
        parent::boot();

        User::resolveRelationUsing('billingCustomer', function (User $user) {
            return $user->hasOne(Customer::class);
        });

        // Register policies
        $this->registerPolicies();
    }

    /**
     * Register config.
     */
    protected function registerConfig(): void
    {
        parent::registerConfig();

        $this->mergeConfigFrom(module_path($this->name, 'config/services.php'), 'services');
    }

    protected function registerPolicies(): void
    {
        Gate::policy(
            \Modules\Billing\Models\Subscription::class,
            \Modules\Billing\Policies\SubscriptionPolicy::class
        );
    }
}
