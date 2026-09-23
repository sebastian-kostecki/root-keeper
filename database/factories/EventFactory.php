<?php

namespace Database\Factories;

use App\Enums\EventType;
use App\Models\Event;
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
            'type' => fake()->randomElement(EventType::cases()),
            'date_year' => fake()->numberBetween(1700, 2020),
            'date_month' => fake()->numberBetween(1, 12),
            'date_day' => fake()->numberBetween(1, 28),
            'date_is_approximate' => false,
            'place' => fake()->city(),
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
