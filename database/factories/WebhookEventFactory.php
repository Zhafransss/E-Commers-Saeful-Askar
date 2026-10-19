<?php

namespace Database\Factories;

use App\Enums\WebhookEventStatus;
use App\Enums\WebhookProvider;
use App\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookEvent>
 */
class WebhookEventFactory extends Factory
{
    protected $model = WebhookEvent::class;

    public function definition(): array
    {
        $payload = ['action' => 'update-product', 'item_group_id' => fake()->numberBetween(1000, 9999)];

        return [
            'provider' => WebhookProvider::Jubelio,
            'event_type' => 'update-product',
            'idempotency_key' => 'jubelio:update-product:'.hash('sha256', json_encode($payload).fake()->uuid()),
            'signature_valid' => true,
            'status' => WebhookEventStatus::Received,
            'payload' => $payload,
            'summary' => "update-product item_group_id={$payload['item_group_id']}",
            'received_at' => now(),
        ];
    }
}
