<?php

namespace Database\Factories;

use App\Enums\EventType;
use App\Models\Cemetery;
use App\Models\Event;
use App\Models\Locality;
use App\Models\Tree;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
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
            'type' => fake()->randomElement(array_filter(EventType::cases(), fn (EventType $type): bool => $type->acceptsSources())),
            'date_year' => fake()->numberBetween(1700, 2020),
            'date_month' => fake()->numberBetween(1, 12),
            'date_day' => fake()->numberBetween(1, 28),
            'date_is_approximate' => false,
        ];
    }

    /**
     * Indicate that the event is a birth.
     */
    public function birth(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => EventType::Birth,
        ]);
    }

    /**
     * Indicate that the event is a death.
     */
    public function death(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => EventType::Death,
        ]);
    }

    /**
     * Indicate that the event is a burial: no date, only a place.
     */
    public function burial(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => EventType::Burial,
            'date_year' => null,
            'date_month' => null,
            'date_day' => null,
            'date_is_approximate' => false,
        ]);
    }

    /**
     * Indicate that the burial lies in the given cemetery, and so in the cemetery's locality.
     */
    public function atCemetery(Cemetery $cemetery): static
    {
        return $this->state(fn (): array => [
            'tree_id' => $cemetery->tree_id,
            'cemetery_id' => $cemetery->id,
            'locality_id' => $cemetery->locality_id,
        ]);
    }

    /**
     * Indicate that the event took place in a locality of the event's own tree.
     */
    public function withLocality(): static
    {
        return $this->afterMaking(function (Event $event): void {
            $event->locality_id = Locality::factory()->create(['tree_id' => $event->tree_id])->id;
        });
    }

    /**
     * Indicate that the event's date is approximate.
     */
    public function approximate(): static
    {
        return $this->state(fn (array $attributes): array => [
            'date_is_approximate' => true,
        ]);
    }

    /**
     * Indicate that the event has no date information at all.
     */
    public function withoutDate(): static
    {
        return $this->state(fn (array $attributes): array => [
            'date_year' => null,
            'date_month' => null,
            'date_day' => null,
            'date_is_approximate' => false,
        ]);
    }
}
