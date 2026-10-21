<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Every distinct (tree, cemetery locality) pair of the gravestones gets one cemetery
     * without a name (an existing one is reused) and the gravestones point at it. The old column keeps its values
     * but loses its foreign key, so that merging localities no longer has to move it.
     */
    public function up(): void
    {
        $pairs = DB::table('sources')
            ->whereNotNull('cemetery_locality_id')
            ->distinct()
            ->orderBy('tree_id')
            ->orderBy('cemetery_locality_id')
            ->get(['tree_id', 'cemetery_locality_id']);

        foreach ($pairs as $pair) {
            $cemeteryId = DB::table('cemeteries')
                ->where('tree_id', $pair->tree_id)
                ->where('locality_id', $pair->cemetery_locality_id)
                ->whereNull('name')
                ->value('id');

            $cemeteryId ??= DB::table('cemeteries')->insertGetId([
                'tree_id' => $pair->tree_id,
                'locality_id' => $pair->cemetery_locality_id,
                'name' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('sources')
                ->where('tree_id', $pair->tree_id)
                ->where('cemetery_locality_id', $pair->cemetery_locality_id)
                ->whereNull('cemetery_id')
                ->update(['cemetery_id' => $cemeteryId]);
        }

        Schema::table('sources', function (Blueprint $table) {
            $table->dropForeign(['cemetery_locality_id']);
        });
    }

    /**
     * Reverse the migrations. The old column takes the locality of the gravestone's cemetery; gravestones without
     * a cemetery get null, because the stored value may point at a locality that a merge has deleted since.
     * The rollback is lossy for cemetery details: names, managers, religions and descriptions disappear when the
     * earlier migration drops the cemeteries table.
     */
    public function down(): void
    {
        DB::statement('UPDATE sources SET cemetery_locality_id = (SELECT locality_id FROM cemeteries WHERE cemeteries.id = sources.cemetery_id)');
        DB::table('sources')->update(['cemetery_id' => null]);

        Schema::table('sources', function (Blueprint $table) {
            // Default NO ACTION, like the original column.
            $table->foreign('cemetery_locality_id')->references('id')->on('localities');
        });
    }
};
