<?php

use App\Actions\Sources\FillBurialPlacesFromGravestone;
use App\Enums\EventType;
use App\Enums\SourceKind;
use App\Models\Event;
use App\Models\Locality;
use App\Models\Person;
use App\Models\Source;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * A person of the tree with a death and, when a place is given, a burial in it.
 */
function deceasedPerson(User $user, string $firstName, ?Locality $buriedIn = null): Person
{
    $person = Person::factory()->for($user->tree)->create(['first_name' => $firstName]);

    attachDeath($person, 1950);

    if ($buriedIn !== null) {
        $burial = Event::factory()->for($user->tree)->burial()->create(['locality_id' => $buriedIn->id]);
        $burial->participants()->attach($person, ['role' => 'subject']);
    }

    return $person;
}

function burialPlaceOf(Person $person): ?int
{
    return Person::query()->with('events')->findOrFail($person->id)->burial()?->locality_id;
}

/**
 * @param  array<string, mixed>  $fields
 * @return array<string, mixed>
 */
function gravestoneFields(array $fields = []): array
{
    return ['kind' => 'gravestone', ...$fields];
}

test('a new gravestone stores its cemetery and the buried people in the order they were added', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $cemetery = Locality::factory()->for($user->tree)->create(['name' => 'Luboml']);
    $first = Person::factory()->for($user->tree)->create();
    $second = Person::factory()->for($user->tree)->create();

    $this->actingAs($user)->post(route('sources.store'), gravestoneFields([
        'file' => UploadedFile::fake()->image('nagrobek.jpg', 400, 300),
        'cemetery_locality_id' => $cemetery->id,
        'buried_person_ids' => [$second->id, $first->id],
    ]))->assertSessionHasNoErrors();

    $source = Source::sole();

    expect($source->cemetery_locality_id)->toBe($cemetery->id);
    expect($source->buriedPeople->pluck('id')->all())->toBe([$second->id, $first->id]);
});

test('updating a gravestone replaces the buried list and a missing key clears it', function () {
    $user = User::factory()->create();
    $source = Source::factory()->for($user->tree)->gravestone()->create();
    $kept = Person::factory()->for($user->tree)->create();
    $dropped = Person::factory()->for($user->tree)->create();
    $added = Person::factory()->for($user->tree)->create();
    $source->buriedPeople()->attach([$kept->id, $dropped->id]);

    $this->actingAs($user)->put(route('sources.update', $source), gravestoneFields(['buried_person_ids' => [$kept->id, $added->id]]))
        ->assertSessionHasNoErrors();

    expect($source->buriedPeople()->pluck('people.id')->all())->toEqualCanonicalizing([$kept->id, $added->id]);

    $this->actingAs($user)->put(route('sources.update', $source), gravestoneFields())
        ->assertSessionHasNoErrors();

    expect($source->buriedPeople()->count())->toBe(0);
});

test('a kind other than gravestone ignores the cemetery and the buried list', function () {
    $user = User::factory()->create();
    $cemetery = Locality::factory()->for($user->tree)->create();
    $person = Person::factory()->for($user->tree)->create();
    $source = Source::factory()->for($user->tree)->create();

    $this->actingAs($user)->put(route('sources.update', $source), [
        'kind' => 'metrical_record',
        'cemetery_locality_id' => $cemetery->id,
        'buried_person_ids' => [$person->id],
    ])->assertSessionHasNoErrors();

    expect($source->refresh()->cemetery_locality_id)->toBeNull();
    expect($source->buriedPeople()->count())->toBe(0);
});

test('another users cemetery or person is rejected and nothing is written', function (string $field) {
    Storage::fake('local');

    $user = User::factory()->create();
    $other = User::factory()->create();
    $source = Source::factory()->for($user->tree)->gravestone()->create();
    $foreign = $field === 'cemetery_locality_id'
        ? Locality::factory()->for($other->tree)->create()->id
        : [Person::factory()->for($other->tree)->create()->id];

    $before = databaseRows();

    $this->actingAs($user)->put(route('sources.update', $source), gravestoneFields([$field => $foreign]))
        ->assertSessionHasErrors($field === 'cemetery_locality_id' ? 'cemetery_locality_id' : 'buried_person_ids.0');

    expect(rowDiff($before, databaseRows())['tables'])->toBe([]);
})->with(['cemetery_locality_id', 'buried_person_ids']);

