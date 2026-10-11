<?php

use App\Enums\EventType;
use App\Enums\ParticipantRole;
use App\Models\Event;
use App\Models\Locality;
use App\Models\ParentChildRelation;
use App\Models\Person;
use App\Models\Residence;
use App\Models\Source;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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

test('persons index events carry is_confirmed from source existence without exposing sources', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $event = Event::factory()->for($user->tree)->birth()->create();
    $event->participants()->attach($person, ['role' => ParticipantRole::Subject]);

    $response = $this->actingAs($user)->get(route('persons.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('persons/index')
        ->where('persons.0.birth.is_confirmed', false)
        ->missing('persons.0.birth.sources'));

    $event->sources()->attach(Source::factory()->for($user->tree)->create());

    $this->actingAs($user)->get(route('persons.index'))->assertInertia(fn ($page) => $page
        ->where('persons.0.birth.is_confirmed', true)
        ->missing('persons.0.birth.sources'));
});

test('persons index exposes date_parts on birth and death events', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $birth = Event::factory()->for($user->tree)->birth()->create();
    $death = Event::factory()->for($user->tree)->death()->create();
    $birth->participants()->attach($person, ['role' => ParticipantRole::Subject]);
    $death->participants()->attach($person, ['role' => ParticipantRole::Subject]);

    $this->actingAs($user)->get(route('persons.index'))->assertInertia(fn ($page) => $page
        ->where('persons.0.birth.date_parts', [
            'year' => $birth->date_year,
            'month' => $birth->date_month,
            'day' => $birth->date_day,
            'is_approximate' => (bool) $birth->date_is_approximate,
        ])
        ->has('persons.0.death.date_parts.year'));
});

test('persons index filters default to the list view sorted by surname', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('persons.index'))->assertInertia(fn ($page) => $page
        ->where('filters.view', 'lista')
        ->where('filters.sort', 'nazwisko'));
});

test('persons index echoes the table view and every sort value', function (string $sort) {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('persons.index', ['view' => 'tabela', 'sort' => $sort]))
        ->assertInertia(fn ($page) => $page
            ->where('filters.view', 'tabela')
            ->where('filters.sort', $sort));
})->with(['nazwisko', '-nazwisko', 'urodzenie', '-urodzenie', 'zgon', '-zgon']);

test('persons index falls back to defaults for unknown, array and blank view and sort', function (mixed $view, mixed $sort) {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('persons.index', ['view' => $view, 'sort' => $sort]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.view', 'lista')
            ->where('filters.sort', 'nazwisko'));
})->with([
    'unknown' => ['kafelki', 'wiek'],
    'array' => [['tabela'], [['zgon']]],
    'blank' => ['', ''],
]);

test('persons index keeps sort at surname when the view is not the table', function (?string $view) {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('persons.index', array_filter(['view' => $view, 'sort' => '-zgon'])))
        ->assertInertia(fn ($page) => $page
            ->where('filters.view', 'lista')
            ->where('filters.sort', 'nazwisko'));
})->with([null, 'lista']);

test('persons index falls back to surname for an invalid sort in the table view', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('persons.index', ['view' => 'tabela', 'sort' => 'wiek']))
        ->assertInertia(fn ($page) => $page
            ->where('filters.view', 'tabela')
            ->where('filters.sort', 'nazwisko'));
});

test('persons index sends the tree proband id or null', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();

    $this->actingAs($user)->get(route('persons.index'))
        ->assertInertia(fn ($page) => $page->where('rootPersonId', null));

    $user->tree->update(['root_person_id' => $person->id]);

    $this->actingAs($user)->get(route('persons.index'))
        ->assertInertia(fn ($page) => $page->where('rootPersonId', $person->id));
});

test('persons index partial reload of filters skips the people payload', function () {
    $user = User::factory()->create();
    Person::factory()->for($user->tree)->create();
    $url = route('persons.index', ['view' => 'tabela']);
    $version = $this->actingAs($user)->get($url, ['X-Inertia' => 'true'])->headers->get('X-Inertia-Version');

    $response = $this->actingAs($user)->get($url, [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $version,
        'X-Inertia-Partial-Data' => 'filters',
        'X-Inertia-Partial-Component' => 'persons/index',
    ]);

    $response->assertOk();
    expect($response->json('props'))->toHaveKey('filters')->not->toHaveKey('persons');
});

test('person show exposes birth is_confirmed false with no sources and true with one, listed in link order', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $event = Event::factory()->for($user->tree)->birth()->create();
    $event->participants()->attach($person, ['role' => ParticipantRole::Subject]);

    $response = $this->actingAs($user)->get(personPreviewUrl($person));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('persons/index')
        ->where('personPreview.person.birth.is_confirmed', false)
        ->where('personPreview.person.birth.sources', []));

    $olderSource = Source::factory()->for($user->tree)->create();
    $newerSource = Source::factory()->for($user->tree)->create();
    $event->sources()->attach($newerSource);
    $this->travel(1)->minute();
    $event->sources()->attach($olderSource);

    $response = $this->actingAs($user)->get(personPreviewUrl($person));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('persons/index')
        ->where('personPreview.person.birth.is_confirmed', true)
        ->where('personPreview.person.birth.sources.0.id', $newerSource->id)
        ->where('personPreview.person.birth.sources.1.id', $olderSource->id));
});

