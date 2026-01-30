<?php

namespace Modules\Billing\Tests\Unit\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Billing\Models\WebhookEvent;
use Tests\TestCase;

class WebhookEventTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_is_processed_returns_true_when_processed_at_set(): void
    {
        $event = WebhookEvent::factory()->processed()->create();

        $this->assertTrue($event->isProcessed());
        $this->assertNotNull($event->processed_at);
    }

    /** @test */
    public function test_is_processed_returns_false_when_processed_at_null(): void
    {
        $event = WebhookEvent::factory()->create([
            'processed_at' => null,
        ]);

        $this->assertFalse($event->isProcessed());
    }

    /** @test */
    public function test_mark_as_processed_sets_timestamp(): void
    {
        $event = WebhookEvent::factory()->create([
            'processed_at' => null,
        ]);

        $this->assertFalse($event->isProcessed());

        $event->markAsProcessed();
        $event->refresh();

        $this->assertTrue($event->isProcessed());
        $this->assertNotNull($event->processed_at);
    }

    /** @test */
    public function test_unprocessed_scope_filters_null_processed_at(): void
    {
        // Create processed events
        WebhookEvent::factory()->processed()->count(3)->create();

        // Create unprocessed events
        WebhookEvent::factory()->count(2)->create([
            'processed_at' => null,
        ]);

        $unprocessed = WebhookEvent::unprocessed()->get();

        $this->assertCount(2, $unprocessed);
        $unprocessed->each(function ($event) {
            $this->assertNull($event->processed_at);
        });
    }
}
