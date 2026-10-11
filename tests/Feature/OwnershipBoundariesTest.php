<?php

use App\Enums\ParticipantRole;
use App\Models\Event;
use App\Models\Locality;
use App\Models\ParentChildRelation;
use App\Models\Parish;
use App\Models\Person;
use App\Models\Source;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/**
 * Records of the two trees a route case is built from: the victim's (whose ids go into the address)
 * and the attacker's own (whose ids make the body "valid").
 *
 * @return array<string, mixed>
 */
function ownershipRecords(User $attacker, User $victim): array
{
    $victimPerson = Person::factory()->for($victim->tree)->create([
        'photo_path' => "persons/{$victim->tree->id}/victim.jpg",
        'photo_thumbnail_path' => "persons/{$victim->tree->id}/thumbnails/victim.jpg",
    ]);
    Storage::disk('local')->put($victimPerson->photo_path, 'original-bytes');
    Storage::disk('local')->put($victimPerson->photo_thumbnail_path, 'thumbnail-bytes');

    $victimSpouse = Person::factory()->for($victim->tree)->create();
    $victimChild = Person::factory()->for($victim->tree)->create();

    $victimSource = Source::factory()->for($victim->tree)->withThumbnail()->create();
    Storage::disk('local')->put($victimSource->path, 'original-bytes');
    Storage::disk('local')->put($victimSource->thumbnail_path, 'thumbnail-bytes');

    $victimEvent = Event::factory()->for($victim->tree)->create();
    $victimEvent->participants()->attach($victimPerson, ['role' => ParticipantRole::Subject]);
    $victimEvent->sources()->attach($victimSource);

    $victimMarriage = attachMarriage($victimPerson, $victimSpouse, 1900);
    $victimMarriage->sources()->attach($victimSource);

    $ownEvent = Event::factory()->for($attacker->tree)->create();
    $ownSource = Source::factory()->for($attacker->tree)->create();

    // Cross-linked on purpose so that events.sources.destroy has a pivot row to (wrongly) remove.
    $victimEvent->sources()->attach($ownSource);
    $ownEvent->sources()->attach($victimSource);

    return [
        'victimPerson' => $victimPerson,
        'victimSource' => $victimSource,
        'victimEvent' => $victimEvent,
        'victimMarriage' => $victimMarriage,
        'victimLocality' => Locality::factory()->for($victim->tree)->create(),
        'victimParish' => Parish::factory()->for($victim->tree)->create(),
        'victimRelation' => ParentChildRelation::factory()->create([
            'tree_id' => $victim->tree->id,
            'parent_id' => $victimPerson->id,
            'child_id' => $victimChild->id,
        ]),
        'ownPerson' => Person::factory()->for($attacker->tree)->create(),
        'ownSource' => $ownSource,
        'ownEvent' => $ownEvent,
        'ownLocality' => Locality::factory()->for($attacker->tree)->create(),
        'ownParish' => Parish::factory()->for($attacker->tree)->create(),
    ];
}

/**
 * Every route with a record id in the address; another tree's id must be a 404 whatever the body holds.
 * One entry per case: route name, HTTP method, closure building the address parameters, closure building the body.
 * The completeness guard below reads the same list.
 *
 * @return array<string, array{0: string, 1: string, 2: Closure(array<string, mixed>): array<string, mixed>, 3: Closure(array<string, mixed>): array<string, mixed>}>
 */
