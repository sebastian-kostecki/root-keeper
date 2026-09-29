<?php

use App\Enums\EventType;
use App\Enums\ParticipantRole;
use App\Models\Event;
use App\Models\ParentChildRelation;
use App\Models\Person;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('guests are redirected to the login page from persons routes', function () {
    $person = Person::factory()->create();

    $this->get(route('persons.index'))->assertRedirect(route('login'));
    $this->post(route('persons.store'), [])->assertRedirect(route('login'));
    $this->put(route('persons.update', $person), [])->assertRedirect(route('login'));
    $this->delete(route('persons.destroy', $person))->assertRedirect(route('login'));
});

test('persons index shows only the people from the authenticated user tree, sorted with unnamed people last', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $zolski = Person::factory()->for($user->tree)->create(['first_name' => 'Adam', 'last_name' => 'Zolski']);
    $zbigniewAdamski = Person::factory()->for($user->tree)->create(['first_name' => 'Zbigniew', 'last_name' => 'Adamski']);
    $adamski = Person::factory()->for($user->tree)->create(['first_name' => 'Bartek', 'last_name' => 'Adamski']);
    $unnamed = Person::factory()->for($user->tree)->create(['first_name' => null, 'last_name' => null]);
    Person::factory()->for($otherUser->tree)->create(['first_name' => 'Obcy', 'last_name' => 'Człowiek']);

    $response = $this->actingAs($user)->get(route('persons.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('persons/index')
        ->has('persons', 4)
        ->where('persons.0.id', $adamski->id)
        ->where('persons.1.id', $zbigniewAdamski->id)
        ->where('persons.2.id', $zolski->id)
        ->where('persons.3.id', $unnamed->id));
});

test('persons index filters by every query word against first or last name in any order', function () {
    $user = User::factory()->create();

    $janKowalski = Person::factory()->for($user->tree)->create(['first_name' => 'Jan', 'last_name' => 'Kowalski']);
    Person::factory()->for($user->tree)->create(['first_name' => 'Anna', 'last_name' => 'Kowalski']);
    $janNowak = Person::factory()->for($user->tree)->create(['first_name' => 'Jan', 'last_name' => 'Nowak']);

    $response = $this->actingAs($user)->get(route('persons.index', ['q' => 'kowalski jan']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('persons/index')
        ->has('persons', 1)
        ->where('persons.0.id', $janKowalski->id));

    $response = $this->actingAs($user)->get(route('persons.index', ['q' => 'JAN']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('persons/index')
        ->has('persons', 2)
        ->where('persons.0.id', $janKowalski->id)
        ->where('persons.1.id', $janNowak->id));
});

test('persons index matches a query word anywhere inside a name', function () {
    $user = User::factory()->create();

    $kowalski = Person::factory()->for($user->tree)->create(['first_name' => 'Jan', 'last_name' => 'Kowalski']);

    $response = $this->actingAs($user)->get(route('persons.index', ['q' => 'owal']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('persons/index')
        ->has('persons', 1)
        ->where('persons.0.id', $kowalski->id));
});

test('persons index ignores case but not polish diacritics', function () {
    $user = User::factory()->create();

    $lukasz = Person::factory()->for($user->tree)->create(['first_name' => 'Łukasz', 'last_name' => 'Żółkiewski']);

    $response = $this->actingAs($user)->get(route('persons.index', ['q' => 'ŁUKASZ żółk']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('persons/index')
        ->has('persons', 1)
        ->where('persons.0.id', $lukasz->id));

    $response = $this->actingAs($user)->get(route('persons.index', ['q' => 'lukasz']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('persons/index')
        ->has('persons', 0));
});

test('persons index treats percent and underscore in the query literally', function () {
    $user = User::factory()->create();

    Person::factory()->for($user->tree)->create(['first_name' => 'Jan', 'last_name' => 'Kowalski']);

    $response = $this->actingAs($user)->get(route('persons.index', ['q' => '%']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('persons/index')
        ->has('persons', 0));

    $response = $this->actingAs($user)->get(route('persons.index', ['q' => '_']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('persons/index')
        ->has('persons', 0));
});

test('persons index search never returns people from another users tree', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $ownMatch = Person::factory()->for($user->tree)->create(['first_name' => 'Jan', 'last_name' => 'Kowalski']);
    Person::factory()->for($otherUser->tree)->create(['first_name' => 'Jan', 'last_name' => 'Kowalski']);

    $response = $this->actingAs($user)->get(route('persons.index', ['q' => 'Kowalski']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('persons/index')
        ->has('persons', 1)
        ->where('persons.0.id', $ownMatch->id));
});

test('persons index with an empty, blank or non-string query returns everyone and echoes filters.q', function () {
    $user = User::factory()->create();

    Person::factory()->for($user->tree)->create(['first_name' => 'Jan', 'last_name' => 'Kowalski']);
    Person::factory()->for($user->tree)->create(['first_name' => 'Anna', 'last_name' => 'Nowak']);

    $response = $this->actingAs($user)->get(route('persons.index', ['q' => '']));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('persons/index')
        ->has('persons', 2)
        ->where('filters.q', ''));

    $response = $this->actingAs($user)->get(route('persons.index', ['q' => '  ']));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('persons/index')
        ->has('persons', 2)
        ->where('filters.q', ''));

    $response = $this->actingAs($user)->get(route('persons.index', ['q' => ['x']]));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('persons/index')
        ->has('persons', 2)
        ->where('filters.q', ''));

    $response = $this->actingAs($user)->get(route('persons.index', ['q' => '  Jan  ']));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('persons/index')
        ->where('filters.q', 'Jan'));
});

test('persons index caps the query at 100 characters without a trailing space', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('persons.index', ['q' => str_repeat('a', 150)]));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('filters.q', str_repeat('a', 100)));

    $response = $this->actingAs($user)->get(route('persons.index', ['q' => str_repeat('a', 99).' Kowalski']));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('filters.q', str_repeat('a', 99)));
});

