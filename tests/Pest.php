<?php

use App\Enums\EventType;
use App\Enums\ParticipantRole;
use App\Models\Event;
use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * Attach a birth event with the given partial date to a person.
 */
function attachBirth(Person $person, ?int $year = null, ?int $month = null, ?int $day = null): void
{
    $event = Event::factory()->for($person->tree)->birth()->create([
        'date_year' => $year,
        'date_month' => $month,
        'date_day' => $day,
    ]);

    $event->participants()->attach($person, ['role' => ParticipantRole::Subject]);
}

/**
 * Attach a marriage event with the given partial date to the two people.
 */
function attachMarriage(Person $person, Person $spouse, ?int $year = null, ?int $month = null, ?int $day = null, ?string $place = null): Event
{
    $event = Event::factory()->for($person->tree)->create([
        'type' => EventType::Marriage,
        'date_year' => $year,
        'date_month' => $month,
        'date_day' => $day,
        'date_is_approximate' => false,
        'place' => $place,
    ]);

    $event->participants()->attach([
        $person->id => ['role' => ParticipantRole::Spouse],
        $spouse->id => ['role' => ParticipantRole::Spouse],
    ]);

    return $event;
}
