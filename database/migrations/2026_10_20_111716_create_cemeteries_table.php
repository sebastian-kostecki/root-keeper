<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Schema only: no existing data is moved.
     */
    public function up(): void
    {
        Schema::create('cemeteries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tree_id')->constrained()->cascadeOnDelete();
            // Default NO ACTION (not RESTRICT): deleting a user cascades users -> trees -> cemeteries and
            // localities in one statement, and NO ACTION is checked at the end of it.
            $table->foreignId('locality_id')->constrained('localities');
            $table->string('name', 255)->nullable();
            $table->string('manager')->nullable();
            $table->string('religion')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Blueprint has no expression index: one cemetery per name (case-insensitive) in a locality of a tree,
        // where a missing name equals a missing name.
        DB::statement("CREATE UNIQUE INDEX cemeteries_tree_id_locality_id_lower_name_unique ON cemeteries (tree_id, locality_id, lower(coalesce(name, '')))");

        Schema::table('sources', function (Blueprint $table) {
            $table->foreignId('cemetery_id')->nullable()->constrained('cemeteries');
            $table->index('cemetery_id');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('cemetery_id')->nullable()->constrained('cemeteries');
            $table->index('cemetery_id');
        });
    }

    /**
     * Reverse the migrations. Cemeteries entered after the migration are lost.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cemetery_id');
        });

        Schema::table('sources', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cemetery_id');
        });

        Schema::dropIfExists('cemeteries');
    }
};
