<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One locality per tree and per distinct lower(btrim(text)) of events.place and people.residence.
     * The name is the trimmed variant used most often (events and residences together); a tie goes to
     * the first variant in COLLATE "C" order. The text columns are left untouched.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            WITH texts AS (
                SELECT tree_id, btrim(place) AS variant FROM events
                WHERE place IS NOT NULL AND btrim(place) <> ''
                UNION ALL
                SELECT tree_id, btrim(residence) FROM people
                WHERE residence IS NOT NULL AND btrim(residence) <> ''
            ), counted AS (
                SELECT tree_id, lower(variant) AS key, variant, count(*) AS uses
                FROM texts
                GROUP BY tree_id, lower(variant), variant
            )
            INSERT INTO localities (tree_id, name, created_at, updated_at)
            SELECT DISTINCT ON (tree_id, key) tree_id, variant, now(), now()
            FROM counted
            ORDER BY tree_id, key, uses DESC, variant COLLATE "C"
            ON CONFLICT (tree_id, lower(name)) DO NOTHING
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op: deleting the seeded rows would also delete the author's corrections (names, descriptions),
        // and hand-added localities cannot be told apart from seeded ones. The `localities` table is
        // dropped by its own migration.
    }
};
