# Billing Module

Payment gateway and subscription management module for Saucebase. Phase 1 MVP implementation with Stripe integration.

## Features (Phase 1 MVP)

- ✅ Stripe payment gateway integration
- ✅ Subscription management (create, cancel, resume)
- ✅ Multiple pricing plans (monthly/yearly)
- ✅ Webhook handling for subscription sync
- ✅ Grace period support for canceled subscriptions
- ✅ Clean adapter pattern for future payment providers

## Installation

### 1. Install Module

```bash
composer require saucebase/billing
composer dump-autoload
php artisan module:enable Billing
```

### 2. Install Dependencies

```bash
cd modules/Billing
composer install
```

### 3. Configure Environment

Add Stripe credentials to your `.env` file:

```bash
STRIPE_SECRET_KEY=sk_test_your_key_here
STRIPE_PUBLISHABLE_KEY=pk_test_your_key_here
STRIPE_WEBHOOK_SECRET=whsec_your_secret_here
```

### 4. Run Migrations

```bash
php artisan module:migrate Billing
```

This creates:
- `subscription_plans` - Plan definitions (Starter, Pro, Enterprise)
- `subscription_plan_prices` - Pricing for each plan (monthly/yearly)
- `subscriptions` - User subscriptions
- Adds `stripe_customer_id` to `users` table

### 5. Setup Stripe Products & Prices

**Important:** Create products and prices in Stripe Dashboard first (test mode):

