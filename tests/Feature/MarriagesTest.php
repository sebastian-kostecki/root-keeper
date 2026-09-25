<?php

use App\Enums\EventType;
use App\Enums\ParticipantRole;
use App\Models\Event;
use App\Models\Person;
use App\Models\Scan;
use App\Models\User;

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

test('guests are redirected to the login page from marriage routes', function () {
    $marriage = Event::factory()->create(['type' => EventType::Marriage]);

    $this->post(route('marriages.store'), [])->assertRedirect(route('login'));
    $this->put(route('marriages.update', $marriage), [])->assertRedirect(route('login'));
    $this->delete(route('marriages.destroy', $marriage))->assertRedirect(route('login'));
});

test('store creates exactly one marriage event with both people as spouses, redirects back and flashes the toast', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $spouse = Person::factory()->for($user->tree)->create();

    $response = $this->from(route('persons.show', $person))
        ->actingAs($user)
        ->post(route('marriages.store'), [
            'person_id' => $person->id,
            'spouse_id' => $spouse->id,
            'marriage' => ['year' => 1850, 'month' => 6, 'day' => 12, 'is_approximate' => true, 'place' => 'Kraków'],
        ]);

    $response->assertRedirect(route('persons.show', $person));
    $response->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Dodano małżeństwo']);

    $this->assertDatabaseCount('events', 1);

    $marriage = Event::where('type', EventType::Marriage)->sole();
    expect($marriage->tree_id)->toBe($user->tree->id);
    expect($marriage->date_year)->toBe(1850);
    expect($marriage->date_month)->toBe(6);
    expect($marriage->date_day)->toBe(12);
    expect($marriage->date_is_approximate)->toBeTrue();
    expect($marriage->place)->toBe('Kraków');

    $participants = $marriage->participants()->get();
    expect($participants)->toHaveCount(2);
    expect($participants->pluck('id')->sort()->values()->all())->toBe(collect([$person->id, $spouse->id])->sort()->values()->all());
    expect($participants->every(fn (Person $participant): bool => $participant->pivot->role === ParticipantRole::Spouse))->toBeTrue();

    $this->actingAs($user)->get(route('persons.show', $person))
        ->assertInertia(fn ($page) => $page
            ->component('persons/show')
            ->has('person.marriages', 1)
            ->where('person.marriages.0.place', 'Kraków')
            ->where('person.marriages.0.spouse.id', $spouse->id));

    $this->actingAs($user)->get(route('persons.show', $spouse))
        ->assertInertia(fn ($page) => $page
            ->has('person.marriages', 1)
            ->where('person.marriages.0.spouse.id', $person->id));
});

test('an impossible calendar date is rejected with the shared message', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $spouse = Person::factory()->for($user->tree)->create();

    $response = $this->actingAs($user)->post(route('marriages.store'), [
        'person_id' => $person->id,
        'spouse_id' => $spouse->id,
        'marriage' => ['year' => 1850, 'month' => 2, 'day' => 31],
    ]);

    $response->assertSessionHasErrors(['marriage.day' => 'Podana data jest nieprawidłowa.']);
    $this->assertDatabaseCount('events', 0);
});

test('a marriage month without a year is rejected', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $spouse = Person::factory()->for($user->tree)->create();

    $response = $this->actingAs($user)->post(route('marriages.store'), [
        'person_id' => $person->id,
        'spouse_id' => $spouse->id,
        'marriage' => ['month' => 6],
    ]);

    $response->assertSessionHasErrors('marriage.year');
    $this->assertDatabaseCount('events', 0);
});

test('marrying oneself is rejected on spouse_id and nothing is created', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();

    $response = $this->actingAs($user)->post(route('marriages.store'), [
        'person_id' => $person->id,
        'spouse_id' => $person->id,
    ]);

    $response->assertSessionHasErrors('spouse_id');
    $this->assertDatabaseCount('events', 0);
});

test('marrying the same pair twice is rejected in both argument orders and only one marriage remains', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $spouse = Person::factory()->for($user->tree)->create();

    $this->actingAs($user)->post(route('marriages.store'), ['person_id' => $person->id, 'spouse_id' => $spouse->id]);

    $response = $this->actingAs($user)->post(route('marriages.store'), ['person_id' => $person->id, 'spouse_id' => $spouse->id]);
    $response->assertSessionHasErrors('spouse_id');

    $reversed = $this->actingAs($user)->post(route('marriages.store'), ['person_id' => $spouse->id, 'spouse_id' => $person->id]);
    $reversed->assertSessionHasErrors('spouse_id');

    $this->assertDatabaseCount('events', 1);
});

