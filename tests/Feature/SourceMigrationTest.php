<?php

use App\Models\Event;
use App\Models\Person;
use App\Models\Source;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

function moveSourceFilesMigration(): Migration
{
    static $migration = null;

    return $migration ??= require database_path('migrations/2026_09_29_100100_move_source_files_to_sources_prefix.php');
}

function legacySource(string $path, ?string $thumbnailPath): Source
{
    return Source::factory()->create(['path' => $path, 'thumbnail_path' => $thumbnailPath]);
}

beforeEach(function () {
    Storage::fake('local');
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
