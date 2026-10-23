<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds the date qualifier and the range's end year, then copies the qualifier from the old flag
     * (`date_is_approximate` → `approximate`, everything else keeps the `exact` default). The flag stays.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('date_qualifier', 16)->default('exact')->after('date_day');
            $table->unsignedSmallInteger('date_until_year')->nullable()->after('date_day');
        });

        DB::table('events')->where('date_is_approximate', true)->update(['date_qualifier' => 'approximate']);
    }

    /**
     * Reverse the migrations.
     *
     * The flag always matches the qualifier, so "ok." survives. "before", "after" and range dates entered after
     * the migration are lost, because the old schema cannot hold them.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['date_qualifier', 'date_until_year']);
        });
    }
};
