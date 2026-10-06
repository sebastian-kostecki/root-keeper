<?php

use App\Enums\EventType;
use App\Enums\ParticipantRole;
use App\Models\Event;
use App\Models\Locality;
use App\Models\ParentChildRelation;
use App\Models\Person;
use App\Models\Source;
use App\Models\User;
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
function attachMarriage(Person $person, Person $spouse, ?int $year = null, ?int $month = null, ?int $day = null, ?Locality $locality = null): Event
{
    $event = Event::factory()->for($person->tree)->create([
        'type' => EventType::Marriage,
        'date_year' => $year,
        'date_month' => $month,
        'date_day' => $day,
        'date_is_approximate' => false,
        'locality_id' => $locality?->id,
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
 * Build a host-page URL that opens the panel of the given person.
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

/**
 * Dump of every row of every `public` table (except `migrations`) and of the `local` disk, to feed `rowDiff()`.
 * Rows are keyed by `id`; tables without an `id` column are keyed by the canonical content of the row.
 * Call `Storage::fake('local')` first.
 *
 * @return array{tables: array<string, array<int|string, array<string, mixed>>>, files: list<string>}
 */
function databaseRows(): array
{
    $tables = DB::table('information_schema.tables')
        ->where('table_schema', 'public')
        ->where('table_type', 'BASE TABLE')
        ->where('table_name', '!=', 'migrations')
        ->orderBy('table_name')
        ->pluck('table_name')
        ->all();

    $dump = [];

    foreach ($tables as $table) {
        $dump[$table] = [];

        foreach (DB::select("select to_jsonb(t) as row from \"{$table}\" t") as $record) {
            /** @var array<string, mixed> $row */
            $row = json_decode($record->row, true, flags: JSON_THROW_ON_ERROR);

            if (array_key_exists('id', $row)) {
                $key = $row['id'];
            } else {
                ksort($row);
                $key = json_encode($row, JSON_THROW_ON_ERROR);
            }

            $dump[$table][$key] = $row;
        }

        ksort($dump[$table]);
    }

    $files = Storage::disk('local')->allFiles();
    sort($files);

    return ['tables' => $dump, 'files' => $files];
}

/**
 * What changed between two `databaseRows()` dumps. Tables with no difference are omitted, so the
 * key list of `tables` alone proves "nothing else changed". Rows are ordered by `id` (or content key).
 *
 * @param  array{tables: array<string, array<int|string, array<string, mixed>>>, files: list<string>}  $before
 * @param  array{tables: array<string, array<int|string, array<string, mixed>>>, files: list<string>}  $after
 * @return array{tables: array<string, array{removed: list<array<string, mixed>>, added: list<array<string, mixed>>, changed: list<array{before: array<string, mixed>, after: array<string, mixed>}>}>, files: array{removed: list<string>, added: list<string>}}
 */
function rowDiff(array $before, array $after): array
{
    $tableNames = array_unique([...array_keys($before['tables']), ...array_keys($after['tables'])]);
    sort($tableNames);

    $tables = [];

    foreach ($tableNames as $table) {
        $rowsBefore = $before['tables'][$table] ?? [];
        $rowsAfter = $after['tables'][$table] ?? [];

        $keys = array_unique([...array_keys($rowsBefore), ...array_keys($rowsAfter)]);
        sort($keys);

        $removed = [];
        $added = [];
        $changed = [];

        foreach ($keys as $key) {
            if (! array_key_exists($key, $rowsAfter)) {
                $removed[] = $rowsBefore[$key];
            } elseif (! array_key_exists($key, $rowsBefore)) {
                $added[] = $rowsAfter[$key];
            } elseif ($rowsBefore[$key] != $rowsAfter[$key]) {
                $changed[] = ['before' => $rowsBefore[$key], 'after' => $rowsAfter[$key]];
            }
        }

        if ($removed !== [] || $added !== [] || $changed !== []) {
            $tables[$table] = ['removed' => $removed, 'added' => $added, 'changed' => $changed];
        }
    }

    return [
        'tables' => $tables,
        'files' => [
            'removed' => array_values(array_diff($before['files'], $after['files'])),
            'added' => array_values(array_diff($after['files'], $before['files'])),
        ],
    ];
}

/**
 * Build one family in the user's tree, with several marriages, a co-parent, shared sources and bystanders,
 * and put the files of every source (original and thumbnail) on the `local` disk. Call `Storage::fake('local')` first.
 *
 * Returned records:
 * - people: `focus` (male, tree proband), `focusFather`, `focusMother` (his parents, not married), `wifeA`, `wifeB`
 *   (no parents), `coParent` (no marriage), `childA` (focus+wifeA), `childB` (focus+wifeB), `childC` (focus+coParent),
 *   `childUnknown` (only focus), `bystander`, `bystanderSpouse` (married, with `bystanderChild`), `loneMan`, `loneWoman`
 *   (no relations).
 * - events: `focusBirth`, `focusDeath`, `marriageA` (1880), `marriageB` (1900), `wifeABirth`, `bystanderBirth`,
 *   `bystanderMarriage`.
 * - sources: `sourceShared` (focusBirth, marriageA, bystanderBirth), `sourceOther` (marriageA, wifeABirth),
 *   `sourceBystander` (bystanderMarriage only).
 *
 * @return array<string, Person|Event|Source>
 */
function familyScene(User $user): array
{
    $tree = $user->tree;

    $person = fn (string $gender): Person => Person::factory()->for($tree)->{$gender}()->create();

    $focus = $person('male');
    $tree->update(['root_person_id' => $focus->id]);

    $focusFather = $person('male');
    $focusMother = $person('female');
    $wifeA = $person('female');
    $wifeB = $person('female');
    $coParent = $person('female');
    $childA = $person('male');
    $childB = $person('female');
    $childC = $person('male');
    $childUnknown = $person('female');
    $bystander = $person('male');
    $bystanderSpouse = $person('female');
    $bystanderChild = $person('male');
    $loneMan = $person('male');
    $loneWoman = $person('female');

    attachBirth($focus, 1850, 3, 14);
    attachDeath($focus, 1920);
    attachBirth($wifeA, 1855, 6, 2);
    attachBirth($bystander, 1860, 1, 9);

    $marriageA = attachMarriage($focus, $wifeA, 1880);
    $marriageB = attachMarriage($focus, $wifeB, 1900);
    $bystanderMarriage = attachMarriage($bystander, $bystanderSpouse, 1885);

    foreach ([[$focusFather, $focus], [$focusMother, $focus],
        [$focus, $childA], [$wifeA, $childA],
        [$focus, $childB], [$wifeB, $childB],
        [$focus, $childC], [$coParent, $childC],
        [$focus, $childUnknown],
        [$bystander, $bystanderChild], [$bystanderSpouse, $bystanderChild]] as [$parent, $child]) {
        linkParent($parent, $child);
    }

    $birthOf = fn (Person $subject): Event => $subject->events()->where('type', EventType::Birth)->firstOrFail();

    $focusBirth = $birthOf($focus);
    $focusDeath = $focus->events()->where('type', EventType::Death)->firstOrFail();
    $wifeABirth = $birthOf($wifeA);
    $bystanderBirth = $birthOf($bystander);

    $sourceShared = Source::factory()->for($tree)->withThumbnail()->create();
    $sourceOther = Source::factory()->for($tree)->withThumbnail()->create();
    $sourceBystander = Source::factory()->for($tree)->withThumbnail()->create();

    foreach ([$sourceShared, $sourceOther, $sourceBystander] as $source) {
        Storage::disk('local')->put($source->path, 'original-bytes');
        Storage::disk('local')->put($source->thumbnail_path, 'thumbnail-bytes');
    }

    $sourceShared->events()->attach([$focusBirth->id, $marriageA->id, $bystanderBirth->id]);
    $sourceOther->events()->attach([$marriageA->id, $wifeABirth->id]);
    $sourceBystander->events()->attach([$bystanderMarriage->id]);

    return compact(
        'focus', 'focusFather', 'focusMother', 'wifeA', 'wifeB', 'coParent',
        'childA', 'childB', 'childC', 'childUnknown',
        'bystander', 'bystanderSpouse', 'bystanderChild', 'loneMan', 'loneWoman',
        'focusBirth', 'focusDeath', 'marriageA', 'marriageB', 'wifeABirth', 'bystanderBirth', 'bystanderMarriage',
        'sourceShared', 'sourceOther', 'sourceBystander',
    );
}