test('the person preview lists the kinds confirming an event in a fixed order regardless of link order', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $event = Event::factory()->for($user->tree)->death()->create();
    $event->participants()->attach($person, ['role' => ParticipantRole::Subject]);
    $event->sources()->attach(Source::factory()->gravestone()->for($user->tree)->create());
    $this->travel(1)->minute();
    $event->sources()->attach(Source::factory()->for($user->tree)->create());

    $this->actingAs($user)->get(personPreviewUrl($person))
        ->assertInertia(fn ($page) => $page
            ->where('personPreview.person.death.confirmed_kinds', ['akt', 'nagrobek'])
            ->where('personPreview.person.death.sources.0.kind', 'gravestone'));
});

test('the person preview lists each confirming kind once', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $twoAkts = Event::factory()->for($user->tree)->birth()->create();
    $allKinds = Event::factory()->for($user->tree)->death()->create();
    $twoAkts->participants()->attach($person, ['role' => ParticipantRole::Subject]);
    $allKinds->participants()->attach($person, ['role' => ParticipantRole::Subject]);
    $twoAkts->sources()->attach(Source::factory()->count(2)->for($user->tree)->create());
    $allKinds->sources()->attach(Source::factory()->other()->for($user->tree)->create());
    $allKinds->sources()->attach(Source::factory()->gravestone()->for($user->tree)->create());
    $allKinds->sources()->attach(Source::factory()->for($user->tree)->create());

    $this->actingAs($user)->get(personPreviewUrl($person))
        ->assertInertia(fn ($page) => $page
            ->where('personPreview.person.birth.confirmed_kinds', ['akt'])
            ->where('personPreview.person.death.confirmed_kinds', ['akt', 'nagrobek', 'inne']));
});

test('an event without sources has no confirming kinds', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $event = Event::factory()->for($user->tree)->birth()->create();
    $event->participants()->attach($person, ['role' => ParticipantRole::Subject]);

    $this->actingAs($user)->get(personPreviewUrl($person))
        ->assertInertia(fn ($page) => $page->where('personPreview.person.birth.confirmed_kinds', []));
});

test('the person preview exposes the short kind label of each linked source', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $event = Event::factory()->for($user->tree)->birth()->create();
    $event->participants()->attach($person, ['role' => ParticipantRole::Subject]);
    $event->sources()->attach(Source::factory()->for($user->tree)->create());
    $this->travel(1)->minute();
    $event->sources()->attach(Source::factory()->gravestone()->for($user->tree)->create());
    $this->travel(1)->minute();
    $event->sources()->attach(Source::factory()->other()->for($user->tree)->create());

    $this->actingAs($user)->get(personPreviewUrl($person))
        ->assertInertia(fn ($page) => $page
            ->where('personPreview.person.birth.sources.0.kind_short_label', 'akt')
            ->where('personPreview.person.birth.sources.1.kind_short_label', 'nagrobek')
            ->where('personPreview.person.birth.sources.2.kind_short_label', 'inne'));
});

test('a marriage confirmed by a gravestone lists nagrobek among its confirming kinds', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $spouse = Person::factory()->for($user->tree)->create();
    $marriage = attachMarriage($person, $spouse);
    $marriage->sources()->attach(Source::factory()->gravestone()->for($user->tree)->create());

    $this->actingAs($user)->get(personPreviewUrl($person))
        ->assertInertia(fn ($page) => $page
            ->where('personPreview.person.marriages.0.is_confirmed', true)
            ->where('personPreview.person.marriages.0.confirmed_kinds', ['nagrobek']));
});

test('the persons index marks a death backed only by a gravestone as confirmed', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $event = Event::factory()->for($user->tree)->death()->create();
    $event->participants()->attach($person, ['role' => ParticipantRole::Subject]);
    $event->sources()->attach(Source::factory()->gravestone()->for($user->tree)->create());

    $this->actingAs($user)->get(route('persons.index'))
        ->assertInertia(fn ($page) => $page
            ->where('persons.0.death.is_confirmed', true)
            ->missing('persons.0.death.confirmed_kinds'));
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

    $source = Source::factory()->for($user->tree)->create();
    $birth->sources()->attach($source);

    $response = $this->actingAs($user)->get(personPreviewUrl($person));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('persons/index')
        ->where('personPreview.personForm.birth.is_confirmed', true));
});

test('update changes names, religion, birth date parts and locality, and redirects with a toast', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create(['first_name' => 'Jan', 'last_name' => 'Kowalski']);
    $birth = Event::factory()->for($user->tree)->birth()->withoutDate()->create();
    $birth->participants()->attach($person, ['role' => ParticipantRole::Subject]);
    $warszawa = Locality::factory()->for($user->tree)->create(['name' => 'Warszawa']);

    $response = $this->actingAs($user)->put(route('persons.update', $person), [
        'first_name' => 'Janina',
        'last_name' => 'Nowak',
        'religion' => 'orthodox',
        'birth' => ['year' => 1950, 'month' => 3, 'day' => 4, 'locality_id' => $warszawa->id],
    ]);

    $response->assertRedirect(route('persons.index'));
    $response->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Zapisano zmiany']);

    $person->refresh();
    expect($person->first_name)->toBe('Janina');
    expect($person->last_name)->toBe('Nowak');
    expect($person->religion->value)->toBe('orthodox');

    $birth->refresh();
    expect($birth->date_year)->toBe(1950);
    expect($birth->date_month)->toBe(3);
    expect($birth->date_day)->toBe(4);
    expect($birth->locality_id)->toBe($warszawa->id);
});