test('persons index payload does not contain is_confirmed or scans on events', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $event = Event::factory()->for($user->tree)->birth()->create();
    $event->participants()->attach($person, ['role' => ParticipantRole::Subject]);

    $response = $this->actingAs($user)->get(route('persons.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('persons/index')
        ->missing('persons.0.birth.is_confirmed')
        ->missing('persons.0.birth.scans'));
});

test('person show exposes birth is_confirmed false with no scans and true with one, listed in link order', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $event = Event::factory()->for($user->tree)->birth()->create();
    $event->participants()->attach($person, ['role' => ParticipantRole::Subject]);

    $response = $this->actingAs($user)->get(personPreviewUrl($person));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('persons/index')
        ->where('personPreview.person.birth.is_confirmed', false)
        ->where('personPreview.person.birth.scans', []));

    $olderScan = Scan::factory()->for($user->tree)->create();
    $newerScan = Scan::factory()->for($user->tree)->create();
    $event->scans()->attach($newerScan);
    $this->travel(1)->minute();
    $event->scans()->attach($olderScan);

    $response = $this->actingAs($user)->get(personPreviewUrl($person));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('persons/index')
        ->where('personPreview.person.birth.is_confirmed', true)
        ->where('personPreview.person.birth.scans.0.id', $newerScan->id)
        ->where('personPreview.person.birth.scans.1.id', $olderScan->id));
});

test('the person URL itself no longer renders a page', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();

    $this->actingAs($user)->get("/persons/{$person->id}")->assertStatus(405);
});

test('store creates a person and a birth event even when birth fields are empty', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->from(route('persons.index', ['q' => 'Kowal']))
        ->post(route('persons.store'), [
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
        ]);

    $person = Person::where('first_name', 'Jan')->where('last_name', 'Kowalski')->firstOrFail();

    $response->assertRedirect(personPreviewUrl($person, 'tree.show', [$person]));

    expect($person->tree_id)->toBe($user->tree->id);

    $events = Event::where('tree_id', $user->tree->id)->get();
    expect($events)->toHaveCount(1);

    $birth = $events->firstWhere('type', EventType::Birth);
    expect($birth)->not->toBeNull();
    expect($birth->date_year)->toBeNull();

    $participant = $birth->participants()->firstOrFail();
    expect($participant->pivot->role)->toBe(ParticipantRole::Subject);
});

