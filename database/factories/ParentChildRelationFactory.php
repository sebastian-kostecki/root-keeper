<?php

namespace Database\Factories;

use App\Models\ParentChildRelation;
use App\Models\Person;
use App\Models\Tree;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ParentChildRelation>
 */
class ParentChildRelationFactory extends Factory
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
            'parent_id' => fn (array $attributes): int => Person::factory()->create(['tree_id' => $attributes['tree_id']])->id,
            'child_id' => fn (array $attributes): int => Person::factory()->create(['tree_id' => $attributes['tree_id']])->id,
        ];
    }
}