test('a spouse_id or person_id from another tree fails validation and creates nothing', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $ownPerson = Person::factory()->for($user->tree)->create();
    $foreignPerson = Person::factory()->for($otherUser->tree)->create();

    $response = $this->actingAs($user)->post(route('marriages.store'), ['person_id' => $foreignPerson->id, 'spouse_id' => $ownPerson->id]);
    $response->assertSessionHasErrors('person_id');
    $this->assertDatabaseCount('events', 0);

    $response = $this->actingAs($user)->post(route('marriages.store'), ['person_id' => $ownPerson->id, 'spouse_id' => $foreignPerson->id]);
    $response->assertSessionHasErrors('spouse_id');
    $this->assertDatabaseCount('events', 0);
});

test('a person with several marriages sees them ordered by date with unknown dates last', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();

    $noDate = Person::factory()->for($user->tree)->create();
    attachMarriage($person, $noDate);

    $laterYear = Person::factory()->for($user->tree)->create();
    attachMarriage($person, $laterYear, year: 1880);

    $yearAndMonth = Person::factory()->for($user->tree)->create();
    attachMarriage($person, $yearAndMonth, year: 1850, month: 3);

    $yearOnly = Person::factory()->for($user->tree)->create();
    attachMarriage($person, $yearOnly, year: 1850);

    $response = $this->actingAs($user)->get(route('persons.show', $person));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('persons/show')
        ->has('person.marriages', 4)
        ->where('person.marriages.0.spouse.id', $yearOnly->id)
        ->where('person.marriages.1.spouse.id', $yearAndMonth->id)
        ->where('person.marriages.2.spouse.id', $laterYear->id)
        ->where('person.marriages.3.spouse.id', $noDate->id));
});

test('a scan linked to a marriage confirms it on both previews and lists both spouses on the scan', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $spouse = Person::factory()->for($user->tree)->create();
    $marriage = attachMarriage($person, $spouse);
    $scan = Scan::factory()->for($user->tree)->create();

    $this->actingAs($user)->post(route('events.scans.store', $marriage), ['scan_id' => $scan->id]);

    $this->actingAs($user)->get(route('persons.show', $person))
        ->assertInertia(fn ($page) => $page->where('person.marriages.0.is_confirmed', true));

    $this->actingAs($user)->get(route('persons.show', $spouse))
        ->assertInertia(fn ($page) => $page->where('person.marriages.0.is_confirmed', true));

    $response = $this->actingAs($user)->get(route('scans.show', $scan));

    $response->assertOk();
    $confirms = null;
    $response->assertInertia(function ($page) use (&$confirms) {
        $confirms = $page->toArray()['props']['confirms'];

        return $page
            ->has('confirms', 2)
            ->where('confirms.0.event_type', 'marriage')
            ->where('confirms.1.event_type', 'marriage');
    });

    $confirmedPersonIds = collect($confirms)->pluck('person.id')->sort()->values()->all();
    expect($confirmedPersonIds)->toBe(collect([$person->id, $spouse->id])->sort()->values()->all());
});

test('update changes the date and place and keeps linked scans', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $spouse = Person::factory()->for($user->tree)->create();
    $marriage = attachMarriage($person, $spouse, year: 1850, month: 6, day: 1, place: 'Warszawa');
    $scan = Scan::factory()->for($user->tree)->create();
    $marriage->scans()->attach($scan);

    $response = $this->from(route('persons.show', $person))
        ->actingAs($user)
        ->put(route('marriages.update', $marriage), [
            'marriage' => ['year' => 1851, 'month' => 7, 'day' => 2, 'is_approximate' => false, 'place' => 'Kraków'],
        ]);

    $response->assertRedirect(route('persons.show', $person));
    $response->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Zapisano zmiany']);

    $marriage->refresh();
    expect($marriage->date_year)->toBe(1851);
    expect($marriage->date_month)->toBe(7);
    expect($marriage->date_day)->toBe(2);
    expect($marriage->place)->toBe('Kraków');

    $this->assertDatabaseHas('event_scan', ['event_id' => $marriage->id, 'scan_id' => $scan->id]);
    expect($marriage->participants()->count())->toBe(2);
});

