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
     * Creates `residences`, seeds a locality for every people.residence text the tree has no locality for
     * (same rule as the S-01 seed: the most used trimmed variant, a tie goes to the first in COLLATE "C"
     * order), then gives every person with a non-empty text one residence without years, pointing at the
     * locality matching lower(btrim(residence)). The `people.residence` text is left untouched.
     */
    public function up(): void
    {
        Schema::create('residences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tree_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('locality_id')->constrained('localities');
            $table->unsignedSmallInteger('year_from')->nullable();
            $table->unsignedSmallInteger('year_to')->nullable();
            $table->timestamps();

            $table->index('tree_id');
            $table->index('person_id');
            $table->index('locality_id');
        });

        DB::statement(<<<'SQL'
            WITH texts AS (
                SELECT tree_id, btrim(residence) AS variant FROM people
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

        DB::statement(<<<'SQL'
            INSERT INTO residences (tree_id, person_id, locality_id, created_at, updated_at)
            SELECT p.tree_id, p.id, l.id, now(), now()
            FROM people p
            JOIN localities l ON l.tree_id = p.tree_id AND lower(l.name) = lower(btrim(p.residence))
            WHERE p.residence IS NOT NULL AND btrim(p.residence) <> ''
            ORDER BY p.id
        SQL);
    }

    /**
     * Reverse the migrations.
     *
     * Localities seeded by `up()` stay: like in S-01 and S-07 they cannot be told apart from hand-added
     * ones. Residences saved after the migration are lost, and `people.residence` shows the state from
     * before the migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('residences');
    }
};
