<?php

namespace Modules\Billing\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Billing\Contracts\PaymentGatewayInterface;
use Modules\Billing\Enums\SubscriptionStatus;
use Modules\Billing\Models\Customer;
use Modules\Billing\Models\Subscription;
use Modules\Billing\Services\PaymentGatewayManager;
use Tests\TestCase;

class SubscriptionCancelTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancel_subscription_requires_auth(): void
    {
        $response = $this->post(route('billing.subscription.cancel'));

        $response->assertRedirect(route('login'));
    }

    public function test_cancel_subscription_calls_billing_service(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create(['user_id' => $user->id]);
        Subscription::factory()->create([
            'customer_id' => $customer->id,
            'status' => SubscriptionStatus::Active,
        ]);

        $gateway = $this->createMock(PaymentGatewayInterface::class);
        $gateway->expects($this->once())
            ->method('cancelSubscription')
            ->with($this->anything(), false);

        $manager = $this->createMock(PaymentGatewayManager::class);
        $manager->method('driver')->willReturn($gateway);
        $this->app->instance(PaymentGatewayManager::class, $manager);

        $response = $this->actingAs($user)->post(route('billing.subscription.cancel'));

        $response->assertRedirect();
    }

    public function test_cancel_subscription_updates_local_state(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create(['user_id' => $user->id]);
        $subscription = Subscription::factory()->create([
            'customer_id' => $customer->id,
            'status' => SubscriptionStatus::Active,
            'current_period_ends_at' => now()->addMonth(),
        ]);

        $gateway = $this->createMock(PaymentGatewayInterface::class);
        $gateway->expects($this->once())
            ->method('cancelSubscription');

        $manager = $this->createMock(PaymentGatewayManager::class);
        $manager->method('driver')->willReturn($gateway);
        $this->app->instance(PaymentGatewayManager::class, $manager);

        $this->actingAs($user)->post(route('billing.subscription.cancel'));

        $subscription->refresh();

        $this->assertNotNull($subscription->cancelled_at);
        $this->assertEquals(
            $subscription->current_period_ends_at->toDateTimeString(),
            $subscription->ends_at->toDateTimeString(),
        );
        $this->assertEquals(SubscriptionStatus::Active, $subscription->status);
    }

    public function test_cancel_uses_gateway_period_end_when_local_is_null(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create(['user_id' => $user->id]);
        $subscription = Subscription::factory()->create([
            'customer_id' => $customer->id,
            'status' => SubscriptionStatus::Active,
            'current_period_ends_at' => null,
        ]);

        $periodEnd = now()->addMonth()->startOfDay();

        $gateway = $this->createMock(PaymentGatewayInterface::class);
        $gateway->method('cancelSubscription')->willReturn($periodEnd);

        $manager = $this->createMock(PaymentGatewayManager::class);
        $manager->method('driver')->willReturn($gateway);
        $this->app->instance(PaymentGatewayManager::class, $manager);

        $this->actingAs($user)->post(route('billing.subscription.cancel'));

        $subscription->refresh();

        $this->assertNotNull($subscription->ends_at);
        $this->assertEquals($periodEnd->toDateTimeString(), $subscription->ends_at->toDateTimeString());
        $this->assertEquals($periodEnd->toDateTimeString(), $subscription->current_period_ends_at->toDateTimeString());
    }

    public function test_cancel_returns_404_when_no_active_subscription(): void
    {
        $user = User::factory()->create();
        Customer::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('billing.subscription.cancel'));

        $response->assertNotFound();
    }
}
