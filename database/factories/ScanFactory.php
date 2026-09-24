<?php

namespace Database\Factories;

use App\Models\Scan;
use App\Models\Tree;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Scan>
 */
class ScanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tree_id' => Tree::factory(),
            'original_name' => fake()->slug().'.jpg',
            'path' => fn (array $attributes): string => "scans/{$attributes['tree_id']}/".Str::uuid().'.jpg',
            'thumbnail_path' => null,
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(50_000, 5_000_000),
            'width' => fake()->numberBetween(800, 4000),
            'height' => fake()->numberBetween(600, 3000),
        ];
    }

    /**
     * Indicate that the scan has a thumbnail.
     */
    public function withThumbnail(): static
    {
        return $this->state([
            'thumbnail_path' => fn (array $attributes): string => "scans/{$attributes['tree_id']}/thumbnails/".Str::uuid().'.jpg',
        ]);
    }
}
