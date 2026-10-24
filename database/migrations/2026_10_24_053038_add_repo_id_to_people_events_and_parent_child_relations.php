<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $tables = ['people', 'events', 'parent_child_relations'];

    /**
     * Run the migrations.
     *
     * Adds the stable repo ID of imported records. Records added in the app keep `null`, and PostgreSQL
     * allows many `NULL`s in a unique index. No data is moved.
     */
    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('repo_id')->nullable();
                $table->unique(['tree_id', 'repo_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropUnique(['tree_id', 'repo_id']);
                $table->dropColumn('repo_id');
            });
        }
    }
};
