<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Drops six columns that earlier migrations copied elsewhere: `people.residence` (→ `residences`),
     * `people.maiden_name` (→ `people.last_name`), `events.place` (→ `events.locality_id`),
     * `sources.entry_number` (→ `event_source.entry_number`), `sources.provenance` (→ `sources.archive`,
     * or the note of a gravestone) and `sources.cemetery_locality_id` (→ `sources.cemetery_id`).
     * Before dropping anything it counts the values that have no copy and stops when any count is above zero.
     *
     * @throws RuntimeException When a value of any of the six columns has no copy.
     */
    public function up(): void
    {
        $uncopied = array_filter($this->valuesWithoutCopy(), fn (int $count): bool => $count > 0);

        if ($uncopied !== []) {
            $details = implode('; ', array_map(
                fn (string $column, int $count): string => "{$column} (bez kopii: {$count})",
                array_keys($uncopied),
                $uncopied,
            ));

            throw new RuntimeException(
                "Nie usuwam starych kolumn: {$details}. Przenieś te wartości w nowe miejsce albo odtwórz bazę z kopii produkcji i uruchom migrate ponownie."
            );
        }

        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn(['residence', 'maiden_name']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('place');
        });

        Schema::table('sources', function (Blueprint $table) {
            $table->dropColumn(['entry_number', 'provenance', 'cemetery_locality_id']);
        });
    }

    /**
     * Reverse the migrations. Brings the six columns back empty, with their last types
     * (`cemetery_locality_id` without its foreign key, as it was before this migration).
     * The values come back only from the `backup:run --only-db` copy that `scripts/deploy.sh`
     * takes before `migrate`.
     */
    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->string('residence')->nullable();
            $table->string('maiden_name')->nullable();
        });

        Schema::table('events', function (Blueprint $table) {
            $table->string('place')->nullable();
        });

        Schema::table('sources', function (Blueprint $table) {
            $table->string('entry_number', 50)->nullable();
            $table->string('provenance', 255)->nullable();
            $table->unsignedBigInteger('cemetery_locality_id')->nullable();
        });
    }

    /**
     * How many non-empty values of each column have no copy in their new place.
     *
     * @return array<string, int>
     */
    private function valuesWithoutCopy(): array
    {
        $count = fn (string $sql): int => (int) DB::selectOne("SELECT count(*) AS total FROM {$sql}")->total;

        return [
            'people.residence' => $count(<<<'SQL'
                people
                WHERE btrim(coalesce(people.residence, '')) <> ''
                  AND NOT EXISTS (SELECT 1 FROM residences WHERE residences.person_id = people.id)
                SQL),
            'people.maiden_name' => $count(<<<'SQL'
                people
                WHERE btrim(coalesce(people.maiden_name, '')) <> ''
                  AND lower(people.maiden_name) <> lower(coalesce(people.last_name, ''))
                SQL),
            'events.place' => $count(<<<'SQL'
                events
                WHERE btrim(coalesce(events.place, '')) <> ''
                  AND events.locality_id IS NULL
                SQL),
            'sources.entry_number' => $count(<<<'SQL'
                sources
                WHERE btrim(coalesce(sources.entry_number, '')) <> ''
                  AND NOT EXISTS (
                      SELECT 1 FROM event_source
                      WHERE event_source.source_id = sources.id
                        AND btrim(coalesce(event_source.entry_number, '')) = btrim(sources.entry_number)
                  )
                SQL),
            'sources.provenance' => $count(<<<'SQL'
                sources
                WHERE btrim(coalesce(sources.provenance, '')) <> ''
                  AND btrim(coalesce(sources.archive, '')) = ''
                  AND position(btrim(sources.provenance) IN coalesce(sources.note, '')) = 0
                SQL),
            'sources.cemetery_locality_id' => $count(<<<'SQL'
                sources
                WHERE sources.cemetery_locality_id IS NOT NULL
                  AND sources.cemetery_id IS NULL
                SQL),
        ];
    }
};
