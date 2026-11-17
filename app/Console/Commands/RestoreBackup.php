<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use ZipArchive;

#[Signature('backup:restore
    {archive : Absolute path, or path relative to the "backups" disk}
    {--force : Allow overwriting an instance that already has data}')]
#[Description('Restore the database and storage files of this instance from a backup archive')]
class RestoreBackup extends Command
{
    use ConfirmableTrait;

    /**
     * Tables that carry no durable data and are left out of counts and comparisons.
     *
     * @var list<string>
     */
    private const EPHEMERAL_TABLES = [
        'migrations',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'password_reset_tokens',
    ];

    private const SOURCES_DIRECTORY = 'sources';

    private const PERSONS_DIRECTORY = 'persons';

    private string $stagingPath = '';

    private string $safetyArchive = '';

    public function handle(): int
    {
        $archive = $this->argument('archive');
        $archive = is_string($archive) ? $archive : '';
        $archivePath = $this->resolveArchivePath($archive);

        if ($archivePath === null) {
            $this->error('Nie znaleziono archiwum: '.$archive);

            return self::FAILURE;
        }

        $zip = new ZipArchive;

        if ($zip->open($archivePath) !== true) {
            $this->error('Nie można otworzyć archiwum: '.$archivePath);

            return self::FAILURE;
        }

        try {
            return $this->restoreFrom($zip, $archivePath);
        } finally {
            $zip->close();

            if ($this->stagingPath !== '') {
                File::deleteDirectory($this->stagingPath);
            }
        }
    }

    private function restoreFrom(ZipArchive $zip, string $archivePath): int
    {
        $password = config('backup.backup.password');

        if (is_string($password) && $password !== '') {
            $zip->setPassword($password);
        }

        $entries = $this->entryNames($zip);

        $dumpIndexes = array_keys(array_filter(
            $entries,
            fn (string $name): bool => preg_match('#^db-dumps/[^/]+\.sql$#', $name) === 1,
        ));

        if (count($dumpIndexes) !== 1) {
            $this->error('Archiwum musi zawierać dokładnie jeden zrzut bazy (db-dumps/*.sql), znaleziono: '.count($dumpIndexes).'.');

            return self::FAILURE;
        }

        $dumpEntry = $entries[$dumpIndexes[0]];

        $unsafe = $this->unsafeEntries($entries);

        if ($unsafe !== []) {
            $this->error('Archiwum zawiera niebezpieczne wpisy, przywracanie przerwane:');

            foreach ($unsafe as $name) {
                $this->line('  '.$name);
            }

            return self::FAILURE;
        }

        if (@$zip->getFromIndex($dumpIndexes[0], 1) === false) {
            $this->error('Nie można odczytać archiwum: brak hasła (BACKUP_ARCHIVE_PASSWORD) albo hasło jest nieprawidłowe.');

            return self::FAILURE;
        }

        $userCount = $this->userCount();
        $sourceFileCount = count(Storage::disk('local')->allFiles(self::SOURCES_DIRECTORY));
        $personFileCount = count(Storage::disk('local')->allFiles(self::PERSONS_DIRECTORY));
        $occupied = $userCount > 0 || $sourceFileCount > 0 || $personFileCount > 0;

        if ($occupied && ! $this->option('force')) {
            $this->error('Ta instancja zawiera już dane, nic nie zmieniono. Użyj --force, aby je nadpisać.');
            $this->line("  użytkownicy: {$userCount}");
            $this->line("  pliki źródeł: {$sourceFileCount}");
            $this->line("  pliki zdjęć osób: {$personFileCount}");

            return self::FAILURE;
        }

        if ($occupied) {
            $this->warn('Ta instancja zawiera dane, które zostaną zastąpione:');
            $this->table(['Tabela', 'Wiersze'], $this->durableRowCounts());
            $this->line("Pliki źródeł: {$sourceFileCount}");
            $this->line("Pliki zdjęć osób: {$personFileCount}");

            if (! $this->confirm('Zastąpić dane z archiwum? Przed zmianą zostanie wykonana kopia bezpieczeństwa.', false)) {
                $this->error('Przerwano, nic nie zmieniono.');

                return self::FAILURE;
            }
        } elseif (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $this->stagingPath = storage_path('app/backup-temp/restore-'.Str::random(16));
        File::ensureDirectoryExists($this->stagingPath, 0700);
        chmod($this->stagingPath, 0700);

        if (! $zip->extractTo($this->stagingPath)) {
            $this->error('Nie udało się rozpakować archiwum.');

            return self::FAILURE;
        }

        $stagedDump = $this->stagingPath.'/'.$dumpEntry;

        if (! is_file($stagedDump)) {
            $this->error('Nie udało się rozpakować zrzutu bazy.');

            return self::FAILURE;
        }

        if ($occupied && ! $this->makeSafetyBackup($archivePath)) {
            return self::FAILURE;
        }

        if ($occupied) {
            Storage::disk('local')->deleteDirectory(self::SOURCES_DIRECTORY);
            Storage::disk('local')->deleteDirectory(self::PERSONS_DIRECTORY);
        }

        $this->components->info('Ładowanie bazy danych...');

        $this->call('db:wipe', ['--force' => true]);

        $psql = $this->loadDump($stagedDump);

        if (! $psql->isSuccessful()) {
            $this->error('Ładowanie zrzutu bazy nie powiodło się, baza pozostała pusta:');
            $this->line(trim($psql->getErrorOutput()));
            $this->reportSafetyArchive();

            return self::FAILURE;
        }

        $filesWritten = $this->copyStagedFiles();

        $missing = $this->missingStoredFiles();

        $this->table(['Tabela', 'Wiersze'], $this->durableRowCounts());
        $this->line("Zapisane pliki: {$filesWritten}");

        if ($missing !== []) {
            $this->error('Brakuje plików przywróconych źródeł lub zdjęć osób:');

            foreach ($missing as $path) {
                $this->line('  '.$path);
            }

            $this->reportSafetyArchive();

            return self::FAILURE;
        }

        $this->components->info('Przywrócono dane z archiwum.');

        return self::SUCCESS;
    }

    private function reportSafetyArchive(): void
    {
        if ($this->safetyArchive !== '') {
            $this->line('Kopia bezpieczeństwa sprzed przywracania: '.$this->safetyArchive);
        }
    }

    private function resolveArchivePath(string $archive): ?string
    {
        if (is_file($archive)) {
            return $archive;
        }

        $disk = Storage::disk('backups');

        return $disk->exists($archive) ? $disk->path($archive) : null;
    }

    /**
     * @return array<int, string> Entry names keyed by their index in the archive.
     */
    private function entryNames(ZipArchive $zip): array
    {
        $names = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $names[$index] = (string) $zip->getNameIndex($index);
        }

        return $names;
    }

