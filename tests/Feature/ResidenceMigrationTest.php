<?php

use App\Models\Locality;
use App\Models\Tree;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function createResidencesMigration(): Migration
{
    static $migration = null;

    return $migration ??= require database_path('migrations/2026_10_06_110402_create_residences_from_residence_texts.php');
}

function insertPersonWithResidence(int $treeId, ?string $residence): int
{
    return DB::table('people')->insertGetId([
        'tree_id' => $treeId,
        'first_name' => 'Jan',
        'residence' => $residence,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * @return list<string>
 */
function residenceTreeLocalityNames(Tree $tree): array
{
    return $tree->localities()->pluck('name')->sort()->values()->all();
}

/**
 * @return list<string>
 */
function localityNamesOfPersonResidences(int $personId): array
{
    return DB::table('residences')
        ->join('localities', 'localities.id', '=', 'residences.locality_id')
        ->where('residences.person_id', $personId)
        ->orderBy('residences.id')
        ->pluck('localities.name')
        ->all();
}

test('up links a text differing only in case and spaces to the existing locality without seeding a new one', function () {
    createResidencesMigration()->down();

    $tree = Tree::factory()->create();
    $locality = Locality::factory()->for($tree)->create(['name' => 'Kraków']);
    $first = insertPersonWithResidence($tree->id, ' KRAKÓW ');
    $second = insertPersonWithResidence($tree->id, 'kraków');

    createResidencesMigration()->up();

    expect(DB::table('residences')->whereIn('person_id', [$first, $second])->orderBy('person_id')->pluck('locality_id')->all())->toBe([$locality->id, $locality->id])
        ->and(residenceTreeLocalityNames($tree))->toBe(['Kraków']);
});

test('up seeds a locality named by the most used variant for a text without a locality and links it', function () {
    createResidencesMigration()->down();

    $tree = Tree::factory()->create();
    $ids = [
        insertPersonWithResidence($tree->id, 'lipnica'),
        insertPersonWithResidence($tree->id, 'Lipnica'),
        insertPersonWithResidence($tree->id, ' Lipnica '),
    ];

    createResidencesMigration()->up();

    expect(residenceTreeLocalityNames($tree))->toBe(['Lipnica'])
        ->and(array_map(localityNamesOfPersonResidences(...), $ids))->toBe([['Lipnica'], ['Lipnica'], ['Lipnica']]);
});

test('up links the same text in two trees to each tree own locality', function () {
    createResidencesMigration()->down();

    $treeA = Tree::factory()->create();
    $treeB = Tree::factory()->create();
    $localityA = Locality::factory()->for($treeA)->create(['name' => 'Kraków']);
    $personA = insertPersonWithResidence($treeA->id, 'Kraków');
    $personB = insertPersonWithResidence($treeB->id, 'Kraków');

    createResidencesMigration()->up();

    $localityB = $treeB->localities()->sole();

    expect(DB::table('residences')->where('person_id', $personA)->value('locality_id'))->toBe($localityA->id)
        ->and(DB::table('residences')->where('person_id', $personB)->value('locality_id'))->toBe($localityB->id)
        ->and($localityB->id)->not->toBe($localityA->id)
        ->and(DB::table('residences')->where('person_id', $personB)->value('tree_id'))->toBe($treeB->id);
});

test('up creates nothing for null and blank texts and seeds no locality for them', function () {
    createResidencesMigration()->down();

    $tree = Tree::factory()->create();
    insertPersonWithResidence($tree->id, null);
    insertPersonWithResidence($tree->id, '');
    insertPersonWithResidence($tree->id, '  ');

    createResidencesMigration()->up();

    expect(DB::table('residences')->count())->toBe(0)
        ->and(Locality::query()->count())->toBe(0);
});

test('up creates exactly one residence without years per person and leaves people.residence byte for byte unchanged', function () {
    createResidencesMigration()->down();

    $tree = Tree::factory()->create();
    $texts = ['Kraków', ' Lwów ', 'lwów', null, '', '  '];

    $ids = [];
    foreach ($texts as $text) {
        $ids[] = insertPersonWithResidence($tree->id, $text);
    }

    createResidencesMigration()->up();

    expect(DB::table('people')->whereIn('id', $ids)->orderBy('id')->pluck('residence')->all())->toBe($texts)
        ->and(DB::table('residences')->count())->toBe(3)
        ->and(DB::table('residences')->whereIn('person_id', array_slice($ids, 0, 3))->selectRaw('person_id, count(*) as total')->groupBy('person_id')->pluck('total')->all())->each->toBe(1)
        ->and(DB::table('residences')->whereNotNull('year_from')->orWhereNotNull('year_to')->count())->toBe(0)
        ->and(array_map(localityNamesOfPersonResidences(...), array_slice($ids, 0, 3)))->toBe([['Kraków'], ['Lwów'], ['Lwów']]);
});

test('down drops the table, keeps the seeded localities and the residence texts', function () {
    createResidencesMigration()->down();

    $tree = Tree::factory()->create();
    $person = insertPersonWithResidence($tree->id, ' Kraków ');
    createResidencesMigration()->up();

    createResidencesMigration()->down();

    expect(Schema::hasTable('residences'))->toBeFalse()
        ->and(DB::table('people')->where('id', $person)->value('residence'))->toBe(' Kraków ')
        ->and(residenceTreeLocalityNames($tree))->toBe(['Kraków']);

    createResidencesMigration()->up();
});