test('saving a gravestone fills only the empty burial places of deceased people', function () {
    $user = User::factory()->create();
    $luboml = Locality::factory()->for($user->tree)->create(['name' => 'Luboml']);
    $chelm = Locality::factory()->for($user->tree)->create(['name' => 'Chełm']);
    $jan = deceasedPerson($user, 'Jan');
    $maria = deceasedPerson($user, 'Maria', $chelm);
    $anna = Person::factory()->for($user->tree)->create(['first_name' => 'Anna']);
    $source = Source::factory()->for($user->tree)->gravestone()->create();

    $this->actingAs($user)->put(route('sources.update', $source), gravestoneFields([
        'cemetery_locality_id' => $luboml->id,
        'buried_person_ids' => [$jan->id, $maria->id, $anna->id],
    ]))->assertSessionHasNoErrors();

    expect(burialPlaceOf($jan))->toBe($luboml->id);
    expect(burialPlaceOf($maria))->toBe($chelm->id);
    expect(burialPlaceOf($anna))->toBeNull();
    expect($anna->events()->count())->toBe(0);
});

test('a cemetery added in a later save fills a person added earlier', function () {
    $user = User::factory()->create();
    $luboml = Locality::factory()->for($user->tree)->create();
    $jan = deceasedPerson($user, 'Jan');
    $source = Source::factory()->for($user->tree)->gravestone()->create();

    $this->actingAs($user)->put(route('sources.update', $source), gravestoneFields(['buried_person_ids' => [$jan->id]]))
        ->assertSessionHasNoErrors();

    expect(burialPlaceOf($jan))->toBeNull();

    $this->actingAs($user)->put(route('sources.update', $source), gravestoneFields([
        'cemetery_locality_id' => $luboml->id,
        'buried_person_ids' => [$jan->id],
    ]))->assertSessionHasNoErrors();

    expect(burialPlaceOf($jan))->toBe($luboml->id);
});

test('removing a person or changing the cemetery does not undo a filled burial place', function () {
    $user = User::factory()->create();
    $luboml = Locality::factory()->for($user->tree)->create();
    $chelm = Locality::factory()->for($user->tree)->create();
    $jan = deceasedPerson($user, 'Jan');
    $source = Source::factory()->for($user->tree)->gravestone()->create();

    $this->actingAs($user)->put(route('sources.update', $source), gravestoneFields([
        'cemetery_locality_id' => $luboml->id,
        'buried_person_ids' => [$jan->id],
    ]))->assertSessionHasNoErrors();

    $this->actingAs($user)->put(route('sources.update', $source), gravestoneFields([
        'cemetery_locality_id' => $chelm->id,
        'buried_person_ids' => [],
    ]))->assertSessionHasNoErrors();

    expect(burialPlaceOf($jan))->toBe($luboml->id);
    expect(Event::query()->where('type', EventType::Burial)->count())->toBe(1);
});

test('changing the kind needs confirmation when a cemetery or buried people would be erased', function (string $stored) {
    $user = User::factory()->create();
    $source = Source::factory()->for($user->tree)->gravestone()->create();

    if ($stored === 'cemetery') {
        $source->update(['cemetery_locality_id' => Locality::factory()->for($user->tree)->create()->id]);
    } else {
        $source->buriedPeople()->attach(Person::factory()->for($user->tree)->create());
    }

    $response = $this->actingAs($user)->put(route('sources.update', $source), ['kind' => 'other']);

    $response->assertInvalid('kind');
    expect($response->getSession()->get('errors')->getBag('default')->first('kind'))->toBe('Zmiana rodzaju usunie pola opisu tego rodzaju. Potwierdź zmianę.');
    expect($source->refresh()->kind)->toBe(SourceKind::Gravestone);
})->with(['cemetery', 'buried people']);

test('changing the kind with confirmation clears the cemetery and the list but keeps the burial place', function () {
    $user = User::factory()->create();
    $luboml = Locality::factory()->for($user->tree)->create();
    $jan = deceasedPerson($user, 'Jan');
    $source = Source::factory()->for($user->tree)->gravestone()->create();

    $this->actingAs($user)->put(route('sources.update', $source), gravestoneFields([
        'cemetery_locality_id' => $luboml->id,
        'buried_person_ids' => [$jan->id],
    ]))->assertSessionHasNoErrors();

    $this->actingAs($user)->put(route('sources.update', $source), ['kind' => 'other', 'discard_record_fields' => '1'])
        ->assertSessionHasNoErrors();

    $source->refresh();

    expect($source->kind)->toBe(SourceKind::Other);
    expect($source->cemetery_locality_id)->toBeNull();
    expect($source->buriedPeople()->count())->toBe(0);
    expect(burialPlaceOf($jan))->toBe($luboml->id);
});

