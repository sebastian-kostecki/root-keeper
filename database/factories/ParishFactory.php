<?php

namespace Database\Factories;

use App\Enums\ParishKind;
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
            'name' => null,
            'kind' => ParishKind::Parish,
            'dedication' => null,
            'religion' => null,
            'locality_id' => fn (array $attributes): int => Locality::factory()->create(['tree_id' => $attributes['tree_id']])->id,
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

    /**
     * Indicate the kind of the record keeper.
     */
    public function ofKind(ParishKind $kind): static
    {
        return $this->state(fn (): array => ['kind' => $kind]);
    }

    /**
     * Indicate the dedication or distinguishing mark.
     */
    public function dedicatedTo(string $dedication): static
    {
        return $this->state(fn (): array => ['dedication' => $dedication]);
    }

    /**
     * A row in the old shape: a former name and, by default, no seat.
     */
    public function legacy(string $name, ?Locality $seat = null): static
    {
        return $this->state(fn (): array => [
            'name' => $name,
            'locality_id' => $seat?->id,
            ...($seat === null ? [] : ['tree_id' => $seat->tree_id]),
        ]);
    }
}
