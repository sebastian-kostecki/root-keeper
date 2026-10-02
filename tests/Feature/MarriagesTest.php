<?php

use App\Enums\EventType;
use App\Enums\ParticipantRole;
use App\Models\Event;
use App\Models\Person;
use App\Models\Source;
use App\Models\User;

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

    $response = $this->from(personPreviewUrl($person))
        ->actingAs($user)
        ->post(route('marriages.store'), [
            'person_id' => $person->id,
            'spouse_id' => $spouse->id,
            'marriage' => ['year' => 1850, 'month' => 6, 'day' => 12, 'is_approximate' => true, 'place' => 'Kraków'],
        ]);

    $response->assertRedirect(personPreviewUrl($person));
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

    $this->actingAs($user)->get(personPreviewUrl($person))
        ->assertInertia(fn ($page) => $page
            ->component('persons/index')
            ->has('personPreview.person.marriages', 1)
            ->where('personPreview.person.marriages.0.place', 'Kraków')
            ->where('personPreview.person.marriages.0.spouse.id', $spouse->id));

    $this->actingAs($user)->get(personPreviewUrl($spouse))
        ->assertInertia(fn ($page) => $page
            ->has('personPreview.person.marriages', 1)
            ->where('personPreview.person.marriages.0.spouse.id', $person->id));
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

    $response->assertSessionHasErrors(['spouse_id' => 'Osoba nie może poślubić samej siebie.']);
    $this->assertDatabaseCount('events', 0);
});

test('marrying the same pair twice is rejected in both argument orders and only one marriage remains', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $spouse = Person::factory()->for($user->tree)->create();

    $this->actingAs($user)->post(route('marriages.store'), ['person_id' => $person->id, 'spouse_id' => $spouse->id]);

    $response = $this->actingAs($user)->post(route('marriages.store'), ['person_id' => $person->id, 'spouse_id' => $spouse->id]);
    $response->assertSessionHasErrors(['spouse_id' => 'Ta para ma już małżeństwo.']);

    $reversed = $this->actingAs($user)->post(route('marriages.store'), ['person_id' => $spouse->id, 'spouse_id' => $person->id]);
    $reversed->assertSessionHasErrors(['spouse_id' => 'Ta para ma już małżeństwo.']);

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

    $response = $this->actingAs($user)->get(personPreviewUrl($person));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('persons/index')
        ->has('personPreview.person.marriages', 4)
        ->where('personPreview.person.marriages.0.spouse.id', $yearOnly->id)
        ->where('personPreview.person.marriages.1.spouse.id', $yearAndMonth->id)
        ->where('personPreview.person.marriages.2.spouse.id', $laterYear->id)
        ->where('personPreview.person.marriages.3.spouse.id', $noDate->id));
});

test('a source linked to a marriage confirms it on both previews and lists both spouses on the source', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $spouse = Person::factory()->for($user->tree)->create();
    $marriage = attachMarriage($person, $spouse);
    $source = Source::factory()->for($user->tree)->create();

    $this->actingAs($user)->post(route('events.sources.store', $marriage), ['source_id' => $source->id]);

    $this->actingAs($user)->get(personPreviewUrl($person))
        ->assertInertia(fn ($page) => $page->where('personPreview.person.marriages.0.is_confirmed', true));

    $this->actingAs($user)->get(personPreviewUrl($spouse))
        ->assertInertia(fn ($page) => $page->where('personPreview.person.marriages.0.is_confirmed', true));

    $response = $this->actingAs($user)->get(route('sources.show', $source));

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

test('update changes the date and place and keeps linked sources', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $spouse = Person::factory()->for($user->tree)->create();
    $marriage = attachMarriage($person, $spouse, year: 1850, month: 6, day: 1, place: 'Warszawa');
    $source = Source::factory()->for($user->tree)->create();
    $marriage->sources()->attach($source);

    $response = $this->from(personPreviewUrl($person))
        ->actingAs($user)
        ->put(route('marriages.update', $marriage), [
            'marriage' => ['year' => 1851, 'month' => 7, 'day' => 2, 'is_approximate' => false, 'place' => 'Kraków'],
        ]);

    $response->assertRedirect(personPreviewUrl($person));
    $response->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Zapisano zmiany']);

    $marriage->refresh();
    expect($marriage->date_year)->toBe(1851);
    expect($marriage->date_month)->toBe(7);
    expect($marriage->date_day)->toBe(2);
    expect($marriage->place)->toBe('Kraków');

    $this->assertDatabaseHas('event_source', ['event_id' => $marriage->id, 'source_id' => $source->id]);
    expect($marriage->participants()->count())->toBe(2);
});