/**
 * The residence list of a person as the preview hands it to the panel form.
 *
 * @return list<array{locality: string, year_from: int|null, year_to: int|null}>
 */
function previewResidences(mixed $test, User $user, Person $person): array
{
    $list = [];

    $test->actingAs($user)->get(personPreviewUrl($person))->assertInertia(function ($page) use (&$list) {
        $list = collect($page->toArray()['props']['personPreview']['personForm']['residences'])
            ->map(fn (array $row): array => ['locality' => $row['locality']['name'], 'year_from' => $row['year_from'], 'year_to' => $row['year_to']])
            ->all();

        return $page;
    });

    return $list;
}

test('update stores the residence list and the preview returns it ordered by year, undated last, ties by order added', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    [$a, $b, $c, $d, $e] = collect(['A', 'B', 'C', 'D', 'E'])
        ->map(fn (string $name): Locality => Locality::factory()->for($user->tree)->create(['name' => $name]))->all();

    $this->actingAs($user)->put(route('persons.update', $person), [
        'first_name' => 'Jan',
        'residences' => [
            ['locality_id' => $a->id],
            ['locality_id' => $b->id, 'year_from' => 1950, 'year_to' => 1960],
            ['locality_id' => $c->id, 'year_to' => 1920],
            ['locality_id' => $d->id, 'year_from' => 1920],
            ['locality_id' => $e->id],
        ],
    ])->assertSessionHasNoErrors()->assertRedirect(route('persons.index'));

    expect($person->residences()->count())->toBe(5);
    expect(collect(previewResidences($this, $user, $person))->pluck('locality')->all())->toBe(['C', 'D', 'B', 'A', 'E']);
    expect(previewResidences($this, $user, $person)[2])->toBe(['locality' => 'B', 'year_from' => 1950, 'year_to' => 1960]);
});

test('the same locality may be a residence more than once', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $locality = Locality::factory()->for($user->tree)->create();

    $this->actingAs($user)->put(route('persons.update', $person), [
        'first_name' => 'Jan',
        'residences' => [
            ['locality_id' => $locality->id, 'year_from' => 1900, 'year_to' => 1910],
            ['locality_id' => $locality->id, 'year_from' => 1920, 'year_to' => 1930],
        ],
    ])->assertSessionHasNoErrors();

    expect($person->residences()->count())->toBe(2);
});

test('update rejects more than 50 residences', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $locality = Locality::factory()->for($user->tree)->create();

    $this->actingAs($user)->put(route('persons.update', $person), [
        'first_name' => 'Jan',
        'residences' => array_fill(0, 51, ['locality_id' => $locality->id]),
    ])->assertSessionHasErrors('residences');

    expect($person->residences()->count())->toBe(0);
});

test('update without a residences key clears the list', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    Residence::factory()->for($person)->create(['tree_id' => $user->tree->id, 'locality_id' => Locality::factory()->for($user->tree)]);

    $this->actingAs($user)->put(route('persons.update', $person), ['first_name' => 'Jan'])->assertSessionHasNoErrors();

    expect($person->residences()->count())->toBe(0);
});

test('a fully empty residence row is skipped and does not shift the error index of later rows', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $locality = Locality::factory()->for($user->tree)->create();

    $this->actingAs($user)->put(route('persons.update', $person), [
        'first_name' => 'Jan',
        'residences' => [['locality_id' => '', 'year_from' => '', 'year_to' => null], ['locality_id' => $locality->id]],
    ])->assertSessionHasNoErrors();

    expect($person->residences()->count())->toBe(1);

    $this->actingAs($user)->put(route('persons.update', $person), [
        'first_name' => 'Jan',
        'residences' => [
            ['locality_id' => $locality->id],
            ['locality_id' => '', 'year_from' => ''],
            ['locality_id' => $locality->id],
            ['year_from' => 1900],
        ],
    ])->assertSessionHasErrors(['residences.3.locality_id' => 'Wybierz miejscowość.'])
        ->assertSessionDoesntHaveErrors(['residences.0.locality_id', 'residences.2.locality_id']);

    expect($person->residences()->count())->toBe(1);
});

test('residence years must be in range and from may not exceed to', function (array $row, string $errorKey) {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $locality = Locality::factory()->for($user->tree)->create();
    Residence::factory()->for($person)->create(['tree_id' => $user->tree->id, 'locality_id' => $locality->id]);

    $this->actingAs($user)->put(route('persons.update', $person), [
        'first_name' => 'Jan',
        'residences' => [['locality_id' => $locality->id, ...$row]],
    ])->assertSessionHasErrors($errorKey);

    expect($person->residences()->count())->toBe(1);
})->with([
    'from after to' => [['year_from' => 1950, 'year_to' => 1940], 'residences.0.year_to'],
    'from before 1000' => [['year_from' => 999], 'residences.0.year_from'],
    'to in the future' => [['year_to' => 9999], 'residences.0.year_to'],
]);

test('the legacy residence text key is ignored and the stored text stays untouched', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create(['residence' => 'Stary tekst']);

    $this->actingAs($user)->put(route('persons.update', $person), [
        'first_name' => 'Jan',
        'residence' => 'Nowy tekst',
    ])->assertSessionHasNoErrors();

    expect($person->refresh()->residence)->toBe('Stary tekst');
    expect($person->residences()->count())->toBe(0);
});

