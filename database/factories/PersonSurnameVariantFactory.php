<?php

namespace Database\Factories;

use App\Models\Person;
use App\Models\PersonSurnameVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersonSurnameVariant>
 */
class PersonSurnameVariantFactory extends Factory
{
    /**
     * Define the model's default state. The tree is taken from the person.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'tree_id' => fn (array $attributes): int => Person::query()->where('id', $attributes['person_id'])->firstOrFail()->tree_id,
            'name' => 'Wariant '.fake()->unique()->numerify('######'),
            'position' => 0,
        ];
    }
}
