<?php

namespace Database\Factories;

use App\Enums\CommerceChannelCode;
use App\Models\CommerceChannel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommerceChannel>
 */
class CommerceChannelFactory extends Factory
{
    protected $model = CommerceChannel::class;

    public function definition(): array
    {
        return [
            'name' => 'Buy From China',
            'code' => CommerceChannelCode::ChinaImport->value,
            'description' => 'Import commerce channel.',
            'is_active' => true,
        ];
    }

    public function china(): static
    {
        return $this->state(fn () => [
            'name' => CommerceChannelCode::ChinaImport->label(),
            'code' => CommerceChannelCode::ChinaImport->value,
            'description' => 'Import commerce channel — air/sea shipping and customer agent delivery.',
        ]);
    }

    public function tanzania(): static
    {
        return $this->state(fn () => [
            'name' => CommerceChannelCode::TzLocal->label(),
            'code' => CommerceChannelCode::TzLocal->value,
            'description' => 'Local Tanzania commerce channel — self pickup and negotiated delivery.',
        ]);
    }
}
