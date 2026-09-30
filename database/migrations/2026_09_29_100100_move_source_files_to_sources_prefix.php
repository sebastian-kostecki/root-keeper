<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * File moves are not transactional, so this migration runs outside one.
     */
    public $withinTransaction = false;

    /**
     * Move every original and thumbnail from scans/{tree}/… to sources/{tree}/…
     * and repoint the rows. Row by row and idempotent: a re-run after a crash
     * finishes the job. Orphans under scans/ are swept, then scans/ is removed.
     */
    public function up(): void
    {
        $this->relocate('scans/', 'sources/');
    }

    /**
     * Mirror image of up(): sources/ back to scans/.
     */
    public function down(): void
    {
        $this->relocate('sources/', 'scans/');
    }

    private function relocate(string $from, string $to): void
    {
        $disk = Storage::disk('local');

        DB::table('sources')
            ->where(fn ($query) => $query->where('path', 'like', $from.'%')->orWhere('thumbnail_path', 'like', $from.'%'))
            ->orderBy('id')
            ->get()
            ->each(function (object $row) use ($from, $to): void {
                $updates = [];

                foreach (['path', 'thumbnail_path'] as $column) {
                    $current = $row->{$column};

                    if ($current === null || ! str_starts_with($current, $from)) {
                        continue;
                    }

                    $resolved = $this->resolve($current, $from, $to);

                    if ($resolved === null) {
                        $this->command("Source #{$row->id}: {$column} file missing ({$current})");

                        continue;
                    }

                    $updates[$column] = $resolved;
                }

                if ($updates !== []) {
                    DB::table('sources')->where('id', $row->id)->update($updates);
                }
            });

        $orphans = 0;

        foreach ($disk->allFiles(rtrim($from, '/')) as $file) {
            $this->move($file, $to.substr($file, strlen($from)));
            $orphans++;
        }

        if ($orphans > 0) {
            $this->command("Moved {$orphans} unreferenced file(s) from {$from} to {$to}");
        }

        $disk->deleteDirectory(rtrim($from, '/'));
    }

    /**
     * Move one file and return its new path, adopt an already-moved file, or
     * return null when neither the old nor the new file exists.
     */
    private function resolve(string $current, string $from, string $to): ?string
    {
        $disk = Storage::disk('local');
        $target = $to.substr($current, strlen($from));

        if ($disk->exists($current)) {
            $this->move($current, $target);

            return $target;
        }

        return $disk->exists($target) ? $target : null;
    }

    private function move(string $source, string $target): void
    {
        if (Storage::disk('local')->move($source, $target) === false) {
            throw new RuntimeException("Unable to move [{$source}] to [{$target}].");
        }
    }

    private function command(string $message): void
    {
        echo "  {$message}".PHP_EOL;
    }
};
