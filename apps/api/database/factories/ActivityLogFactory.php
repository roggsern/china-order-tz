<?php

namespace Database\Factories;

use App\Enums\ActivityActorType;
use App\Enums\ActivityEventType;
use App\Models\ActivityLog;
use App\Models\Admin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    protected $model = ActivityLog::class;

    public function definition(): array
    {
        $event = fake()->randomElement(ActivityEventType::cases());

        return [
            'event_type' => $event,
            'action' => $event->defaultAction(),
            'actor_type' => ActivityActorType::Admin,
            'actor_id' => Admin::factory(),
            'subject_type' => null,
            'subject_id' => null,
            'description' => fake()->sentence(),
            'old_values' => null,
            'new_values' => ['status' => 'active'],
            'metadata' => ['source' => 'factory'],
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'created_at' => now(),
        ];
    }
}