test('updating a birth event through the marriage routes returns not found', function () {
    $user = User::factory()->create();
    $birth = Event::factory()->for($user->tree)->birth()->create();

    $this->actingAs($user)->put(route('marriages.update', $birth), ['marriage' => ['year' => 1900]])
        ->assertNotFound();

    $this->actingAs($user)->put(route('marriages.update', $birth), ['marriage' => ['month' => 13]])
        ->assertNotFound();
});

test('destroying a birth event through the marriage routes returns not found', function () {
    $user = User::factory()->create();
    $birth = Event::factory()->for($user->tree)->birth()->create();

    $this->actingAs($user)->delete(route('marriages.destroy', $birth))
        ->assertNotFound();
});

test('destroy removes the event, its participants and its source links, but keeps the source, and both previews stop showing it', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $spouse = Person::factory()->for($user->tree)->create();
    $marriage = attachMarriage($person, $spouse);
    $source = Source::factory()->for($user->tree)->create();
    $marriage->sources()->attach($source);

    $response = $this->from(personPreviewUrl($person))
        ->actingAs($user)
        ->delete(route('marriages.destroy', $marriage));

    $response->assertRedirect(personPreviewUrl($person));
    $response->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Usunięto małżeństwo']);

    $this->assertDatabaseMissing('events', ['id' => $marriage->id]);
    $this->assertDatabaseMissing('event_participants', ['event_id' => $marriage->id]);
    $this->assertDatabaseMissing('event_source', ['event_id' => $marriage->id]);
    $this->assertDatabaseHas('sources', ['id' => $source->id]);

    $this->actingAs($user)->get(personPreviewUrl($person))
        ->assertInertia(fn ($page) => $page->has('personPreview.person.marriages', 0));

    $this->actingAs($user)->get(personPreviewUrl($spouse))
        ->assertInertia(fn ($page) => $page->has('personPreview.person.marriages', 0));
});

test('deleting one spouse removes the marriage but keeps the other spouse and the source', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $spouse = Person::factory()->for($user->tree)->create();
    $marriage = attachMarriage($person, $spouse);
    $source = Source::factory()->for($user->tree)->create();
    $marriage->sources()->attach($source);

    $this->actingAs($user)->delete(route('persons.destroy', $person))->assertRedirect();

    $this->assertDatabaseMissing('people', ['id' => $person->id]);
    $this->assertDatabaseMissing('events', ['id' => $marriage->id]);
    $this->assertDatabaseHas('people', ['id' => $spouse->id]);
    $this->assertDatabaseHas('sources', ['id' => $source->id]);

    $this->actingAs($user)->get(personPreviewUrl($spouse))
        ->assertInertia(fn ($page) => $page->has('personPreview.person.marriages', 0));
});

test('guests are redirected to the login page from the spouse attach route', function () {
    $person = Person::factory()->create();

    $this->post(route('persons.spouses.attach', $person), [])->assertRedirect(route('login'));
});

test('attach marries the centre to an existing person without a date and shows the new union', function () {
    $user = User::factory()->create();
    $focus = Person::factory()->for($user->tree)->male()->create();
    $spouse = Person::factory()->for($user->tree)->female()->create();

    $response = $this->actingAs($user)
        ->post(route('persons.spouses.attach', $focus), ['spouse_id' => $spouse->id]);

    $response->assertRedirect(route('tree.show', ['person' => $focus->id, 'spouse' => $spouse->id]));
    $response->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Dodano małżonka']);

    $marriage = Event::where('type', EventType::Marriage)->sole();
    expect($marriage->date_year)->toBeNull();
    expect($marriage->place)->toBeNull();
    expect($marriage->participants()->pluck('people.id')->sort()->values()->all())
        ->toBe(collect([$focus->id, $spouse->id])->sort()->values()->all());
    expect($marriage->participants()->get()->every(fn (Person $participant): bool => $participant->pivot->role === ParticipantRole::Spouse))->toBeTrue();
});

