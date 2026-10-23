<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The old entry number of a source (`sources.entry_number`) moves to its link with a fact
     * (`event_source.entry_number`) when the source has exactly one link and that link has no
     * number yet. Gravestones are skipped, because their links never carry an entry number.
     * `sources.entry_number` is left untouched (copy first, drop later); any other case keeps
     * the old number only in the source, and the migration that drops the column stops on it.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::statement(<<<'SQL'
                UPDATE event_source
                SET entry_number = btrim(sources.entry_number)
                FROM sources
                WHERE sources.id = event_source.source_id
                  AND sources.kind <> 'gravestone'
                  AND btrim(coalesce(sources.entry_number, '')) <> ''
                  AND btrim(coalesce(event_source.entry_number, '')) = ''
                  AND (SELECT count(*) FROM event_source AS links WHERE links.source_id = sources.id) = 1
                SQL);
        });
    }

    /**
     * Reverse the migrations.
     *
     * Clears the number on the only link of a source when it equals the source's old entry number.
     * A number the author typed by hand with the same value cannot be told apart and is cleared too,
     * but nothing is lost: the same value still sits in `sources.entry_number`. After the later
     * migration that drops that column is rolled back, the column is empty and nothing is cleared.
     */
    public function down(): void
    {
        DB::transaction(function (): void {
            DB::statement(<<<'SQL'
                UPDATE event_source
                SET entry_number = NULL
                FROM sources
                WHERE sources.id = event_source.source_id
                  AND sources.kind <> 'gravestone'
                  AND btrim(coalesce(sources.entry_number, '')) <> ''
                  AND event_source.entry_number = btrim(sources.entry_number)
                  AND (SELECT count(*) FROM event_source AS links WHERE links.source_id = sources.id) = 1
                SQL);
        });
    }
};