function ownershipRouteCases(): array
{
    $noBody = fn (array $r): array => [];

    $cases = [];

    $withBodies = [
        ['persons.parents.store', 'post', fn (array $r): array => ['person' => $r['victimPerson']->id], fn (array $r): array => ['role' => 'parent', 'first_name' => 'Anna']],
        ['persons.children.attach', 'post', fn (array $r): array => ['person' => $r['victimPerson']->id], fn (array $r): array => ['child_id' => $r['ownPerson']->id]],
        ['persons.children.store', 'post', fn (array $r): array => ['person' => $r['victimPerson']->id], fn (array $r): array => ['first_name' => 'Anna']],
        ['persons.spouses.attach', 'post', fn (array $r): array => ['person' => $r['victimPerson']->id], fn (array $r): array => ['spouse_id' => $r['ownPerson']->id]],
        ['persons.spouses.store', 'post', fn (array $r): array => ['person' => $r['victimPerson']->id], fn (array $r): array => ['first_name' => 'Anna']],
        ['persons.update', 'put', fn (array $r): array => ['person' => $r['victimPerson']->id], fn (array $r): array => ['first_name' => 'Hacked']],
        ['persons.note.update', 'put', fn (array $r): array => ['person' => $r['victimPerson']->id], fn (array $r): array => ['note' => 'Przejęta']],
        ['persons.photo.store', 'post', fn (array $r): array => ['person' => $r['victimPerson']->id], fn (array $r): array => ['photo' => new UploadedFile(base_path('tests/Fixtures/portrait-small.jpg'), 'portret.jpg', 'image/jpeg', null, true)]],
        ['sources.update', 'put', fn (array $r): array => ['source' => $r['victimSource']->id], fn (array $r): array => ['kind' => 'other', 'name' => 'Przejęte']],
        ['localities.update', 'put', fn (array $r): array => ['locality' => $r['victimLocality']->id], fn (array $r): array => ['name' => 'Przejęta']],
        ['localities.merge', 'post', fn (array $r): array => ['locality' => $r['victimLocality']->id], fn (array $r): array => ['target_id' => $r['ownLocality']->id]],
        ['parishes.update', 'put', fn (array $r): array => ['parish' => $r['victimParish']->id], fn (array $r): array => ['name' => 'Przejęta']],
        ['parishes.merge', 'post', fn (array $r): array => ['parish' => $r['victimParish']->id], fn (array $r): array => ['target_id' => $r['ownParish']->id]],
        ['events.sources.store', 'post', fn (array $r): array => ['event' => $r['victimEvent']->id], fn (array $r): array => ['source_id' => $r['ownSource']->id]],
        ['marriages.update', 'put', fn (array $r): array => ['marriage' => $r['victimMarriage']->id], fn (array $r): array => ['marriage' => ['year' => 1950]]],
    ];

    foreach ($withBodies as [$name, $method, $parameters, $validBody]) {
        $cases["$name / poprawna treść"] = [$name, $method, $parameters, $validBody];
        $cases["$name / pusta treść"] = [$name, $method, $parameters, $noBody];
    }

    $withoutBody = [
        ['tree.show', 'get', fn (array $r): array => ['person' => $r['victimPerson']->id]],
        ['tree.ancestors', 'get', fn (array $r): array => ['person' => $r['victimPerson']->id]],
        ['sources.show', 'get', fn (array $r): array => ['source' => $r['victimSource']->id]],
        ['sources.file', 'get', fn (array $r): array => ['source' => $r['victimSource']->id]],
        ['sources.thumbnail', 'get', fn (array $r): array => ['source' => $r['victimSource']->id]],
        ['tree.root-person.update', 'put', fn (array $r): array => ['person' => $r['victimPerson']->id]],
        ['persons.destroy', 'delete', fn (array $r): array => ['person' => $r['victimPerson']->id]],
        ['persons.photo.show', 'get', fn (array $r): array => ['person' => $r['victimPerson']->id]],
        ['persons.photo.destroy', 'delete', fn (array $r): array => ['person' => $r['victimPerson']->id]],
        ['sources.destroy', 'delete', fn (array $r): array => ['source' => $r['victimSource']->id]],
        ['sources.legacy-entry-number.destroy', 'delete', fn (array $r): array => ['source' => $r['victimSource']->id]],
        ['localities.destroy', 'delete', fn (array $r): array => ['locality' => $r['victimLocality']->id]],
        ['parishes.destroy', 'delete', fn (array $r): array => ['parish' => $r['victimParish']->id]],
        ['parent-child-relations.destroy', 'delete', fn (array $r): array => ['parent_child_relation' => $r['victimRelation']->id]],
        ['marriages.destroy', 'delete', fn (array $r): array => ['marriage' => $r['victimMarriage']->id]],
    ];

    foreach ($withoutBody as [$name, $method, $parameters]) {
        $cases["$name / brak treści"] = [$name, $method, $parameters, $noBody];
    }

    $cases['events.sources.destroy / cudze wydarzenie i źródło atakującego'] = [
        'events.sources.destroy', 'delete',
        fn (array $r): array => ['event' => $r['victimEvent']->id, 'source' => $r['ownSource']->id],
        $noBody,
    ];
    $cases['events.sources.destroy / wydarzenie atakującego i cudze źródło'] = [
        'events.sources.destroy', 'delete',
        fn (array $r): array => ['event' => $r['ownEvent']->id, 'source' => $r['victimSource']->id],
        $noBody,
    ];

    $updateBody = fn (array $r): array => ['entry_number' => '1'];

    $cases['events.sources.update / cudze wydarzenie i źródło atakującego'] = [
        'events.sources.update', 'patch',
        fn (array $r): array => ['event' => $r['victimEvent']->id, 'source' => $r['ownSource']->id],
        $updateBody,
    ];
    $cases['events.sources.update / wydarzenie atakującego i cudze źródło'] = [
        'events.sources.update', 'patch',
        fn (array $r): array => ['event' => $r['ownEvent']->id, 'source' => $r['victimSource']->id],
        $updateBody,
    ];

    return $cases;
}