test('update of a birth with a linked source keeps the same birth event id and the source link', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $birth = Event::factory()->for($user->tree)->birth()->create();
    $birth->participants()->attach($person, ['role' => ParticipantRole::Subject]);
    $source = Source::factory()->for($user->tree)->create();
    $birth->sources()->attach($source);

    $this->actingAs($user)->put(route('persons.update', $person), [
        'first_name' => 'Jan',
        'birth' => ['year' => 1888],
    ])->assertRedirect(route('persons.index'));

    $person->refresh()->load('events');
    $updatedBirth = $person->birth();

    expect($updatedBirth->id)->toBe($birth->id);
    expect($updatedBirth->date_year)->toBe(1888);
    expect($updatedBirth->sources()->pluck('sources.id')->all())->toBe([$source->id]);
});

test('checking is_deceased on a living person creates a death event with role subject', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $birth = Event::factory()->for($user->tree)->birth()->withoutDate()->create();
    $birth->participants()->attach($person, ['role' => ParticipantRole::Subject]);

    $poznan = Locality::factory()->for($user->tree)->create(['name' => 'Poznań']);

    $this->actingAs($user)->put(route('persons.update', $person), [
        'first_name' => 'Jan',
        'is_deceased' => '1',
        'death' => ['year' => 1999, 'locality_id' => $poznan->id],
    ])->assertSessionHasNoErrors();

    $person->refresh()->load('events');
    $death = $person->death();

    expect($death)->not->toBeNull();
    expect($death->date_year)->toBe(1999);
    expect($death->locality_id)->toBe($poznan->id);
    expect($death->participants()->firstOrFail()->pivot->role)->toBe(ParticipantRole::Subject);
});