test('store creates a death event when marked deceased even with empty death fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('persons.store'), [
        'first_name' => 'Jan',
        'last_name' => 'Kowalski',
        'is_deceased' => '1',
    ])->assertSessionHasNoErrors();

    $person = Person::where('first_name', 'Jan')->firstOrFail();

    $events = Event::where('tree_id', $user->tree->id)->get();
    expect($events)->toHaveCount(2);

    $death = $events->firstWhere('type', EventType::Death);
    expect($death)->not->toBeNull();
    expect($death->date_year)->toBeNull();
});

test('store accepts a person with only one name when the other is sent empty', function (array $names) {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('persons.store'), $names);

    $response->assertSessionHasNoErrors();
    expect($user->tree->people()->count())->toBe(1);
})->with([
    'only first name' => [['first_name' => 'Maria', 'last_name' => '']],
    'only last name' => [['first_name' => '', 'last_name' => 'Kowalski']],
]);

test('store treats a JSON boolean is_deceased like the checkbox value', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('persons.store'), [
        'first_name' => 'Jan',
        'is_deceased' => true,
        'death' => ['year' => 1900],
    ])->assertRedirect();

    $death = Event::where('tree_id', $user->tree->id)->where('type', EventType::Death)->first();
    expect($death?->date_year)->toBe(1900);
});

test('store rejects a person with neither first nor last name', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('persons.store'), []);

    $response->assertSessionHasErrors(['first_name', 'last_name']);
});

test('store rejects a birth day without a month', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('persons.store'), [
        'first_name' => 'Jan',
        'birth' => ['day' => 15],
    ]);

    $response->assertSessionHasErrors('birth.month');
});

test('store rejects a birth month without a year', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('persons.store'), [
        'first_name' => 'Jan',
        'birth' => ['month' => 5],
    ]);

    $response->assertSessionHasErrors('birth.year');
});

test('store rejects an impossible calendar date', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('persons.store'), [
        'first_name' => 'Jan',
        'birth' => ['year' => 1850, 'month' => 2, 'day' => 31],
    ]);

    $response->assertSessionHasErrors('birth.day');
});

test('store reports only the invalid date part, not an impossible date', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('persons.store'), [
        'first_name' => 'Jan',
        'birth' => ['year' => 1850, 'month' => 13, 'day' => 1],
    ]);

    $response->assertSessionHasErrors('birth.month');
    $response->assertSessionDoesntHaveErrors('birth.day');
});

test('store rejects a birth year in the future', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('persons.store'), [
        'first_name' => 'Jan',
        'birth' => ['year' => (int) date('Y') + 1],
    ]);

    $response->assertSessionHasErrors('birth.year');
});

test('store rejects an approximate date without a year', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('persons.store'), [
        'first_name' => 'Jan',
        'birth' => ['is_approximate' => '1'],
    ]);

    $response->assertSessionHasErrors('birth.year');
});

test('store rejects an unknown religion', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('persons.store'), [
        'first_name' => 'Jan',
        'religion' => 'unknown',
    ]);

    $response->assertSessionHasErrors('religion');
});

test('store persists a valid gender and null when unset or sent empty', function (?string $sent, ?string $expected) {
    $user = User::factory()->create();

    $payload = ['first_name' => 'Jan'];

    if ($sent !== null) {
        $payload['gender'] = $sent;
    }

    $this->actingAs($user)->post(route('persons.store'), $payload)->assertSessionHasNoErrors();

    expect($user->tree->people()->firstOrFail()->gender?->value)->toBe($expected);
})->with([
    'male' => ['male', 'male'],
    'female' => ['female', 'female'],
    'empty string' => ['', null],
    'omitted' => [null, null],
]);

test('store rejects an unknown gender', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('persons.store'), [
        'first_name' => 'Jan',
        'gender' => 'other',
    ]);

    $response->assertSessionHasErrors('gender');
});

