<?php

use App\Models\Source;
use Illuminate\Support\Facades\Storage;

test('the backup archive contains the database dump and every source original and thumbnail byte for byte', function () {
    $parent = $this->arrangeBackupData();

    $archive = $this->runBackup();

    $dump = $this->readArchiveEntry($archive, $this->dumpEntryName());

    expect($dump)->not->toBeNull()
        ->and($dump)->toContain($parent->last_name);

    $comparedThumbnails = 0;

    foreach (Source::query()->get() as $source) {
        $original = $this->readArchiveEntry($archive, "private/{$source->path}");

        expect($original)->not->toBeNull("Original [{$source->path}] is missing from the archive.")
            ->and(hash('sha256', $original))->toBe(hash('sha256', Storage::disk('local')->get($source->path)));

        if ($source->thumbnail_path !== null) {
            $thumbnail = $this->readArchiveEntry($archive, "private/{$source->thumbnail_path}");

            expect($thumbnail)->not->toBeNull("Thumbnail [{$source->thumbnail_path}] is missing from the archive.")
                ->and(hash('sha256', $thumbnail))->toBe(hash('sha256', Storage::disk('local')->get($source->thumbnail_path)));

            $comparedThumbnails++;
        }
    }

    expect($comparedThumbnails)->toBeGreaterThan(0);
});

test('an encrypted backup archive opens only with the backup password', function () {
    if (! ZipArchive::isEncryptionMethodSupported(ZipArchive::EM_AES_256)) {
        $this->markTestSkipped('AES-256 zip encryption is not supported by this PHP/libzip build.');
    }

    $this->useEncryptedBackups();

    $parent = $this->arrangeBackupData();

    $archive = $this->runBackup();

    $entry = $this->dumpEntryName();

    expect($this->readArchiveEntry($archive, $entry))->toBeNull();

    $dump = $this->readArchiveEntry($archive, $entry, 'backup-test-password');

    expect($dump)->not->toBeNull()
        ->and($dump)->toContain($parent->last_name);
});

test('the backup archive contains the photo and thumbnail of a person', function () {
    $parent = $this->arrangeBackupData();

    $photo = "persons/{$parent->tree_id}/zdjecie.jpg";
    $thumbnail = "persons/{$parent->tree_id}/thumbnails/zdjecie.jpg";
    Storage::disk('local')->put($photo, 'zdjecie-osoby');
    Storage::disk('local')->put($thumbnail, 'miniatura-osoby');
    $parent->update(['photo_path' => $photo, 'photo_thumbnail_path' => $thumbnail]);

    $archive = $this->runBackup();

    expect($this->readArchiveEntry($archive, "private/{$photo}"))->toBe('zdjecie-osoby')
        ->and($this->readArchiveEntry($archive, "private/{$thumbnail}"))->toBe('miniatura-osoby');
});
