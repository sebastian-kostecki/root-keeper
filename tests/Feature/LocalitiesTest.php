<?php

use App\Actions\Localities\StoreLocality;
use App\Actions\Localities\UpdateLocality;
use App\Enums\LocalityKind;
use App\Models\Cemetery;
use App\Models\Event;
use App\Models\Locality;
use App\Models\Parish;
use App\Models\Person;
use App\Models\Residence;
use App\Models\Source;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The details the store and update actions take, empty apart from the name.
 *
 * @return array{name: string, description: ?string, kind: null, latitude: null, longitude: null, historical_affiliation: null, is_defunct: false, defunct_note: null}
 */
function localityDetails(string $name): array
{
    return [
        'name' => $name,
        'description' => null,
        'kind' => null,
        'latitude' => null,
        'longitude' => null,
        'historical_affiliation' => null,
        'is_defunct' => false,
        'defunct_note' => null,
    ];
}

test('guests are redirected to the login page from localities routes', function () {
    $locality = Locality::factory()->create();

    $this->get(route('localities.index'))->assertRedirect(route('login'));
    $this->post(route('localities.store'), [])->assertRedirect(route('login'));
    $this->put(route('localities.update', $locality), [])->assertRedirect(route('login'));
    $this->delete(route('localities.destroy', $locality))->assertRedirect(route('login'));
    $this->post(route('localities.merge', $locality), [])->assertRedirect(route('login'));
});

test('storing creates a locality in the user tree and redirects to its panel with the toast', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('localities.store'), ['name' => 'Kraków', 'description' => 'Małopolska']);

    $locality = $user->tree->localities()->firstOrFail();

    $response
        ->assertRedirect(route('places.index', ['locality' => $locality->id]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Dodano miejscowość']);

    expect($locality->name)->toBe('Kraków');
    expect($locality->description)->toBe('Małopolska');
});