test('update persists and clears gender and rejects an unknown value', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create(['first_name' => 'Jan']);

    $this->actingAs($user)->put(route('persons.update', $person), [
        'first_name' => 'Jan',
        'gender' => 'female',
    ])->assertSessionHasNoErrors();
    expect($person->fresh()->gender->value)->toBe('female');

    $this->actingAs($user)->put(route('persons.update', $person), [
        'first_name' => 'Jan',
        'gender' => 'male',
    ])->assertSessionHasNoErrors();
    expect($person->fresh()->gender->value)->toBe('male');

    $this->actingAs($user)->put(route('persons.update', $person), [
        'first_name' => 'Jan',
        'gender' => '',
    ])->assertSessionHasNoErrors();
    expect($person->fresh()->gender)->toBeNull();

    $this->actingAs($user)->put(route('persons.update', $person), [
        'first_name' => 'Jan',
        'gender' => 'other',
    ])->assertSessionHasErrors('gender');
});

test('person show exposes gender as value and label, or null', function () {
    $user = User::factory()->create();
    $female = Person::factory()->for($user->tree)->female()->create();
    $unknown = Person::factory()->for($user->tree)->create();

    $this->actingAs($user)->get(personPreviewUrl($female))
        ->assertInertia(fn ($page) => $page
            ->component('persons/index')
            ->where('personPreview.person.gender', ['value' => 'female', 'label' => 'Kobieta']));

    $this->actingAs($user)->get(personPreviewUrl($unknown))
        ->assertInertia(fn ($page) => $page
            ->component('persons/index')
            ->where('personPreview.person.gender', null));
});

test('persons index passes gender and religion options', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('persons.index'))
        ->assertInertia(fn ($page) => $page
            ->component('persons/index')
            ->has('genders', 2)
            ->where('genders.0', ['value' => 'male', 'label' => 'Mężczyzna'])
            ->has('religions', 6)
            ->where('religions.0.value', 'roman_catholic'));
});

test('person show passes gender and religion options and prefills personForm.gender', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->male()->create();

    $this->actingAs($user)->get(personPreviewUrl($person))
        ->assertInertia(fn ($page) => $page
            ->component('persons/index')
            ->has('personPreview.genders', 2)
            ->has('personPreview.religions', 6)
            ->where('personPreview.personForm.gender', 'male'));
});

test('the old create and edit urls return not found', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();

    $this->actingAs($user)->get('/persons/create')->assertNotFound();
    $this->actingAs($user)->get("/persons/{$person->id}/edit")->assertNotFound();
});

test('store redirects to the tree of the created person regardless of the referrer', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('persons.store'), ['first_name' => 'Jan']);

    $person = Person::where('first_name', 'Jan')->firstOrFail();

    $response->assertRedirect(personPreviewUrl($person, 'tree.show', [$person]));
});

test('store rejects death fields when the person is not marked deceased', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('persons.store'), [
        'first_name' => 'Jan',
        'death' => ['year' => 1900],
    ]);

    $response->assertSessionHasErrors('death.year');
});

test('validation errors are in polish', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('persons.store'), []);

    $response->assertInvalid();
    $errors = $response->getSession()->get('errors')->getBag('default');

    expect($errors->first('first_name'))->toContain('wymagane');
});

test('person show exposes personForm with raw date parts, religion value, is_deceased and is_confirmed', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create(['religion' => 'roman_catholic']);
    $birth = Event::factory()->for($user->tree)->birth()->create(['date_year' => 1900, 'date_month' => 5, 'date_day' => 10]);
    $birth->participants()->attach($person, ['role' => ParticipantRole::Subject]);

    $response = $this->actingAs($user)->get(personPreviewUrl($person));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('persons/index')
        ->where('personPreview.personForm.religion', 'roman_catholic')
        ->where('personPreview.personForm.is_deceased', false)
        ->where('personPreview.personForm.birth.year', 1900)
        ->where('personPreview.personForm.birth.month', 5)
        ->where('personPreview.personForm.birth.day', 10)
        ->where('personPreview.personForm.birth.is_confirmed', false)
        ->where('personPreview.personForm.death', null));

    $scan = Scan::factory()->for($user->tree)->create();
    $birth->scans()->attach($scan);

    $response = $this->actingAs($user)->get(personPreviewUrl($person));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('persons/index')
        ->where('personPreview.personForm.birth.is_confirmed', true));
});

test('persons update returns not found for a person from another users tree', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $person = Person::factory()->for($otherUser->tree)->create(['first_name' => 'Obcy']);

    $this->actingAs($user)->put(route('persons.update', $person), ['first_name' => 'Hacked'])->assertNotFound();

    expect($person->fresh()->first_name)->toBe('Obcy');
});

