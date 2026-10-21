<?php

namespace Database\Factories;

use App\Enums\CemeteryManager;
use App\Enums\Religion;
use App\Models\Cemetery;
use App\Models\Locality;
use App\Models\Tree;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cemetery>
 */
class CemeteryFactory extends Factory
{
    /**
     * Define the model's default state. The locality is created in the cemetery's own tree.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tree_id' => Tree::factory(),
            'locality_id' => fn (array $attributes): int => Locality::factory()->create(['tree_id' => $attributes['tree_id']])->id,
            'name' => 'Cmentarz '.fake()->unique()->numerify('######'),
            'manager' => null,
            'religion' => null,
            'description' => null,
        ];
    }

    /**
     * Indicate that the cemetery has no name.
     */
    public function unnamed(): static
    {
        return $this->state(fn (): array => ['name' => null]);
    }

    /**
     * Indicate that the cemetery lies in the given locality (and its tree).
     */
    public function in(Locality $locality): static
    {
        return $this->state(fn (): array => ['tree_id' => $locality->tree_id, 'locality_id' => $locality->id]);
    }

    /**
     * Indicate that the cemetery has a manager.
     */
    public function withManager(CemeteryManager $manager): static
    {
        return $this->state(fn (): array => ['manager' => $manager]);
    }

    /**
     * Indicate that the cemetery has a religion.
     */
    public function withReligion(Religion $religion): static
    {
        return $this->state(fn (): array => ['religion' => $religion]);
    }
}
