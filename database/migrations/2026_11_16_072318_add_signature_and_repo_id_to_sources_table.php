<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds the archive signature and the stable repo ID of imported sources. Sources added in the app keep
     * `null` in both, and PostgreSQL allows many `NULL`s in a unique index. No data is moved.
     */
    public function up(): void
    {
        Schema::table('sources', function (Blueprint $table) {
            $table->string('signature', 255)->nullable();
            $table->string('repo_id')->nullable();
            $table->unique(['tree_id', 'repo_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * Permanently discards the signatures and the repo IDs: afterwards a re-import can no longer tell
     * imported sources from those added in the app.
     */
    public function down(): void
    {
        Schema::table('sources', function (Blueprint $table) {
            $table->dropUnique(['tree_id', 'repo_id']);
            $table->dropColumn(['signature', 'repo_id']);
        });
    }
};
