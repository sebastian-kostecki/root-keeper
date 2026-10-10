<?php

use App\Enums\EventType;
use App\Enums\ParticipantRole;
use App\Models\Event;
use App\Models\Locality;
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

    $krakow = Locality::factory()->for($user->tree)->create(['name' => 'Kraków']);

    $response = $this->from(personPreviewUrl($person))
        ->actingAs($user)
        ->post(route('marriages.store'), [
            'person_id' => $person->id,
            'spouse_id' => $spouse->id,
            'marriage' => ['year' => 1850, 'month' => 6, 'day' => 12, 'is_approximate' => true, 'locality_id' => $krakow->id],
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
    expect($marriage->locality_id)->toBe($krakow->id);

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

    $response = $this->actingAs($user)->get(route('sources.index', ['source' => $source->id]));

    $response->assertOk();
    $confirms = null;
    $response->assertInertia(function ($page) use (&$confirms) {
        $confirms = $page->toArray()['props']['sourcePanel']['confirms'];

        return $page
            ->has('sourcePanel.confirms', 2)
            ->where('sourcePanel.confirms.0.event_type', 'marriage')
            ->where('sourcePanel.confirms.1.event_type', 'marriage');
    });

    $confirmedPersonIds = collect($confirms)->pluck('person.id')->sort()->values()->all();
    expect($confirmedPersonIds)->toBe(collect([$person->id, $spouse->id])->sort()->values()->all());
});