test('update changes names, residence, religion, birth date parts and place, and redirects with a toast', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create(['first_name' => 'Jan', 'last_name' => 'Kowalski']);
    $birth = Event::factory()->for($user->tree)->birth()->withoutDate()->create();
    $birth->participants()->attach($person, ['role' => ParticipantRole::Subject]);

    $response = $this->actingAs($user)->put(route('persons.update', $person), [
        'first_name' => 'Janina',
        'last_name' => 'Nowak',
        'residence' => 'Kraków',
        'religion' => 'orthodox',
        'birth' => ['year' => 1950, 'month' => 3, 'day' => 4, 'place' => 'Warszawa'],
    ]);

    $response->assertRedirect(route('persons.index'));
    $response->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Zapisano zmiany']);

    $person->refresh();
    expect($person->first_name)->toBe('Janina');
    expect($person->last_name)->toBe('Nowak');
    expect($person->residence)->toBe('Kraków');
    expect($person->religion->value)->toBe('orthodox');

    $birth->refresh();
    expect($birth->date_year)->toBe(1950);
    expect($birth->date_month)->toBe(3);
    expect($birth->date_day)->toBe(4);
    expect($birth->place)->toBe('Warszawa');
});

test('update of a birth with a linked scan keeps the same birth event id and the scan link', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $birth = Event::factory()->for($user->tree)->birth()->create();
    $birth->participants()->attach($person, ['role' => ParticipantRole::Subject]);
    $scan = Scan::factory()->for($user->tree)->create();
    $birth->scans()->attach($scan);

    $this->actingAs($user)->put(route('persons.update', $person), [
        'first_name' => 'Jan',
        'birth' => ['year' => 1888],
    ])->assertRedirect(route('persons.index'));

    $person->refresh()->load('events');
    $updatedBirth = $person->birth();

    expect($updatedBirth->id)->toBe($birth->id);
    expect($updatedBirth->date_year)->toBe(1888);
    expect($updatedBirth->scans()->pluck('scans.id')->all())->toBe([$scan->id]);
});

test('checking is_deceased on a living person creates a death event with role subject', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $birth = Event::factory()->for($user->tree)->birth()->withoutDate()->create();
    $birth->participants()->attach($person, ['role' => ParticipantRole::Subject]);

    $this->actingAs($user)->put(route('persons.update', $person), [
        'first_name' => 'Jan',
        'is_deceased' => '1',
        'death' => ['year' => 1999, 'place' => 'Poznań'],
    ])->assertSessionHasNoErrors();

    $person->refresh()->load('events');
    $death = $person->death();

    expect($death)->not->toBeNull();
    expect($death->date_year)->toBe(1999);
    expect($death->place)->toBe('Poznań');
    expect($death->participants()->firstOrFail()->pivot->role)->toBe(ParticipantRole::Subject);
});

test('unchecking is_deceased when the death event has no scans deletes the death event', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $birth = Event::factory()->for($user->tree)->birth()->withoutDate()->create();
    $birth->participants()->attach($person, ['role' => ParticipantRole::Subject]);
    $death = Event::factory()->for($user->tree)->death()->create();
    $death->participants()->attach($person, ['role' => ParticipantRole::Subject]);

    $this->actingAs($user)->put(route('persons.update', $person), [
        'first_name' => 'Jan',
    ])->assertSessionHasNoErrors();

    expect(Event::find($death->id))->toBeNull();
});

test('unchecking is_deceased when the death event has a linked scan fails validation', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $birth = Event::factory()->for($user->tree)->birth()->withoutDate()->create();
    $birth->participants()->attach($person, ['role' => ParticipantRole::Subject]);
    $death = Event::factory()->for($user->tree)->death()->create();
    $death->participants()->attach($person, ['role' => ParticipantRole::Subject]);
    $scan = Scan::factory()->for($user->tree)->create();
    $death->scans()->attach($scan);

    $response = $this->actingAs($user)->put(route('persons.update', $person), [
        'first_name' => 'Jan',
    ]);

    $response->assertSessionHasErrors(['is_deceased' => 'Najpierw odepnij skany od zgonu na podglądzie osoby.']);

    expect(Event::find($death->id))->not->toBeNull();
    expect($death->scans()->pluck('scans.id')->all())->toBe([$scan->id]);
});

