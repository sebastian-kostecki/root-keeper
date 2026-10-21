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
     * The key moves from the name to the seat. Copy first, drop later: `name` stays as the former name, read-only,
     * until the author clears it. Parishes that would collide under the new key (same tree, seat and religion, the
     * old key kept their names apart) get their name copied to the dedication, which keeps the new key unique.
     */
    public function up(): void
    {
        Schema::table('parishes', function (Blueprint $table) {
            $table->string('kind')->default('parish')->after('name');
            $table->string('dedication', 255)->nullable()->after('kind');
            $table->string('name', 255)->nullable()->change();
        });

        DB::statement(<<<'SQL'
            UPDATE parishes SET dedication = name
            WHERE locality_id IS NOT NULL AND (tree_id, locality_id, coalesce(religion, '')) IN (
                SELECT tree_id, locality_id, coalesce(religion, '')
                FROM parishes
                WHERE locality_id IS NOT NULL
                GROUP BY tree_id, locality_id, coalesce(religion, '')
                HAVING count(*) > 1
            )
        SQL);

        DB::statement('DROP INDEX parishes_tree_id_lower_name_religion_unique');

        // Blueprint has no expression or partial index: a row without a seat (legacy only) stays out of the key.
        DB::statement("CREATE UNIQUE INDEX parishes_tree_seat_kind_religion_dedication_unique ON parishes (tree_id, locality_id, kind, coalesce(religion, ''), lower(coalesce(dedication, ''))) WHERE locality_id IS NOT NULL");

        DB::statement('ALTER TABLE parishes ADD CONSTRAINT parishes_seat_or_name_check CHECK (locality_id IS NOT NULL OR name IS NOT NULL)');
    }

    /**
     * Reverse the migrations.
     *
     * Restoring the old unique index fails when parishes created after the migration share a name and religion
     * (for instance the same seat as the name, different kinds); merge them first.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE parishes DROP CONSTRAINT parishes_seat_or_name_check');
        DB::statement('DROP INDEX parishes_tree_seat_kind_religion_dedication_unique');

        DB::statement(<<<'SQL'
            UPDATE parishes SET name = trim(concat_ws(' ', localities.name, parishes.dedication))
            FROM localities
            WHERE parishes.locality_id = localities.id AND parishes.name IS NULL
        SQL);

        Schema::table('parishes', function (Blueprint $table) {
            $table->string('name', 255)->nullable(false)->change();
        });

        DB::statement("CREATE UNIQUE INDEX parishes_tree_id_lower_name_religion_unique ON parishes (tree_id, lower(name), coalesce(religion, ''))");

        Schema::table('parishes', function (Blueprint $table) {
            $table->dropColumn(['kind', 'dedication']);
        });
    }
};
