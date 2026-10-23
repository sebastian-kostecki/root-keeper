<?php

use App\Models\Tree;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function addEntryNumberToEventSourceMigration(): Migration
{
    static $migration = null;

    return $migration ??= require database_path('migrations/2026_10_05_135710_add_entry_number_to_event_source_table.php');
}

beforeEach(function () {
    // The schema is fully migrated: bring back the legacy columns this test's migration works on.
    restoreLegacyColumns();
});

/**
 * Put the schema back to its pre-migration shape and insert links (and a source with the old entry number).
 *
 * @return array{links: list<array{id: int, event_id: int, source_id: int}>, source_id: int}
 */
function arrangeLinksWithoutEntryNumber(): array
{
    addEntryNumberToEventSourceMigration()->down();

    $tree = Tree::factory()->create();
    $source = fn (?string $entryNumber): int => DB::table('sources')->insertGetId([
        'tree_id' => $tree->id,
        'kind' => 'metrical_record',
        'original_name' => 'akt.jpg',
        'path' => "sources/{$tree->id}/akt.jpg",
        'mime_type' => 'image/jpeg',
        'size' => 1,
        'entry_number' => $entryNumber,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $event = fn (): int => DB::table('events')->insertGetId([
        'tree_id' => $tree->id,
        'type' => 'birth',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $sourceWithNumber = $source('47');
    $otherSource = $source(null);
    $links = [];

    foreach ([[$event(), $sourceWithNumber], [$event(), $sourceWithNumber], [$event(), $otherSource]] as [$eventId, $sourceId]) {
        $id = DB::table('event_source')->insertGetId([
            'event_id' => $eventId,
            'source_id' => $sourceId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $links[] = ['id' => $id, 'event_id' => $eventId, 'source_id' => $sourceId];
    }

    return ['links' => $links, 'source_id' => $sourceWithNumber];
}

/**
 * @return list<array{id: int, event_id: int, source_id: int}>
 */
function eventSourceLinks(): array
{
    return DB::table('event_source')->orderBy('id')->get(['id', 'event_id', 'source_id'])
        ->map(fn (object $row): array => ['id' => $row->id, 'event_id' => $row->event_id, 'source_id' => $row->source_id])
        ->all();
}

test('up keeps every link and leaves the new entry number empty', function () {
    $arranged = arrangeLinksWithoutEntryNumber();

    addEntryNumberToEventSourceMigration()->up();

    expect(eventSourceLinks())->toBe($arranged['links']);
    expect(DB::table('event_source')->whereNotNull('entry_number')->count())->toBe(0);
    expect(DB::table('sources')->where('id', $arranged['source_id'])->value('entry_number'))->toBe('47');
});

test('down after up keeps every link and the sources entry number', function () {
    $arranged = arrangeLinksWithoutEntryNumber();

    addEntryNumberToEventSourceMigration()->up();
    addEntryNumberToEventSourceMigration()->down();

    expect(Schema::hasColumn('event_source', 'entry_number'))->toBeFalse();
    expect(eventSourceLinks())->toBe($arranged['links']);
    expect(DB::table('sources')->where('id', $arranged['source_id'])->value('entry_number'))->toBe('47');

    addEntryNumberToEventSourceMigration()->up();
});
