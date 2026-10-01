<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Base case for tests that run the real backup against real (committed) rows and files.
 *
 * Storage is pointed at a throwaway root before the app boots, so the developer's own
 * storage/app is never read into an archive or written to.
 */
abstract class BackupTestCase extends TestCase
{
    /**
     * Tables whose rows the backup must carry, in the order they are listed in assertions.
     *
     * @var list<string>
     */
    protected const DOMAIN_TABLES = [
        'users',
        'trees',
        'people',
        'events',
        'event_participants',
        'sources',
        'event_source',
        'parent_child_relations',
    ];

    protected const RESTORE_DATABASE = 'testing_restore';

    protected string $storageRoot = '';

    public function createApplication()
    {
        $app = require Application::inferBasePath().'/bootstrap/app.php';

        $this->traitsUsedByTest = class_uses_recursive(static::class);

        $this->storageRoot = sys_get_temp_dir().'/root-keeper-backup-test-'.bin2hex(random_bytes(6));

        foreach (['app/private', 'framework/views', 'framework/cache', 'logs'] as $directory) {
            mkdir($this->storageRoot.'/'.$directory, 0777, true);
        }

        $app->useStoragePath($this->storageRoot);

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function tearDown(): void
    {
        if (isset($this->app)) {
            DB::purge('restore');
            DB::statement('TRUNCATE TABLE '.implode(', ', self::DOMAIN_TABLES).' RESTART IDENTITY CASCADE');
            $this->psql('DROP DATABASE IF EXISTS '.self::RESTORE_DATABASE.' WITH (FORCE)', database: 'postgres');
        }

        if ($this->storageRoot !== '' && is_dir($this->storageRoot)) {
            File::deleteDirectory($this->storageRoot);
        }

        parent::tearDown();
    }

    /**
     * Run the real backup and return the absolute path of the single archive it wrote.
     */
    protected function runBackup(): string
    {
        $exitCode = Artisan::call('backup:run', ['--disable-notifications' => true]);

        expect($exitCode)->toBe(0, Artisan::output());

        $archives = Storage::disk('backups')->allFiles();

        expect($archives)->toHaveCount(1);

        return Storage::disk('backups')->path($archives[0]);
    }

    /**
     * Name of the database dump inside the archive, derived from the connection the backup dumps.
     */
    protected function dumpEntryName(): string
    {
        return 'db-dumps/postgresql-'.config('database.connections.pgsql.database').'.sql';
    }

    /**
     * Read one entry of the archive in full, or null when it is missing or unreadable.
     */
    protected function readArchiveEntry(string $archivePath, string $entry, ?string $password = null): ?string
    {
        $zip = new ZipArchive;

        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException("Unable to open archive [{$archivePath}].");
        }

        if ($password !== null) {
            $zip->setPassword($password);
        }

        $contents = @$zip->getFromName($entry);
        $zip->close();

        return $contents === false ? null : $contents;
    }

    /**
     * Run psql against the application's PostgreSQL server and return the finished process.
     *
     * @param  list<string>  $arguments  Extra psql arguments, e.g. ['-f', $file].
     */
    protected function psql(?string $command = null, ?string $database = null, array $arguments = []): Process
    {
        $connection = config('database.connections.pgsql');

        $process = new Process([
            'psql',
            '-v', 'ON_ERROR_STOP=1',
            '-h', (string) $connection['host'],
            '-p', (string) $connection['port'],
            '-U', (string) $connection['username'],
            '-d', $database ?? (string) $connection['database'],
            ...($command !== null ? ['-c', $command] : []),
            ...$arguments,
        ], env: ['PGPASSWORD' => (string) $connection['password']]);

        $process->setTimeout(120);
        $process->run();

        return $process;
    }
}
