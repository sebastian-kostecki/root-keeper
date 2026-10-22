<?php

use App\Models\Locality;
use App\Models\Tree;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function linkEventPlacesMigration(): Migration
{
    static $migration = null;

    return $migration ??= require database_path('migrations/2026_10_06_070850_link_event_places_to_localities.php');
}

beforeEach(function () {
    // The schema is fully migrated: bring back the legacy columns this test's migration works on.
    restoreLegacyColumns();
});

function insertEventWithPlace(int $treeId, ?string $place, string $type = 'birth'): int
{
    return DB::table('events')->insertGetId([
        'tree_id' => $treeId,
        'type' => $type,
        'place' => $place,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * @return list<string>
 */
function treeLocalityNames(Tree $tree): array
{
    return $tree->localities()->pluck('name')->sort()->values()->all();
}

function localityNameOfEvent(int $eventId): ?string
{
    return DB::table('events')
        ->leftJoin('localities', 'localities.id', '=', 'events.locality_id')
        ->where('events.id', $eventId)
        ->value('localities.name');
}

test('up links a text differing only in case and spaces to the existing locality without seeding a new one', function () {
    linkEventPlacesMigration()->down();

    $tree = Tree::factory()->create();
    $locality = Locality::factory()->for($tree)->create(['name' => 'Kraków']);
    $first = insertEventWithPlace($tree->id, ' KRAKÓW ');
    $second = insertEventWithPlace($tree->id, 'kraków');

    linkEventPlacesMigration()->up();

    expect(DB::table('events')->whereIn('id', [$first, $second])->pluck('locality_id')->all())->toBe([$locality->id, $locality->id])
        ->and(treeLocalityNames($tree))->toBe(['Kraków']);
});

test('up seeds a locality named by the most used variant for a text without a locality and links it', function () {
    linkEventPlacesMigration()->down();

    $tree = Tree::factory()->create();
    $ids = [
        insertEventWithPlace($tree->id, 'lipnica'),
        insertEventWithPlace($tree->id, 'Lipnica'),
        insertEventWithPlace($tree->id, ' Lipnica '),
    ];

    linkEventPlacesMigration()->up();

    expect(treeLocalityNames($tree))->toBe(['Lipnica'])
        ->and(array_map(localityNameOfEvent(...), $ids))->toBe(['Lipnica', 'Lipnica', 'Lipnica']);
});

test('up links the same text in two trees to each tree own locality', function () {
    linkEventPlacesMigration()->down();

    $treeA = Tree::factory()->create();
    $treeB = Tree::factory()->create();
    $localityA = Locality::factory()->for($treeA)->create(['name' => 'Kraków']);
    $eventA = insertEventWithPlace($treeA->id, 'Kraków');
    $eventB = insertEventWithPlace($treeB->id, 'Kraków');

    linkEventPlacesMigration()->up();

    $localityB = $treeB->localities()->sole();

    expect(DB::table('events')->where('id', $eventA)->value('locality_id'))->toBe($localityA->id)
        ->and(DB::table('events')->where('id', $eventB)->value('locality_id'))->toBe($localityB->id)
        ->and($localityB->id)->not->toBe($localityA->id);
});

test('up leaves null and blank texts without a locality and seeds nothing for them', function () {
    linkEventPlacesMigration()->down();

    $tree = Tree::factory()->create();
    $ids = [
        insertEventWithPlace($tree->id, null),
        insertEventWithPlace($tree->id, ''),
        insertEventWithPlace($tree->id, '  '),
    ];

    linkEventPlacesMigration()->up();

    expect(DB::table('events')->whereIn('id', $ids)->pluck('locality_id')->all())->toBe([null, null, null])
        ->and(Locality::query()->count())->toBe(0);
});

test('up links birth, death and marriage events and leaves events.place byte for byte unchanged', function () {
    linkEventPlacesMigration()->down();

    $tree = Tree::factory()->create();
    $texts = ['Kraków', ' Lwów ', 'lwów', null, '', '  '];
    $types = ['birth', 'death', 'marriage', 'birth', 'death', 'marriage'];

    $ids = [];
    foreach ($texts as $index => $text) {
        $ids[] = insertEventWithPlace($tree->id, $text, $types[$index]);
    }

    linkEventPlacesMigration()->up();

    expect(DB::table('events')->whereIn('id', $ids)->orderBy('id')->pluck('place')->all())->toBe($texts)
        ->and(array_map(localityNameOfEvent(...), array_slice($ids, 0, 3)))->toBe(['Kraków', 'Lwów', 'Lwów']);
});

test('down drops the column and burial events and keeps the place texts', function () {
    linkEventPlacesMigration()->down();

    $tree = Tree::factory()->create();
    $birth = insertEventWithPlace($tree->id, ' Kraków ');
    linkEventPlacesMigration()->up();
    $burial = insertEventWithPlace($tree->id, null, 'burial');

    linkEventPlacesMigration()->down();

    expect(Schema::hasColumn('events', 'locality_id'))->toBeFalse()
        ->and(DB::table('events')->where('id', $burial)->exists())->toBeFalse()
        ->and(DB::table('events')->where('id', $birth)->value('place'))->toBe(' Kraków ');

    linkEventPlacesMigration()->up();
});
