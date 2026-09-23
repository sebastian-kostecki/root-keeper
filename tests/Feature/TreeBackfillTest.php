<?php

use App\Models\Tree;
use App\Models\User;

function loadTreeBackfillMigration(): object
{
    static $migration = null;

    if ($migration === null) {
        $files = glob(database_path('migrations/*_create_trees_for_existing_users.php'));

        expect($files)->not->toBeEmpty();

        $migration = require $files[0];
    }

    return $migration;
}

test('backfill migration creates a tree for a user without one', function () {
    $user = User::factory()->withoutTree()->create();

    expect(Tree::where('user_id', $user->id)->count())->toBe(0);

    loadTreeBackfillMigration()->up();

    expect(Tree::where('user_id', $user->id)->count())->toBe(1);
});

test('backfill migration does not create a second tree for a user that already has one', function () {
    $user = User::factory()->create();

    expect(Tree::where('user_id', $user->id)->count())->toBe(1);

    loadTreeBackfillMigration()->up();

    expect(Tree::where('user_id', $user->id)->count())->toBe(1);
});
