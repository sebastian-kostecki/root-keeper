<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the optional description of a source. Every column is nullable, so
     * existing rows need no backfill.
     */
    public function up(): void
    {
        Schema::table('sources', function (Blueprint $table) {
            $table->string('name', 255)->nullable();
            $table->string('record_type')->nullable();
            $table->string('entry_number', 50)->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('provenance', 255)->nullable();
            $table->string('url', 2048)->nullable();
            $table->text('note')->nullable();
        });
    }

    /**
     * Drop exactly the description columns.
     */
    public function down(): void
    {
        Schema::table('sources', function (Blueprint $table) {
            $table->dropColumn(['name', 'record_type', 'entry_number', 'year', 'provenance', 'url', 'note']);
        });
    }
};
