<?php

use App\Models\Tree;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

function dateQualifierMigration(): Migration
{
    static $migration = null;

    return $migration ??= require database_path('migrations/2026_10_23_123108_add_date_qualifier_to_events_table.php');
}

/**
 * Put the schema back to its pre-qualifier shape and insert one event per legacy case.
 *
 * @return array<string, int> Event ids keyed by case.
 */
function legacyDateEvents(Tree $tree): array
{
    dateQualifierMigration()->down();

    $rows = [
        'exact' => ['type' => 'birth', 'date_year' => 1850, 'date_month' => 5, 'date_day' => 12, 'date_is_approximate' => false],
        'approximate_year' => ['type' => 'birth', 'date_year' => 1799, 'date_month' => null, 'date_day' => null, 'date_is_approximate' => true],
        'approximate_month' => ['type' => 'death', 'date_year' => 1870, 'date_month' => 3, 'date_day' => null, 'date_is_approximate' => true],
        'undated' => ['type' => 'death', 'date_year' => null, 'date_month' => null, 'date_day' => null, 'date_is_approximate' => false],
        'marriage' => ['type' => 'marriage', 'date_year' => 1830, 'date_month' => null, 'date_day' => null, 'date_is_approximate' => true],
        'burial' => ['type' => 'burial', 'date_year' => null, 'date_month' => null, 'date_day' => null, 'date_is_approximate' => false],
    ];

    return array_map(fn (array $row): int => DB::table('events')->insertGetId([
        ...$row,
        'tree_id' => $tree->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]), $rows);
}

test('the migration copies the approximate flag into the qualifier and keeps every date value', function () {
    $ids = legacyDateEvents(User::factory()->create()->tree);

    dateQualifierMigration()->up();

    $qualifiers = [
        'exact' => 'exact',
        'approximate_year' => 'approximate',
        'approximate_month' => 'approximate',
        'undated' => 'exact',
        'marriage' => 'approximate',
        'burial' => 'exact',
    ];

    foreach ($qualifiers as $case => $qualifier) {
        expect(DB::table('events')->where('id', $ids[$case])->value('date_qualifier'))->toBe($qualifier, $case);
    }

    $exact = DB::table('events')->where('id', $ids['exact'])->first();
    expect([$exact->date_year, $exact->date_month, $exact->date_day])->toBe([1850, 5, 12]);

    $month = DB::table('events')->where('id', $ids['approximate_month'])->first();
    expect([$month->date_year, $month->date_month, $month->date_day])->toBe([1870, 3, null]);

    expect(DB::table('events')->whereIn('id', $ids)->whereNotNull('date_until_year')->count())->toBe(0);
});

test('rolling back and migrating again keeps the approximate dates', function () {
    $ids = legacyDateEvents(User::factory()->create()->tree);

    dateQualifierMigration()->up();
    dateQualifierMigration()->down();
    dateQualifierMigration()->up();

    expect(DB::table('events')->where('id', $ids['approximate_year'])->value('date_qualifier'))->toBe('approximate');
    expect(DB::table('events')->where('id', $ids['exact'])->value('date_qualifier'))->toBe('exact');
    expect((bool) DB::table('events')->where('id', $ids['approximate_year'])->value('date_is_approximate'))->toBeTrue();
});
