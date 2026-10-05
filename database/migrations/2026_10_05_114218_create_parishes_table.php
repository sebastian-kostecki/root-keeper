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
        Schema::create('parishes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tree_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('religion')->nullable();
            // Default NO ACTION (not RESTRICT): deleting a user cascades users -> trees -> parishes and
            // localities in one statement, and NO ACTION is checked at the end of it.
            $table->foreignId('locality_id')->nullable()->constrained('localities');
            $table->timestamps();
        });

        // Blueprint has no expression index: one parish per name (case-insensitive) and religion in a tree,
        // where a missing religion equals a missing religion.
        DB::statement("CREATE UNIQUE INDEX parishes_tree_id_lower_name_religion_unique ON parishes (tree_id, lower(name), coalesce(religion, ''))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parishes');
    }
};
