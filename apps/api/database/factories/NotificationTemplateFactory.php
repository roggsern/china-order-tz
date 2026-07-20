<?php

namespace Database\Factories;

use App\Enums\NotificationChannel;
use App\Models\NotificationTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NotificationTemplate>
 */
class NotificationTemplateFactory extends Factory
{
    protected $model = NotificationTemplate::class;

    public function definition(): array
    {
        $channel = NotificationChannel::InApp;

        return [
            'key' => 'order_created.'.$channel->value.'.'.Str::lower(Str::random(6)),
            'name' => fake()->sentence(3),
            'channel' => $channel,
            'subject' => 'Hello {{customer_name}}',
            'body' => 'Order {{order_number}} update.',
            'is_active' => true,
        ];
    }
}