test('unchecking is_deceased when the death event has no sources deletes the death event', function () {
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

test('unchecking is_deceased when the death event has a linked source fails validation', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $birth = Event::factory()->for($user->tree)->birth()->withoutDate()->create();
    $birth->participants()->attach($person, ['role' => ParticipantRole::Subject]);
    $death = Event::factory()->for($user->tree)->death()->create();
    $death->participants()->attach($person, ['role' => ParticipantRole::Subject]);
    $source = Source::factory()->for($user->tree)->create();
    $death->sources()->attach($source);

    $response = $this->actingAs($user)->put(route('persons.update', $person), [
        'first_name' => 'Jan',
    ]);

    $response->assertSessionHasErrors(['is_deceased' => 'Najpierw odepnij źródła od zgonu na podglądzie osoby.']);

    expect(Event::find($death->id))->not->toBeNull();
    expect($death->sources()->pluck('sources.id')->all())->toBe([$source->id]);
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

test('destroy deletes the person, their birth and death events and event_source rows, keeps sources, and redirects with a toast', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $birth = Event::factory()->for($user->tree)->birth()->create();
    $birth->participants()->attach($person, ['role' => ParticipantRole::Subject]);
    $death = Event::factory()->for($user->tree)->death()->create();
    $death->participants()->attach($person, ['role' => ParticipantRole::Subject]);
    $source = Source::factory()->for($user->tree)->create();
    $birth->sources()->attach($source);

    $response = $this->actingAs($user)->delete(route('persons.destroy', $person));

    $response->assertRedirect(route('persons.index'));
    $response->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Usunięto osobę']);

    expect(Person::find($person->id))->toBeNull();
    expect(Event::find($birth->id))->toBeNull();
    expect(Event::find($death->id))->toBeNull();
    expect(DB::table('event_source')->where('event_id', $birth->id)->exists())->toBeFalse();
    expect(Source::find($source->id))->not->toBeNull();
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

test('destroy of a person deletes the photo and thumbnail files of that person', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create([
        'photo_path' => "persons/{$user->tree->id}/foto.jpg",
        'photo_thumbnail_path' => "persons/{$user->tree->id}/thumbnails/foto.jpg",
    ]);
    $other = Person::factory()->for($user->tree)->create([
        'photo_path' => "persons/{$user->tree->id}/inne.jpg",
    ]);
    Storage::disk('local')->put($person->photo_path, 'original');
    Storage::disk('local')->put($person->photo_thumbnail_path, 'thumbnail');
    Storage::disk('local')->put($other->photo_path, 'inne');

    $this->actingAs($user)->delete(route('persons.destroy', $person))->assertRedirect();

    expect(Storage::disk('local')->exists("persons/{$user->tree->id}/foto.jpg"))->toBeFalse()
        ->and(Storage::disk('local')->exists("persons/{$user->tree->id}/thumbnails/foto.jpg"))->toBeFalse()
        ->and(Storage::disk('local')->exists($other->photo_path))->toBeTrue();
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

test('the person preview carries the age', function () {
    $this->travelTo('2026-10-05 12:00:00');
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    attachBirth($person, 1850);
    attachDeath($person, 1922);

    $this->actingAs($user)->get(personPreviewUrl($person))
        ->assertInertia(fn ($page) => $page
            ->where('personPreview.person.age', ['unit' => 'years', 'value' => 72]));
});

test('the age counts today in the Europe/Warsaw day', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    attachBirth($person, 1980, 3, 15);

    $this->travelTo('2026-03-14 23:30:00 UTC');

    $this->actingAs($user)->get(personPreviewUrl($person))
        ->assertInertia(fn ($page) => $page
            ->where('personPreview.person.age', ['unit' => 'years', 'value' => 46]));
});

test('the tree and source pages carry the preview props and flag the proband', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $user->tree->update(['root_person_id' => $person->id]);
    $source = Source::factory()->for($user->tree)->create();

    $this->actingAs($user)->get(personPreviewUrl($person, 'tree.show', [$person]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tree/show')
            ->where('personPreview.person.id', $person->id)
            ->where('personPreview.isRootPerson', true)
            ->has('personPreview.personForm'));

    $this->actingAs($user)->get(personPreviewUrl($person, 'sources.index', ['source' => $source->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sources/index')
            ->where('personPreview.person.id', $person->id)
            ->where('sourcePanel.source.id', $source->id)
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
    Storage::fake('local');

    $user = User::factory()->create();
    $otherPerson = Person::factory()->for(User::factory()->create()->tree)->create();
    $before = ownershipSnapshot();

    $this->actingAs($user)->get(personPreviewUrl($otherPerson))->assertNotFound();
    $this->actingAs($user)->get(route('persons.index').'?person=999999')->assertNotFound();

    expect(ownershipSnapshot())->toBe($before);
});

test('available sources load through a partial reload of a host page with a person parameter', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $oldest = Source::factory()->for($user->tree)->create(['created_at' => now()->subDays(2)]);
    $older = Source::factory()->gravestone()->for($user->tree)->create(['created_at' => now()->subDay()]);
    $newer = Source::factory()->other()->for($user->tree)->create();
    Source::factory()->for(User::factory()->create()->tree)->create();
    Source::factory()->gravestone()->for(User::factory()->create()->tree)->create();
    $url = personPreviewUrl($person);

    $version = $this->actingAs($user)->get($url, ['X-Inertia' => 'true'])->headers->get('X-Inertia-Version');

    $normal = $this->actingAs($user)->get($url);
    $normal->assertInertia(fn ($page) => $page->missing('availableSources'));

    $response = $this->actingAs($user)->get($url, [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $version,
        'X-Inertia-Partial-Data' => 'availableSources',
        'X-Inertia-Partial-Component' => 'persons/index',
    ]);

    $response->assertOk();
    $props = $response->json('props');

    expect($props)->not->toHaveKey('personPreview');
    expect(collect($props['availableSources'])->pluck('id')->all())->toBe([$newer->id, $older->id, $oldest->id]);
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

test('destroy from the ancestors view centred on the deleted person returns to the proband tree', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();

    $this->actingAs($user)->from(personPreviewUrl($person, 'tree.ancestors', [$person]))
        ->delete(route('persons.destroy', $person))
        ->assertRedirect(route('tree.index'));

    expect(Person::find($person->id))->toBeNull();
});

test('destroy of an ancestor from the ancestors view keeps the view without the person parameter', function () {
    $user = User::factory()->create();
    $child = Person::factory()->for($user->tree)->create();
    $father = Person::factory()->for($user->tree)->male()->create();
    linkParent($father, $child);

    $from = personPreviewUrl($father, 'tree.ancestors', [$child, 'generations' => 3]);

    $this->actingAs($user)->from($from)
        ->delete(route('persons.destroy', $father))
        ->assertRedirect(route('tree.ancestors', [$child, 'generations' => 3]));

    expect(Person::find($father->id))->toBeNull();
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

test('the preview carries relative and spouse gender and the upload date of linked sources', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $parent = Person::factory()->for($user->tree)->female()->create();
    $spouse = Person::factory()->for($user->tree)->male()->create();
    ParentChildRelation::factory()->create(['tree_id' => $user->tree->id, 'parent_id' => $parent->id, 'child_id' => $person->id]);
    attachBirth($person, year: 1850);
    $birth = $person->birth();
    $source = Source::factory()->for($user->tree)->create();
    $birth->sources()->attach($source);
    attachMarriage($person, $spouse);

    $this->actingAs($user)->get(personPreviewUrl($person))
        ->assertInertia(fn ($page) => $page
            ->where('personPreview.person.parents.0.gender', 'female')
            ->where('personPreview.person.marriages.0.spouse.gender', 'male')
            ->where('personPreview.person.birth.sources.0.uploaded_at', $source->created_at->toIso8601String()));
});

test('destroy from the table view keeps the search, view and sort and drops the person parameter', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create(['last_name' => 'Kowalski']);
    $parameters = ['q' => 'Kow', 'view' => 'tabela', 'sort' => '-urodzenie'];

    $this->actingAs($user)->from(personPreviewUrl($person, 'persons.index', $parameters))
        ->delete(route('persons.destroy', $person))
        ->assertRedirect(route('persons.index', $parameters));
});

test('the person preview exposes the display name of an event source', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $event = Event::factory()->for($user->tree)->birth()->create();
    $event->participants()->attach($person, ['role' => ParticipantRole::Subject]);
    $event->sources()->attach(Source::factory()->for($user->tree)->create(['original_name' => 'IMG_1.jpg', 'name' => 'Akt chrztu']));
    $event->sources()->attach(Source::factory()->for($user->tree)->create(['original_name' => 'IMG_2.jpg']));

    $this->actingAs($user)->get(personPreviewUrl($person))->assertInertia(fn ($page) => $page
        ->where('personPreview.person.birth.sources.0.display_name', 'Akt chrztu')
        ->where('personPreview.person.birth.sources.1.display_name', 'IMG_2.jpg'));
});

test('changing the sex of a parent is rejected when their child already has a parent of that sex', function (?string $from, string $to, string $partnerSex, bool $withSecondChild, string $message) {
    Storage::fake('local');
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create(['first_name' => 'Jan', 'gender' => $from]);
    $partner = Person::factory()->for($user->tree)->create(['gender' => $partnerSex]);
    $child = Person::factory()->for($user->tree)->create();
    linkParent($partner, $child);
    linkParent($person, $child);

    if ($withSecondChild) {
        linkParent($person, Person::factory()->for($user->tree)->create());
    }

    $before = ownershipSnapshot();

    $this->actingAs($user)->put(route('persons.update', $person), ['first_name' => 'Jan', 'gender' => $to])
        ->assertSessionHasErrors(['gender' => $message]);

    expect(ownershipSnapshot())->toBe($before);
})->with([
    'unknown to male, child has a father' => [null, 'male', 'male', false, 'Ta osoba jest rodzicem dziecka, które ma już ojca.'],
    'female to male, child has a father' => ['female', 'male', 'male', false, 'Ta osoba jest rodzicem dziecka, które ma już ojca.'],
    'male to female, child has a mother' => ['male', 'female', 'female', false, 'Ta osoba jest rodzicem dziecka, które ma już matkę.'],
    'conflict at only one of two children' => [null, 'male', 'male', true, 'Ta osoba jest rodzicem dziecka, które ma już ojca.'],
]);

test('changing the sex of a parent is allowed when no child gets two parents of the same sex', function (string $from, string $to, ?string $partnerSex, string $expected) {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create(['first_name' => 'Jan', 'gender' => $from]);
    $child = Person::factory()->for($user->tree)->create();
    linkParent($person, $child);

    if ($partnerSex !== 'none') {
        linkParent(Person::factory()->for($user->tree)->create(['gender' => $partnerSex]), $child);
    }

    $this->actingAs($user)->put(route('persons.update', $person), ['first_name' => 'Jan', 'gender' => $to])
        ->assertSessionHasNoErrors();

    expect($person->fresh()->gender?->value)->toBe($expected === '' ? null : $expected);
})->with([
    'lone male parent to female' => ['male', 'female', 'none', 'female'],
    'male to unknown next to a mother' => ['male', '', 'female', ''],
    'co-parent of unknown sex' => ['female', 'male', null, 'male'],
]);

test('editing a parent without changing their sex is allowed even when their child already has two parents of that sex', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->male()->create(['first_name' => 'Jan']);
    $child = Person::factory()->for($user->tree)->create();
    linkParent(Person::factory()->for($user->tree)->male()->create(), $child);
    linkParent($person, $child);

    $this->actingAs($user)->put(route('persons.update', $person), ['first_name' => 'Janusz', 'gender' => 'male'])
        ->assertSessionHasNoErrors();

    expect($person->fresh()->first_name)->toBe('Janusz');
});

test('store saves a maiden name and the display name reads "z d."', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('persons.store'), [
        'first_name' => 'Anna',
        'last_name' => 'Kowalska',
        'maiden_name' => 'Nowak',
    ])->assertSessionHasNoErrors();

    $person = $user->tree->people()->firstOrFail();

    expect($person->maiden_name)->toBe('Nowak');

    $this->actingAs($user)->get(route('persons.index'))->assertInertia(fn ($page) => $page
        ->where('persons.0.display_name', 'Anna Kowalska z d. Nowak')
        ->where('persons.0.maiden_name', 'Nowak'));
});

test('store rejects a maiden name without a last name with a polish message', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('persons.store'), [
        'first_name' => 'Anna',
        'last_name' => '',
        'maiden_name' => 'Nowak',
    ]);

    $response->assertInvalid('maiden_name');
    expect($response->getSession()->get('errors')->getBag('default')->first('maiden_name'))
        ->toBe('Gdy znasz tylko jedno nazwisko, wpisz je w pole „Nazwisko”.');
    expect($user->tree->people()->count())->toBe(0);
});

test('display name omits "z d." when the maiden name equals the last name ignoring case', function () {
    $user = User::factory()->create();
    Person::factory()->for($user->tree)->create([
        'first_name' => 'Anna',
        'last_name' => 'Nowak',
        'maiden_name' => 'NOWAK',
    ]);

    $this->actingAs($user)->get(route('persons.index'))->assertInertia(fn ($page) => $page
        ->where('persons.0.display_name', 'Anna Nowak'));
});

test('persons index finds a person by maiden name, also combined with another column', function () {
    $user = User::factory()->create();
    $anna = Person::factory()->for($user->tree)->create([
        'first_name' => 'Anna',
        'last_name' => 'Kowalska',
        'maiden_name' => 'Nowak',
    ]);
    Person::factory()->for($user->tree)->create(['first_name' => 'Jan', 'last_name' => 'Wiśniewski']);

    foreach (['Nowak', 'Anna Nowak', 'Anna Kowalska z d. Nowak'] as $query) {
        $this->actingAs($user)->get(route('persons.index', ['q' => $query]))->assertInertia(fn ($page) => $page
            ->has('persons', 1)
            ->where('persons.0.id', $anna->id));
    }
});

test('update keeps the maiden name when another section is saved with the form payload', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create([
        'first_name' => 'Anna',
        'last_name' => 'Kowalska',
        'maiden_name' => 'Nowak',
    ]);
    $birth = Event::factory()->for($user->tree)->birth()->withoutDate()->create();
    $birth->participants()->attach($person, ['role' => ParticipantRole::Subject]);

    $this->actingAs($user)->put(route('persons.update', $person), [
        'first_name' => 'Anna',
        'last_name' => 'Kowalska',
        'maiden_name' => 'Nowak',
        'birth' => ['year' => 1950],
    ])->assertSessionHasNoErrors();

    expect($person->refresh()->maiden_name)->toBe('Nowak');
    expect($birth->refresh()->date_year)->toBe(1950);
});

