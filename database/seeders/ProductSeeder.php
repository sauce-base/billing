<?php

namespace Modules\Billing\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Billing\Enums\BillingScheme;
use Modules\Billing\Enums\Currency;
use Modules\Billing\Models\Product;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $this->createFreeProduct();
        $this->createBasicProduct();
        $this->createProProduct();
        $this->createEnterpriseProduct();
    }

    private function createFreeProduct(): void
    {
        $product = Product::create([
            'sku' => 'free',
            'slug' => 'free',
            'name' => 'Free',
            'description' => 'Get started with the basics',
            'display_order' => 1,
            'is_visible' => true,
            'is_highlighted' => false,
            'is_active' => true,
            'features' => [
                '1 project',
                '500MB storage',
                'Community support',
            ],
        ]);

        $product->prices()->createMany([
            [
                'provider_price_id' => 'price_free_monthly',
                'currency' => Currency::default(),
                'amount' => 0,
                'billing_scheme' => BillingScheme::FlatRate,
                'interval' => 'month',
                'interval_count' => 1,
                'is_active' => true,
            ],
            [
                'provider_price_id' => 'price_free_yearly',
                'currency' => Currency::default(),
                'amount' => 0,
                'billing_scheme' => BillingScheme::FlatRate,
                'interval' => 'year',
                'interval_count' => 1,
                'is_active' => true,
            ],
        ]);
    }

    private function createBasicProduct(): void
    {
        $product = Product::create([
            'sku' => 'basic',
            'slug' => 'basic',
            'name' => 'Basic',
            'description' => 'Perfect for individuals and small teams',
            'display_order' => 2,
            'is_visible' => true,
            'is_highlighted' => false,
            'is_active' => true,
            'features' => [
                '10 projects',
                '10GB storage',
                'Email support',
                'Basic analytics',
            ],
        ]);

        $product->prices()->createMany([
            [
                'provider_price_id' => 'price_basic_monthly',
                'currency' => Currency::default(),
                'amount' => 900,
                'billing_scheme' => BillingScheme::FlatRate,
                'interval' => 'month',
                'interval_count' => 1,
                'is_active' => true,
            ],
            [
                'provider_price_id' => 'price_basic_yearly',
                'currency' => Currency::default(),
                'amount' => 9000,
                'billing_scheme' => BillingScheme::FlatRate,
                'interval' => 'year',
                'interval_count' => 1,
                'is_active' => true,
            ],
        ]);
    }

    private function createProProduct(): void
    {
        $product = Product::create([
            'sku' => 'pro',
            'slug' => 'pro',
            'name' => 'Pro',
            'description' => 'For growing teams and businesses',
            'display_order' => 3,
            'is_visible' => true,
            'is_highlighted' => true,
            'is_active' => true,
            'features' => [
                'Unlimited projects',
                '100GB storage',
                'Priority support',
                'Advanced analytics',
                'API access',
            ],
        ]);

        $product->prices()->createMany([
            [
                'provider_price_id' => 'price_pro_monthly',
                'currency' => Currency::default(),
                'amount' => 2900,
                'billing_scheme' => BillingScheme::FlatRate,
                'interval' => 'month',
                'interval_count' => 1,
                'is_active' => true,
            ],
            [
                'provider_price_id' => 'price_pro_yearly',
                'currency' => Currency::default(),
                'amount' => 29000,
                'billing_scheme' => BillingScheme::FlatRate,
                'interval' => 'year',
                'interval_count' => 1,
                'is_active' => true,
            ],
        ]);
    }

    private function createEnterpriseProduct(): void
    {
        $product = Product::create([
            'sku' => 'enterprise',
            'slug' => 'enterprise',
            'name' => 'Enterprise',
            'description' => 'For large organizations with advanced needs',
            'display_order' => 4,
            'is_visible' => true,
            'is_highlighted' => false,
            'is_active' => true,
            'features' => [
                'Unlimited everything',
                '1TB storage',
                'Dedicated support',
                'Custom integrations',
                'SLA',
                'SSO',
            ],
        ]);

        $product->prices()->createMany([
            [
                'provider_price_id' => 'price_enterprise_monthly',
                'currency' => Currency::default(),
                'amount' => 9900,
                'billing_scheme' => BillingScheme::FlatRate,
                'interval' => 'month',
                'interval_count' => 1,
                'is_active' => true,
            ],
            [
                'provider_price_id' => 'price_enterprise_yearly',
                'currency' => Currency::default(),
                'amount' => 99000,
                'billing_scheme' => BillingScheme::FlatRate,
                'interval' => 'year',
                'interval_count' => 1,
                'is_active' => true,
            ],
        ]);
    }
}
