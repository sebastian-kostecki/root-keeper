<?php

use App\Models\Locality;
use App\Models\Person;
use App\Models\Tree;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

function seedLocalitiesMigration(): Migration
{
    static $migration = null;

    return $migration ??= require database_path('migrations/2026_10_05_081632_seed_localities_from_place_texts.php');
}

beforeEach(function () {
    // The schema is fully migrated: bring back the legacy columns this test's migration works on.
    restoreLegacyColumns();
});

function insertPlaceText(int $treeId, ?string $place): void
{
    DB::table('events')->insert([
        'tree_id' => $treeId,
        'type' => 'birth',
        'place' => $place,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function insertResidenceText(int $treeId, ?string $residence): void
{
    DB::table('people')->insert([
        'tree_id' => $treeId,
        'residence' => $residence,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * @return list<string>
 */
function localityNames(Tree $tree): array
{
    return $tree->localities()->orderBy('name', 'asc')->pluck('name')->sort()->values()->all();
}

/**
 * @return array{treeA: Tree, treeB: Tree}
 */
function arrangeLocalityTexts(): array
{
    $treeA = Tree::factory()->create();
    $treeB = Tree::factory()->create();

    foreach (['Kraków', 'Kraków', ' Lwów ', '', null, 'Krakow', 'lipnica'] as $place) {
        insertPlaceText($treeA->id, $place);
    }

    foreach (['kraków', 'Lipnica'] as $residence) {
        insertResidenceText($treeA->id, $residence);
    }

    insertPlaceText($treeB->id, 'Kraków');

    return ['treeA' => $treeA, 'treeB' => $treeB];
}

/**
 * @return array{events: list<?string>, residences: list<?string>}
 */
function placeTexts(): array
{
    return [
        'events' => DB::table('events')->orderBy('id')->pluck('place')->all(),
        'residences' => DB::table('people')->orderBy('id')->pluck('residence')->all(),
    ];
}

test('seed creates one locality per group of place texts, named by the most used variant', function () {
    ['treeA' => $treeA, 'treeB' => $treeB] = arrangeLocalityTexts();

    seedLocalitiesMigration()->up();

    expect(localityNames($treeA))->toBe(['Krakow', 'Kraków', 'Lipnica', 'Lwów'])
        ->and(localityNames($treeB))->toBe(['Kraków'])
        ->and(Locality::query()->whereNotNull('description')->count())->toBe(0);
});

test('seed leaves the text columns byte for byte unchanged', function () {
    arrangeLocalityTexts();

    $before = placeTexts();

    seedLocalitiesMigration()->up();

    expect(placeTexts())->toBe($before)
        ->and($before['events'])->toContain(' Lwów ');
});

test('seed does not add rows or fail when run again', function () {
    arrangeLocalityTexts();

    seedLocalitiesMigration()->up();
    $count = Locality::query()->count();

    seedLocalitiesMigration()->up();

    expect($count)->toBe(5)
        ->and(Locality::query()->count())->toBe(5);
});

test('seed skips a group the tree already has a locality for and leaves it untouched', function () {
    ['treeB' => $treeB] = arrangeLocalityTexts();

    $manual = Locality::factory()->for($treeB)->create(['name' => 'KRAKÓW', 'description' => 'Ręczny wpis']);

    seedLocalitiesMigration()->up();

    expect(localityNames($treeB))->toBe(['KRAKÓW'])
        ->and($manual->fresh()->name)->toBe('KRAKÓW')
        ->and($manual->fresh()->description)->toBe('Ręczny wpis');
});

test('seed ignores a tree without place texts', function () {
    Person::factory()->for(Tree::factory())->create(['residence' => null]);

    seedLocalitiesMigration()->up();

    expect(Locality::query()->count())->toBe(0);
});

test('down leaves the localities unchanged', function () {
    arrangeLocalityTexts();

    seedLocalitiesMigration()->up();
    seedLocalitiesMigration()->down();

    expect(Locality::query()->count())->toBe(5);
});
