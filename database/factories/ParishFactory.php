<?php

namespace Database\Factories;

use App\Enums\Religion;
use App\Models\Locality;
use App\Models\Parish;
use App\Models\Tree;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Parish>
 */
class ParishFactory extends Factory
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
            'name' => 'Parafia '.fake()->unique()->numerify('######'),
            'religion' => null,
            'locality_id' => null,
        ];
    }

    /**
     * Indicate that the parish has a religion.
     */
    public function withReligion(Religion $religion): static
    {
        return $this->state(fn (): array => ['religion' => $religion]);
    }

    /**
     * Indicate that the parish is seated in the given locality.
     */
    public function seatedIn(Locality $locality): static
    {
        return $this->state(fn (): array => ['tree_id' => $locality->tree_id, 'locality_id' => $locality->id]);
    }
}
