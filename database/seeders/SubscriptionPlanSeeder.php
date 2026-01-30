<?php

namespace Modules\Billing\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Billing\Models\SubscriptionPlan;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        // Starter Plan
        $starter = SubscriptionPlan::create([
            'name' => 'Starter',
            'slug' => 'starter',
            'description' => 'Perfect for individuals and small teams',
            'provider_ids' => [
                'stripe' => 'prod_starter', // Replace with real Stripe product ID
            ],
            'features' => ['10 projects', '5GB storage', 'Email support'],
            'is_active' => true,
        ]);

        $starter->prices()->createMany([
            [
                'provider_ids' => [
                    'stripe' => 'price_starter_monthly', // Replace with real Stripe price ID
                ],
                'amount' => 9.99,
                'currency' => 'usd',
                'billing_interval' => 'month',
            ],
            [
                'provider_ids' => [
                    'stripe' => 'price_starter_yearly',
                ],
                'amount' => 99.99,
                'currency' => 'usd',
                'billing_interval' => 'year',
            ],
        ]);

        // Pro Plan
        $pro = SubscriptionPlan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'description' => 'For growing teams and businesses',
            'provider_ids' => [
                'stripe' => 'prod_pro',
            ],
            'features' => ['Unlimited projects', '50GB storage', 'Priority support', 'Advanced analytics'],
            'is_active' => true,
        ]);

        $pro->prices()->createMany([
            [
                'provider_ids' => [
                    'stripe' => 'price_pro_monthly',
                ],
                'amount' => 29.99,
                'currency' => 'usd',
                'billing_interval' => 'month',
            ],
            [
                'provider_ids' => [
                    'stripe' => 'price_pro_yearly',
                ],
                'amount' => 299.99,
                'currency' => 'usd',
                'billing_interval' => 'year',
            ],
        ]);

        // Enterprise Plan
        $enterprise = SubscriptionPlan::create([
            'name' => 'Enterprise',
            'slug' => 'enterprise',
            'description' => 'For large organizations',
            'provider_ids' => [
                'stripe' => 'prod_enterprise',
            ],
            'features' => ['Unlimited everything', 'Dedicated support', 'Custom integrations', 'SLA'],
            'is_active' => true,
        ]);

        $enterprise->prices()->createMany([
            [
                'provider_ids' => [
                    'stripe' => 'price_enterprise_monthly',
                ],
                'amount' => 99.99,
                'currency' => 'usd',
                'billing_interval' => 'month',
            ],
            [
                'provider_ids' => [
                    'stripe' => 'price_enterprise_yearly',
                ],
                'amount' => 999.99,
                'currency' => 'usd',
                'billing_interval' => 'year',
            ],
        ]);
    }
}