test('attach adds a second marriage and the redirected sheet selects the new union', function () {
    $user = User::factory()->create();
    $focus = Person::factory()->for($user->tree)->male()->create();
    $first = Person::factory()->for($user->tree)->female()->create();
    $second = Person::factory()->for($user->tree)->female()->create();
    attachMarriage($focus, $first, 1880);

    $this->actingAs($user)
        ->post(route('persons.spouses.attach', $focus), ['spouse_id' => $second->id])
        ->assertRedirect(route('tree.show', ['person' => $focus->id, 'spouse' => $second->id]));

    expect($focus->marriages()->count())->toBe(2);

    $this->actingAs($user)
        ->get(route('tree.show', ['person' => $focus->id, 'spouse' => $second->id]))
        ->assertInertia(fn ($page) => $page
            ->where('sheet.selected_union', "person-{$second->id}")
            ->where('sheet.partner.person.id', $second->id));
});

test('attach rejects a pair that already has a marriage', function () {
    $user = User::factory()->create();
    $focus = Person::factory()->for($user->tree)->male()->create();
    $spouse = Person::factory()->for($user->tree)->female()->create();
    attachMarriage($focus, $spouse, 1880);

    $this->actingAs($user)
        ->post(route('persons.spouses.attach', $focus), ['spouse_id' => $spouse->id])
        ->assertSessionHasErrors(['spouse_id' => 'Ta para ma już małżeństwo.']);

    $this->assertDatabaseCount('events', 1);
});

test('attach rejects marrying the person to themselves', function () {
    $user = User::factory()->create();
    $focus = Person::factory()->for($user->tree)->create();

    $this->actingAs($user)
        ->post(route('persons.spouses.attach', $focus), ['spouse_id' => $focus->id])
        ->assertSessionHasErrors(['spouse_id' => 'Osoba nie może poślubić samej siebie.']);

    $this->assertDatabaseCount('events', 0);
});

test('attach rejects a spouse from another users tree', function () {
    $user = User::factory()->create();
    $focus = Person::factory()->for($user->tree)->create();
    $foreign = Person::factory()->for(User::factory()->create()->tree)->create();

    $this->actingAs($user)
        ->post(route('persons.spouses.attach', $focus), ['spouse_id' => $foreign->id])
        ->assertSessionHasErrors('spouse_id');

    $this->assertDatabaseCount('events', 0);
});

test('guests are redirected to the login page from the spouse store route', function () {
    $person = Person::factory()->create();

    $this->post(route('persons.spouses.store', $person), [])->assertRedirect(route('login'));
});

test('store creates a new person married to the centre without a date and shows the new union', function () {
    $user = User::factory()->create();
    $focus = Person::factory()->for($user->tree)->male()->create();

    $response = $this->actingAs($user)
        ->post(route('persons.spouses.store', $focus), ['first_name' => 'Anna', 'last_name' => 'Nowak', 'gender' => 'female']);

    $spouse = Person::where('first_name', 'Anna')->sole();

    $response->assertRedirect(route('tree.show', ['person' => $focus->id, 'spouse' => $spouse->id]));
    $response->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Dodano małżonka']);

    $this->assertDatabaseCount('people', 2);
    $marriage = Event::where('type', EventType::Marriage)->sole();
    expect($marriage->date_year)->toBeNull();
    expect($marriage->place)->toBeNull();
    expect($marriage->participants()->pluck('people.id')->sort()->values()->all())
        ->toBe(collect([$focus->id, $spouse->id])->sort()->values()->all());
});

test('store without a first name creates no person and no marriage', function () {
    $user = User::factory()->create();
    $focus = Person::factory()->for($user->tree)->create();

    $this->actingAs($user)
        ->post(route('persons.spouses.store', $focus), ['first_name' => ''])
        ->assertSessionHasErrors('first_name');

    $this->assertDatabaseCount('people', 1);
    expect(Event::where('type', EventType::Marriage)->count())->toBe(0);
});

test('store without a date creates one marriage with no date and no place', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $spouse = Person::factory()->for($user->tree)->create();

    $this->actingAs($user)
        ->post(route('marriages.store'), ['person_id' => $person->id, 'spouse_id' => $spouse->id])
        ->assertSessionHasNoErrors();

    $marriage = Event::where('type', EventType::Marriage)->sole();
    expect($marriage->date_year)->toBeNull();
    expect($marriage->date_month)->toBeNull();
    expect($marriage->date_day)->toBeNull();
    expect($marriage->place)->toBeNull();
    expect($marriage->participants()->count())->toBe(2);
});