test('update reuses store validation and rejects a person with neither first nor last name', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();

    $response = $this->actingAs($user)->put(route('persons.update', $person), [
        'first_name' => '',
        'last_name' => '',
    ]);

    $response->assertSessionHasErrors(['first_name', 'last_name']);
});

test('destroy deletes the person, their birth and death events and event_scan rows, keeps scans, and redirects with a toast', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $birth = Event::factory()->for($user->tree)->birth()->create();
    $birth->participants()->attach($person, ['role' => ParticipantRole::Subject]);
    $death = Event::factory()->for($user->tree)->death()->create();
    $death->participants()->attach($person, ['role' => ParticipantRole::Subject]);
    $scan = Scan::factory()->for($user->tree)->create();
    $birth->scans()->attach($scan);

    $response = $this->actingAs($user)->delete(route('persons.destroy', $person));

    $response->assertRedirect(route('persons.index'));
    $response->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Usunięto osobę']);

    expect(Person::find($person->id))->toBeNull();
    expect(Event::find($birth->id))->toBeNull();
    expect(Event::find($death->id))->toBeNull();
    expect(DB::table('event_scan')->where('event_id', $birth->id)->exists())->toBeFalse();
    expect(Scan::find($scan->id))->not->toBeNull();
});

test('destroy of a person sharing an event with another person keeps the event and the other participant', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $spouse = Person::factory()->for($user->tree)->create();
    $sharedEvent = Event::factory()->for($user->tree)->birth()->create();
    $sharedEvent->participants()->attach($person, ['role' => ParticipantRole::Subject]);
    $sharedEvent->participants()->attach($spouse, ['role' => ParticipantRole::Subject]);

    $this->actingAs($user)->delete(route('persons.destroy', $person))->assertRedirect(route('persons.index'));

    expect(Person::find($person->id))->toBeNull();
    expect(Person::find($spouse->id))->not->toBeNull();
    expect(Event::find($sharedEvent->id))->not->toBeNull();
    expect($sharedEvent->fresh()->participants()->pluck('people.id')->all())->toBe([$spouse->id]);
});

test('destroy of a person from another users tree returns not found and deletes nothing', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $person = Person::factory()->for($otherUser->tree)->create();

    $response = $this->actingAs($user)->delete(route('persons.destroy', $person));

    $response->assertNotFound();
    expect(Person::find($person->id))->not->toBeNull();
});

test('persons index carries the preview props of the person named by the person query parameter', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->male()->create(['first_name' => 'Jan', 'last_name' => 'Kowalski']);

    $this->actingAs($user)->get(personPreviewUrl($person))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('persons/index')
            ->where('personPreview.person.id', $person->id)
            ->where('personPreview.isRootPerson', false)
            ->where('personPreview.personForm.id', $person->id)
            ->has('personPreview.genders', 2)
            ->has('personPreview.religions', 6));
});

test('the tree and scan pages carry the preview props and flag the proband', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $user->tree->update(['root_person_id' => $person->id]);
    $scan = Scan::factory()->for($user->tree)->create();

    $this->actingAs($user)->get(personPreviewUrl($person, 'tree.show', [$person]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tree/show')
            ->where('personPreview.person.id', $person->id)
            ->where('personPreview.isRootPerson', true)
            ->has('personPreview.personForm'));

    $this->actingAs($user)->get(personPreviewUrl($person, 'scans.show', [$scan]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('scans/show')
            ->where('personPreview.person.id', $person->id)
            ->has('personPreview.personForm'));
});

test('personPreview is null without the person parameter and with a non-numeric one', function (string $query) {
    $user = User::factory()->create();
    Person::factory()->for($user->tree)->create();

    $this->actingAs($user)->get(route('persons.index').$query)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('persons/index')
            ->where('personPreview', null));
})->with([
    'absent' => [''],
    'non-numeric' => ['?person=abc'],
    'array' => ['?person[]=1'],
]);

test('the preview of another users person or a missing person is not found', function () {
    $user = User::factory()->create();
    $otherPerson = Person::factory()->for(User::factory()->create()->tree)->create();

    $this->actingAs($user)->get(personPreviewUrl($otherPerson))->assertNotFound();
    $this->actingAs($user)->get(route('persons.index').'?person=999999')->assertNotFound();
});