it('answers 404 and writes nothing when the address holds another tree\'s id', function (string $route, string $method, Closure $parameters, Closure $body) {
    Storage::fake('local');

    $attacker = User::factory()->create();
    $victim = User::factory()->create();
    $records = ownershipRecords($attacker, $victim);

    $before = ownershipSnapshot();

    $this->actingAs($attacker)
        ->{$method}(route($route, $parameters($records)), $body($records))
        ->assertNotFound();

    expect(ownershipSnapshot())->toBe($before);
})->with(ownershipRouteCases());

it('has a case for every controller route that carries an id in the address', function () {
    $routed = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (LaravelRoute $route): bool => str_starts_with(ltrim($route->getActionName(), '\\'), 'App\\Http\\Controllers')
            && str_contains($route->uri(), '{'))
        ->map(fn (LaravelRoute $route): ?string => $route->getName())
        ->unique()
        ->sort()
        ->values()
        ->all();

    $covered = collect(ownershipRouteCases())->pluck(0)->unique()->sort()->values()->all();

    $missing = array_values(array_diff($routed, $covered));
    $surplus = array_values(array_diff($covered, $routed));

    expect([$missing, $surplus])->toBe([[], []], 'missing: '.implode(', ', $missing).'; surplus: '.implode(', ', $surplus));
});

it('answers 404 and writes nothing when ?person= names another tree\'s person on a host page', function (string $route, Closure $parameters) {
    Storage::fake('local');

    $attacker = User::factory()->create();
    $victim = User::factory()->create();
    $records = ownershipRecords($attacker, $victim);

    $before = ownershipSnapshot();

    $this->actingAs($attacker)
        ->get(personPreviewUrl($records['victimPerson'], $route, $parameters($records)))
        ->assertNotFound();

    expect(ownershipSnapshot())->toBe($before);
})->with([
    'tree.index' => ['tree.index', fn (array $r): array => []],
    'tree.show (własna osoba w adresie)' => ['tree.show', fn (array $r): array => ['person' => $r['ownPerson']->id]],
    'tree.ancestors (własna osoba w adresie)' => ['tree.ancestors', fn (array $r): array => ['person' => $r['ownPerson']->id]],
    'sources.index' => ['sources.index', fn (array $r): array => []],
    'localities.index' => ['localities.index', fn (array $r): array => []],
    'sources.index (otwarty panel własnego źródła)' => ['sources.index', fn (array $r): array => ['source' => $r['ownSource']->id]],
]);

it('answers 404 and writes nothing when ?locality= names another tree\'s locality', function () {
    Storage::fake('local');

    $attacker = User::factory()->create();
    $victim = User::factory()->create();
    $records = ownershipRecords($attacker, $victim);

    $before = ownershipSnapshot();

    $this->actingAs($attacker)
        ->get(route('localities.index', ['locality' => $records['victimLocality']->id]))
        ->assertNotFound();

    expect(ownershipSnapshot())->toBe($before);
});

it('answers 404 and writes nothing when ?parish= names another tree\'s parish', function () {
    Storage::fake('local');

    $attacker = User::factory()->create();
    $victim = User::factory()->create();
    $records = ownershipRecords($attacker, $victim);

    $before = ownershipSnapshot();

    $this->actingAs($attacker)
        ->get(route('parishes.index', ['parish' => $records['victimParish']->id]))
        ->assertNotFound();

    expect(ownershipSnapshot())->toBe($before);
});

it('answers 404 and writes nothing when ?source= names another tree\'s source', function () {
    Storage::fake('local');

    $attacker = User::factory()->create();
    $victim = User::factory()->create();
    $records = ownershipRecords($attacker, $victim);

    $before = ownershipSnapshot();

    $this->actingAs($attacker)
        ->get(route('sources.index', ['source' => $records['victimSource']->id]))
        ->assertNotFound();

    expect(ownershipSnapshot())->toBe($before);
});

