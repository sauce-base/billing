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

class SubscriptionResumeTest extends TestCase
{
    use RefreshDatabase;

    public function test_resume_subscription_requires_auth(): void
    {
        $response = $this->post(route('billing.subscription.resume'));

        $response->assertRedirect(route('login'));
    }

    public function test_resume_subscription_calls_billing_service(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create(['user_id' => $user->id]);
        Subscription::factory()->create([
            'customer_id' => $customer->id,
            'status' => SubscriptionStatus::Active,
            'cancelled_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $gateway = $this->createMock(PaymentGatewayInterface::class);
        $gateway->expects($this->once())
            ->method('resumeSubscription');

        $manager = $this->createMock(PaymentGatewayManager::class);
        $manager->method('driver')->willReturn($gateway);
        $this->app->instance(PaymentGatewayManager::class, $manager);

        $response = $this->actingAs($user)->post(route('billing.subscription.resume'));

        $response->assertRedirect();
    }

    public function test_resume_subscription_updates_local_state(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create(['user_id' => $user->id]);
        $subscription = Subscription::factory()->create([
            'customer_id' => $customer->id,
            'status' => SubscriptionStatus::Active,
            'cancelled_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $gateway = $this->createMock(PaymentGatewayInterface::class);
        $gateway->expects($this->once())
            ->method('resumeSubscription');

        $manager = $this->createMock(PaymentGatewayManager::class);
        $manager->method('driver')->willReturn($gateway);
        $this->app->instance(PaymentGatewayManager::class, $manager);

        $this->actingAs($user)->post(route('billing.subscription.resume'));

        $subscription->refresh();

        $this->assertNull($subscription->cancelled_at);
        $this->assertNull($subscription->ends_at);
        $this->assertEquals(SubscriptionStatus::Active, $subscription->status);
    }

    public function test_resume_returns_404_when_no_pending_cancellation(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create(['user_id' => $user->id]);
        Subscription::factory()->create([
            'customer_id' => $customer->id,
            'status' => SubscriptionStatus::Active,
            'cancelled_at' => null,
        ]);

        $response = $this->actingAs($user)->post(route('billing.subscription.resume'));

        $response->assertNotFound();
    }
}
