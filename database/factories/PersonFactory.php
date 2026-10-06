<?php

namespace Database\Factories;

use App\Enums\Gender;
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
            'gender' => null,
            'religion' => null,
        ];
    }

    public function male(): static
    {
        return $this->state(fn (array $attributes): array => ['gender' => Gender::Male]);
    }

    public function female(): static
    {
        return $this->state(fn (array $attributes): array => ['gender' => Gender::Female]);
    }
}
