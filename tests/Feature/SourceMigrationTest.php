<?php

use App\Enums\SourceKind;
use App\Models\Event;
use App\Models\Person;
use App\Models\Source;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

function renameScansToSourcesMigration(): Migration
{
    static $migration = null;

    return $migration ??= require database_path('migrations/2026_09_29_100000_rename_scans_to_sources.php');
}

function moveSourceFilesMigration(): Migration
{
    static $migration = null;

    return $migration ??= require database_path('migrations/2026_09_29_100100_move_source_files_to_sources_prefix.php');
}

/**
 * Put the schema back to its pre-rename shape and link one legacy scan to the event.
 *
 * @return array{scan: int, link: int}
 */
function legacyScanLinkedTo(Event $event): array
{
    renameScansToSourcesMigration()->down();

    $scan = DB::table('scans')->insertGetId([
        'tree_id' => $event->tree_id,
        'original_name' => 'akt.jpg',
        'path' => "scans/{$event->tree_id}/akt.jpg",
        'mime_type' => 'image/jpeg',
        'size' => 1024,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $link = DB::table('event_scan')->insertGetId([
        'event_id' => $event->id,
        'scan_id' => $scan,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return ['scan' => $scan, 'link' => $link];
}

function legacySource(string $path, ?string $thumbnailPath): Source
{
    return Source::factory()->create(['path' => $path, 'thumbnail_path' => $thumbnailPath]);
}

function addDescriptionMigration(): Migration
{
    static $migration = null;

    return $migration ??= require database_path('migrations/2026_09_30_072040_add_description_to_sources_table.php');
}

beforeEach(function () {
    Storage::fake('local');
});

test('the rename keeps every event link under its id, so the event stays confirmed', function () {
    $event = Event::factory()->for(User::factory()->create()->tree)->birth()->create();
    $legacy = legacyScanLinkedTo($event);

    renameScansToSourcesMigration()->up();

    expect(DB::table('event_source')->where('id', $legacy['link'])->value('source_id'))->toBe($legacy['scan']);
    expect($event->sources()->pluck('sources.id')->all())->toBe([$legacy['scan']]);
});

test('the rename turns every existing scan into a metrical record', function () {
    $event = Event::factory()->for(User::factory()->create()->tree)->birth()->create();
    $legacy = legacyScanLinkedTo($event);

    renameScansToSourcesMigration()->up();

    expect(Source::findOrFail($legacy['scan'])->kind)->toBe(SourceKind::MetricalRecord);
});

test('after the rename a source row without a kind is rejected', function () {
    $event = Event::factory()->for(User::factory()->create()->tree)->birth()->create();
    legacyScanLinkedTo($event);
    renameScansToSourcesMigration()->up();

    $insertWithoutKind = fn () => DB::transaction(fn () => DB::table('sources')->insert([
        'tree_id' => $event->tree_id,
        'original_name' => 'nagrobek.jpg',
        'path' => "sources/{$event->tree_id}/nagrobek.jpg",
        'mime_type' => 'image/jpeg',
        'size' => 1024,
    ]));

    expect($insertWithoutKind)->toThrow(QueryException::class, 'kind');
    expect(DB::table('sources')->count())->toBe(1);
});

test('rolling the rename back restores the scans names and keeps the event links', function () {
    $event = Event::factory()->for(User::factory()->create()->tree)->birth()->create();
    $legacy = legacyScanLinkedTo($event);
    renameScansToSourcesMigration()->up();

    renameScansToSourcesMigration()->down();

    expect(Schema::hasTable('sources'))->toBeFalse();
    expect(Schema::hasTable('event_source'))->toBeFalse();
    expect(Schema::hasColumn('scans', 'kind'))->toBeFalse();
    expect(DB::table('event_scan')->where('id', $legacy['link'])->value('scan_id'))->toBe($legacy['scan']);
});

test('the file move relocates originals and thumbnails and repoints the rows', function () {
    $disk = Storage::disk('local');
    $disk->put('scans/1/a.jpg', 'original');
    $disk->put('scans/1/thumbnails/a.jpg', 'thumb');
    $source = legacySource('scans/1/a.jpg', 'scans/1/thumbnails/a.jpg');

    moveSourceFilesMigration()->up();

    $source->refresh();
    expect($source->path)->toBe('sources/1/a.jpg');
    expect($source->thumbnail_path)->toBe('sources/1/thumbnails/a.jpg');
    $disk->assertExists('sources/1/a.jpg');
    $disk->assertExists('sources/1/thumbnails/a.jpg');
    $disk->assertMissing('scans/1/a.jpg');
    expect($disk->get('sources/1/a.jpg'))->toBe('original');
});

test('running the file move twice is a no-op', function () {
    $disk = Storage::disk('local');
    $disk->put('scans/1/a.jpg', 'original');
    $source = legacySource('scans/1/a.jpg', null);

    moveSourceFilesMigration()->up();
    moveSourceFilesMigration()->up();

    expect($source->refresh()->path)->toBe('sources/1/a.jpg');
    $disk->assertExists('sources/1/a.jpg');
});

test('a row whose file is already under sources only gets its path updated', function () {
    $disk = Storage::disk('local');
    $disk->put('sources/1/a.jpg', 'original');
    $source = legacySource('scans/1/a.jpg', null);

    moveSourceFilesMigration()->up();

    expect($source->refresh()->path)->toBe('sources/1/a.jpg');
    $disk->assertExists('sources/1/a.jpg');
});

test('a row with both files missing is left untouched', function () {
    $source = legacySource('scans/1/gone.jpg', 'scans/1/thumbnails/gone.jpg');

    moveSourceFilesMigration()->up();

    $source->refresh();
    expect($source->path)->toBe('scans/1/gone.jpg');
    expect($source->thumbnail_path)->toBe('scans/1/thumbnails/gone.jpg');
});

test('a mixed row moves the original and keeps the thumbnail path when that file is missing everywhere', function () {
    Storage::disk('local')->put('scans/1/a.jpg', 'original');
    $source = legacySource('scans/1/a.jpg', 'scans/1/thumbnails/a.jpg');

    moveSourceFilesMigration()->up();

    $source->refresh();
    expect($source->path)->toBe('sources/1/a.jpg');
    expect($source->thumbnail_path)->toBe('scans/1/thumbnails/a.jpg');
});

test('an unreferenced orphan under scans is moved to sources and scans is removed', function () {
    $disk = Storage::disk('local');
    $disk->put('scans/1/orphan.jpg', 'orphan');

    moveSourceFilesMigration()->up();

    $disk->assertExists('sources/1/orphan.jpg');
    expect($disk->exists('scans'))->toBeFalse();
    expect($disk->directoryExists('scans'))->toBeFalse();
});

test('down restores the scans prefix', function () {
    $disk = Storage::disk('local');
    $disk->put('sources/1/a.jpg', 'original');
    $disk->put('sources/1/thumbnails/a.jpg', 'thumb');
    $source = legacySource('sources/1/a.jpg', 'sources/1/thumbnails/a.jpg');

    moveSourceFilesMigration()->down();

    $source->refresh();
    expect($source->path)->toBe('scans/1/a.jpg');
    expect($source->thumbnail_path)->toBe('scans/1/thumbnails/a.jpg');
    $disk->assertExists('scans/1/a.jpg');
    $disk->assertExists('scans/1/thumbnails/a.jpg');
    expect($disk->directoryExists('sources'))->toBeFalse();
});

test('events keep their confirmed state through the preview props', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $confirmed = Event::factory()->for($user->tree)->birth()->create();
    $unconfirmed = Event::factory()->for($user->tree)->death()->create();
    $confirmed->participants()->attach($person, ['role' => 'subject']);
    $unconfirmed->participants()->attach($person, ['role' => 'subject']);
    $confirmed->sources()->attach(Source::factory()->for($user->tree)->create());

    $this->actingAs($user)->get(personPreviewUrl($person))->assertInertia(fn ($page) => $page
        ->where('personPreview.person.birth.is_confirmed', true)
        ->where('personPreview.person.death.is_confirmed', false));
});

test('the description migration keeps existing rows with an empty description and rolls back cleanly', function () {
    $columns = ['name', 'record_type', 'entry_number', 'year', 'provenance', 'url', 'note'];
    $source = Source::factory()->create(['original_name' => 'akt.jpg']);

    addDescriptionMigration()->down();

    foreach ($columns as $column) {
        expect(Schema::hasColumn('sources', $column))->toBeFalse();
    }
    expect(DB::table('sources')->where('id', $source->id)->value('original_name'))->toBe('akt.jpg');

    addDescriptionMigration()->up();

    expect(Source::findOrFail($source->id)->only($columns))->each->toBeNull();
});
