<?php

use App\Enums\ParishKind;
use App\Models\Locality;
use App\Models\Parish;
use App\Models\Source;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

function parishSeatMigration(): Migration
{
    static $migration = null;

    return $migration ??= require database_path('migrations/2026_10_21_080725_add_kind_and_dedication_to_parishes.php');
}

beforeEach(function () {
    // The schema is already migrated: step back to the shape before this migration (the test transaction undoes it).
    parishSeatMigration()->down();
});

/**
 * A parish in the pre-migration shape: it has a name and no kind or dedication.
 */
function legacyParish(User $user, string $name, ?Locality $seat, ?string $religion = null): int
{
    return DB::table('parishes')->insertGetId([
        'tree_id' => $user->tree->id,
        'name' => $name,
        'religion' => $religion,
        'locality_id' => $seat?->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('every parish becomes a parish and only colliding ones copy the name to the dedication', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $plock = Locality::factory()->for($user->tree)->create();
    $gostynin = Locality::factory()->for($user->tree)->create();
    $foreign = Locality::factory()->for($other->tree)->create();

    $seatless = legacyParish($user, 'Parafia w Lubomlu', null, 'roman_catholic');
    $alone = legacyParish($user, 'Św. Jana', $gostynin, 'roman_catholic');
    $firstOfPair = legacyParish($user, 'Św. Bartłomieja', $plock, 'roman_catholic');
    $secondOfPair = legacyParish($user, 'Fara', $plock, 'roman_catholic');
    $otherReligion = legacyParish($user, 'Fara', $plock, 'orthodox');
    $foreignOne = legacyParish($other, 'Fara', $foreign, 'roman_catholic');
    $foreignTwo = legacyParish($other, 'Kościół', $foreign, 'roman_catholic');
    $foreignThird = legacyParish($other, 'Bez siedziby', null, 'roman_catholic');

    parishSeatMigration()->up();

    expect(DB::table('parishes')->where('kind', '!=', 'parish')->count())->toBe(0);

    $dedications = DB::table('parishes')->pluck('dedication', 'id')->all();

    expect($dedications)->toEqual([
        $seatless => null,
        $alone => null,
        $firstOfPair => 'Św. Bartłomieja',
        $secondOfPair => 'Fara',
        $otherReligion => null,
        $foreignOne => 'Fara',
        $foreignTwo => 'Kościół',
        $foreignThird => null,
    ]);
});

test('the name, religion, seat and the sources pointing at a parish stay untouched', function () {
    $user = User::factory()->create();
    $plock = Locality::factory()->for($user->tree)->create();
    $parishId = legacyParish($user, 'Św. Bartłomieja', $plock, 'roman_catholic');
    $seatlessId = legacyParish($user, 'Parafia w Lubomlu', null);
    $source = Source::factory()->for($user->tree)->create(['parish_id' => $parishId]);
    $seatlessSource = Source::factory()->for($user->tree)->create(['parish_id' => $seatlessId]);

    parishSeatMigration()->up();

    $parish = Parish::query()->findOrFail($parishId);

    expect($parish->name)->toBe('Św. Bartłomieja');
    expect($parish->religion?->value)->toBe('roman_catholic');
    expect($parish->locality_id)->toBe($plock->id);
    expect($parish->kind)->toBe(ParishKind::Parish);
    expect($source->refresh()->parish_id)->toBe($parishId);

    $seatless = Parish::query()->findOrFail($seatlessId);

    expect($seatless->name)->toBe('Parafia w Lubomlu');
    expect($seatless->locality_id)->toBeNull();
    expect($seatlessSource->refresh()->parish_id)->toBe($seatlessId);
});

test('the new key refuses a duplicate seat, kind, religion and dedication but allows another kind', function () {
    $user = User::factory()->create();
    $seat = Locality::factory()->for($user->tree)->create();

    parishSeatMigration()->up();

    $make = fn (string $kind, ?string $dedication): int => DB::table('parishes')->insertGetId([
        'tree_id' => $user->tree->id,
        'locality_id' => $seat->id,
        'kind' => $kind,
        'dedication' => $dedication,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $make('parish', 'Fara');
    $make('civil_registry', 'Fara');

    expect(fn () => $make('parish', 'FARA'))->toThrow(UniqueConstraintViolationException::class);
});

test('a parish needs a seat or a former name', function () {
    $user = User::factory()->create();

    parishSeatMigration()->up();

    expect(fn () => DB::table('parishes')->insert([
        'tree_id' => $user->tree->id,
        'name' => null,
        'locality_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('rolling back builds the name from the seat and dedication and restores the old key', function () {
    $user = User::factory()->create();
    $plock = Locality::factory()->for($user->tree)->create(['name' => 'Płock']);
    $legacyId = legacyParish($user, 'Parafia w Lubomlu', null);
    $keptId = legacyParish($user, 'Św. Jana', $plock, 'roman_catholic');

    parishSeatMigration()->up();

    $newId = DB::table('parishes')->insertGetId([
        'tree_id' => $user->tree->id,
        'locality_id' => $plock->id,
        'kind' => 'civil_registry',
        'dedication' => 'I',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    parishSeatMigration()->down();

    $names = DB::table('parishes')->pluck('name', 'id')->all();

    expect($names)->toEqual([$legacyId => 'Parafia w Lubomlu', $keptId => 'Św. Jana', $newId => 'Płock I']);

    expect(fn () => legacyParish($user, 'ŚW. JANA', $plock, 'roman_catholic'))
        ->toThrow(UniqueConstraintViolationException::class);
});