test('updating changes the name and description, redirects back with the toast and stores an empty description as null', function () {
    $user = User::factory()->create();
    $locality = Locality::factory()->for($user->tree)->create(['name' => 'Krakow', 'description' => 'Stary opis']);

    $this->actingAs($user)->from(route('places.index', ['locality' => $locality->id]))
        ->put(route('localities.update', $locality), ['name' => 'Kraków', 'description' => ''])
        ->assertRedirect(route('places.index', ['locality' => $locality->id]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Zapisano miejscowość']);

    $locality->refresh();

    expect($locality->name)->toBe('Kraków');
    expect($locality->description)->toBeNull();
});

test('storing and updating save the kind, coordinates, historical affiliation and defunct status', function () {
    $user = User::factory()->create();
    $payload = [
        'name' => 'Lipnica',
        'kind' => 'village',
        'coordinates' => '50.0614, 19.9366',
        'historical_affiliation' => 'gm. Lipnica, pow. bocheński, Galicja',
        'description' => 'Opis',
        'is_defunct' => '1',
        'defunct_note' => 'Zajęta pod zalew 1975',
    ];

    $this->actingAs($user)->post(route('localities.store'), $payload)->assertSessionHasNoErrors();

    $locality = $user->tree->localities()->firstOrFail();

    expect($locality->kind)->toBe(LocalityKind::Village);
    expect($locality->latitude)->toBe(50.0614);
    expect($locality->longitude)->toBe(19.9366);
    expect($locality->historical_affiliation)->toBe('gm. Lipnica, pow. bocheński, Galicja');
    expect($locality->is_defunct)->toBeTrue();
    expect($locality->defunct_note)->toBe('Zajęta pod zalew 1975');

    $this->actingAs($user)->put(route('localities.update', $locality), [...$payload, 'kind' => 'hamlet', 'coordinates' => '-33.9 151.2'])
        ->assertSessionHasNoErrors();

    $locality->refresh();

    expect($locality->kind)->toBe(LocalityKind::Hamlet);
    expect($locality->latitude)->toBe(-33.9);
    expect($locality->longitude)->toBe(151.2);
});

test('updating with empty fields clears the details to null and turns defunct off', function () {
    $user = User::factory()->create();
    $locality = Locality::factory()->for($user->tree)->defunct('Zalana')->create([
        'kind' => LocalityKind::Colony,
        'latitude' => 50.0614,
        'longitude' => 19.9366,
        'historical_affiliation' => 'Galicja',
    ]);

    $this->actingAs($user)->put(route('localities.update', $locality), [
        'name' => $locality->name,
        'kind' => '',
        'coordinates' => '',
        'historical_affiliation' => '',
        'description' => '',
    ])->assertSessionHasNoErrors();

    $locality->refresh();

    expect($locality->kind)->toBeNull();
    expect($locality->latitude)->toBeNull();
    expect($locality->longitude)->toBeNull();
    expect($locality->historical_affiliation)->toBeNull();
    expect($locality->is_defunct)->toBeFalse();
    expect($locality->defunct_note)->toBeNull();
});

test('the coordinates are parsed from the pasted text', function (string $text, float $latitude, float $longitude) {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('localities.store'), ['name' => 'Miejsce', 'coordinates' => $text])
        ->assertSessionHasNoErrors();

    $locality = $user->tree->localities()->firstOrFail();

    expect($locality->latitude)->toBe($latitude);
    expect($locality->longitude)->toBe($longitude);
})->with([
    'comma and space' => ['50.0614, 19.9366', 50.0614, 19.9366],
    'space only' => ['50.0614 19.9366', 50.0614, 19.9366],
    'semicolon' => ['50.0614;19.9366', 50.0614, 19.9366],
    'negative' => ['-33.9, 151.2', -33.9, 151.2],
    'integers' => ['50, 19', 50.0, 19.0],
    'rounded to six places' => ['50.12345678, 19.1', 50.123457, 19.1],
]);

test('invalid coordinates are an error on the field and nothing is saved', function (string $text) {
    $user = User::factory()->create();
    $locality = Locality::factory()->for($user->tree)->create();

    $this->actingAs($user)->post(route('localities.store'), ['name' => 'Nowa', 'coordinates' => $text])->assertInvalid('coordinates');
    $this->actingAs($user)->put(route('localities.update', $locality), ['name' => 'Zmieniona', 'coordinates' => $text])->assertInvalid('coordinates');

    expect($user->tree->localities()->count())->toBe(1);
    expect($locality->refresh()->name)->not->toBe('Zmieniona');
    expect($locality->latitude)->toBeNull();
})->with([
    'one number' => ['50.0614'],
    'decimal comma' => ['50,0614, 19,9366'],
    'single number with a decimal comma' => ['52,21'],
    'degrees minutes seconds' => ['50°03\'41"N 19°56\'12"E'],
    'latitude over 90' => ['91, 19.9366'],
    'longitude under -180' => ['50.0614, -181'],
    'text' => ['gdzieś pod Krakowem'],
]);

test('an unknown kind is an error on the field', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('localities.store'), ['name' => 'Nowa', 'kind' => 'metropolis'])->assertInvalid('kind');

    expect($user->tree->localities()->count())->toBe(0);
});

test('a defunct note without the defunct switch is an error, and switching it off clears the saved note', function () {
    $user = User::factory()->create();
    $locality = Locality::factory()->for($user->tree)->defunct('Zalana')->create();

    $this->actingAs($user)->post(route('localities.store'), ['name' => 'Nowa', 'defunct_note' => 'Zalana'])->assertInvalid('defunct_note');
    $this->actingAs($user)->put(route('localities.update', $locality), ['name' => $locality->name, 'defunct_note' => 'Zalana'])->assertInvalid('defunct_note');

    expect($locality->refresh()->defunct_note)->toBe('Zalana');

    $this->actingAs($user)->put(route('localities.update', $locality), ['name' => $locality->name])->assertSessionHasNoErrors();

    expect($locality->refresh()->is_defunct)->toBeFalse();
    expect($locality->defunct_note)->toBeNull();
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

    expect(fn () => app(StoreLocality::class)->handle($user->tree, localityDetails('kraków')))->toThrow($nameError);
    expect(fn () => app(UpdateLocality::class)->handle($locality, localityDetails('KRAKÓW')))->toThrow($nameError);

    expect($user->tree->localities()->orderBy('name')->pluck('name')->all())->toBe(['Kraków', 'Lwów']);
});

test('an unused locality can be deleted and the user goes back to the list with the toast', function () {
    $user = User::factory()->create();
    $locality = Locality::factory()->for($user->tree)->create();

    $this->actingAs($user)->delete(route('localities.destroy', $locality))
        ->assertRedirect(route('places.index'))
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
        'gravestone' => Source::factory()->for($user->tree)->gravestone()->create(['cemetery_id' => Cemetery::factory()->in($locality)->create()->id]),
        'cemetery' => Cemetery::factory()->in($locality)->create(),
    };

    $this->actingAs($user)->delete(route('localities.destroy', $locality))->assertInvalid('locality');

    expect(Locality::find($locality->id))->not->toBeNull();
})->with(['parish', 'fact', 'residence', 'gravestone', 'cemetery']);