test('update changes the date and locality and keeps linked sources', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $spouse = Person::factory()->for($user->tree)->create();
    $warszawa = Locality::factory()->for($user->tree)->create(['name' => 'Warszawa']);
    $krakow = Locality::factory()->for($user->tree)->create(['name' => 'Kraków']);
    $marriage = attachMarriage($person, $spouse, year: 1850, month: 6, day: 1, locality: $warszawa);
    $source = Source::factory()->for($user->tree)->create();
    $marriage->sources()->attach($source);

    $response = $this->from(personPreviewUrl($person))
        ->actingAs($user)
        ->put(route('marriages.update', $marriage), [
            'marriage' => ['year' => 1851, 'month' => 7, 'day' => 2, 'is_approximate' => false, 'locality_id' => $krakow->id],
        ]);

    $response->assertRedirect(personPreviewUrl($person));
    $response->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Zapisano zmiany']);

    $marriage->refresh();
    expect($marriage->date_year)->toBe(1851);
    expect($marriage->date_month)->toBe(7);
    expect($marriage->date_day)->toBe(2);
    expect($marriage->locality_id)->toBe($krakow->id);
    expect($marriage->place)->toBeNull();

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

test('attach keeps the panel that was open before adding the spouse', function () {
    $user = User::factory()->create();
    $focus = Person::factory()->for($user->tree)->male()->create();
    $spouse = Person::factory()->for($user->tree)->female()->create();
    $openPerson = Person::factory()->for($user->tree)->create();

    $this->from(route('tree.show', ['person' => $focus]).'?person='.$openPerson->id)
        ->actingAs($user)
        ->post(route('persons.spouses.attach', $focus), ['spouse_id' => $spouse->id])
        ->assertRedirect(route('tree.show', ['person' => $focus->id, 'spouse' => $spouse->id]).'&person='.$openPerson->id);
});

test('attach ignores a person kept only in the session previous url', function () {
    $user = User::factory()->create();
    $focus = Person::factory()->for($user->tree)->male()->create();
    $spouse = Person::factory()->for($user->tree)->female()->create();
    $openPerson = Person::factory()->for($user->tree)->create();

    $this->withSession(['_previous' => ['url' => route('tree.show', ['person' => $focus]).'?person='.$openPerson->id]])
        ->actingAs($user)
        ->post(route('persons.spouses.attach', $focus), ['spouse_id' => $spouse->id])
        ->assertRedirect(route('tree.show', ['person' => $focus->id, 'spouse' => $spouse->id]));
});

test('attach ignores a non-numeric person in the previous url', function () {
    $user = User::factory()->create();
    $focus = Person::factory()->for($user->tree)->male()->create();
    $spouse = Person::factory()->for($user->tree)->female()->create();

    $this->from(route('tree.show', ['person' => $focus]).'?person=abc')
        ->actingAs($user)
        ->post(route('persons.spouses.attach', $focus), ['spouse_id' => $spouse->id])
        ->assertRedirect(route('tree.show', ['person' => $focus->id, 'spouse' => $spouse->id]));
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

test('store keeps the panel that was open before adding the spouse', function () {
    $user = User::factory()->create();
    $focus = Person::factory()->for($user->tree)->male()->create();
    $openPerson = Person::factory()->for($user->tree)->create();

    $response = $this->from(route('tree.show', ['person' => $focus]).'?person='.$openPerson->id)
        ->actingAs($user)
        ->post(route('persons.spouses.store', $focus), ['first_name' => 'Anna', 'last_name' => 'Nowak', 'gender' => 'female']);

    $spouse = Person::where('first_name', 'Anna')->sole();

    $response->assertRedirect(route('tree.show', ['person' => $focus->id, 'spouse' => $spouse->id]).'&person='.$openPerson->id);
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

test('a foreign or missing locality in marriage.locality_id fails validation and writes nothing', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $spouse = Person::factory()->for($user->tree)->create();
    $foreign = Locality::factory()->for(User::factory()->create()->tree)->create();
    $marriage = attachMarriage($person, $spouse, 1850);

    foreach ([$foreign->id, 999999] as $localityId) {
        $this->actingAs($user)->post(route('marriages.store'), [
            'person_id' => $person->id,
            'spouse_id' => Person::factory()->for($user->tree)->create()->id,
            'marriage' => ['locality_id' => $localityId],
        ])->assertSessionHasErrors('marriage.locality_id');

        $this->actingAs($user)->put(route('marriages.update', $marriage), [
            'marriage' => ['year' => 1851, 'locality_id' => $localityId],
        ])->assertSessionHasErrors('marriage.locality_id');
    }

    expect(Event::where('type', EventType::Marriage)->count())->toBe(1)
        ->and($marriage->refresh()->date_year)->toBe(1850)
        ->and($marriage->locality_id)->toBeNull();
});

test('the marriage panel opens the marriage with its spouses, shared children and sources in order', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $scene = familyScene($user);
    $focus = $scene['focus'];
    $marriageA = $scene['marriageA'];
    $marriageA->sources()->updateExistingPivot($scene['sourceShared']->id, ['entry_number' => '12']);
    $marriageA->sources()->updateExistingPivot($scene['sourceOther']->id, ['entry_number' => '34']);

    $this->actingAs($user)->get(marriagePanelUrl($marriageA))
        ->assertInertia(fn ($page) => $page
            ->where('marriagePanel.spouses.0.id', $focus->id)
            ->where('marriagePanel.spouses.1.id', $scene['wifeA']->id)
            ->has('marriagePanel.children', 1)
            ->where('marriagePanel.children.0.id', $scene['childA']->id)
            ->has('marriagePanel.marriage.sources', 2)
            ->where('marriagePanel.marriage.sources.0.id', $scene['sourceShared']->id)
            ->where('marriagePanel.marriage.sources.0.entry_number', '12')
            ->where('marriagePanel.marriage.sources.1.id', $scene['sourceOther']->id)
            ->where('marriagePanel.marriage.sources.1.entry_number', '34'));
});

test('the marriage panel lists only the children both spouses share', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $scene = familyScene($user);

    $this->actingAs($user)->get(marriagePanelUrl($scene['marriageB']))
        ->assertInertia(fn ($page) => $page
            ->has('marriagePanel.children', 1)
            ->where('marriagePanel.children.0.id', $scene['childB']->id));
});

test('spouses put the man first whatever the creation order', function () {
    $user = User::factory()->create();
    $woman = Person::factory()->for($user->tree)->female()->create();
    $man = Person::factory()->for($user->tree)->male()->create();
    $marriage = attachMarriage($woman, $man, 1900);

    $this->actingAs($user)->get(marriagePanelUrl($marriage))
        ->assertInertia(fn ($page) => $page
            ->where('marriagePanel.spouses.0.id', $man->id)
            ->where('marriagePanel.spouses.1.id', $woman->id));
});

test('a man with an unknown-sex spouse orders the older spouse first, unknown birth dates last', function () {
    $user = User::factory()->create();
    $man = Person::factory()->for($user->tree)->male()->create();
    $olderUnknownSex = Person::factory()->for($user->tree)->create(['gender' => null]);
    $youngerUnknownSex = Person::factory()->for($user->tree)->create(['gender' => null]);
    attachBirth($man, 1900);
    attachBirth($olderUnknownSex, 1850);

    $marriage = attachMarriage($man, $olderUnknownSex, 1920);
    $this->actingAs($user)->get(marriagePanelUrl($marriage))
        ->assertInertia(fn ($page) => $page
            ->where('marriagePanel.spouses.0.id', $olderUnknownSex->id)
            ->where('marriagePanel.spouses.1.id', $man->id));

    $undatedMarriage = attachMarriage($man, $youngerUnknownSex, 1930);
    $this->actingAs($user)->get(marriagePanelUrl($undatedMarriage))
        ->assertInertia(fn ($page) => $page
            ->where('marriagePanel.spouses.0.id', $man->id)
            ->where('marriagePanel.spouses.1.id', $youngerUnknownSex->id));
});

test('a birth event in ?marriage= is a not found', function () {
    $user = User::factory()->create();
    $scene = familyScene($user);

    $this->actingAs($user)->get(route('persons.index', ['marriage' => $scene['focusBirth']->id]))
        ->assertNotFound();
});

test('a non-numeric or missing ?marriage= leaves the marriage panel null', function (?string $value) {
    $user = User::factory()->create();
    $focus = Person::factory()->for($user->tree)->create();
    $query = $value === null ? [] : ['marriage' => $value];

    $this->actingAs($user)->get(route('persons.index', $query))
        ->assertInertia(fn ($page) => $page->where('marriagePanel', null));
})->with([
    'nienumeryczna' => ['abc'],
    'pusta' => [''],
    'brak klucza' => [null],
]);

test('the marriage panel resolves on the tree, persons and sources host pages', function (string $route) {
    Storage::fake('local');

    $user = User::factory()->create();
    $scene = familyScene($user);
    $parameters = $route === 'tree.show' ? [$scene['focus']] : [];

    $this->actingAs($user)->get(marriagePanelUrl($scene['marriageA'], $route, $parameters))
        ->assertInertia(fn ($page) => $page->where('marriagePanel.spouses.0.id', $scene['focus']->id));
})->with(['tree.show', 'persons.index', 'sources.index']);

test('destroy from the marriage panel returns to the referring page without the marriage key', function () {
    $user = User::factory()->create();
    $focus = Person::factory()->for($user->tree)->create();
    $spouse = Person::factory()->for($user->tree)->create();
    $marriage = attachMarriage($focus, $spouse);

    $this->from(marriagePanelUrl($marriage, 'tree.show', [$focus]).'&person='.$focus->id)
        ->actingAs($user)
        ->delete(route('marriages.destroy', $marriage))
        ->assertRedirect(route('tree.show', $focus).'?'.http_build_query(['person' => $focus->id]));
});

test('destroy without a referer returns to the tree index even when the session holds an old marriage address', function () {
    $user = User::factory()->create();
    $focus = Person::factory()->for($user->tree)->create();
    $spouse = Person::factory()->for($user->tree)->create();
    $marriage = attachMarriage($focus, $spouse);

    $this->actingAs($user)
        ->withSession(['_previous' => ['url' => marriagePanelUrl($marriage, 'tree.show', [$focus])]])
        ->delete(route('marriages.destroy', $marriage))
        ->assertRedirect(route('tree.index'));
});

test('destroy with a foreign referer returns to the tree index', function () {
    $user = User::factory()->create();
    $focus = Person::factory()->for($user->tree)->create();
    $spouse = Person::factory()->for($user->tree)->create();
    $marriage = attachMarriage($focus, $spouse);

    $this->from('https://example.com/tree/1?marriage='.$marriage->id)
        ->actingAs($user)
        ->delete(route('marriages.destroy', $marriage))
        ->assertRedirect(route('tree.index'));
});

test('destroy from a referer carrying only the person key keeps it unchanged', function () {
    $user = User::factory()->create();
    $focus = Person::factory()->for($user->tree)->create();
    $spouse = Person::factory()->for($user->tree)->create();
    $marriage = attachMarriage($focus, $spouse);

    $this->from(personPreviewUrl($focus, 'tree.show', [$focus]))
        ->actingAs($user)
        ->delete(route('marriages.destroy', $marriage))
        ->assertRedirect(personPreviewUrl($focus, 'tree.show', [$focus]));
});