test('updating a birth event through the marriage routes returns not found', function () {
    $user = User::factory()->create();
    $birth = Event::factory()->for($user->tree)->birth()->create();

    $this->actingAs($user)->put(route('marriages.update', $birth), ['marriage' => ['year' => 1900]])
        ->assertNotFound();
});

test('destroying a birth event through the marriage routes returns not found', function () {
    $user = User::factory()->create();
    $birth = Event::factory()->for($user->tree)->birth()->create();

    $this->actingAs($user)->delete(route('marriages.destroy', $birth))
        ->assertNotFound();
});

test('updating another trees marriage returns not found', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $personA = Person::factory()->for($otherUser->tree)->create();
    $personB = Person::factory()->for($otherUser->tree)->create();
    $marriage = attachMarriage($personA, $personB);

    $this->actingAs($user)->put(route('marriages.update', $marriage), ['marriage' => ['year' => 1900]])
        ->assertNotFound();
});

test('destroying another trees marriage returns not found', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $personA = Person::factory()->for($otherUser->tree)->create();
    $personB = Person::factory()->for($otherUser->tree)->create();
    $marriage = attachMarriage($personA, $personB);

    $this->actingAs($user)->delete(route('marriages.destroy', $marriage))
        ->assertNotFound();

    $this->assertDatabaseHas('events', ['id' => $marriage->id]);
});

test('destroy removes the event, its participants and its scan links, but keeps the scan, and both previews stop showing it', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $spouse = Person::factory()->for($user->tree)->create();
    $marriage = attachMarriage($person, $spouse);
    $scan = Scan::factory()->for($user->tree)->create();
    $marriage->scans()->attach($scan);

    $response = $this->from(route('persons.show', $person))
        ->actingAs($user)
        ->delete(route('marriages.destroy', $marriage));

    $response->assertRedirect(route('persons.show', $person));
    $response->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Usunięto małżeństwo']);

    $this->assertDatabaseMissing('events', ['id' => $marriage->id]);
    $this->assertDatabaseMissing('event_participants', ['event_id' => $marriage->id]);
    $this->assertDatabaseMissing('event_scan', ['event_id' => $marriage->id]);
    $this->assertDatabaseHas('scans', ['id' => $scan->id]);

    $this->actingAs($user)->get(route('persons.show', $person))
        ->assertInertia(fn ($page) => $page->has('person.marriages', 0));

    $this->actingAs($user)->get(route('persons.show', $spouse))
        ->assertInertia(fn ($page) => $page->has('person.marriages', 0));
});

test('deleting one spouse removes the marriage but keeps the other spouse and the scan', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $spouse = Person::factory()->for($user->tree)->create();
    $marriage = attachMarriage($person, $spouse);
    $scan = Scan::factory()->for($user->tree)->create();
    $marriage->scans()->attach($scan);

    $this->actingAs($user)->delete(route('persons.destroy', $person))->assertRedirect();

    $this->assertDatabaseMissing('people', ['id' => $person->id]);
    $this->assertDatabaseMissing('events', ['id' => $marriage->id]);
    $this->assertDatabaseHas('people', ['id' => $spouse->id]);
    $this->assertDatabaseHas('scans', ['id' => $scan->id]);
});

test('relationCandidates marks the existing spouse and leaves other people unblocked', function () {
    $user = User::factory()->create();
    $tree = $user->tree;

    $person = Person::factory()->for($tree)->create();
    $spouse = Person::factory()->for($tree)->create();
    $other = Person::factory()->for($tree)->create();
    attachMarriage($person, $spouse);

    $initial = $this->actingAs($user)->get(route('persons.show', $person), ['X-Inertia' => 'true']);
    $version = $initial->headers->get('X-Inertia-Version');

    $response = $this->actingAs($user)->get(route('persons.show', $person), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $version,
        'X-Inertia-Partial-Data' => 'relationCandidates',
        'X-Inertia-Partial-Component' => 'persons/show',
    ]);

    $response->assertOk();
    $candidates = collect($response->json('props.relationCandidates'))->keyBy('id');

    expect($candidates[$spouse->id]['as_spouse_blocked_reason'])->toBe('Już jest małżonkiem');
    expect($candidates[$other->id]['as_spouse_blocked_reason'])->toBeNull();
});
