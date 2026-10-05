<?php

namespace Tests;

use App\Enums\EventType;
use App\Models\Event;
use App\Models\Locality;
use App\Models\Person;
use App\Models\Source;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\UploadedFile;
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
     * Tables that hold no durable data; every other table of the schema must survive a restore.
     * A new cache or queue style table belongs here.
     *
     * @var list<string>
     */
    protected const EPHEMERAL_TABLES = [
        'migrations',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'password_reset_tokens',
    ];

    protected const RESTORE_DATABASE = 'testing_restore';

    protected string $storageRoot = '';

    protected string $restoreStorageRoot = '';

    /**
     * Connection database and storage settings to put back when the test switched instances.
     *
     * @var array{database: string, storagePath: string, diskRoots: array<string, string>}|null
     */
    private ?array $originalInstance = null;

    protected ?User $backupUser = null;

    /**
     * Sha256 of each uploaded source, keyed by its original name.
     *
     * @var array<string, array{original: string, thumbnail: string}>
     */
    protected array $uploadedSourceHashes = [];

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
            $this->restoreOriginalInstance();

            $tables = $this->durableTables('pgsql');

            if ($tables !== []) {
                DB::statement('TRUNCATE TABLE '.implode(', ', $tables).' RESTART IDENTITY CASCADE');
            }

            $this->psql('DROP DATABASE IF EXISTS '.self::RESTORE_DATABASE.' WITH (FORCE)', database: 'postgres');
        }

        foreach ([$this->storageRoot, $this->restoreStorageRoot] as $root) {
            if ($root !== '' && is_dir($root)) {
                File::deleteDirectory($root);
            }
        }

        parent::tearDown();
    }

    /**
     * Switch the backup to the encrypted production mode. Call before the first backup of the test.
     */
    protected function useEncryptedBackups(string $password = 'backup-test-password'): void
    {
        config([
            'backup.backup.password' => $password,
            'backup.backup.encryption' => 'default',
        ]);

        $this->app->forgetScopedInstances();
    }

    /**
     * Create a two-factor user with a tree, people, events, relations, a passkey, a locality and two
     * uploaded sources of different bytes, one linked to an event.
     */
    protected function arrangeBackupData(): Person
    {
        $user = User::factory()->withTwoFactor()->create();
        $tree = $user->tree;

        $this->backupUser = $user;

        DB::table('passkeys')->insert([
            'user_id' => $user->id,
            'name' => 'Klucz testowy',
            'credential_id' => 'credential-'.bin2hex(random_bytes(8)),
            'credential' => json_encode(['id' => 'credential', 'type' => 'public-key']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $parent = Person::factory()->for($tree)->create(['first_name' => 'Zygmunt', 'last_name' => 'Backupowski']);
        $child = Person::factory()->for($tree)->create();

        attachBirth($child, 1950, 3, 14);
        attachMarriage($parent, Person::factory()->for($tree)->create(), 1945);
        linkParent($parent, $child);

        Locality::factory()->for($tree)->create(['name' => 'Lipnica Murowana']);

        $this->uploadedSourceHashes = [];

        foreach (['akt-1.jpg' => 'rotated-exif.jpg', 'akt-2.jpg' => 'portrait-small.jpg'] as $name => $fixture) {
            $source = $this->uploadSource($user, $name, $fixture);

            $this->uploadedSourceHashes[$name] = [
                'original' => hash_file('sha256', base_path('tests/Fixtures/'.$fixture)),
                'thumbnail' => hash('sha256', Storage::disk('local')->get($source->thumbnail_path)),
            ];
        }

        $birth = Event::query()->where('tree_id', $tree->id)->where('type', EventType::Birth)->firstOrFail();

        $this->post(route('events.sources.store', $birth), ['source_id' => Source::query()->orderBy('id')->firstOrFail()->id])
            ->assertSessionHasNoErrors();

        expect(Source::query()->count())->toBe(2)
            ->and(DB::table('event_source')->count())->toBe(1)
            ->and(DB::table('passkeys')->count())->toBe(1);

        return $parent;
    }

    /**
     * Upload a fixture through the real sources.store endpoint and return the stored source.
     */
    protected function uploadSource(User $user, string $name, string $fixture): Source
    {
        $this->actingAs($user)
            ->post(route('sources.store'), [
                'kind' => 'metrical_record',
                'file' => new UploadedFile(base_path('tests/Fixtures/'.$fixture), $name, 'image/jpeg', null, true),
            ])
            ->assertSessionHasNoErrors();

        return Source::query()->where('original_name', $name)->latest('id')->firstOrFail();
    }

    /**
     * Point the app at an empty second instance: a new empty database and a new storage root.
     * tearDown puts everything back.
     */
    protected function switchToCleanInstance(): void
    {
        $this->originalInstance = [
            'database' => (string) config('database.connections.pgsql.database'),
            'storagePath' => $this->app->storagePath(),
            'diskRoots' => [
                'local' => (string) config('filesystems.disks.local.root'),
                'public' => (string) config('filesystems.disks.public.root'),
                'backups' => (string) config('filesystems.disks.backups.root'),
            ],
        ];

        $this->psql('DROP DATABASE IF EXISTS '.self::RESTORE_DATABASE.' WITH (FORCE)', database: 'postgres');

        $create = $this->psql('CREATE DATABASE '.self::RESTORE_DATABASE, database: 'postgres');

        expect($create->isSuccessful())->toBeTrue($create->getErrorOutput());

        $this->restoreStorageRoot = sys_get_temp_dir().'/root-keeper-restore-test-'.bin2hex(random_bytes(6));

        foreach (['app/private', 'app/public', 'app/backups', 'framework/views', 'framework/cache', 'logs'] as $directory) {
            mkdir($this->restoreStorageRoot.'/'.$directory, 0777, true);
        }

        $this->applyInstance(self::RESTORE_DATABASE, $this->restoreStorageRoot);
    }

    private function restoreOriginalInstance(): void
    {
        if ($this->originalInstance === null) {
            return;
        }

        config([
            'filesystems.disks.local.root' => $this->originalInstance['diskRoots']['local'],
            'filesystems.disks.public.root' => $this->originalInstance['diskRoots']['public'],
            'filesystems.disks.backups.root' => $this->originalInstance['diskRoots']['backups'],
            'database.connections.pgsql.database' => $this->originalInstance['database'],
        ]);

        $this->app->useStoragePath($this->originalInstance['storagePath']);

        Storage::forgetDisk(['local', 'public', 'backups']);
        DB::purge('pgsql');

        $this->originalInstance = null;
    }

    private function applyInstance(string $database, string $storageRoot): void
    {
        $this->app->useStoragePath($storageRoot);

        config([
            'filesystems.disks.local.root' => $storageRoot.'/app/private',
            'filesystems.disks.public.root' => $storageRoot.'/app/public',
            'filesystems.disks.backups.root' => $storageRoot.'/app/backups',
            'database.connections.pgsql.database' => $database,
        ]);

        Storage::forgetDisk(['local', 'public', 'backups']);
        DB::purge('pgsql');
    }

    /**
     * Every table of the public schema that holds durable data, derived from the live schema.
     *
     * @return list<string>
     */
    protected function durableTables(string $connection): array
    {
        $tables = DB::connection($connection)->table('information_schema.tables')
            ->where('table_schema', 'public')
            ->where('table_type', 'BASE TABLE')
            ->orderBy('table_name')
            ->pluck('table_name')
            ->all();

        return array_values(array_diff($tables, self::EPHEMERAL_TABLES));
    }

    /**
     * Row count and content hash of every durable table, independent of primary keys.
     *
     * @return array<string, array{rows: int, digest: string}>
     */
    protected function tableDigests(string $connection): array
    {
        $digests = [];

        foreach ($this->durableTables($connection) as $table) {
            $row = DB::connection($connection)->selectOne(
                "select count(*) as rows, md5(coalesce(string_agg(t::text, '|' order by t::text), '')) as digest from \"{$table}\" t"
            );

            $digests[$table] = ['rows' => (int) $row->rows, 'digest' => (string) $row->digest];
        }

        return $digests;
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
