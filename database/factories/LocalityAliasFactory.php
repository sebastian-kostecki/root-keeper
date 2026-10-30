<?php

namespace Database\Factories;

use App\Models\Locality;
use App\Models\LocalityAlias;
use App\Models\Tree;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LocalityAlias>
 */
class LocalityAliasFactory extends Factory
{
    /**
     * Define the model's default state. The locality is created in the alias's own tree.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tree_id' => Tree::factory(),
            'locality_id' => fn (array $attributes): int => Locality::factory()->create(['tree_id' => $attributes['tree_id']])->id,
            'name' => 'Wariant '.fake()->unique()->numerify('######'),
        ];
    }
}
