<?php

use App\Models\Tree;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function addParishAndArchiveMigration(): Migration
{
    static $migration = null;

    return $migration ??= require database_path('migrations/2026_10_05_114945_add_parish_and_archive_to_sources_table.php');
}

beforeEach(function () {
    // The schema is fully migrated: bring back the legacy columns this test's migration works on.
    restoreLegacyColumns();
});

function insertSourceWithProvenance(int $treeId, ?string $provenance): int
{
    return DB::table('sources')->insertGetId([
        'tree_id' => $treeId,
        'kind' => 'metrical_record',
        'original_name' => 'akt.jpg',
        'path' => "sources/{$treeId}/akt.jpg",
        'mime_type' => 'image/jpeg',
        'size' => 1,
        'provenance' => $provenance,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * Put the schema back to its pre-migration shape and insert provenance values in two trees.
 *
 * @return list<int>
 */
function arrangeProvenanceRows(): array
{
    addParishAndArchiveMigration()->down();

    $treeA = Tree::factory()->create();
    $treeB = Tree::factory()->create();

    return [
        insertSourceWithProvenance($treeA->id, 'Archiwum Diecezjalne w Tarnowie, sygnatura 1234'),
        insertSourceWithProvenance($treeA->id, null),
        insertSourceWithProvenance($treeA->id, ''),
        insertSourceWithProvenance($treeA->id, '   '),
        insertSourceWithProvenance($treeB->id, ' Archiwum Państwowe w Krakowie '),
    ];
}

/**
 * @param  list<int>  $ids
 * @return array<int, array{archive: ?string, provenance: ?string}>
 */
function archiveAndProvenance(array $ids): array
{
    return DB::table('sources')->whereIn('id', $ids)->orderBy('id')->get(['id', 'archive', 'provenance'])
        ->mapWithKeys(fn (object $row): array => [$row->id => ['archive' => $row->archive, 'provenance' => $row->provenance]])
        ->all();
}

test('up copies a non-empty provenance into the archive byte for byte and leaves empty ones null', function () {
    $ids = arrangeProvenanceRows();

    addParishAndArchiveMigration()->up();

    $rows = archiveAndProvenance($ids);

    expect($rows[$ids[0]]['archive'])->toBe('Archiwum Diecezjalne w Tarnowie, sygnatura 1234')
        ->and($rows[$ids[1]]['archive'])->toBeNull()
        ->and($rows[$ids[2]]['archive'])->toBeNull()
        ->and($rows[$ids[3]]['archive'])->toBeNull()
        ->and($rows[$ids[4]]['archive'])->toBe(' Archiwum Państwowe w Krakowie ');
});

test('up leaves the provenance of every row untouched', function () {
    $ids = arrangeProvenanceRows();

    addParishAndArchiveMigration()->up();

    expect(array_column(archiveAndProvenance($ids), 'provenance'))->toBe([
        'Archiwum Diecezjalne w Tarnowie, sygnatura 1234', null, '', '   ', ' Archiwum Państwowe w Krakowie ',
    ]);
});

test('running the copy again changes no data', function () {
    $ids = arrangeProvenanceRows();

    addParishAndArchiveMigration()->up();
    $before = archiveAndProvenance($ids);

    DB::table('sources')->where('id', $ids[0])->update(['archive' => 'Ręcznie poprawione']);
    $before[$ids[0]]['archive'] = 'Ręcznie poprawione';

    DB::statement("UPDATE sources SET archive = provenance WHERE archive IS NULL AND provenance IS NOT NULL AND btrim(provenance) <> ''");

    expect(archiveAndProvenance($ids))->toBe($before);
});

test('down drops the new columns and keeps the provenance', function () {
    $ids = arrangeProvenanceRows();

    addParishAndArchiveMigration()->up();
    addParishAndArchiveMigration()->down();

    expect(Schema::hasColumn('sources', 'archive'))->toBeFalse()
        ->and(Schema::hasColumn('sources', 'parish_id'))->toBeFalse()
        ->and(DB::table('sources')->whereIn('id', $ids)->orderBy('id')->pluck('provenance')->all())->toBe([
            'Archiwum Diecezjalne w Tarnowie, sygnatura 1234', null, '', '   ', ' Archiwum Państwowe w Krakowie ',
        ]);

    addParishAndArchiveMigration()->up();
});
