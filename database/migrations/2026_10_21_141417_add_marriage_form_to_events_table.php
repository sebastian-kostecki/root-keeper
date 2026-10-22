<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Null means an unknown form, so existing marriages need no data copy. Only meaningful for `type = marriage`.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('marriage_form')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('marriage_form');
        });
    }
};
