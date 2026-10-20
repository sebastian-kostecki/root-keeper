<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('localities', function (Blueprint $table) {
            $table->string('kind')->nullable()->after('name');
            $table->decimal('latitude', 8, 6)->nullable()->after('kind');
            $table->decimal('longitude', 9, 6)->nullable()->after('latitude');
            $table->text('historical_affiliation')->nullable()->after('longitude');
            $table->boolean('is_defunct')->default(false)->after('description');
            $table->string('defunct_note', 255)->nullable()->after('is_defunct');
        });
    }

    /**
     * Reverse the migrations. The details are dropped with their columns.
     */
    public function down(): void
    {
        Schema::table('localities', function (Blueprint $table) {
            $table->dropColumn(['kind', 'latitude', 'longitude', 'historical_affiliation', 'is_defunct', 'defunct_note']);
        });
    }
};