test('deleting the source or the person clears the buried list', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $first = Person::factory()->for($user->tree)->create();
    $second = Person::factory()->for($user->tree)->create();
    $gone = Source::factory()->for($user->tree)->gravestone()->create();
    $kept = Source::factory()->for($user->tree)->gravestone()->create();
    $gone->buriedPeople()->attach([$first->id, $second->id]);
    $kept->buriedPeople()->attach([$first->id, $second->id]);

    $this->actingAs($user)->delete(route('sources.destroy', $gone))->assertSessionHasNoErrors();

    expect(DB::table('buried_people')->where('source_id', $gone->id)->count())->toBe(0);
    expect(DB::table('buried_people')->where('source_id', $kept->id)->count())->toBe(2);

    $first->delete();

    expect(DB::table('buried_people')->where('person_id', $first->id)->count())->toBe(0);
    expect(DB::table('buried_people')->where('source_id', $kept->id)->count())->toBe(1);
});

test('a failure while filling burial places leaves no source row, no list and no files', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $cemetery = Locality::factory()->for($user->tree)->create();
    $jan = deceasedPerson($user, 'Jan');

    $this->mock(FillBurialPlacesFromGravestone::class)
        ->shouldReceive('handle')->once()->andThrow(new RuntimeException('boom'));

    $this->actingAs($user)->post(route('sources.store'), gravestoneFields([
        'file' => UploadedFile::fake()->image('nagrobek.jpg', 400, 300),
        'cemetery_locality_id' => $cemetery->id,
        'buried_person_ids' => [$jan->id],
    ]))->assertInternalServerError();

    expect(Source::count())->toBe(0);
    expect(DB::table('buried_people')->count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

test('updating another users gravestone is not found', function () {
    $user = User::factory()->create();
    $source = Source::factory()->for(User::factory()->create()->tree)->gravestone()->create();

    $this->actingAs($user)->put(route('sources.update', $source), gravestoneFields())->assertNotFound();
});

test('the sources page offers the tree people as an optional prop', function () {
    $user = User::factory()->create();
    $mine = Person::factory()->for($user->tree)->create();
    Person::factory()->for(User::factory()->create()->tree)->create();

    $this->actingAs($user)->get(route('sources.index'))
        ->assertInertia(fn ($page) => $page->component('sources/index')->missing('personCandidates'));

    $version = $this->actingAs($user)->get(route('sources.index'), ['X-Inertia' => 'true'])->headers->get('X-Inertia-Version');

    $response = $this->actingAs($user)->get(route('sources.index'), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $version,
        'X-Inertia-Partial-Data' => 'personCandidates',
        'X-Inertia-Partial-Component' => 'sources/index',
    ]);

    $response->assertOk();
    expect(collect($response->json('props.personCandidates'))->pluck('id')->all())->toBe([$mine->id]);
});

test('the person preview lists the gravestones and suggests the one shared cemetery', function (array $cemeteryNames, ?string $suggested) {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();

    foreach ($cemeteryNames as $name) {
        $cemetery = Locality::query()->firstOrCreate(['tree_id' => $user->tree->id, 'name' => $name]);
        Source::factory()->for($user->tree)->gravestone()->create(['cemetery_locality_id' => $cemetery->id])
            ->buriedPeople()->attach($person);
    }

    $this->actingAs($user)->get(personPreviewUrl($person))->assertInertia(function ($page) use ($cemeteryNames, $suggested) {
        $page->has('personPreview.person.gravestones', count($cemeteryNames));

        $suggested === null
            ? $page->where('personPreview.personForm.burial_suggestion', null)
            : $page->where('personPreview.personForm.burial_suggestion.name', $suggested);

        if ($cemeteryNames !== []) {
            $page->where('personPreview.person.gravestones.0.cemetery_name', $cemeteryNames[0]);
        }
    });
})->with([
    'one cemetery' => [['Luboml'], 'Luboml'],
    'two gravestones, same cemetery' => [['Luboml', 'Luboml'], 'Luboml'],
    'two different cemeteries' => [['Luboml', 'Chełm'], null],
    'no gravestones' => [[], null],
]);
