<?php

namespace Database\Factories;

use App\Models\Locality;
use App\Models\Person;
use App\Models\Residence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Residence>
 */
class ResidenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'tree_id' => fn (array $attributes): int => Person::query()->where('id', $attributes['person_id'])->firstOrFail()->tree_id,
            'locality_id' => fn (array $attributes): int => Locality::factory()->create(['tree_id' => $attributes['tree_id']])->id,
            'year_from' => null,
            'year_to' => null,
        ];
    }

    /**
     * Indicate that the residence lasted from and/or to the given years.
     */
    public function years(?int $from, ?int $to): static
    {
        return $this->state(fn (array $attributes): array => [
            'year_from' => $from,
            'year_to' => $to,
        ]);
    }
}