test('person events read the locality name as place and the form carries the locality', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $lipnica = Locality::factory()->for($user->tree)->create(['name' => 'Lipnica']);
    $birth = Event::factory()->for($user->tree)->birth()->create(['locality_id' => $lipnica->id]);
    $birth->participants()->attach($person, ['role' => ParticipantRole::Subject]);

    $this->actingAs($user)->get(personPreviewUrl($person))
        ->assertInertia(fn ($page) => $page
            ->where('personPreview.person.birth.place', 'Lipnica')
            ->where('personPreview.person.birth.locality_id', $lipnica->id)
            ->where('personPreview.personForm.birth.locality', ['id' => $lipnica->id, 'name' => 'Lipnica'])
            ->where('personPreview.personForm.burial', null)
            ->where('personPreview.person.burial_place', null));
});

test('saving a birth without locality_id clears the stored locality', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $birth = Event::factory()->for($user->tree)->birth()->withLocality()->create();
    $birth->participants()->attach($person, ['role' => ParticipantRole::Subject]);

    $this->actingAs($user)->put(route('persons.update', $person), [
        'first_name' => 'Jan',
        'birth' => ['year' => 1900],
    ])->assertSessionHasNoErrors();

    expect($birth->refresh()->locality_id)->toBeNull();
});