it('ignores a tree_id sent in the body and creates the locality in the attacker\'s tree', function () {
    $attacker = User::factory()->create();
    $victim = User::factory()->create();
    $victimLocalitiesBefore = $victim->tree->localities()->count();

    $this->actingAs($attacker)
        ->post(route('localities.store'), ['name' => 'Wstrzyknięta', 'tree_id' => $victim->tree->id]);

    $locality = Locality::where('name', 'Wstrzyknięta')->firstOrFail();

    expect($locality->tree_id)->toBe($attacker->tree->id);
    expect($victim->tree->localities()->count())->toBe($victimLocalitiesBefore);
});

it('ignores a tree_id sent in the body and creates the parish in the attacker\'s tree', function (string $route) {
    $attacker = User::factory()->create();
    $victim = User::factory()->create();
    $victimParishesBefore = $victim->tree->parishes()->count();

    $this->actingAs($attacker)
        ->postJson(route($route), ['name' => 'Wstrzyknięta', 'tree_id' => $victim->tree->id]);

    $parish = Parish::where('name', 'Wstrzyknięta')->firstOrFail();

    expect($parish->tree_id)->toBe($attacker->tree->id);
    expect($victim->tree->parishes()->count())->toBe($victimParishesBefore);
})->with(['parishes.store', 'parishes.inline.store']);

it('ignores a tree_id sent in the body and creates the person in the attacker\'s tree', function () {
    $attacker = User::factory()->create();
    $victim = User::factory()->create();
    $victimPeopleBefore = $victim->tree->people()->count();

    $this->actingAs($attacker)
        ->post(route('persons.store'), ['first_name' => 'Wstrzyknięta', 'tree_id' => $victim->tree->id]);

    $person = Person::where('first_name', 'Wstrzyknięta')->firstOrFail();

    expect($person->tree_id)->toBe($attacker->tree->id);
    expect($victim->tree->people()->count())->toBe($victimPeopleBefore);
});

it('ignores a tree_id sent in the body and stores the source and its file under the attacker\'s tree', function () {
    Storage::fake('local');

    $attacker = User::factory()->create();
    $victim = User::factory()->create();
    $victimSourcesBefore = $victim->tree->sources()->count();

    $this->actingAs($attacker)
        ->post(route('sources.store'), [
            'kind' => 'metrical_record',
            'file' => UploadedFile::fake()->image('akt.jpg'),
            'tree_id' => $victim->tree->id,
        ]);

    $source = Source::firstOrFail();

    expect($source->tree_id)->toBe($attacker->tree->id);
    expect($victim->tree->sources()->count())->toBe($victimSourcesBefore);
    expect($source->path)->toStartWith("sources/{$attacker->tree->id}/");
    Storage::disk('local')->assertExists($source->path);
});

it('rejects a residence in another tree\'s or a missing locality with a validation error and writes nothing', function (string $kind) {
    Storage::fake('local');

    $attacker = User::factory()->create();
    $victim = User::factory()->create();
    $records = ownershipRecords($attacker, $victim);

    $localityId = $kind === 'foreign'
        ? Locality::factory()->for($victim->tree)->create()->id
        : 999999;

    $before = ownershipSnapshot();

    $this->actingAs($attacker)
        ->put(route('persons.update', $records['ownPerson']), [
            'first_name' => 'Jan',
            'residences' => [['locality_id' => $localityId]],
        ])
        ->assertSessionHasErrors('residences.0.locality_id');

    expect(ownershipSnapshot())->toBe($before);
})->with(['foreign locality' => 'foreign', 'missing locality' => 'missing']);

it('answers 404 and writes nothing when ?marriage= names another tree\'s marriage on a host page', function (string $route) {
    Storage::fake('local');

    $attacker = User::factory()->create();
    $victim = User::factory()->create();
    $records = ownershipRecords($attacker, $victim);

    $before = ownershipSnapshot();

    $this->actingAs($attacker)
        ->get(marriagePanelUrl($records['victimMarriage'], $route))
        ->assertNotFound();

    expect(ownershipSnapshot())->toBe($before);
})->with([
    'tree.index' => ['tree.index'],
    'persons.index' => ['persons.index'],
    'sources.index' => ['sources.index'],
]);
