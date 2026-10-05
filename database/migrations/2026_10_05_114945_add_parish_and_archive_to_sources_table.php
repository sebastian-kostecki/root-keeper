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
     * Copies `provenance` into `archive` and leaves `provenance` untouched (copy first, drop later).
     */
    public function up(): void
    {
        Schema::table('sources', function (Blueprint $table) {
            // Default NO ACTION (not RESTRICT): deleting a user cascades users -> trees -> sources and
            // parishes in one statement, and NO ACTION is checked at the end of it.
            $table->foreignId('parish_id')->nullable()->constrained('parishes');
            $table->string('archive', 255)->nullable();
        });

        DB::statement("UPDATE sources SET archive = provenance WHERE archive IS NULL AND provenance IS NOT NULL AND btrim(provenance) <> ''");
    }

    /**
     * Reverse the migrations. Lossless: `provenance` is never touched, so only the new columns are dropped.
     */
    public function down(): void
    {
        Schema::table('sources', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parish_id');
            $table->dropColumn('archive');
        });
    }
};
