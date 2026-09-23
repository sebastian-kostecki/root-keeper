<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        $usersWithoutTree = DB::table('users')
            ->whereNotIn('id', DB::table('trees')->select('user_id'))
            ->orderBy('id')
            ->pluck('id');

        foreach ($usersWithoutTree as $userId) {
            DB::table('trees')->insert([
                'user_id' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op: the `trees` table is dropped by its own migration.
    }
};
