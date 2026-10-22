<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A gravestone no longer carries a year, an archive or an entry number on its event links.
     * The values it had are appended to its note as "Rok: …", "Archiwum: …" and one
     * "Numer wpisu: …" line per link (in `event_source.id` order), then the fields are cleared.
     * Records and other kinds are left untouched.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('sources')
                ->where('kind', 'gravestone')
                ->chunkById(200, function ($sources): void {
                    foreach ($sources as $source) {
                        $links = DB::table('event_source')
                            ->where('source_id', $source->id)
                            ->orderBy('id')
                            ->get(['id', 'entry_number'])
                            ->filter(fn ($link): bool => filled($link->entry_number) && trim((string) $link->entry_number) !== '');

                        $lines = [];

                        if (filled($source->year) && trim((string) $source->year) !== '') {
                            $lines[] = 'Rok: '.trim((string) $source->year);
                        }

                        if (filled($source->archive) && trim($source->archive) !== '') {
                            $lines[] = 'Archiwum: '.trim($source->archive);
                        }

                        foreach ($links as $link) {
                            $lines[] = 'Numer wpisu: '.trim((string) $link->entry_number);
                        }

                        $hasStoredValue = $source->year !== null || $source->archive !== null;

                        if ($lines === [] && ! $hasStoredValue && ! $this->hasAnyEntryNumber($source->id)) {
                            continue;
                        }

                        $update = ['year' => null, 'archive' => null];

                        if ($lines !== []) {
                            $block = implode("\n", $lines);
                            $update['note'] = filled($source->note) ? $source->note."\n\n".$block : $block;
                        }

                        DB::table('sources')->where('id', $source->id)->update($update);
                        DB::table('event_source')->where('source_id', $source->id)->update(['entry_number' => null]);
                    }
                });
        });
    }

    /**
     * Whether any link of the source has an entry number stored, even a blank one that has to be cleared.
     */
    private function hasAnyEntryNumber(int $sourceId): bool
    {
        return DB::table('event_source')->where('source_id', $sourceId)->whereNotNull('entry_number')->exists();
    }

    /**
     * Reverse the migrations. Not reversible: the values now live in a note the author may have
     * edited, so they cannot be restored reliably. Restore from the `backup:run --only-db` copy
     * that `scripts/deploy.sh` takes before `migrate`.
     */
    public function down(): void
    {
        //
    }
};
