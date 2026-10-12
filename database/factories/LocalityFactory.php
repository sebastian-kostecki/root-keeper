<?php

namespace Database\Factories;

use App\Models\Locality;
use App\Models\Tree;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Locality>
 */
class LocalityFactory extends Factory
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
            'name' => fake()->randomElement([
                'Kraków', 'Lwów', 'Lipnica Murowana', 'Luboml', 'Łódź', 'Mielec', 'Tarnów', 'Sanok',
                'Przemyśl', 'Żywiec', 'Zakopane', 'Rzeszów', 'Nowy Sącz', 'Wieliczka', 'Bochnia', 'Jasło',
            ]).' '.fake()->unique()->numerify('###'),
            'description' => null,
        ];
    }
}
