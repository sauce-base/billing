<?php

namespace Modules\Billing\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Modules\Billing\Models\Subscription;
use Modules\Billing\Models\WebhookEvent;
use Modules\Billing\Services\PaymentGatewayFactory;

class WebhookController
{
    public function __construct(
        protected PaymentGatewayFactory $gatewayFactory
    ) {}

    public function handle(Request $request, string $provider): Response
    {
        // Validate provider exists in config
        if (! $this->isValidProvider($provider)) {
            Log::warning("Webhook received for invalid provider: {$provider}");

            return response()->json(['error' => 'Invalid provider'], 400);
        }

        // Validate provider is enabled
        if (! $this->isProviderEnabled($provider)) {
            Log::warning("Webhook received for disabled provider: {$provider}");

            return response()->json(['error' => 'Provider not enabled'], 400);
        }

        try {
            // Get provider-specific gateway
            $gateway = $this->gatewayFactory->driver($provider);

            // Get signature from provider-specific header
            $signature = $this->getSignatureHeader($request, $provider);

            // Verify and parse webhook
            $event = $gateway->verifyAndParseWebhook(
                $request->getContent(),
                $signature
            );

            // Check if webhook already processed (idempotency)
            $webhookEvent = WebhookEvent::firstOrCreate(
                [
                    'provider' => $provider,
                    'event_id' => $event['id'],
                ],
                [
                    'type' => $event['type'],
                    'payload' => $event['data'],
                ]
            );

            if ($webhookEvent->isProcessed()) {
                Log::info('Webhook already processed', [
                    'provider' => $provider,
                    'event_id' => $event['id'],
                    'type' => $event['type'],
                ]);

                return response()->json(['message' => 'Webhook already processed'], 200);
            }

            // Process event
            $this->processEvent($event, $provider);

            // Mark as processed
            $webhookEvent->markAsProcessed();

            return response()->json(['message' => 'Webhook received'], 200);
        } catch (\Exception $e) {
            Log::error("Webhook processing failed for {$provider}", [
                'error' => $e->getMessage(),
                'provider' => $provider,
            ]);

            return response()->json(['error' => 'Webhook failed'], 400);
        }
    }

    protected function processEvent(array $event, string $provider): void
    {
        $type = $event['type'];
        $data = $event['data']['object'];

        // Normalize event type across providers
        $normalizedType = $this->normalizeEventType($type, $provider);

        // Handle only critical subscription events for MVP
        match ($normalizedType) {
            'subscription.updated' => $this->handleSubscriptionUpdated($data, $provider),
            'subscription.deleted' => $this->handleSubscriptionDeleted($data, $provider),
            default => null,
        };
    }

    protected function handleSubscriptionUpdated(array $data, string $provider): void
    {
        $subscription = Subscription::where('provider', $provider)
            ->where('provider_subscription_id', $data['id'])
            ->first();

        if (! $subscription) {
            return;
        }

        $subscription->update([
            'status' => $data['status'],
            'current_period_start' => Carbon::createFromTimestamp($data['current_period_start']),
            'current_period_end' => Carbon::createFromTimestamp($data['current_period_end']),
        ]);
    }

    protected function handleSubscriptionDeleted(array $data, string $provider): void
    {
        $subscription = Subscription::where('provider', $provider)
            ->where('provider_subscription_id', $data['id'])
            ->first();

        if (! $subscription) {
            return;
        }

        $subscription->update([
            'status' => 'canceled',
            'ends_at' => now(),
        ]);
    }

    /**
     * Check if provider is valid (exists in config).
     */
    protected function isValidProvider(string $provider): bool
    {
        $gateways = config('billing.gateways', []);

        return isset($gateways[$provider]);
    }

    /**
     * Check if provider is enabled in config.
     */
    protected function isProviderEnabled(string $provider): bool
    {
        $gateways = config('billing.gateways', []);

        return isset($gateways[$provider]) &&
               ($gateways[$provider]['enabled'] ?? false);
    }

    /**
     * Get signature header based on provider.
     */
    protected function getSignatureHeader(Request $request, string $provider): string
    {
        return match ($provider) {
            'stripe' => $request->header('Stripe-Signature'),
            'paddle' => $request->header('Paddle-Signature'),
            'lemonsqueezy' => $request->header('X-Signature'),
            default => throw new \InvalidArgumentException("Unknown provider: {$provider}"),
        };
    }

    /**
     * Normalize event type across providers.
     */
    protected function normalizeEventType(string $eventType, string $provider): string
    {
        return match ($provider) {
            'stripe' => str_replace('customer.subscription.', 'subscription.', $eventType),
            'paddle' => str_replace('subscription_', 'subscription.', $eventType),
            default => $eventType,
        };
    }
}
