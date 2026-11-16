<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * E-mail verification becomes mandatory (User implements MustVerifyEmail). Accounts created
 * before it was enforced were never asked to verify, so treat them as verified rather than
 * locking them out of the tree or letting model:prune remove them.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // Nothing to undo: which accounts were unverified before is not recorded.
    }
};
