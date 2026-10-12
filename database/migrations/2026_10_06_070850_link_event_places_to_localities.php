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
     * Adds `events.locality_id`, seeds a locality for every events.place text the tree has no locality for
     * (same rule as the S-01 seed: the most used trimmed variant, a tie goes to the first in COLLATE "C"
     * order), then points every event with a non-empty place text at the locality matching
     * lower(btrim(place)). The `events.place` text is left untouched.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('locality_id')->nullable()->constrained('localities');
            $table->index('locality_id');
        });

        DB::statement(<<<'SQL'
            WITH texts AS (
                SELECT tree_id, btrim(place) AS variant FROM events
                WHERE place IS NOT NULL AND btrim(place) <> ''
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

        DB::statement(<<<'SQL'
            UPDATE events e SET locality_id = l.id
            FROM localities l
            WHERE l.tree_id = e.tree_id
                AND lower(l.name) = lower(btrim(e.place))
                AND e.place IS NOT NULL
                AND btrim(e.place) <> ''
        SQL);
    }

    /**
     * Reverse the migrations.
     *
     * Burial events only exist in the new shape (their place lives solely in `locality_id`), so they are
     * deleted. Localities seeded by `up()` stay: like in S-01 they cannot be told apart from hand-added
     * ones. Locality choices saved after the migration are lost, and `events.place` shows the state from
     * before the migration.
     */
    public function down(): void
    {
        DB::table('events')->where('type', 'burial')->delete();

        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('locality_id');
        });
    }
};
