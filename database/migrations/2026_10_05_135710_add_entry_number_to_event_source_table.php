<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds an empty column; no existing row is rewritten. `sources.entry_number` is left untouched (copy first, drop later).
     */
    public function up(): void
    {
        Schema::table('event_source', function (Blueprint $table) {
            $table->string('entry_number', 50)->nullable();
        });
    }

    /**
     * Reverse the migrations. Reversible but lossy: entry numbers entered on links after the deploy are lost with the column.
     */
    public function down(): void
    {
        Schema::table('event_source', function (Blueprint $table) {
            $table->dropColumn('entry_number');
        });
    }
};
