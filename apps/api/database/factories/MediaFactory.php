<?php

namespace Database\Factories;

use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    protected $model = Media::class;

    public function definition(): array
    {
        $filename = fake()->uuid().'.jpg';

        return [
            'disk' => 'public',
            'path' => 'cms/hero/'.$filename,
            'filename' => $filename,
            'mime' => 'image/jpeg',
            'size' => fake()->numberBetween(10_000, 500_000),
            'alt_text' => fake()->optional()->sentence(3),
            'mediable_type' => null,
            'mediable_id' => null,
        ];
    }

    public function image(): static
    {
        return $this->state(fn () => [
            'mime' => 'image/jpeg',
            'filename' => fake()->uuid().'.jpg',
        ]);
    }

    public function video(): static
    {
        return $this->state(fn () => [
            'mime' => 'video/mp4',
            'filename' => fake()->uuid().'.mp4',
        ]);
    }
}
