<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Schema only: no existing data is moved.
     */
    public function up(): void
    {
        Schema::table('sources', function (Blueprint $table) {
            // Default NO ACTION (not RESTRICT): deleting a user cascades users -> trees -> sources and
            // localities in one statement, and NO ACTION is checked at the end of it.
            $table->foreignId('cemetery_locality_id')->nullable()->constrained('localities');
        });

        Schema::create('buried_people', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['source_id', 'person_id']);
            $table->index('person_id');
        });
    }

    /**
     * Reverse the migrations. Cemeteries and buried-people lists entered after the migration are lost;
     * burial events created from them are ordinary events and stay.
     */
    public function down(): void
    {
        Schema::dropIfExists('buried_people');

        Schema::table('sources', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cemetery_locality_id');
        });
    }
};
