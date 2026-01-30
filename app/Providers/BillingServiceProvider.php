<?php

namespace Modules\Billing\Providers;

use App\Providers\ModuleServiceProvider;
use Illuminate\Support\Facades\Gate;
use Modules\Billing\Contracts\PaymentGatewayInterface;
use Modules\Billing\Services\Gateways\StripeGateway;
use Modules\Billing\Services\PaymentGatewayFactory;
use Stripe\StripeClient;

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

        // Register StripeClient as singleton for dependency injection
        $this->app->singleton(StripeClient::class, function () {
            return new StripeClient(config('services.stripe.secret_key'));
        });

        // Tag all payment gateways for auto-registration
        $this->app->tag([
            StripeGateway::class,
            // Future gateways will be added here
            // PaddleGateway::class,
            // LemonSqueezyGateway::class,
        ], 'payment-gateways');

        // Register PaymentGatewayFactory as singleton
        $this->app->singleton(PaymentGatewayFactory::class);

        // Bind interface to factory resolution
        $this->app->bind(PaymentGatewayInterface::class, function ($app) {
            return $app->make(PaymentGatewayFactory::class)
                ->driver(config('billing.default_gateway'));
        });
    }

    public function boot(): void
    {
        parent::boot();

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
