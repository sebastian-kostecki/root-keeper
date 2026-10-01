<?php

use App\Models\Source;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\BackupTestCase;

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

test('the database dump restores into an empty database with the same row counts', function () {
    $this->arrangeBackupData();

    $archive = $this->runBackup();

    $dumpFile = $this->storageRoot.'/restore.sql';
    file_put_contents($dumpFile, $this->readArchiveEntry($archive, $this->dumpEntryName()));

    $create = $this->psql('CREATE DATABASE '.BackupTestCase::RESTORE_DATABASE, database: 'postgres');
    expect($create->isSuccessful())->toBeTrue($create->getErrorOutput());

    $restore = $this->psql(database: BackupTestCase::RESTORE_DATABASE, arguments: ['-f', $dumpFile]);
    expect($restore->isSuccessful())->toBeTrue($restore->getErrorOutput());

    config(['database.connections.restore' => [
        ...config('database.connections.pgsql'),
        'database' => BackupTestCase::RESTORE_DATABASE,
    ]]);

    foreach ([...BackupTestCase::DOMAIN_TABLES, 'migrations'] as $table) {
        $original = DB::connection('pgsql')->table($table)->count();

        expect(DB::connection('restore')->table($table)->count())->toBe($original, "Row count differs after restore for [{$table}].");

        if ($table !== 'migrations') {
            expect($original)->toBeGreaterThan(0, "Table [{$table}] has no rows, so the comparison proves nothing.");
        }
    }
});

test('an encrypted backup archive opens only with the backup password', function () {
    if (! ZipArchive::isEncryptionMethodSupported(ZipArchive::EM_AES_256)) {
        $this->markTestSkipped('AES-256 zip encryption is not supported by this PHP/libzip build.');
    }

    config([
        'backup.backup.password' => 'backup-test-password',
        'backup.backup.encryption' => 'default',
    ]);
    $this->app->forgetScopedInstances();

    $parent = $this->arrangeBackupData();

    $archive = $this->runBackup();

    $entry = $this->dumpEntryName();

    expect($this->readArchiveEntry($archive, $entry))->toBeNull();

    $dump = $this->readArchiveEntry($archive, $entry, 'backup-test-password');

    expect($dump)->not->toBeNull()
        ->and($dump)->toContain($parent->last_name);
});