test('the legacy place key is ignored and events.place stays untouched', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $birth = Event::factory()->for($user->tree)->birth()->create(['place' => 'Stary tekst']);
    $birth->participants()->attach($person, ['role' => ParticipantRole::Subject]);

    $this->actingAs($user)->put(route('persons.update', $person), [
        'first_name' => 'Jan',
        'birth' => ['year' => 1900, 'place' => 'Nowy tekst'],
    ])->assertSessionHasNoErrors();

    $birth->refresh();
    expect($birth->place)->toBe('Stary tekst');
    expect($birth->locality_id)->toBeNull();
});

test('store creates a burial event with a locality for a deceased person', function () {
    $user = User::factory()->create();
    $cemetery = Locality::factory()->for($user->tree)->create(['name' => 'Lipnica']);

    $this->actingAs($user)->post(route('persons.store'), [
        'first_name' => 'Jan',
        'is_deceased' => '1',
        'burial' => ['locality_id' => $cemetery->id],
    ])->assertSessionHasNoErrors();

    $person = Person::where('first_name', 'Jan')->firstOrFail()->load('events');
    $burial = $person->burial();

    expect($burial)->not->toBeNull()
        ->and($burial->locality_id)->toBe($cemetery->id)
        ->and($burial->date_year)->toBeNull()
        ->and($burial->date_is_approximate)->toBeFalse()
        ->and($burial->participants()->firstOrFail()->pivot->role)->toBe(ParticipantRole::Subject);
});

test('store without a burial locality creates no burial event', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('persons.store'), [
        'first_name' => 'Jan',
        'is_deceased' => '1',
        'burial' => ['locality_id' => ''],
    ])->assertSessionHasNoErrors();

    expect(Event::where('type', EventType::Burial)->count())->toBe(0);
});

test('store rejects a burial locality when the person is not deceased', function () {
    $user = User::factory()->create();
    $cemetery = Locality::factory()->for($user->tree)->create();

    $this->actingAs($user)->post(route('persons.store'), [
        'first_name' => 'Jan',
        'burial' => ['locality_id' => $cemetery->id],
    ])->assertSessionHasErrors('burial.locality_id');

    expect(Person::count())->toBe(0);
});

test('update creates, changes in place and clears the burial without touching the death event', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $death = Event::factory()->for($user->tree)->death()->create(['date_year' => 1999, 'date_month' => 5, 'date_day' => 6]);
    $death->participants()->attach($person, ['role' => ParticipantRole::Subject]);
    $first = Locality::factory()->for($user->tree)->create(['name' => 'Lipnica']);
    $second = Locality::factory()->for($user->tree)->create(['name' => 'Nowy Targ']);
    $payload = fn (?int $localityId): array => [
        'first_name' => 'Jan',
        'is_deceased' => '1',
        'death' => ['year' => 1999, 'month' => 5, 'day' => 6],
        'burial' => ['locality_id' => $localityId],
    ];

    $this->actingAs($user)->put(route('persons.update', $person), $payload($first->id))->assertSessionHasNoErrors();
    $burial = Event::where('type', EventType::Burial)->sole();
    expect($burial->locality_id)->toBe($first->id);

    $this->actingAs($user)->put(route('persons.update', $person), $payload($second->id))->assertSessionHasNoErrors();
    expect(Event::where('type', EventType::Burial)->sole()->id)->toBe($burial->id)
        ->and($burial->refresh()->locality_id)->toBe($second->id)
        ->and($burial->date_year)->toBeNull();

    $death->refresh();
    expect($death->type)->toBe(EventType::Death)
        ->and($death->date_year)->toBe(1999)
        ->and($death->locality_id)->toBeNull();

    $this->actingAs($user)->put(route('persons.update', $person), $payload(null))->assertSessionHasNoErrors();
    expect(Event::where('type', EventType::Burial)->count())->toBe(0)
        ->and(Event::whereKey($death->id)->exists())->toBeTrue();
});

