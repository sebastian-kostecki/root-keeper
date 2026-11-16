<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('person_surname_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tree_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('name', 255);
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->index('person_id');
        });

        // Blueprint has no expression index: one variant per name (case-insensitive) on a person.
        DB::statement('CREATE UNIQUE INDEX person_surname_variants_person_id_lower_name_unique ON person_surname_variants (person_id, lower(name))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('person_surname_variants');
    }
};
