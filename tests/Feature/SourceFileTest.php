<?php

use App\Actions\Sources\FillBurialPlacesFromGravestone;
use App\Actions\Sources\ReplaceSourceFile;
use App\Actions\Sources\StoreSource;
use App\Enums\SourceKind;
use App\Models\Source;
use App\Models\Tree;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

afterEach(function () {
    Source::flushEventListeners();
});

/**
 * Write a JPEG of the given size into a fresh temporary directory and return it as a disk file.
 */
function diskScan(int $width, int $height, string $name = 'skan-repo.jpg'): File
{
    $directory = sys_get_temp_dir().'/source-file-test-'.bin2hex(random_bytes(6));
    mkdir($directory);

    $image = imagecreatetruecolor($width, $height);
    imagejpeg($image, "{$directory}/{$name}", 90);

    return new File("{$directory}/{$name}");
}

test('storing a source from a disk file keeps its name, creates a thumbnail and leaves the original in place', function () {
    $tree = Tree::factory()->create();
    $file = diskScan(800, 600);
    $contents = file_get_contents($file->getRealPath());

    $source = app(StoreSource::class)->handle($tree, $file, SourceKind::MetricalRecord, ['signature' => '35/1813']);

    expect($source->tree_id)->toBe($tree->id)
        ->and($source->original_name)->toBe('skan-repo.jpg')
        ->and($source->mime_type)->toBe('image/jpeg')
        ->and($source->width)->toBe(800)
        ->and($source->height)->toBe(600)
        ->and($source->signature)->toBe('35/1813')
        ->and($source->thumbnail_path)->not->toBeNull();

    Storage::disk('local')->assertExists([$source->path, $source->thumbnail_path]);
    expect(Storage::disk('local')->get($source->path))->toBe($contents);
    expect(file_exists($file->getRealPath()))->toBeTrue();
    expect(file_get_contents($file->getRealPath()))->toBe($contents);
});

test('a failed source row leaves no orphan files', function () {
    $tree = Tree::factory()->create();

    $this->mock(FillBurialPlacesFromGravestone::class)->shouldReceive('handle')->andThrow(new RuntimeException('boom'));

    expect(fn () => app(StoreSource::class)->handle($tree, diskScan(800, 600), SourceKind::MetricalRecord))
        ->toThrow(RuntimeException::class, 'boom');

    expect(Source::count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

test('replacing the file changes the scan, its dimensions and thumbnail and returns the old paths untouched', function () {
    $tree = Tree::factory()->create();
    $source = app(StoreSource::class)->handle($tree, diskScan(800, 600), SourceKind::MetricalRecord);
    [$oldPath, $oldThumbnail] = [$source->path, $source->thumbnail_path];

    $replacement = diskScan(1000, 500, 'nowy-skan.jpg');

    $oldPaths = app(ReplaceSourceFile::class)->handle($source, $replacement);

    $source->refresh();

    expect($oldPaths)->toBe([$oldPath, $oldThumbnail])
        ->and($source->path)->not->toBe($oldPath)
        ->and($source->thumbnail_path)->not->toBe($oldThumbnail)
        ->and($source->original_name)->toBe('nowy-skan.jpg')
        ->and($source->width)->toBe(1000)
        ->and($source->height)->toBe(500);

    Storage::disk('local')->assertExists([$oldPath, $oldThumbnail, $source->path, $source->thumbnail_path]);
    expect(file_exists($replacement->getRealPath()))->toBeTrue();
});

test('a failed replacement removes only the files it stored', function () {
    $tree = Tree::factory()->create();
    $source = app(StoreSource::class)->handle($tree, diskScan(800, 600), SourceKind::MetricalRecord);
    [$oldPath, $oldThumbnail] = [$source->path, $source->thumbnail_path];
    $before = Storage::disk('local')->allFiles();

    Source::updating(fn () => throw new RuntimeException('boom'));

    expect(fn () => app(ReplaceSourceFile::class)->handle($source, diskScan(1000, 500)))
        ->toThrow(RuntimeException::class, 'boom');

    expect(Storage::disk('local')->allFiles())->toEqualCanonicalizing($before);
    Storage::disk('local')->assertExists([$oldPath, $oldThumbnail]);
    expect($source->fresh()->path)->toBe($oldPath);
});