test('update is a full replace: a deceased person saved without a burial key loses the burial', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $death = Event::factory()->for($user->tree)->death()->create();
    $death->participants()->attach($person, ['role' => ParticipantRole::Subject]);
    $burial = Event::factory()->for($user->tree)->burial()->withLocality()->create();
    $burial->participants()->attach($person, ['role' => ParticipantRole::Subject]);

    $this->actingAs($user)->put(route('persons.update', $person), [
        'first_name' => 'Jan',
        'is_deceased' => '1',
    ])->assertSessionHasNoErrors();

    expect(Event::where('type', EventType::Burial)->count())->toBe(0);
});

test('unchecking is_deceased removes the burial together with the death', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $death = Event::factory()->for($user->tree)->death()->create();
    $death->participants()->attach($person, ['role' => ParticipantRole::Subject]);
    $burial = Event::factory()->for($user->tree)->burial()->withLocality()->create();
    $burial->participants()->attach($person, ['role' => ParticipantRole::Subject]);

    $this->actingAs($user)->put(route('persons.update', $person), ['first_name' => 'Jan'])
        ->assertSessionHasNoErrors();

    expect(Event::whereIn('type', [EventType::Death, EventType::Burial])->count())->toBe(0);
});

test('the preview exposes the burial locality for the form and the death section', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $death = Event::factory()->for($user->tree)->death()->create();
    $death->participants()->attach($person, ['role' => ParticipantRole::Subject]);
    $lipnica = Locality::factory()->for($user->tree)->create(['name' => 'Lipnica']);
    $burial = Event::factory()->for($user->tree)->burial()->create(['locality_id' => $lipnica->id]);
    $burial->participants()->attach($person, ['role' => ParticipantRole::Subject]);

    $this->actingAs($user)->get(personPreviewUrl($person))
        ->assertInertia(fn ($page) => $page
            ->where('personPreview.person.burial_place', 'Lipnica')
            ->where('personPreview.personForm.burial', ['locality' => ['id' => $lipnica->id, 'name' => 'Lipnica']]));
});

test('a foreign or missing locality in any fact place field fails with the same error and writes nothing', function (string $field) {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $foreign = Locality::factory()->for(User::factory()->create()->tree)->create();
    $eventsBefore = Event::count();

    foreach ([$foreign->id, 999999] as $localityId) {
        [$key] = explode('.', $field);

        $this->actingAs($user)->put(route('persons.update', $person), [
            'first_name' => 'Jan',
            'is_deceased' => '1',
            $key => ['locality_id' => $localityId],
        ])->assertSessionHasErrors($field);
    }

    expect(Event::count())->toBe($eventsBefore);
})->with(['birth.locality_id', 'death.locality_id', 'burial.locality_id']);

test('the parent slot request inherits the locality rule', function () {
    $user = User::factory()->create();
    $child = Person::factory()->for($user->tree)->create();
    $village = Locality::factory()->for($user->tree)->create();
    $foreign = Locality::factory()->for(User::factory()->create()->tree)->create();

    $this->actingAs($user)->post(route('persons.parents.store', $child), [
        'role' => 'parent', 'first_name' => 'Anna', 'birth' => ['locality_id' => $foreign->id],
    ])->assertSessionHasErrors('birth.locality_id');

    $this->actingAs($user)->post(route('persons.parents.store', $child), [
        'role' => 'parent', 'first_name' => 'Anna', 'birth' => ['locality_id' => $village->id],
    ])->assertSessionHasNoErrors();

    expect(Event::where('type', EventType::Birth)->sole()->locality_id)->toBe($village->id);
});

test('the shared localityOptions prop lists only the own tree localities sorted by name', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $zator = Locality::factory()->for($user->tree)->create(['name' => 'Zator']);
    $andrychow = Locality::factory()->for($user->tree)->create(['name' => 'Andrychów']);
    Locality::factory()->for(User::factory()->create()->tree)->create(['name' => 'Obca']);
    $url = personPreviewUrl($person);

    $version = $this->actingAs($user)->get($url, ['X-Inertia' => 'true'])->headers->get('X-Inertia-Version');

    $this->actingAs($user)->get($url)->assertInertia(fn ($page) => $page->missing('localityOptions'));

    $response = $this->actingAs($user)->get($url, [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $version,
        'X-Inertia-Partial-Data' => 'localityOptions',
        'X-Inertia-Partial-Component' => 'persons/index',
    ]);

    expect($response->json('props.localityOptions'))->toBe([
        ['id' => $andrychow->id, 'name' => 'Andrychów'],
        ['id' => $zator->id, 'name' => 'Zator'],
    ]);
});
