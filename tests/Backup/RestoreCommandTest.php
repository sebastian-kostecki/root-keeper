<?php

use App\Models\User;
use Illuminate\Support\Facades\Storage;

const OVERWRITE_QUESTION = 'Zastąpić dane z archiwum? Przed zmianą zostanie wykonana kopia bezpieczeństwa.';

/**
 * Hash of every source file currently on the local disk, keyed by path.
 *
 * @return array<string, string>
 */
function sourceFileHashes(): array
{
    $hashes = [];

    foreach (Storage::disk('local')->allFiles('sources') as $path) {
        $hashes[$path] = hash('sha256', Storage::disk('local')->get($path));
    }

    ksort($hashes);

    return $hashes;
}

test('an occupied instance is refused without --force and left unchanged', function () {
    $this->arrangeBackupData();
    $archive = $this->runBackup();

    $users = User::query()->count();
    $files = sourceFileHashes();

    $this->artisan('backup:restore', ['archive' => $archive])
        ->expectsOutputToContain('--force')
        ->assertFailed();

    expect(User::query()->count())->toBe($users)
        ->and(sourceFileHashes())->toBe($files)
        ->and($files)->not->toBeEmpty();
});

test('declining the confirmation leaves the instance unchanged and writes no safety archive', function () {
    $this->arrangeBackupData();
    $archive = $this->runBackup();

    $users = User::query()->count();
    $files = sourceFileHashes();

    $this->artisan('backup:restore', ['archive' => $archive, '--force' => true])
        ->expectsConfirmation(OVERWRITE_QUESTION, 'no')
        ->assertFailed();

    expect(User::query()->count())->toBe($users)
        ->and(sourceFileHashes())->toBe($files)
        ->and(Storage::disk('backups')->allFiles())->toHaveCount(1);
});

test('an encrypted archive with the wrong password fails before any change', function () {
    config([
        'backup.backup.password' => 'backup-test-password',
        'backup.backup.encryption' => 'default',
    ]);
    $this->app->forgetScopedInstances();

    $this->arrangeBackupData();
    $archive = $this->runBackup();

    config(['backup.backup.password' => 'wrong-password']);

    $users = User::query()->count();
    $files = sourceFileHashes();

    $this->artisan('backup:restore', ['archive' => $archive, '--force' => true])
        ->expectsOutputToContain('hasło')
        ->assertFailed();

    expect(User::query()->count())->toBe($users)
        ->and(sourceFileHashes())->toBe($files)
        ->and(Storage::disk('backups')->allFiles())->toHaveCount(1)
        ->and(glob(storage_path('app/backup-temp/restore-*')))->toBeEmpty();
});

test('an archive with an entry climbing out of the storage root is rejected', function () {
    $archive = $this->storageRoot.'/evil.zip';

    $zip = new ZipArchive;
    expect($zip->open($archive, ZipArchive::CREATE))->toBeTrue();
    $zip->addFromString('db-dumps/postgresql-evil.sql', 'select 1;');
    $zip->addFromString('../evil', 'pwned');
    $zip->close();

    $this->artisan('backup:restore', ['archive' => $archive])
        ->expectsOutputToContain('niebezpieczne')
        ->assertFailed();

    expect($this->storageRoot.'/evil')->not->toBeFile()
        ->and(storage_path('app/evil'))->not->toBeFile()
        ->and(glob(storage_path('app/backup-temp/restore-*')))->toBeEmpty();
});

test('a fresh backup dump carries no ownership statements', function () {
    $this->arrangeBackupData();
    $archive = $this->runBackup();

    $dump = $this->readArchiveEntry($archive, $this->dumpEntryName());

    expect($dump)->toContain('CREATE TABLE')
        ->and($dump)->not->toContain('OWNER TO');
});
