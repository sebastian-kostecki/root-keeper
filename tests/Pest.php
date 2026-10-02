<?php

use App\Enums\EventType;
use App\Enums\ParticipantRole;
use App\Models\Event;
use App\Models\ParentChildRelation;
use App\Models\Person;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\BackupTestCase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(BackupTestCase::class)
    ->use(DatabaseTruncation::class)
    ->in('Backup');

/**
 * Attach a birth event with the given partial date to a person.
 */
function attachBirth(Person $person, ?int $year = null, ?int $month = null, ?int $day = null): void
{
    $event = Event::factory()->for($person->tree)->birth()->create([
        'date_year' => $year,
        'date_month' => $month,
        'date_day' => $day,
    ]);

    $event->participants()->attach($person, ['role' => ParticipantRole::Subject]);
}

/**
 * Attach a marriage event with the given partial date to the two people.
 */
function attachMarriage(Person $person, Person $spouse, ?int $year = null, ?int $month = null, ?int $day = null, ?string $place = null): Event
{
    $event = Event::factory()->for($person->tree)->create([
        'type' => EventType::Marriage,
        'date_year' => $year,
        'date_month' => $month,
        'date_day' => $day,
        'date_is_approximate' => false,
        'place' => $place,
    ]);

    $event->participants()->attach([
        $person->id => ['role' => ParticipantRole::Spouse],
        $spouse->id => ['role' => ParticipantRole::Spouse],
    ]);

    return $event;
}

/**
 * Make the parent a parent of the child in the parent's tree.
 */
function linkParent(Person $parent, Person $child): void
{
    ParentChildRelation::factory()->create([
        'tree_id' => $parent->tree_id,
        'parent_id' => $parent->id,
        'child_id' => $child->id,
    ]);
}

/**
 * Attach a death event with the given year to a person.
 */
function attachDeath(Person $person, ?int $year, bool $approximate = false): void
{
    $event = Event::factory()->for($person->tree)->death()->create([
        'date_year' => $year,
        'date_month' => null,
        'date_day' => null,
        'date_is_approximate' => $approximate,
    ]);

    $event->participants()->attach($person, ['role' => ParticipantRole::Subject]);
}

/**
 * Build a host-page URL that opens the preview modal of the given person.
 *
 * @param  array<int|string, mixed>  $parameters  Route parameters and query values of the host page.
 */
function personPreviewUrl(Person $person, string $route = 'persons.index', array $parameters = []): string
{
    $url = route($route, $parameters);

    return $url.(str_contains($url, '?') ? '&' : '?').http_build_query(['person' => $person->id]);
}

/**
 * Snapshot of every durable table (row count and content hash) and of the `local` disk,
 * so a test can prove a refused request wrote nothing anywhere. Call `Storage::fake('local')` first.
 *
 * @return array{tables: array<string, array{rows: int, digest: string}>, files: list<string>}
 */
function ownershipSnapshot(): array
{
    $tables = DB::table('information_schema.tables')
        ->where('table_schema', 'public')
        ->where('table_type', 'BASE TABLE')
        ->where('table_name', '!=', 'migrations')
        ->orderBy('table_name')
        ->pluck('table_name')
        ->all();

    $digests = [];

    foreach ($tables as $table) {
        $row = DB::selectOne(
            "select count(*) as rows, md5(coalesce(string_agg(t::text, '|' order by t::text), '')) as digest from \"{$table}\" t"
        );

        $digests[$table] = ['rows' => (int) $row->rows, 'digest' => (string) $row->digest];
    }

    $files = Storage::disk('local')->allFiles();
    sort($files);

    return ['tables' => $digests, 'files' => $files];
}
