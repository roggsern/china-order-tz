<?php

namespace Database\Factories;

use App\Enums\NotificationChannel;
use App\Enums\NotificationDeliveryStatus;
use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        $type = fake()->randomElement(NotificationType::cases());

        return [
            'user_id' => User::factory(),
            'type' => $type,
            'event_type' => $type->value,
            'template_key' => null,
            'title' => fake()->sentence(4),
            'message' => fake()->paragraph(),
            'channel' => NotificationChannel::InApp,
            'status' => NotificationDeliveryStatus::Sent,
            'provider' => 'in_app',
            'data' => ['source' => 'system'],
            'sent_at' => now(),
            'read_at' => fake()->optional(0.3)->dateTime(),
        ];
    }

    public function unread(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => null,
            'status' => NotificationDeliveryStatus::Sent,
        ]);
    }
}