test('merging moves every kind of reference of the duplicate to the target and deletes the duplicate', function () {
    $user = User::factory()->create();
    $duplicate = Locality::factory()->for($user->tree)->create(['name' => 'Krakow']);
    $target = Locality::factory()->for($user->tree)->create(['name' => 'Kraków']);
    $other = Locality::factory()->for($user->tree)->create();
    $foreign = Locality::factory()->for(User::factory()->create()->tree)->create();

    $person = Person::factory()->for($user->tree)->create();
    $spouse = Person::factory()->for($user->tree)->create();

    $parish = Parish::factory()->for($user->tree)->seatedIn($duplicate)->create();
    $cemetery = Cemetery::factory()->in($duplicate)->create();
    $gravestone = Source::factory()->for($user->tree)->gravestone()->create(['cemetery_id' => $cemetery->id]);
    $birth = Event::factory()->for($user->tree)->birth()->create(['locality_id' => $duplicate->id]);
    $marriage = attachMarriage($person, $spouse, 1900, locality: $duplicate);
    $burial = Event::factory()->for($user->tree)->burial()->create(['locality_id' => $duplicate->id]);
    $residence = Residence::factory()->for($person)->create(['tree_id' => $user->tree->id, 'locality_id' => $duplicate->id, 'year_from' => 1900, 'year_to' => 1910]);

    $otherParish = Parish::factory()->for($user->tree)->seatedIn($other)->create();
    $otherCemetery = Cemetery::factory()->in($other)->create();
    $otherGravestone = Source::factory()->for($user->tree)->gravestone()->create(['cemetery_id' => $otherCemetery->id]);
    $otherEvent = Event::factory()->for($user->tree)->birth()->create(['locality_id' => $other->id]);
    $otherResidence = Residence::factory()->for($person)->create(['tree_id' => $user->tree->id, 'locality_id' => $other->id]);
    $foreignEvent = Event::factory()->for(User::factory()->create()->tree)->birth()->create(['locality_id' => $foreign->id]);

    $this->actingAs($user)->post(route('localities.merge', $duplicate), ['target_id' => $target->id])
        ->assertRedirect(route('places.index', ['locality' => $target->id]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Scalono z miejscowością „Kraków”']);

    expect(Locality::find($duplicate->id))->toBeNull();
    expect($target->refresh()->name)->toBe('Kraków');
    expect($parish->refresh()->locality_id)->toBe($target->id);
    expect($cemetery->refresh()->locality_id)->toBe($target->id);
    expect($gravestone->refresh()->cemetery_id)->toBe($cemetery->id);
    expect($birth->refresh()->locality_id)->toBe($target->id);
    expect($marriage->refresh()->locality_id)->toBe($target->id);
    expect($burial->refresh()->locality_id)->toBe($target->id);
    expect($residence->refresh()->locality_id)->toBe($target->id);

    expect($otherParish->refresh()->locality_id)->toBe($other->id);
    expect($otherCemetery->refresh()->locality_id)->toBe($other->id);
    expect($otherGravestone->refresh()->cemetery_id)->toBe($otherCemetery->id);
    expect($otherEvent->refresh()->locality_id)->toBe($other->id);
    expect($otherResidence->refresh()->locality_id)->toBe($other->id);
    expect($foreignEvent->refresh()->locality_id)->toBe($foreign->id);
});

test('merging works for a legacy gravestone whose old cemetery locality still names the duplicate', function () {
    $user = User::factory()->create();
    $duplicate = Locality::factory()->for($user->tree)->create();
    $target = Locality::factory()->for($user->tree)->create();
    $gravestone = Source::factory()->for($user->tree)->gravestone()->create();

    DB::table('sources')->where('id', $gravestone->id)->update(['cemetery_locality_id' => $duplicate->id]);

    $this->actingAs($user)->post(route('localities.merge', $duplicate), ['target_id' => $target->id])
        ->assertSessionHasNoErrors();

    expect(Locality::find($duplicate->id))->toBeNull();
    expect(Source::find($gravestone->id))->not->toBeNull();
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

test('merging fills the empty details of the target from the duplicate and keeps the ones the target has', function () {
    $user = User::factory()->create();
    $duplicate = Locality::factory()->for($user->tree)->create([
        'kind' => LocalityKind::Village,
        'latitude' => 50.0614,
        'longitude' => 19.9366,
        'historical_affiliation' => 'Galicja',
    ]);
    $target = Locality::factory()->for($user->tree)->create();

    $this->actingAs($user)->post(route('localities.merge', $duplicate), ['target_id' => $target->id])->assertSessionHasNoErrors();

    $target->refresh();

    expect($target->kind)->toBe(LocalityKind::Village);
    expect($target->latitude)->toBe(50.0614);
    expect($target->longitude)->toBe(19.9366);
    expect($target->historical_affiliation)->toBe('Galicja');
});

test('merging keeps the filled details of the target and joins different historical affiliations', function () {
    $user = User::factory()->create();
    $duplicate = Locality::factory()->for($user->tree)->create([
        'kind' => LocalityKind::Village,
        'latitude' => 1.5,
        'longitude' => 2.5,
        'historical_affiliation' => 'Galicja',
    ]);
    $target = Locality::factory()->for($user->tree)->create([
        'kind' => LocalityKind::City,
        'latitude' => 50.0614,
        'longitude' => 19.9366,
        'historical_affiliation' => 'pow. bocheński',
    ]);

    $this->actingAs($user)->post(route('localities.merge', $duplicate), ['target_id' => $target->id])->assertSessionHasNoErrors();

    $target->refresh();

    expect($target->kind)->toBe(LocalityKind::City);
    expect($target->latitude)->toBe(50.0614);
    expect($target->longitude)->toBe(19.9366);
    expect($target->historical_affiliation)->toBe("pow. bocheński\n\nGalicja");
});

test('merging takes the coordinates of the duplicate only as a pair', function (?float $targetLatitude, ?float $targetLongitude, ?float $duplicateLatitude, ?float $duplicateLongitude, ?float $expectedLatitude, ?float $expectedLongitude) {
    $user = User::factory()->create();
    $duplicate = Locality::factory()->for($user->tree)->create(['latitude' => $duplicateLatitude, 'longitude' => $duplicateLongitude]);
    $target = Locality::factory()->for($user->tree)->create(['latitude' => $targetLatitude, 'longitude' => $targetLongitude]);

    $this->actingAs($user)->post(route('localities.merge', $duplicate), ['target_id' => $target->id])->assertSessionHasNoErrors();

    expect($target->refresh()->latitude)->toBe($expectedLatitude);
    expect($target->longitude)->toBe($expectedLongitude);
})->with([
    'an empty target takes the full pair' => [null, null, 1.5, 2.5, 1.5, 2.5],
    'a half pair of the duplicate is not taken' => [null, null, 1.5, null, null, null],
    'a target with a half pair takes the full pair' => [3.5, null, 1.5, 2.5, 1.5, 2.5],
    'a target with a full pair keeps it' => [3.5, 4.5, 1.5, 2.5, 3.5, 4.5],
]);

test('merging marks the target defunct when either was, keeping its own note first', function (bool $targetDefunct, ?string $targetNote, bool $duplicateDefunct, ?string $duplicateNote, bool $expectedDefunct, ?string $expectedNote) {
    $user = User::factory()->create();
    $duplicate = Locality::factory()->for($user->tree)->create(['is_defunct' => $duplicateDefunct, 'defunct_note' => $duplicateNote]);
    $target = Locality::factory()->for($user->tree)->create(['is_defunct' => $targetDefunct, 'defunct_note' => $targetNote]);

    $this->actingAs($user)->post(route('localities.merge', $duplicate), ['target_id' => $target->id])->assertSessionHasNoErrors();

    $target->refresh();

    expect($target->is_defunct)->toBe($expectedDefunct);
    expect($target->defunct_note)->toBe($expectedNote);
})->with([
    'neither' => [false, null, false, null, false, null],
    'only the duplicate' => [false, null, true, 'Zalana', true, 'Zalana'],
    'only the target' => [true, 'Spalona', false, null, true, 'Spalona'],
    'both keep the target note' => [true, 'Spalona', true, 'Zalana', true, 'Spalona'],
    'target without a note takes the duplicate note' => [true, null, true, 'Zalana', true, 'Zalana'],
]);

test('merging two defunct localities keeps a different duplicate note in the description', function (string $duplicateNote, string $expectedDescription) {
    $user = User::factory()->create();
    $duplicate = Locality::factory()->for($user->tree)->defunct($duplicateNote)->create(['name' => 'Zalesie']);
    $target = Locality::factory()->for($user->tree)->defunct('Spalona')->create(['description' => 'Wieś nad rzeką']);

    $this->actingAs($user)->post(route('localities.merge', $duplicate), ['target_id' => $target->id])->assertSessionHasNoErrors();

    $target->refresh();

    expect($target->defunct_note)->toBe('Spalona');
    expect($target->description)->toBe($expectedDescription);
})->with([
    'different notes' => ['Zalana', "Wieś nad rzeką\n\nOpis zaniku z „Zalesie”: Zalana"],
    'the same note' => ['Spalona', 'Wieś nad rzeką'],
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

test('the inline store returns the locality as 201 JSON and a duplicate in another letter case returns the existing one as 200', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('localities.inline.store'), ['name' => 'Kraków', 'description' => 'Pominięty'])
        ->assertCreated()
        ->assertExactJson(['id' => $user->tree->localities()->firstOrFail()->id, 'name' => 'Kraków', 'is_defunct' => false]);

    expect($user->tree->localities()->firstOrFail()->description)->toBeNull();

    $this->actingAs($user)->postJson(route('localities.inline.store'), ['name' => 'KRAKÓW'])
        ->assertOk()
        ->assertExactJson(['id' => $user->tree->localities()->firstOrFail()->id, 'name' => 'Kraków', 'is_defunct' => false]);

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
        ->assertExactJson(['id' => $user->tree->localities()->firstOrFail()->id, 'name' => 'Kraków', 'is_defunct' => false]);

    expect($user->tree->localities()->count())->toBe(1);
});

test('merging moves the cemeteries of the duplicate to the target', function () {
    $user = User::factory()->create();
    $duplicate = Locality::factory()->for($user->tree)->create();
    $target = Locality::factory()->for($user->tree)->create();
    $cemetery = Cemetery::factory()->in($duplicate)->create(['name' => 'Cmentarz parafialny']);
    $burial = Event::factory()->burial()->atCemetery($cemetery)->create();

    $this->actingAs($user)->post(route('localities.merge', $duplicate), ['target_id' => $target->id])->assertSessionHasNoErrors();

    expect($cemetery->refresh()->locality_id)->toBe($target->id);
    expect($burial->refresh()->locality_id)->toBe($target->id);
});

test('merging merges the colliding cemeteries, named and unnamed, and moves their gravestones and burials to the target cemetery', function () {
    $user = User::factory()->create();
    $duplicate = Locality::factory()->for($user->tree)->create();
    $target = Locality::factory()->for($user->tree)->create();

    $duplicateNamed = Cemetery::factory()->in($duplicate)->create(['name' => 'Cmentarz Parafialny']);
    $targetNamed = Cemetery::factory()->in($target)->create(['name' => 'cmentarz parafialny']);
    $duplicateUnnamed = Cemetery::factory()->in($duplicate)->unnamed()->create();
    $targetUnnamed = Cemetery::factory()->in($target)->unnamed()->create();
    $duplicateOnly = Cemetery::factory()->in($duplicate)->create(['name' => 'Wojenny']);

    $namedGravestone = Source::factory()->for($user->tree)->gravestone()->create(['cemetery_id' => $duplicateNamed->id]);
    $unnamedBurial = Event::factory()->burial()->atCemetery($duplicateUnnamed)->create();
    $onlyBurial = Event::factory()->burial()->atCemetery($duplicateOnly)->create();

    $this->actingAs($user)->post(route('localities.merge', $duplicate), ['target_id' => $target->id])->assertSessionHasNoErrors();

    expect(Cemetery::find($duplicateNamed->id))->toBeNull();
    expect(Cemetery::find($duplicateUnnamed->id))->toBeNull();
    expect($namedGravestone->refresh()->cemetery_id)->toBe($targetNamed->id);
    expect($unnamedBurial->refresh()->cemetery_id)->toBe($targetUnnamed->id);
    expect($unnamedBurial->locality_id)->toBe($target->id);
    expect($duplicateOnly->refresh()->locality_id)->toBe($target->id);
    expect($onlyBurial->refresh()->locality_id)->toBe($target->id);
    expect($target->cemeteries()->count())->toBe(3);
});