    /**
     * Entries that are absolute, climb out of the target, or point into the backup directories.
     * The bare directory entries spatie adds for its excluded folders are harmless (only files are copied).
     *
     * @param  array<int, string>  $entries
     * @return list<string>
     */
    private function unsafeEntries(array $entries): array
    {
        $unsafe = [];

        foreach ($entries as $name) {
            $normalized = str_replace('\\', '/', $name);
            $segments = explode('/', $normalized);

            $isUnsafe = $normalized === ''
                || str_starts_with($normalized, '/')
                || preg_match('#^[A-Za-z]:#', $normalized) === 1
                || in_array('..', $segments, true)
                || (in_array($segments[0], ['backups', 'backup-temp'], true) && count(array_filter($segments)) > 1);

            if ($isUnsafe) {
                $unsafe[] = $name;
            }
        }

        return $unsafe;
    }

    private function userCount(): int
    {
        return Schema::hasTable('users') ? DB::table('users')->count() : 0;
    }

    /**
     * @return list<string>
     */
    private function durableTables(): array
    {
        $tables = DB::table('information_schema.tables')
            ->where('table_schema', 'public')
            ->where('table_type', 'BASE TABLE')
            ->orderBy('table_name')
            ->pluck('table_name')
            ->all();

        return array_values(array_diff($tables, self::EPHEMERAL_TABLES));
    }

    /**
     * @return list<array{string, int}>
     */
    private function durableRowCounts(): array
    {
        return array_map(
            fn (string $table): array => [$table, DB::table($table)->count()],
            $this->durableTables(),
        );
    }

    private function makeSafetyBackup(string $archivePath): bool
    {
        $disk = Storage::disk('backups');
        $before = $disk->allFiles();

        $this->components->info('Kopia bezpieczeństwa przed nadpisaniem danych...');

        if (basename($archivePath) === now()->format('Y-m-d-H-i-s').'.zip') {
            sleep(1);
        }

        if ($this->call('backup:run', ['--disable-notifications' => true]) !== self::SUCCESS) {
            $this->error('Kopia bezpieczeństwa nie powiodła się, nic nie zmieniono.');

            return false;
        }

        $created = array_values(array_diff($disk->allFiles(), $before));

        if ($created === [] || in_array(realpath($archivePath), array_map(fn (string $file): string|false => realpath($disk->path($file)), $created), true)) {
            $this->error('Kopia bezpieczeństwa nadpisałaby archiwum wejściowe (ta sama nazwa), nic nie zmieniono. Spróbuj ponownie za chwilę.');

            return false;
        }

        $this->safetyArchive = $disk->path($created[0]);
        $this->line('Kopia bezpieczeństwa: '.$this->safetyArchive);

        return true;
    }

    private function loadDump(string $dumpFile): Process
    {
        /** @var array<string, mixed> $connection */
        $connection = config('database.connections.'.config('database.default'));

        $environment = ['PGPASSWORD' => (string) ($connection['password'] ?? '')];

        if (! empty($connection['sslmode'])) {
            $environment['PGSSLMODE'] = (string) $connection['sslmode'];
        }

        $process = new Process([
            'psql',
            '--single-transaction',
            '-v', 'ON_ERROR_STOP=1',
            '-h', (string) $connection['host'],
            '-p', (string) $connection['port'],
            '-U', (string) $connection['username'],
            '-d', (string) $connection['database'],
            '-f', $dumpFile,
        ], env: $environment);

        $process->setTimeout(null);
        $process->run();

        return $process;
    }

    private function copyStagedFiles(): int
    {
        $written = 0;
        $target = storage_path('app');

        foreach (File::allFiles($this->stagingPath, true) as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());

            if (str_starts_with($relative, 'db-dumps/')) {
                continue;
            }

            File::ensureDirectoryExists(dirname($target.'/'.$relative));
            File::copy($file->getPathname(), $target.'/'.$relative);
            $written++;
        }

        return $written;
    }

    /**
     * The source files and person photo files the restored rows point at that are not on disk.
     *
     * @return list<string>
     */
    private function missingStoredFiles(): array
    {
        $disk = Storage::disk('local');
        $missing = [];

        foreach (DB::table('sources')->get(['path', 'thumbnail_path']) as $source) {
            foreach ([$source->path, $source->thumbnail_path] as $path) {
                if ($path !== null && ! $disk->exists($path)) {
                    $missing[] = $path;
                }
            }
        }

        foreach (DB::table('people')->get(['photo_path', 'photo_thumbnail_path']) as $person) {
            foreach ([$person->photo_path, $person->photo_thumbnail_path] as $path) {
                if ($path !== null && ! $disk->exists($path)) {
                    $missing[] = $path;
                }
            }
        }

        return $missing;
    }
}