test('available scans load through a partial reload of a host page with a person parameter', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $newer = Scan::factory()->for($user->tree)->create();
    $older = Scan::factory()->for($user->tree)->create(['created_at' => now()->subDay()]);
    Scan::factory()->for(User::factory()->create()->tree)->create();
    $url = personPreviewUrl($person);

    $version = $this->actingAs($user)->get($url, ['X-Inertia' => 'true'])->headers->get('X-Inertia-Version');

    $normal = $this->actingAs($user)->get($url);
    $normal->assertInertia(fn ($page) => $page->missing('availableScans'));

    $response = $this->actingAs($user)->get($url, [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $version,
        'X-Inertia-Partial-Data' => 'availableScans',
        'X-Inertia-Partial-Component' => 'persons/index',
    ]);

    $response->assertOk();
    $props = $response->json('props');

    expect($props)->not->toHaveKey('personPreview');
    expect(collect($props['availableScans'])->pluck('id')->all())->toBe([$newer->id, $older->id]);
});

test('update redirects back to the page the preview is open over', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create(['first_name' => 'Jan']);
    $from = personPreviewUrl($person);

    $this->actingAs($user)->from($from)
        ->put(route('persons.update', $person), ['first_name' => 'Janusz'])
        ->assertRedirect($from);
});

test('destroy from the tree centred on the deleted person returns to the proband tree', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();

    $this->actingAs($user)->from(personPreviewUrl($person, 'tree.show', [$person]))
        ->delete(route('persons.destroy', $person))
        ->assertRedirect(route('tree.index'));

    expect(Person::find($person->id))->toBeNull();
});

test('destroy from another persons tree keeps that tree without the person parameter and it still renders', function () {
    $user = User::factory()->create();
    $centre = Person::factory()->for($user->tree)->create();
    $spouse = Person::factory()->for($user->tree)->create();
    attachMarriage($centre, $spouse);

    $from = personPreviewUrl($spouse, 'tree.show', [$centre, 'spouse' => $spouse->id]);

    $this->actingAs($user)->from($from)
        ->delete(route('persons.destroy', $spouse))
        ->assertRedirect(route('tree.show', [$centre, 'spouse' => $spouse->id]));

    $this->actingAs($user)->get(route('tree.show', [$centre, 'spouse' => $spouse->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('tree/show')->where('personPreview', null));
});

test('destroy from the filtered list keeps the search and drops the person parameter', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create(['last_name' => 'Kowalski']);

    $this->actingAs($user)->from(personPreviewUrl($person, 'persons.index', ['q' => 'Kow']))
        ->delete(route('persons.destroy', $person))
        ->assertRedirect(route('persons.index', ['q' => 'Kow']));
});

test('destroy without a referrer or from an external page goes to the persons list', function () {
    $user = User::factory()->create();
    $first = Person::factory()->for($user->tree)->create();
    $second = Person::factory()->for($user->tree)->create();

    $this->actingAs($user)->delete(route('persons.destroy', $first))->assertRedirect(route('persons.index'));

    $this->actingAs($user)->from('https://example.com/persons?person=1')
        ->delete(route('persons.destroy', $second))
        ->assertRedirect(route('persons.index'));
});

test('the preview carries relative and spouse gender and the upload date of linked scans', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $parent = Person::factory()->for($user->tree)->female()->create();
    $spouse = Person::factory()->for($user->tree)->male()->create();
    ParentChildRelation::factory()->create(['tree_id' => $user->tree->id, 'parent_id' => $parent->id, 'child_id' => $person->id]);
    attachBirth($person, year: 1850);
    $birth = $person->birth();
    $scan = Scan::factory()->for($user->tree)->create();
    $birth->scans()->attach($scan);
    attachMarriage($person, $spouse);

    $this->actingAs($user)->get(personPreviewUrl($person))
        ->assertInertia(fn ($page) => $page
            ->where('personPreview.person.parents.0.gender', 'female')
            ->where('personPreview.person.marriages.0.spouse.gender', 'male')
            ->where('personPreview.person.birth.scans.0.uploaded_at', $scan->created_at->toIso8601String()));
});
