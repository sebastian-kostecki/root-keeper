<?php

use App\Enums\ParticipantRole;
use App\Models\Cemetery;
use App\Models\Event;
use App\Models\Locality;
use App\Models\Person;
use App\Models\Source;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

function assignCemeteriesToBurialsMigration(): Migration
{
    static $migration = null;

    return $migration ??= require database_path('migrations/2026_10_21_100000_assign_cemeteries_to_burials.php');
}

/**
 * A burial in the pre-migration shape: a locality and no cemetery.
 */
function legacyBurial(User $user, Person $person, ?Locality $locality): Event
{
    $burial = Event::factory()->for($user->tree)->burial()->create(['locality_id' => $locality?->id]);
    $burial->participants()->attach($person, ['role' => ParticipantRole::Subject]);

    return $burial;
}

function listOnGravestone(User $user, Person $person, Cemetery $cemetery): void
{
    Source::factory()->for($user->tree)->gravestone()->create(['cemetery_id' => $cemetery->id])
        ->buriedPeople()->attach($person);
}

test('a burial without a gravestone gets the cemetery without a name in its locality', function () {
    $user = User::factory()->create();
    $luboml = Locality::factory()->for($user->tree)->create();
    $maria = Person::factory()->for($user->tree)->create();
    $burial = legacyBurial($user, $maria, $luboml);

    assignCemeteriesToBurialsMigration()->up();

    $cemetery = Cemetery::query()->where('tree_id', $user->tree->id)->sole();

    expect($cemetery->locality_id)->toBe($luboml->id);
    expect($cemetery->name)->toBeNull();
    expect($burial->refresh()->cemetery_id)->toBe($cemetery->id);
    expect($burial->locality_id)->toBe($luboml->id);
});

test('an existing cemetery without a name is reused and a named one is not', function () {
    $user = User::factory()->create();
    $luboml = Locality::factory()->for($user->tree)->create();
    Cemetery::factory()->for($user->tree)->in($luboml)->create(['name' => 'Parafialny']);
    $unnamed = Cemetery::factory()->for($user->tree)->in($luboml)->unnamed()->create();
    $maria = Person::factory()->for($user->tree)->create();
    $burial = legacyBurial($user, $maria, $luboml);

    assignCemeteriesToBurialsMigration()->up();

    expect(Cemetery::query()->count())->toBe(2);
    expect($burial->refresh()->cemetery_id)->toBe($unnamed->id);
});

test('burials in one locality share one cemetery and each tree gets its own', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $luboml = Locality::factory()->for($user->tree)->create();
    $first = legacyBurial($user, Person::factory()->for($user->tree)->create(), $luboml);
    $second = legacyBurial($user, Person::factory()->for($user->tree)->create(), $luboml);
    $theirs = legacyBurial($other, Person::factory()->for($other->tree)->create(), Locality::factory()->for($other->tree)->create());

    assignCemeteriesToBurialsMigration()->up();

    expect(Cemetery::query()->count())->toBe(2);
    expect($first->refresh()->cemetery_id)->toBe($second->refresh()->cemetery_id);
    expect($theirs->refresh()->cemetery->tree_id)->toBe($other->tree->id);
});

test('a person on a gravestone in the burial locality gets that cemetery', function () {
    $user = User::factory()->create();
    $luboml = Locality::factory()->for($user->tree)->create();
    $cemetery = Cemetery::factory()->for($user->tree)->in($luboml)->unnamed()->create();
    $jan = Person::factory()->for($user->tree)->create();
    listOnGravestone($user, $jan, $cemetery);
    $burial = legacyBurial($user, $jan, $luboml);

    assignCemeteriesToBurialsMigration()->up();

    expect($burial->refresh()->cemetery_id)->toBe($cemetery->id);
    expect($burial->locality_id)->toBe($luboml->id);
});

test('a gravestone in another locality does not decide the cemetery of the burial', function () {
    $user = User::factory()->create();
    $luboml = Locality::factory()->for($user->tree)->create();
    $other = Cemetery::factory()->for($user->tree)->create();
    $jan = Person::factory()->for($user->tree)->create();
    listOnGravestone($user, $jan, $other);
    $burial = legacyBurial($user, $jan, $luboml);

    assignCemeteriesToBurialsMigration()->up();

    expect($burial->refresh()->cemetery_id)->not->toBe($other->id);
    expect($burial->cemetery->locality_id)->toBe($luboml->id);
    expect($burial->cemetery->name)->toBeNull();
});

test('two cemeteries of two gravestones in the burial locality are not chosen between', function () {
    $user = User::factory()->create();
    $luboml = Locality::factory()->for($user->tree)->create();
    $first = Cemetery::factory()->for($user->tree)->in($luboml)->create();
    $second = Cemetery::factory()->for($user->tree)->in($luboml)->create();
    $jan = Person::factory()->for($user->tree)->create();
    listOnGravestone($user, $jan, $first);
    listOnGravestone($user, $jan, $second);
    $burial = legacyBurial($user, $jan, $luboml);

    assignCemeteriesToBurialsMigration()->up();

    expect($burial->refresh()->cemetery_id)->not->toBeIn([$first->id, $second->id]);
    expect($burial->cemetery->name)->toBeNull();
});

test('two gravestones of one cemetery still make the cemetery unambiguous', function () {
    $user = User::factory()->create();
    $luboml = Locality::factory()->for($user->tree)->create();
    $cemetery = Cemetery::factory()->for($user->tree)->in($luboml)->create();
    $jan = Person::factory()->for($user->tree)->create();
    listOnGravestone($user, $jan, $cemetery);
    listOnGravestone($user, $jan, $cemetery);
    $burial = legacyBurial($user, $jan, $luboml);

    assignCemeteriesToBurialsMigration()->up();

    expect($burial->refresh()->cemetery_id)->toBe($cemetery->id);
});

test('a burial that has a cemetery or no locality is untouched', function () {
    $user = User::factory()->create();
    $luboml = Locality::factory()->for($user->tree)->create();
    $listed = Cemetery::factory()->for($user->tree)->in($luboml)->create();
    $chosen = Cemetery::factory()->for($user->tree)->in($luboml)->create();
    $jan = Person::factory()->for($user->tree)->create();
    $ewa = Person::factory()->for($user->tree)->create();
    listOnGravestone($user, $jan, $listed);
    listOnGravestone($user, $ewa, $listed);

    $withCemetery = Event::factory()->for($user->tree)->burial()->atCemetery($chosen)->create();
    $withCemetery->participants()->attach($jan, ['role' => ParticipantRole::Subject]);
    $withoutLocality = legacyBurial($user, $ewa, null);

    assignCemeteriesToBurialsMigration()->up();

    expect($withCemetery->refresh()->cemetery_id)->toBe($chosen->id);
    expect($withoutLocality->refresh()->cemetery_id)->toBeNull();
    expect($withoutLocality->locality_id)->toBeNull();
    expect(Cemetery::query()->count())->toBe(2);
});

test('rolling the migration back changes nothing', function () {
    $user = User::factory()->create();
    $cemetery = Cemetery::factory()->for($user->tree)->create();
    $jan = Person::factory()->for($user->tree)->create();
    listOnGravestone($user, $jan, $cemetery);
    $burial = legacyBurial($user, $jan, $cemetery->locality);

    assignCemeteriesToBurialsMigration()->up();
    assignCemeteriesToBurialsMigration()->down();

    expect($burial->refresh()->cemetery_id)->toBe($cemetery->id);
});
