<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds empty columns; no existing row is rewritten.
     */
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->string('photo_path')->nullable();
            $table->string('photo_thumbnail_path')->nullable();
        });
    }

    /**
     * Reverse the migrations. The stored photo files stay on disk; only the paths are dropped.
     */
    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn(['photo_path', 'photo_thumbnail_path']);
        });
    }
};
