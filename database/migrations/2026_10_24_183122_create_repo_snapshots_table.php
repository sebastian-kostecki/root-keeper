<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds the memory of the last import: for each repo key (the same shape as `repo_id`) the values the repo held
     * then. It lives apart from the records, so it survives a record deleted in the app. No data is moved.
     */
    public function up(): void
    {
        Schema::create('repo_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tree_id')->constrained()->cascadeOnDelete();
            $table->string('repo_id');
            $table->jsonb('values');
            $table->boolean('deletion_kept')->default(false);
            $table->timestamps();

            $table->unique(['tree_id', 'repo_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * Permanently discards the memory of the last import: afterwards a re-import treats every record like one from
     * the first imports, so each difference between the repo and the app is a conflict.
     */
    public function down(): void
    {
        Schema::dropIfExists('repo_snapshots');
    }
};
