<?php

use App\Models\Event;
use App\Models\Source;
use App\Models\Tree;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

function moveLegacyEntryNumbersMigration(): Migration
{
    static $migration = null;

    return $migration ??= require database_path('migrations/2026_10_22_124121_move_legacy_entry_numbers_to_links.php');
}

beforeEach(function () {
    // The schema is fully migrated: bring back `sources.entry_number` (the test transaction undoes it).
    restoreLegacyColumns();
});

/**
 * A source in the pre-migration shape: the old entry number on the source, linked to facts with the given link numbers.
 *
 * @param  list<?string>  $linkNumbers
 */
function sourceWithLegacyEntryNumber(?string $entryNumber, array $linkNumbers, bool $gravestone = false): Source
{
    $tree = Tree::factory()->create();
    $factory = Source::factory()->for($tree);
    $source = ($gravestone ? $factory->gravestone() : $factory)->create();

    DB::table('sources')->where('id', $source->id)->update(['entry_number' => $entryNumber]);

    foreach ($linkNumbers as $linkNumber) {
        Event::factory()->for($tree)->create()->sources()->attach($source, ['entry_number' => $linkNumber]);
    }

    return $source;
}

/**
 * @return list<?string>
 */
function linkEntryNumbers(Source $source): array
{
    return DB::table('event_source')->where('source_id', $source->id)->orderBy('id')->pluck('entry_number')->all();
}

function legacyEntryNumber(Source $source): ?string
{
    return DB::table('sources')->where('id', $source->id)->value('entry_number');
}

test('the old number moves to the only link without a number and stays on the source', function () {
    $empty = sourceWithLegacyEntryNumber(' 38 ', [null]);
    $blank = sourceWithLegacyEntryNumber('145', ['  ']);

    moveLegacyEntryNumbersMigration()->up();

    expect(linkEntryNumbers($empty))->toBe(['38']);
    expect(linkEntryNumbers($blank))->toBe(['145']);
    expect(legacyEntryNumber($empty))->toBe(' 38 ');
    expect(legacyEntryNumber($blank))->toBe('145');
});

test('a link that already has a number keeps it', function () {
    $source = sourceWithLegacyEntryNumber('38', ['12']);

    moveLegacyEntryNumbersMigration()->up();

    expect(linkEntryNumbers($source))->toBe(['12']);
    expect(legacyEntryNumber($source))->toBe('38');
});

test('a source with two links keeps the number on the source only', function () {
    $source = sourceWithLegacyEntryNumber('38', [null, null]);

    moveLegacyEntryNumbersMigration()->up();

    expect(linkEntryNumbers($source))->toBe([null, null]);
    expect(legacyEntryNumber($source))->toBe('38');
});

test('a source without an old number and a gravestone stay unchanged', function () {
    $missing = sourceWithLegacyEntryNumber(null, [null]);
    $blank = sourceWithLegacyEntryNumber('  ', [null]);
    $gravestone = sourceWithLegacyEntryNumber('38', [null], gravestone: true);

    moveLegacyEntryNumbersMigration()->up();

    expect(linkEntryNumbers($missing))->toBe([null]);
    expect(linkEntryNumbers($blank))->toBe([null]);
    expect(linkEntryNumbers($gravestone))->toBe([null]);
    expect(legacyEntryNumber($gravestone))->toBe('38');
});

test('rolling back clears the moved number and keeps the others', function () {
    $moved = sourceWithLegacyEntryNumber('38', [null]);
    $kept = sourceWithLegacyEntryNumber('38', ['12']);

    moveLegacyEntryNumbersMigration()->up();
    moveLegacyEntryNumbersMigration()->down();

    expect(linkEntryNumbers($moved))->toBe([null]);
    expect(linkEntryNumbers($kept))->toBe(['12']);
    expect(legacyEntryNumber($moved))->toBe('38');
});
