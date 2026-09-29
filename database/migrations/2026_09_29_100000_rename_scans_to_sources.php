<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rename scans to sources (and the event_scan pivot to event_source) and add
     * the kind. Pivot rows keep their ids, so every event's confirmed state is
     * preserved. Every existing row becomes a metrical record.
     */
    public function up(): void
    {
        Schema::rename('scans', 'sources');
        Schema::rename('event_scan', 'event_source');

        Schema::table('event_source', function (Blueprint $table) {
            $table->renameColumn('scan_id', 'source_id');
        });

        Schema::table('sources', function (Blueprint $table) {
            $table->string('kind')->default('metrical_record')->after('tree_id');
        });

        Schema::table('sources', function (Blueprint $table) {
            $table->string('kind')->default(null)->change();
        });
    }

    /**
     * Restore the previous schema. The kind is dropped.
     */
    public function down(): void
    {
        Schema::table('sources', function (Blueprint $table) {
            $table->dropColumn('kind');
        });

        Schema::table('event_source', function (Blueprint $table) {
            $table->renameColumn('source_id', 'scan_id');
        });

        Schema::rename('event_source', 'event_scan');
        Schema::rename('sources', 'scans');
    }
};
