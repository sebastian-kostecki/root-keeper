<?php

use App\Models\Person;
use App\Models\Source;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('a backup restores onto a clean instance with identical data and files the app can serve', function () {
    $this->useEncryptedBackups();
    $this->arrangeBackupData();

    $archive = $this->runBackup();
    $snapshot = $this->tableDigests('pgsql');

    $this->switchToCleanInstance();
    $this->artisan('migrate', ['--force' => true])->assertSuccessful();

    $this->artisan('backup:restore', ['archive' => $archive])->assertSuccessful();

    $restored = $this->tableDigests('pgsql');

    expect($restored)->toBe($snapshot)
        ->and($snapshot)->toHaveKey('passkeys');

    foreach ($restored as $table => $digest) {
        expect($digest['rows'])->toBeGreaterThan(0, "Table [{$table}] has no rows, so the comparison proves nothing.");
    }

    $user = User::query()->firstOrFail();

    foreach (Source::query()->get() as $source) {
        $expected = $this->uploadedSourceHashes[$source->original_name];

        $original = $this->actingAs($user)->get(route('sources.file', $source))->assertOk();
        $thumbnail = $this->actingAs($user)->get(route('sources.thumbnail', $source))->assertOk();

        expect(hash('sha256', $original->streamedContent()))->toBe($expected['original'])
            ->and(hash('sha256', $thumbnail->streamedContent()))->toBe($expected['thumbnail']);
    }

    expect($user->recoveryCodes())->toBe(['recovery-code-1']);

    $this->uploadSource($user, 'akt-3.jpg', 'portrait-small.jpg');

    expect(Source::query()->count())->toBe(3);
});

test('--force restores an occupied instance to the archived state and keeps a safety copy', function () {
    $this->useEncryptedBackups();
    $parent = $this->arrangeBackupData();

    $archive = $this->runBackup();
    $archiveHash = hash_file('sha256', $archive);
    $snapshot = $this->tableDigests('pgsql');

    $this->delete(route('persons.destroy', $parent))->assertSessionHasNoErrors();
    $extra = $this->uploadSource($this->backupUser, 'akt-3.jpg', 'portrait-small.jpg');
    $extraPhoto = "persons/{$parent->tree_id}/po-kopii.jpg";
    Storage::disk('local')->put($extraPhoto, 'zdjecie-po-kopii');

    expect(Person::query()->find($parent->id))->toBeNull()
        ->and(Storage::disk('local')->exists($extra->path))->toBeTrue()
        ->and(Storage::disk('local')->exists($extraPhoto))->toBeTrue()
        ->and($this->tableDigests('pgsql'))->not->toBe($snapshot);

    $this->travel(1)->seconds();

    $this->artisan('backup:restore', ['archive' => $archive, '--force' => true])
        ->expectsConfirmation('Zastąpić dane z archiwum? Przed zmianą zostanie wykonana kopia bezpieczeństwa.', 'yes')
        ->assertSuccessful();

    $archives = Storage::disk('backups')->allFiles();

    expect($this->tableDigests('pgsql'))->toBe($snapshot)
        ->and(Storage::disk('local')->exists($extra->path))->toBeFalse()
        ->and(Storage::disk('local')->exists($extraPhoto))->toBeFalse()
        ->and($archives)->toHaveCount(2)
        ->and(array_unique(array_map('basename', $archives)))->toHaveCount(2)
        ->and(hash_file('sha256', $archive))->toBe($archiveHash);
});

test('a backup restores a person photo onto a clean instance', function () {
    $this->useEncryptedBackups();
    $parent = $this->arrangeBackupData();

    $photo = "persons/{$parent->tree_id}/zdjecie.jpg";
    $thumbnail = "persons/{$parent->tree_id}/thumbnails/zdjecie.jpg";
    Storage::disk('local')->put($photo, 'zdjecie-osoby');
    Storage::disk('local')->put($thumbnail, 'miniatura-osoby');
    $parent->update(['photo_path' => $photo, 'photo_thumbnail_path' => $thumbnail]);

    $archive = $this->runBackup();

    $this->switchToCleanInstance();
    $this->artisan('migrate', ['--force' => true])->assertSuccessful();

    $this->artisan('backup:restore', ['archive' => $archive])->assertSuccessful();

    expect(Storage::disk('local')->get($photo))->toBe('zdjecie-osoby')
        ->and(Storage::disk('local')->get($thumbnail))->toBe('miniatura-osoby')
        ->and(Person::query()->findOrFail($parent->id)->photo_thumbnail_path)->toBe($thumbnail);
});

test('a restore reports a person photo that is missing from the archive', function () {
    $this->useEncryptedBackups();
    $parent = $this->arrangeBackupData();

    $thumbnail = "persons/{$parent->tree_id}/thumbnails/brak.jpg";
    $parent->update(['photo_path' => "persons/{$parent->tree_id}/brak.jpg", 'photo_thumbnail_path' => $thumbnail]);

    $archive = $this->runBackup();

    $this->switchToCleanInstance();
    $this->artisan('migrate', ['--force' => true])->assertSuccessful();

    $this->artisan('backup:restore', ['archive' => $archive])
        ->expectsOutputToContain($thumbnail)
        ->expectsOutputToContain('zdjęć osób')
        ->assertFailed();
});