1. Login to [Stripe Dashboard](https://dashboard.stripe.com/test/products)
2. Create 3 products: Starter, Pro, Enterprise
3. For each product, create 2 recurring prices: monthly and yearly
4. Copy the product IDs and price IDs

### 6. Seed Plans

Update `modules/Billing/database/seeders/SubscriptionPlanSeeder.php` with your real Stripe IDs:

```php
'stripe_product_id' => 'prod_YOUR_REAL_STRIPE_PRODUCT_ID',
'stripe_price_id' => 'price_YOUR_REAL_STRIPE_PRICE_ID',
```

Then run the seeder:

```bash
php artisan module:seed Billing
```

### 7. Setup Webhooks (Local Development)

Install Stripe CLI:

```bash
# macOS
brew install stripe/stripe-cli/stripe

# Login
stripe login
```

Forward webhooks to your local server:

```bash
stripe listen --forward-to https://localhost/api/billing/webhooks/stripe
```

Copy the webhook signing secret and add to `.env`:

```bash
STRIPE_WEBHOOK_SECRET=whsec_xxx
```

### 8. Build Frontend Assets

```bash
npm run build
# or for development
npm run dev
```

## Configuration

The Billing module follows Laravel conventions for organizing configuration:

### Stripe Credentials (`services.stripe`)

Payment provider credentials are stored in `config/services.php` namespace, following the same pattern as Laravel's built-in services (AWS, Mailgun, etc.) and the Auth module's OAuth providers:

```php
// Access Stripe API credentials
config('services.stripe.secret_key')
config('services.stripe.publishable_key')
config('services.stripe.webhook_secret')
```

### Module Settings (`billing`)

Module-specific configuration (logging, feature flags, etc.) remains in the `billing` namespace:

```php
// Access module settings
config('billing.logging.enabled')
config('billing.logging.channel')
```

This separation makes it clear which values are third-party API credentials vs. internal module configuration.

## Usage

### Routes

#### Web Routes (Auth Required)
- `GET /billing` - Billing dashboard
- `GET /billing/plans` - View subscription plans
- `POST /billing/subscriptions` - Create subscription
- `DELETE /billing/subscriptions/{subscription}` - Cancel subscription
- `POST /billing/subscriptions/{subscription}/resume` - Resume subscription

#### API Routes (Public)
- `POST /api/billing/webhooks/stripe` - Stripe webhook handler (CSRF exempt)

### User Model Methods

After installation, the `User` model has these subscription helpers:

```php
// Get user's active subscription
$subscription = $user->subscription();

// Check if user has active subscription
if ($user->hasActiveSubscription()) {
    // ...
}

// Check specific plan
if ($user->isSubscribedTo('pro')) {
    // ...
}

// Get all subscriptions
$subscriptions = $user->subscriptions;
```

### Subscription Model Methods

```php
// Check if subscription is active
$subscription->isActive(); // bool

// Check if canceled
$subscription->isCanceled(); // bool

// Check if in grace period
$subscription->onGracePeriod(); // bool
```

## Architecture

### Gateway Pattern

The module uses a clean adapter pattern for payment providers:

```
PaymentGatewayInterface (Contract)
    └── StripeGateway (Implementation)
```

To add new providers in future phases, just implement `PaymentGatewayInterface`.

### Service Layer

`SubscriptionService` handles business logic:
- `subscribe()` - Create subscription
- `cancel()` - Cancel at period end
- `resume()` - Resume canceled subscription
- `isActive()` - Check subscription status

### Models

- `SubscriptionPlan` - Plan definitions (name, features)
- `SubscriptionPlanPrice` - Pricing options (monthly/yearly)
- `Subscription` - User subscriptions

### Webhook Handling

Webhooks are processed directly (no queues in MVP):
- `customer.subscription.updated` - Sync subscription status
- `customer.subscription.deleted` - Mark as canceled

## Manual Testing

### 1. View Dashboard

Navigate to: `https://localhost/billing`

You should see "No Active Subscription" state.

### 2. View Plans

Click "View Plans" → `https://localhost/billing/plans`

You should see 3 plan cards with monthly/yearly pricing.

### 3. Subscribe (Test Mode)

For MVP, Stripe Checkout integration is pending. To test manually:

```bash
# Create subscription via Tinker
php artisan tinker

$user = User::first();
$planPrice = SubscriptionPlanPrice::find(1); // Monthly Starter

$service = app(\Modules\Billing\Services\SubscriptionService::class);
$subscription = $service->subscribe($user, $planPrice, 'pm_card_visa');
```

### 4. Test Webhooks

Trigger test events with Stripe CLI:

```bash
stripe trigger customer.subscription.updated
```

Check logs to verify webhook received:

```bash
tail -f storage/logs/laravel.log
```

## Frontend Components

### Billing Dashboard (`Index.vue`)

Shows:
- No subscription state (CTA to view plans)
- Active subscription details (plan, price, status, next billing)
- Cancel/Resume buttons
- Grace period alert

### Plans Page (`Plans.vue`)

Shows:
- Monthly/yearly toggle
- 3 plan cards with features
- Subscribe buttons (Stripe Checkout integration pending)

## Phase 2 (Upcoming)

After MVP is working, add:
- [ ] Unit tests (StripeGatewayTest, SubscriptionServiceTest)
- [ ] Feature tests (subscription flow, webhooks)
- [ ] E2E tests (Playwright)
- [ ] Free trials support
- [ ] Plan swapping/upgrades
- [ ] Invoice history page
- [ ] Custom Stripe Elements checkout
- [ ] Webhook event logging (idempotency)
- [ ] Queue jobs for webhook processing

## Phase 3 (Future)

Advanced features:
- [ ] Metered billing (usage tracking)
- [ ] PDF invoice generation
- [ ] Transaction history
- [ ] Second payment provider (Paddle)
- [ ] Filament admin resources
- [ ] Email notifications

## Database Schema

### subscription_plans
- `id`, `name`, `slug`, `description`
- `stripe_product_id`, `features` (JSON), `is_active`

### subscription_plan_prices
- `id`, `subscription_plan_id`, `stripe_price_id`
- `amount`, `currency`, `billing_interval`, `billing_interval_count`, `is_active`

### subscriptions
- `id`, `user_id`, `subscription_plan_id`, `subscription_plan_price_id`
- `stripe_subscription_id`, `status`
- `current_period_start`, `current_period_end`
- `canceled_at`, `ends_at`

### users (modified)
- `stripe_customer_id` (added)

## Troubleshooting

### Webhook signature verification fails

Make sure you're using the webhook secret from Stripe CLI (not dashboard) for local development:

```bash
stripe listen --forward-to https://localhost/api/billing/webhooks/stripe
# Copy the whsec_xxx value to STRIPE_WEBHOOK_SECRET
```

### Plans not showing

1. Check plans are seeded: `php artisan tinker` → `SubscriptionPlan::count()`
2. Verify Stripe IDs are correct in seeder
3. Clear cache: `php artisan optimize:clear`
4. Rebuild assets: `npm run build`

### User model methods not found

1. Run migrations: `php artisan module:migrate Billing`
2. Clear cache: `php artisan optimize:clear`
3. Regenerate autoload: `composer dump-autoload`

## Security Notes

- Webhook routes are CSRF exempt (using `withoutMiddleware()`)
- Subscription actions require authentication
- Policy enforces ownership (users can only manage their own subscriptions)
- Stripe signature verification prevents webhook spoofing

## Support

For issues or questions, open an issue at: https://github.com/sauce-base/billing/issues

## License

MIT License - see LICENSE file
