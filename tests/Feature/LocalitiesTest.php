<?php

use App\Actions\Localities\StoreLocality;
use App\Actions\Localities\UpdateLocality;
use App\Models\Event;
use App\Models\Locality;
use App\Models\Parish;
use App\Models\Person;
use App\Models\Residence;
use App\Models\Source;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

test('guests are redirected to the login page from localities routes', function () {
    $locality = Locality::factory()->create();

    $this->get(route('localities.index'))->assertRedirect(route('login'));
    $this->post(route('localities.store'), [])->assertRedirect(route('login'));
    $this->put(route('localities.update', $locality), [])->assertRedirect(route('login'));
    $this->delete(route('localities.destroy', $locality))->assertRedirect(route('login'));
    $this->post(route('localities.merge', $locality), [])->assertRedirect(route('login'));
});

test('the index lists only the user own localities alphabetically', function () {
    $user = User::factory()->create();
    Locality::factory()->for($user->tree)->create(['name' => 'Lwów']);
    Locality::factory()->for($user->tree)->create(['name' => 'Kraków']);
    Locality::factory()->for(User::factory()->create()->tree)->create(['name' => 'Cudza']);

    $this->actingAs($user)->get(route('localities.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('localities/index')
            ->has('localities', 2)
            ->where('localities.0.name', 'Kraków')
            ->where('localities.1.name', 'Lwów')
            ->has('localities.0', fn ($locality) => $locality->hasAll(['id', 'name', 'description', 'usage']))
            ->where('filters.q', ''));
});

test('storing creates a locality in the user tree and redirects to its panel with the toast', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('localities.store'), ['name' => 'Kraków', 'description' => 'Małopolska']);

    $locality = $user->tree->localities()->firstOrFail();

    $response
        ->assertRedirect(route('localities.index', ['locality' => $locality->id]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Dodano miejscowość']);

    expect($locality->name)->toBe('Kraków');
    expect($locality->description)->toBe('Małopolska');
});

test('updating changes the name and description, redirects back with the toast and stores an empty description as null', function () {
    $user = User::factory()->create();
    $locality = Locality::factory()->for($user->tree)->create(['name' => 'Krakow', 'description' => 'Stary opis']);

    $this->actingAs($user)->from(route('localities.index', ['locality' => $locality->id]))
        ->put(route('localities.update', $locality), ['name' => 'Kraków', 'description' => ''])
        ->assertRedirect(route('localities.index', ['locality' => $locality->id]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Zapisano miejscowość']);

    $locality->refresh();

    expect($locality->name)->toBe('Kraków');
    expect($locality->description)->toBeNull();
});

test('storing and updating validate the fields', function (array $payload, string $field) {
    $user = User::factory()->create();
    $locality = Locality::factory()->for($user->tree)->create();
    $before = $user->tree->localities()->count();

    $this->actingAs($user)->post(route('localities.store'), $payload)->assertInvalid($field);
    $this->actingAs($user)->put(route('localities.update', $locality), $payload)->assertInvalid($field);

    expect($user->tree->localities()->count())->toBe($before);
})->with([
    'missing name' => [['description' => 'Opis'], 'name'],
    'long name' => [['name' => str_repeat('a', 256)], 'name'],
    'array name' => [['name' => ['Kraków', 'Lwów']], 'name'],
    'long description' => [['name' => 'Kraków', 'description' => str_repeat('a', 5001)], 'description'],
]);

test('a name differing only in letter case from an existing one in the tree is rejected', function () {
    $user = User::factory()->create();
    Locality::factory()->for($user->tree)->create(['name' => 'Kraków']);

    $response = $this->actingAs($user)->post(route('localities.store'), ['name' => 'kraków']);

    $response->assertInvalid('name');
    expect($response->getSession()->get('errors')->getBag('default')->first('name'))->toBe('Miejscowość o tej nazwie jest już w spisie.');
    expect($user->tree->localities()->count())->toBe(1);
});

test('the same name in another tree is allowed', function () {
    $user = User::factory()->create();
    Locality::factory()->for(User::factory()->create()->tree)->create(['name' => 'Kraków']);

    $this->actingAs($user)->post(route('localities.store'), ['name' => 'Kraków'])->assertSessionHasNoErrors();

    expect($user->tree->localities()->count())->toBe(1);
});

test('updating a locality to its own name in another letter case passes', function () {
    $user = User::factory()->create();
    $locality = Locality::factory()->for($user->tree)->create(['name' => 'kraków']);

    $this->actingAs($user)->put(route('localities.update', $locality), ['name' => 'Kraków'])->assertSessionHasNoErrors();

    expect($locality->refresh()->name)->toBe('Kraków');
});

test('updating a locality to the name of another one in the tree is rejected', function () {
    $user = User::factory()->create();
    Locality::factory()->for($user->tree)->create(['name' => 'Kraków']);
    $locality = Locality::factory()->for($user->tree)->create(['name' => 'Lwów']);

    $this->actingAs($user)->put(route('localities.update', $locality), ['name' => 'KRAKÓW'])->assertInvalid('name');

    expect($locality->refresh()->name)->toBe('Lwów');
});

test('a duplicate name that slipped past validation is a name error, not a server error', function () {
    $user = User::factory()->create();
    Locality::factory()->for($user->tree)->create(['name' => 'Kraków']);
    $locality = Locality::factory()->for($user->tree)->create(['name' => 'Lwów']);

    $nameError = fn (ValidationException $exception) => expect($exception->errors())
        ->toBe(['name' => ['Miejscowość o tej nazwie jest już w spisie.']]);

    expect(fn () => app(StoreLocality::class)->handle($user->tree, 'kraków', null))->toThrow($nameError);
    expect(fn () => app(UpdateLocality::class)->handle($locality, 'KRAKÓW', null))->toThrow($nameError);

    expect($user->tree->localities()->orderBy('name')->pluck('name')->all())->toBe(['Kraków', 'Lwów']);
});

test('the list and the panel carry the usage counts of every kind of reference', function () {
    $user = User::factory()->create();
    $locality = Locality::factory()->for($user->tree)->create();
    $person = Person::factory()->for($user->tree)->create();

    Event::factory()->for($user->tree)->birth()->create(['locality_id' => $locality->id]);
    Parish::factory()->for($user->tree)->seatedIn($locality)->create();
    Residence::factory()->for($person)->create(['tree_id' => $user->tree->id, 'locality_id' => $locality->id]);
    Source::factory()->for($user->tree)->gravestone()->create(['cemetery_locality_id' => $locality->id]);

    $this->actingAs($user)->get(route('localities.index'))->assertInertia(fn ($page) => $page
        ->where('localities.0.usage.facts', 1)
        ->where('localities.0.usage.parishes', 1)
        ->where('localities.0.usage.residences', 1)
        ->where('localities.0.usage.gravestones', 1)
        ->where('localities.0.usage.total', 4));

    $this->actingAs($user)->get(route('localities.index', ['locality' => $locality->id]))->assertInertia(fn ($page) => $page
        ->where('localityPanel.locality.usage.facts', 1)
        ->where('localityPanel.locality.usage.parishes', 1)
        ->where('localityPanel.locality.usage.residences', 1)
        ->where('localityPanel.locality.usage.gravestones', 1)
        ->where('localityPanel.locality.usage.total', 4));
});

test('an unused locality can be deleted and the user goes back to the list with the toast', function () {
    $user = User::factory()->create();
    $locality = Locality::factory()->for($user->tree)->create();

    $this->actingAs($user)->delete(route('localities.destroy', $locality))
        ->assertRedirect(route('localities.index'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Usunięto miejscowość']);

    expect(Locality::find($locality->id))->toBeNull();
});

test('a locality still referenced by any kind of record cannot be deleted', function (string $kind) {
    $user = User::factory()->create();
    $locality = Locality::factory()->for($user->tree)->create();
    $person = Person::factory()->for($user->tree)->create();

    match ($kind) {
        'parish' => Parish::factory()->for($user->tree)->seatedIn($locality)->create(),
        'fact' => Event::factory()->for($user->tree)->birth()->create(['locality_id' => $locality->id]),
        'residence' => Residence::factory()->for($person)->create(['tree_id' => $user->tree->id, 'locality_id' => $locality->id]),
        'gravestone' => Source::factory()->for($user->tree)->gravestone()->create(['cemetery_locality_id' => $locality->id]),
    };

    $this->actingAs($user)->delete(route('localities.destroy', $locality))->assertInvalid('locality');

    expect(Locality::find($locality->id))->not->toBeNull();
})->with(['parish', 'fact', 'residence', 'gravestone']);

test('merging moves every kind of reference of the duplicate to the target and deletes the duplicate', function () {
    $user = User::factory()->create();
    $duplicate = Locality::factory()->for($user->tree)->create(['name' => 'Krakow']);
    $target = Locality::factory()->for($user->tree)->create(['name' => 'Kraków']);
    $other = Locality::factory()->for($user->tree)->create();
    $foreign = Locality::factory()->for(User::factory()->create()->tree)->create();

    $person = Person::factory()->for($user->tree)->create();
    $spouse = Person::factory()->for($user->tree)->create();

    $parish = Parish::factory()->for($user->tree)->seatedIn($duplicate)->create();
    $gravestone = Source::factory()->for($user->tree)->gravestone()->create(['cemetery_locality_id' => $duplicate->id]);
    $birth = Event::factory()->for($user->tree)->birth()->create(['locality_id' => $duplicate->id]);
    $marriage = attachMarriage($person, $spouse, 1900, locality: $duplicate);
    $burial = Event::factory()->for($user->tree)->burial()->create(['locality_id' => $duplicate->id]);
    $residence = Residence::factory()->for($person)->create(['tree_id' => $user->tree->id, 'locality_id' => $duplicate->id, 'year_from' => 1900, 'year_to' => 1910]);

    $otherParish = Parish::factory()->for($user->tree)->seatedIn($other)->create();
    $otherGravestone = Source::factory()->for($user->tree)->gravestone()->create(['cemetery_locality_id' => $other->id]);
    $otherEvent = Event::factory()->for($user->tree)->birth()->create(['locality_id' => $other->id]);
    $otherResidence = Residence::factory()->for($person)->create(['tree_id' => $user->tree->id, 'locality_id' => $other->id]);
    $foreignEvent = Event::factory()->for(User::factory()->create()->tree)->birth()->create(['locality_id' => $foreign->id]);

    $this->actingAs($user)->post(route('localities.merge', $duplicate), ['target_id' => $target->id])
        ->assertRedirect(route('localities.index', ['locality' => $target->id]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Scalono z miejscowością „Kraków”']);

    expect(Locality::find($duplicate->id))->toBeNull();
    expect($target->refresh()->name)->toBe('Kraków');
    expect($parish->refresh()->locality_id)->toBe($target->id);
    expect($gravestone->refresh()->cemetery_locality_id)->toBe($target->id);
    expect($birth->refresh()->locality_id)->toBe($target->id);
    expect($marriage->refresh()->locality_id)->toBe($target->id);
    expect($burial->refresh()->locality_id)->toBe($target->id);
    expect($residence->refresh()->locality_id)->toBe($target->id);

    expect($otherParish->refresh()->locality_id)->toBe($other->id);
    expect($otherGravestone->refresh()->cemetery_locality_id)->toBe($other->id);
    expect($otherEvent->refresh()->locality_id)->toBe($other->id);
    expect($otherResidence->refresh()->locality_id)->toBe($other->id);
    expect($foreignEvent->refresh()->locality_id)->toBe($foreign->id);
});

test('merging keeps one residence where the same person lived in the same years', function (?int $fromTarget, ?int $toTarget, ?int $fromDuplicate, ?int $toDuplicate, int $expectedRows) {
    $user = User::factory()->create();
    $duplicate = Locality::factory()->for($user->tree)->create();
    $target = Locality::factory()->for($user->tree)->create();
    $person = Person::factory()->for($user->tree)->create();

    Residence::factory()->for($person)->create(['tree_id' => $user->tree->id, 'locality_id' => $target->id, 'year_from' => $fromTarget, 'year_to' => $toTarget]);
    Residence::factory()->for($person)->create(['tree_id' => $user->tree->id, 'locality_id' => $duplicate->id, 'year_from' => $fromDuplicate, 'year_to' => $toDuplicate]);

    $this->actingAs($user)->post(route('localities.merge', $duplicate), ['target_id' => $target->id])->assertSessionHasNoErrors();

    expect(Residence::where('locality_id', $target->id)->count())->toBe($expectedRows);
})->with([
    'identical years' => [1900, 1910, 1900, 1910, 1],
    'no years at all' => [null, null, null, null, 1],
    'one side open' => [1900, null, 1900, null, 1],
    'different years' => [1900, 1910, 1900, 1920, 2],
    'only one year known differs' => [1900, null, 1900, 1910, 2],
]);

test('merging keeps a moved residence that is lower-id than the identical one already at the target only once', function () {
    $user = User::factory()->create();
    $duplicate = Locality::factory()->for($user->tree)->create();
    $target = Locality::factory()->for($user->tree)->create();
    $person = Person::factory()->for($user->tree)->create();

    $moved = Residence::factory()->for($person)->create(['tree_id' => $user->tree->id, 'locality_id' => $duplicate->id, 'year_from' => 1900, 'year_to' => 1910]);
    $existing = Residence::factory()->for($person)->create(['tree_id' => $user->tree->id, 'locality_id' => $target->id, 'year_from' => 1900, 'year_to' => 1910]);

    $this->actingAs($user)->post(route('localities.merge', $duplicate), ['target_id' => $target->id])->assertSessionHasNoErrors();

    expect(Residence::where('locality_id', $target->id)->pluck('id')->all())->toBe([$existing->id])
        ->and(Residence::find($moved->id))->toBeNull();
});

test('merging leaves identical residences that were already at the target alone', function () {
    $user = User::factory()->create();
    $duplicate = Locality::factory()->for($user->tree)->create();
    $target = Locality::factory()->for($user->tree)->create();
    $person = Person::factory()->for($user->tree)->create();

    Residence::factory()->for($person)->count(2)->create(['tree_id' => $user->tree->id, 'locality_id' => $target->id, 'year_from' => 1900, 'year_to' => 1910]);
    Residence::factory()->for($person)->create(['tree_id' => $user->tree->id, 'locality_id' => $duplicate->id, 'year_from' => 1920, 'year_to' => 1930]);

    $this->actingAs($user)->post(route('localities.merge', $duplicate), ['target_id' => $target->id])->assertSessionHasNoErrors();

    expect(Residence::where('locality_id', $target->id)->count())->toBe(3);
});

test('merging keeps the residences of different people even in the same years', function () {
    $user = User::factory()->create();
    $duplicate = Locality::factory()->for($user->tree)->create();
    $target = Locality::factory()->for($user->tree)->create();
    $first = Person::factory()->for($user->tree)->create();
    $second = Person::factory()->for($user->tree)->create();

    Residence::factory()->for($first)->create(['tree_id' => $user->tree->id, 'locality_id' => $target->id, 'year_from' => 1900, 'year_to' => 1910]);
    Residence::factory()->for($second)->create(['tree_id' => $user->tree->id, 'locality_id' => $duplicate->id, 'year_from' => 1900, 'year_to' => 1910]);

    $this->actingAs($user)->post(route('localities.merge', $duplicate), ['target_id' => $target->id])->assertSessionHasNoErrors();

    expect(Residence::where('locality_id', $target->id)->pluck('person_id')->sort()->values()->all())
        ->toBe(collect([$first->id, $second->id])->sort()->values()->all());
});

test('merging combines the descriptions of the two localities', function (?string $targetDescription, ?string $duplicateDescription, ?string $expected) {
    $user = User::factory()->create();
    $duplicate = Locality::factory()->for($user->tree)->create(['description' => $duplicateDescription]);
    $target = Locality::factory()->for($user->tree)->create(['description' => $targetDescription]);

    $this->actingAs($user)->post(route('localities.merge', $duplicate), ['target_id' => $target->id])->assertSessionHasNoErrors();

    expect($target->refresh()->description)->toBe($expected);
})->with([
    'target empty takes the duplicate' => [null, 'Opis duplikatu', 'Opis duplikatu'],
    'both different are joined' => ['Opis właściwego', 'Opis duplikatu', "Opis właściwego\n\nOpis duplikatu"],
    'equal stays as it was' => ['Ten sam opis', 'Ten sam opis', 'Ten sam opis'],
    'empty duplicate changes nothing' => ['Opis właściwego', null, 'Opis właściwego'],
    'blank duplicate changes nothing' => ['Opis właściwego', '   ', 'Opis właściwego'],
]);

test('merging refuses a target that is foreign, missing or the duplicate itself and changes nothing', function (string $kind) {
    $user = User::factory()->create();
    $duplicate = Locality::factory()->for($user->tree)->create();
    $parish = Parish::factory()->for($user->tree)->seatedIn($duplicate)->create();

    $targetId = match ($kind) {
        'foreign' => Locality::factory()->for(User::factory()->create()->tree)->create()->id,
        'missing' => 999999,
        'duplicate itself' => $duplicate->id,
        'empty' => null,
    };

    $this->actingAs($user)->post(route('localities.merge', $duplicate), ['target_id' => $targetId])
        ->assertInvalid('target_id');

    expect(Locality::find($duplicate->id))->not->toBeNull();
    expect($parish->refresh()->locality_id)->toBe($duplicate->id);
    expect($user->tree->localities()->count())->toBe(1);
})->with(['foreign', 'missing', 'duplicate itself', 'empty']);

test('the panel resolver returns null without a key or with a non-numeric one', function (string $query) {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('localities.index').$query)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('localityPanel', null));
})->with([
    'no key' => [''],
    'letters' => ['?locality=abc'],
]);

test('the panel resolver returns an empty panel for new and the locality data for an own id', function () {
    $user = User::factory()->create();
    $locality = Locality::factory()->for($user->tree)->create(['name' => 'Lipnica', 'description' => 'Opis']);

    $this->actingAs($user)->get(route('localities.index', ['locality' => 'new']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('localityPanel.locality', null));

    $this->actingAs($user)->get(route('localities.index', ['locality' => $locality->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('localityPanel.locality.id', $locality->id)
            ->where('localityPanel.locality.name', 'Lipnica')
            ->where('localityPanel.locality.description', 'Opis'));
});

test('the panel resolver answers 404 for a missing id', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('localities.index', ['locality' => 999999]))->assertNotFound();
});

test('the index search query is trimmed, cut to 100 characters and empty for a non-string', function (array $query, string $expected) {
    $this->actingAs(User::factory()->create())->get(route('localities.index', $query))->assertInertia(fn ($page) => $page
        ->where('filters.q', $expected));
})->with([
    'trimmed' => [['q' => '  łódź  '], 'łódź'],
    'cut' => [['q' => str_repeat('a', 120)], str_repeat('a', 100)],
    'array' => [['q' => ['łódź']], ''],
]);

test('a partial reload of the filters with an open panel runs no localities query', function () {
    $user = User::factory()->create();
    $locality = Locality::factory()->for($user->tree)->create();

    $version = $this->actingAs($user)->get(route('localities.index'), ['X-Inertia' => 'true'])->headers->get('X-Inertia-Version');

    DB::enableQueryLog();

    $response = $this->actingAs($user)->get(route('localities.index', ['locality' => $locality->id, 'q' => 'łódź']), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $version,
        'X-Inertia-Partial-Data' => 'filters',
        'X-Inertia-Partial-Component' => 'localities/index',
    ]);

    $localityQueries = collect(DB::getQueryLog())->filter(fn (array $log): bool => str_contains($log['query'], '"localities"'));

    $response->assertOk();
    expect($response->json('props.filters.q'))->toBe('łódź');
    expect($response->json('props'))->not->toHaveKey('localities')->not->toHaveKey('localityPanel');
    expect($localityQueries)->toBeEmpty();
});

test('the inline store returns the locality as 201 JSON and a duplicate in another letter case returns the existing one as 200', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('localities.inline.store'), ['name' => 'Kraków', 'description' => 'Pominięty'])
        ->assertCreated()
        ->assertExactJson(['id' => $user->tree->localities()->firstOrFail()->id, 'name' => 'Kraków']);

    expect($user->tree->localities()->firstOrFail()->description)->toBeNull();

    $this->actingAs($user)->postJson(route('localities.inline.store'), ['name' => 'KRAKÓW'])
        ->assertOk()
        ->assertExactJson(['id' => $user->tree->localities()->firstOrFail()->id, 'name' => 'Kraków']);

    expect($user->tree->localities()->count())->toBe(1);
});

test('the inline store returns the locality created by a concurrent request as 200', function () {
    $user = User::factory()->create();

    $this->mock(StoreLocality::class)
        ->shouldReceive('handle')
        ->once()
        ->andReturnUsing(function () use ($user): never {
            Locality::factory()->for($user->tree)->create(['name' => 'Kraków']);

            throw ValidationException::withMessages(['name' => 'Miejscowość o tej nazwie jest już w spisie.']);
        });

    $this->actingAs($user)->postJson(route('localities.inline.store'), ['name' => 'Kraków'])
        ->assertOk()
        ->assertExactJson(['id' => $user->tree->localities()->firstOrFail()->id, 'name' => 'Kraków']);

    expect($user->tree->localities()->count())->toBe(1);
});
