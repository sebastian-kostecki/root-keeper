<?php

use App\Models\Cemetery;
use App\Models\Locality;
use App\Models\Source;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function moveGravestoneCemeteriesMigration(): Migration
{
    static $migration = null;

    return $migration ??= require database_path('migrations/2026_10_20_130000_move_gravestone_cemeteries_to_cemeteries.php');
}

beforeEach(function () {
    // The schema is already migrated: step back to the shape before this migration (the test transaction undoes it).
    restoreLegacyColumns();
    moveGravestoneCemeteriesMigration()->down();
});

/**
 * A gravestone in the pre-migration shape: it names a locality in the old column and has no cemetery.
 */
function legacyGravestone(User $user, ?Locality $locality): Source
{
    $gravestone = Source::factory()->for($user->tree)->gravestone()->create();

    DB::table('sources')->where('id', $gravestone->id)->update(['cemetery_locality_id' => $locality?->id]);

    return $gravestone->refresh();
}

test('gravestones in one locality share one cemetery without a name', function () {
    $user = User::factory()->create();
    $luboml = Locality::factory()->for($user->tree)->create();
    $first = legacyGravestone($user, $luboml);
    $second = legacyGravestone($user, $luboml);

    moveGravestoneCemeteriesMigration()->up();

    $cemetery = Cemetery::query()->where('tree_id', $user->tree->id)->sole();

    expect($cemetery->locality_id)->toBe($luboml->id);
    expect($cemetery->name)->toBeNull();
    expect($first->refresh()->cemetery_id)->toBe($cemetery->id);
    expect($second->refresh()->cemetery_id)->toBe($cemetery->id);
});

test('the same locality in two trees gets one cemetery per tree', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $mine = legacyGravestone($user, Locality::factory()->for($user->tree)->create());
    $theirs = legacyGravestone($other, Locality::factory()->for($other->tree)->create());

    moveGravestoneCemeteriesMigration()->up();

    expect(Cemetery::query()->count())->toBe(2);
    expect($mine->refresh()->cemetery->tree_id)->toBe($user->tree->id);
    expect($theirs->refresh()->cemetery->tree_id)->toBe($other->tree->id);
});

test('a gravestone without a cemetery and a source of another kind stay unchanged', function () {
    $user = User::factory()->create();
    $bare = legacyGravestone($user, null);
    $other = Source::factory()->for($user->tree)->create();

    moveGravestoneCemeteriesMigration()->up();

    expect(Cemetery::query()->count())->toBe(0);
    expect($bare->refresh()->cemetery_id)->toBeNull();
    expect($other->refresh()->cemetery_id)->toBeNull();
});

test('an existing cemetery without a name is reused and a named one is left alone', function () {
    $user = User::factory()->create();
    $luboml = Locality::factory()->for($user->tree)->create();
    $unnamed = Cemetery::factory()->in($luboml)->unnamed()->create();
    $named = Cemetery::factory()->in($luboml)->create(['name' => 'Parafialny']);
    $gravestone = legacyGravestone($user, $luboml);

    moveGravestoneCemeteriesMigration()->up();

    expect(Cemetery::query()->count())->toBe(2);
    expect($gravestone->refresh()->cemetery_id)->toBe($unnamed->id);
    expect($named->refresh()->sources()->count())->toBe(0);
});

test('the old column keeps its values and loses its foreign key', function () {
    $user = User::factory()->create();
    $luboml = Locality::factory()->for($user->tree)->create();
    $gravestone = legacyGravestone($user, $luboml);

    moveGravestoneCemeteriesMigration()->up();

    expect($gravestone->refresh()->cemetery_locality_id)->toBe($luboml->id);

    DB::table('sources')->where('id', $gravestone->id)->update(['cemetery_locality_id' => 999999]);

    expect($gravestone->refresh()->cemetery_locality_id)->toBe(999999);
});

test('rolling back restores the old column from the cemeteries and its foreign key', function () {
    $user = User::factory()->create();
    $luboml = Locality::factory()->for($user->tree)->create();
    $gravestone = legacyGravestone($user, $luboml);
    $stale = legacyGravestone($user, null);

    moveGravestoneCemeteriesMigration()->up();
    DB::table('sources')->where('id', $stale->id)->update(['cemetery_locality_id' => 999999]);

    moveGravestoneCemeteriesMigration()->down();

    expect($gravestone->refresh()->cemetery_locality_id)->toBe($luboml->id);
    expect($gravestone->cemetery_id)->toBeNull();
    expect($stale->refresh()->cemetery_locality_id)->toBeNull();

    expect(fn () => DB::table('sources')->where('id', $stale->id)->update(['cemetery_locality_id' => 999999]))
        ->toThrow(QueryException::class);
});
