<?php

namespace Database\Factories;

use App\Models\Person;
use App\Models\Tree;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Person>
 */
class PersonFactory extends Factory
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
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'residence' => fake()->city(),
            'religion' => null,
        ];
    }
}
