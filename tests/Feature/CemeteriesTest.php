<?php

use App\Enums\CemeteryManager;
use App\Enums\Religion;
use App\Models\Cemetery;
use App\Models\Event;
use App\Models\Locality;
use App\Models\Source;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('guests are redirected to the login page from cemeteries routes', function () {
    $cemetery = Cemetery::factory()->create();

    $this->post(route('cemeteries.store'), [])->assertRedirect(route('login'));
    $this->put(route('cemeteries.update', $cemetery), [])->assertRedirect(route('login'));
    $this->delete(route('cemeteries.destroy', $cemetery))->assertRedirect(route('login'));
    $this->post(route('cemeteries.merge', $cemetery), [])->assertRedirect(route('login'));
    $this->postJson(route('cemeteries.inline.store'), [])->assertUnauthorized();
});

test('storing creates a cemetery with manager, religion and description and redirects to its panel with the toast', function () {
    $user = User::factory()->create();
    $locality = Locality::factory()->for($user->tree)->create(['name' => 'Luboml']);

    $response = $this->actingAs($user)->post(route('cemeteries.store'), [
        'name' => 'Cmentarz parafialny',
        'locality_id' => $locality->id,
        'manager' => 'religious',
        'religion' => 'roman_catholic',
        'description' => 'Za kościołem.',
    ]);

    $cemetery = $user->tree->cemeteries()->firstOrFail();

    $response
        ->assertRedirect(route('places.index', ['kind' => 'cmentarze', 'cemetery' => $cemetery->id]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Dodano cmentarz.']);

    expect($cemetery->name)->toBe('Cmentarz parafialny');
    expect($cemetery->locality_id)->toBe($locality->id);
    expect($cemetery->manager)->toBe(CemeteryManager::Religious);
    expect($cemetery->religion)->toBe(Religion::RomanCatholic);
    expect($cemetery->description)->toBe('Za kościołem.');
});

test('a blank name is stored as null and the label falls back to the locality', function () {
    $user = User::factory()->create();
    $locality = Locality::factory()->for($user->tree)->create(['name' => 'Luboml']);

    $this->actingAs($user)->post(route('cemeteries.store'), ['name' => '   ', 'locality_id' => $locality->id])->assertSessionHasNoErrors();
    $this->actingAs($user)->postJson(route('cemeteries.inline.store'), ['name' => '', 'locality_id' => Locality::factory()->for($user->tree)->create(['name' => 'Tarnów'])->id])
        ->assertCreated()
        ->assertJsonPath('data.name', null)
        ->assertJsonPath('data.label', 'cmentarz Tarnów');

    expect($user->tree->cemeteries()->whereNull('name')->count())->toBe(2);
    expect($user->tree->cemeteries()->where('name', '')->count())->toBe(0);
});

test('updating changes the fields, redirects back with the toast and clears the optional ones', function () {
    $user = User::factory()->create();
    $locality = Locality::factory()->for($user->tree)->create();
    $other = Locality::factory()->for($user->tree)->create();
    $cemetery = Cemetery::factory()->in($locality)->withManager(CemeteryManager::War)->withReligion(Religion::Orthodox)->create(['name' => 'Stary', 'description' => 'Opis']);

    $this->actingAs($user)->from(route('places.index', ['kind' => 'cmentarze', 'cemetery' => $cemetery->id]))
        ->put(route('cemeteries.update', $cemetery), ['name' => '', 'locality_id' => $other->id, 'manager' => '', 'religion' => '', 'description' => ''])
        ->assertRedirect(route('places.index', ['kind' => 'cmentarze', 'cemetery' => $cemetery->id]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Zapisano cmentarz.']);

    $cemetery->refresh();

    expect($cemetery->name)->toBeNull();
    expect($cemetery->locality_id)->toBe($other->id);
    expect($cemetery->manager)->toBeNull();
    expect($cemetery->religion)->toBeNull();
    expect($cemetery->description)->toBeNull();
});

test('a cemetery may keep its own name when updated', function () {
    $user = User::factory()->create();
    $cemetery = Cemetery::factory()->for($user->tree)->create(['name' => 'Parafialny']);

    $this->actingAs($user)->put(route('cemeteries.update', $cemetery), ['name' => 'PARAFIALNY', 'locality_id' => $cemetery->locality_id])
        ->assertSessionHasNoErrors();

    expect($cemetery->refresh()->name)->toBe('PARAFIALNY');
});

test('the same name in another locality is allowed and in the same locality rejected ignoring letter case', function () {
    $user = User::factory()->create();
    $first = Locality::factory()->for($user->tree)->create();
    $second = Locality::factory()->for($user->tree)->create();
    Cemetery::factory()->in($first)->create(['name' => 'Parafialny']);

    $this->actingAs($user)->post(route('cemeteries.store'), ['name' => 'Parafialny', 'locality_id' => $second->id])->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('cemeteries.store'), ['name' => 'PARAFIALNY', 'locality_id' => $first->id])
        ->assertInvalid(['name' => 'W tej miejscowości jest już cmentarz o tej nazwie.']);

    expect($user->tree->cemeteries()->count())->toBe(2);
});

test('a second unnamed cemetery in one locality is rejected, in another locality allowed', function () {
    $user = User::factory()->create();
    $first = Locality::factory()->for($user->tree)->create();
    $second = Locality::factory()->for($user->tree)->create();
    Cemetery::factory()->in($first)->unnamed()->create();

    $this->actingAs($user)->post(route('cemeteries.store'), ['locality_id' => $first->id])
        ->assertInvalid(['name' => 'W tej miejscowości jest już cmentarz bez nazwy — nadaj nazwę.']);
    $this->actingAs($user)->post(route('cemeteries.store'), ['name' => '', 'locality_id' => $second->id])->assertSessionHasNoErrors();

    expect($user->tree->cemeteries()->count())->toBe(2);
});

test('the same name in a locality of another tree is irrelevant', function () {
    $user = User::factory()->create();
    $locality = Locality::factory()->for($user->tree)->create();
    Cemetery::factory()->for(User::factory()->create()->tree)->create(['name' => 'Parafialny']);

    $this->actingAs($user)->post(route('cemeteries.store'), ['name' => 'Parafialny', 'locality_id' => $locality->id])->assertSessionHasNoErrors();

    expect($user->tree->cemeteries()->count())->toBe(1);
});

test('storing and updating validate the fields', function (array $payload, string $field) {
    $user = User::factory()->create();
    $locality = Locality::factory()->for($user->tree)->create();
    $cemetery = Cemetery::factory()->in($locality)->create();

    $payload = array_merge(['locality_id' => $locality->id], $payload);

    $this->actingAs($user)->post(route('cemeteries.store'), $payload)->assertInvalid($field);
    $this->actingAs($user)->put(route('cemeteries.update', $cemetery), $payload)->assertInvalid($field);

    expect($user->tree->cemeteries()->count())->toBe(1);
})->with([
    'long name' => [['name' => str_repeat('a', 256)], 'name'],
    'unknown manager' => [['name' => 'X', 'manager' => 'private'], 'manager'],
    'unknown religion' => [['name' => 'X', 'religion' => 'pastafarian'], 'religion'],
    'long description' => [['name' => 'X', 'description' => str_repeat('a', 5001)], 'description'],
    'missing locality' => [['name' => 'X', 'locality_id' => null], 'locality_id'],
    'non-numeric locality' => [['name' => 'X', 'locality_id' => 'abc'], 'locality_id'],
]);

test('a locality from another tree is a validation error and nothing is saved', function () {
    $user = User::factory()->create();
    $foreignLocality = Locality::factory()->for(User::factory()->create()->tree)->create();
    $cemetery = Cemetery::factory()->for($user->tree)->create();
    $ownLocalityId = $cemetery->locality_id;

    $this->actingAs($user)->post(route('cemeteries.store'), ['name' => 'Nowy', 'locality_id' => $foreignLocality->id])->assertInvalid('locality_id');
    $this->actingAs($user)->put(route('cemeteries.update', $cemetery), ['name' => 'Zmieniony', 'locality_id' => $foreignLocality->id])->assertInvalid('locality_id');
    $this->actingAs($user)->postJson(route('cemeteries.inline.store'), ['name' => 'Inline', 'locality_id' => $foreignLocality->id])->assertUnprocessable();

    expect($user->tree->cemeteries()->count())->toBe(1);
    expect($cemetery->refresh()->locality_id)->toBe($ownLocalityId);
});

test('the inline store returns the cemetery as 201 JSON and a duplicate is a 422 with a name error', function () {
    $user = User::factory()->create();
    $locality = Locality::factory()->for($user->tree)->create(['name' => 'Tarnów']);

    $this->actingAs($user)->postJson(route('cemeteries.inline.store'), [
        'name' => 'Parafialny',
        'locality_id' => $locality->id,
        'manager' => 'municipal',
    ])
        ->assertCreated()
        ->assertJsonPath('data.label', 'Parafialny, Tarnów')
        ->assertJsonPath('data.locality_name', 'Tarnów')
        ->assertJsonPath('data.manager_label', 'komunalny')
        ->assertJsonStructure(['data' => ['id', 'name', 'locality_id', 'locality_name', 'manager', 'manager_label', 'religion', 'religion_label', 'description', 'label']]);

    $this->actingAs($user)->postJson(route('cemeteries.inline.store'), ['name' => 'PARAFIALNY', 'locality_id' => $locality->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');

    expect($user->tree->cemeteries()->count())->toBe(1);
});

test('moving a cemetery to another locality moves the locality of its burials too', function () {
    $user = User::factory()->create();
    $from = Locality::factory()->for($user->tree)->create();
    $to = Locality::factory()->for($user->tree)->create();
    $cemetery = Cemetery::factory()->in($from)->create();
    $burial = Event::factory()->burial()->atCemetery($cemetery)->create();
    $plainBurial = Event::factory()->for($user->tree)->burial()->create(['locality_id' => $from->id]);

    $this->actingAs($user)->put(route('cemeteries.update', $cemetery), ['name' => $cemetery->name, 'locality_id' => $to->id])->assertSessionHasNoErrors();

    expect($burial->refresh()->locality_id)->toBe($to->id);
    expect($plainBurial->refresh()->locality_id)->toBe($from->id);
});

test('deleting a user whose cemetery lies in a locality removes the tree cemeteries and localities', function () {
    $user = User::factory()->create();
    Cemetery::factory()->for($user->tree)->create();
    $treeId = $user->tree->id;

    $user->delete();

    expect(Cemetery::query()->where('tree_id', $treeId)->count())->toBe(0);
    expect(Locality::query()->where('tree_id', $treeId)->count())->toBe(0);
});

test('an unused cemetery can be deleted and the user goes back to the cemeteries tab with the toast', function () {
    $user = User::factory()->create();
    $cemetery = Cemetery::factory()->for($user->tree)->create();

    $this->actingAs($user)->delete(route('cemeteries.destroy', $cemetery))
        ->assertRedirect(route('places.index', ['kind' => 'cmentarze']))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Usunięto cmentarz.']);

    expect(Cemetery::find($cemetery->id))->toBeNull();
});

test('a cemetery that a gravestone or a burial still names cannot be deleted', function (string $kind) {
    Storage::fake('local');

    $user = User::factory()->create();
    $cemetery = Cemetery::factory()->for($user->tree)->create();

    match ($kind) {
        'gravestone' => Source::factory()->for($user->tree)->gravestone()->create(['cemetery_id' => $cemetery->id]),
        'burial' => Event::factory()->burial()->atCemetery($cemetery)->create(),
    };

    $this->actingAs($user)->delete(route('cemeteries.destroy', $cemetery))
        ->assertSessionHasErrors(['cemetery' => 'Cmentarz jest używany — scal go z innym zamiast usuwać.']);

    expect(Cemetery::find($cemetery->id))->not->toBeNull();
})->with(['gravestone', 'burial']);

test('merging moves gravestones and burials to the target, also across localities, and deletes the duplicate', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $duplicatePlace = Locality::factory()->for($user->tree)->create();
    $targetPlace = Locality::factory()->for($user->tree)->create(['name' => 'Luboml']);
    $duplicate = Cemetery::factory()->in($duplicatePlace)->create();
    $target = Cemetery::factory()->in($targetPlace)->unnamed()->create();
    $other = Cemetery::factory()->for($user->tree)->create();
    $movedGravestone = Source::factory()->for($user->tree)->gravestone()->create(['cemetery_id' => $duplicate->id]);
    $otherGravestone = Source::factory()->for($user->tree)->gravestone()->create(['cemetery_id' => $other->id]);
    $movedBurial = Event::factory()->burial()->atCemetery($duplicate)->create();
    $otherBurial = Event::factory()->burial()->atCemetery($other)->create();

    $this->actingAs($user)->post(route('cemeteries.merge', $duplicate), ['target_id' => $target->id])
        ->assertRedirect(route('places.index', ['kind' => 'cmentarze', 'cemetery' => $target->id]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Scalono z cmentarzem „cmentarz Luboml”']);

    expect(Cemetery::find($duplicate->id))->toBeNull();
    expect($movedGravestone->refresh()->cemetery_id)->toBe($target->id);
    expect($movedBurial->refresh()->cemetery_id)->toBe($target->id);
    expect($movedBurial->locality_id)->toBe($targetPlace->id);
    expect($otherGravestone->refresh()->cemetery_id)->toBe($other->id);
    expect($otherBurial->refresh()->cemetery_id)->toBe($other->id);
    expect($otherBurial->locality_id)->toBe($other->locality_id);
    expect($target->refresh()->locality_id)->toBe($targetPlace->id);
});

test('merging fills the empty manager and religion of the target and joins the descriptions', function () {
    $user = User::factory()->create();
    $duplicate = Cemetery::factory()->for($user->tree)->withManager(CemeteryManager::War)->withReligion(Religion::Jewish)->create(['description' => 'Z duplikatu']);
    $target = Cemetery::factory()->for($user->tree)->withManager(CemeteryManager::Municipal)->create(['description' => 'Z celu']);

    $this->actingAs($user)->post(route('cemeteries.merge', $duplicate), ['target_id' => $target->id])->assertSessionHasNoErrors();

    $target->refresh();

    expect($target->manager)->toBe(CemeteryManager::Municipal);
    expect($target->religion)->toBe(Religion::Jewish);
    expect($target->description)->toBe("Z celu\n\nZ duplikatu");
});

test('merging refuses a target that is foreign, missing or the duplicate itself and changes nothing', function (string $kind) {
    $user = User::factory()->create();
    $duplicate = Cemetery::factory()->for($user->tree)->create();
    $foreign = Cemetery::factory()->for(User::factory()->create()->tree)->create();
    $targetId = match ($kind) {
        'foreign' => $foreign->id,
        'missing' => 999_999,
        'itself' => $duplicate->id,
    };

    $this->actingAs($user)->post(route('cemeteries.merge', $duplicate), ['target_id' => $targetId])
        ->assertSessionHasErrors('target_id');

    expect(Cemetery::find($duplicate->id))->not->toBeNull();
})->with(['foreign', 'missing', 'itself']);

test('another user cemetery is a 404 for update, merge and delete', function () {
    $attacker = User::factory()->create();
    $victim = Cemetery::factory()->for(User::factory()->create()->tree)->create();
    $own = Cemetery::factory()->for($attacker->tree)->create();

    $this->actingAs($attacker)->put(route('cemeteries.update', $victim), ['name' => 'X', 'locality_id' => $own->locality_id])->assertNotFound();
    $this->actingAs($attacker)->post(route('cemeteries.merge', $victim), ['target_id' => $own->id])->assertNotFound();
    $this->actingAs($attacker)->delete(route('cemeteries.destroy', $victim))->assertNotFound();

    expect(Cemetery::find($victim->id))->not->toBeNull();
});
