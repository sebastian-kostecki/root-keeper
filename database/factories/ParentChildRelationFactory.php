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
        $tree = Tree::factory()->create();

        return [
            'tree_id' => $tree->id,
            'parent_id' => Person::factory()->for($tree),
            'child_id' => Person::factory()->for($tree),
        ];
    }
}
