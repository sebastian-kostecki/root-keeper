<?php

use App\Enums\Religion;
use App\Models\Locality;
use App\Models\Parish;
use App\Models\Source;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('guests are redirected to the login page from parishes routes', function () {
    $parish = Parish::factory()->create();

    $this->get(route('parishes.index'))->assertRedirect(route('login'));
    $this->post(route('parishes.store'), [])->assertRedirect(route('login'));
    $this->put(route('parishes.update', $parish), [])->assertRedirect(route('login'));
    $this->postJson(route('parishes.inline.store'), [])->assertUnauthorized();
});

test('storing creates a parish with religion and seat and redirects to its panel with the toast', function () {
    $user = User::factory()->create();
    $seat = Locality::factory()->for($user->tree)->create();

    $response = $this->actingAs($user)->post(route('parishes.store'), [
        'name' => 'Św. Jana',
        'religion' => 'roman_catholic',
        'locality_id' => $seat->id,
    ]);

    $parish = $user->tree->parishes()->firstOrFail();

    $response
        ->assertRedirect(route('places.index', ['kind' => 'parafie', 'parish' => $parish->id]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Dodano parafię']);

    expect($parish->name)->toBe('Św. Jana');
    expect($parish->religion)->toBe(Religion::RomanCatholic);
    expect($parish->locality_id)->toBe($seat->id);
});

test('updating changes the fields, redirects back with the toast and clears religion and seat', function () {
    $user = User::factory()->create();
    $seat = Locality::factory()->for($user->tree)->create();
    $parish = Parish::factory()->for($user->tree)->withReligion(Religion::Orthodox)->seatedIn($seat)->create(['name' => 'Jana']);

    $this->actingAs($user)->from(route('places.index', ['kind' => 'parafie', 'parish' => $parish->id]))
        ->put(route('parishes.update', $parish), ['name' => 'Św. Jana', 'religion' => '', 'locality_id' => ''])
        ->assertRedirect(route('places.index', ['kind' => 'parafie', 'parish' => $parish->id]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Zapisano parafię']);

    $parish->refresh();

    expect($parish->name)->toBe('Św. Jana');
    expect($parish->religion)->toBeNull();
    expect($parish->locality_id)->toBeNull();
});

test('a parish may keep its own name and religion when updated', function () {
    $user = User::factory()->create();
    $parish = Parish::factory()->for($user->tree)->withReligion(Religion::Orthodox)->create(['name' => 'Św. Jana']);

    $this->actingAs($user)->put(route('parishes.update', $parish), ['name' => 'św. jana', 'religion' => 'orthodox'])
        ->assertSessionHasNoErrors();

    expect($parish->refresh()->name)->toBe('św. jana');
});

test('a name equal ignoring case is rejected when the existing parish has no religion either', function () {
    $user = User::factory()->create();
    Parish::factory()->for($user->tree)->create(['name' => 'Św. Jana']);

    $this->actingAs($user)->post(route('parishes.store'), ['name' => 'św. jana'])->assertInvalid(['name' => 'Parafia o tej nazwie i wyznaniu już istnieje.']);

    expect($user->tree->parishes()->count())->toBe(1);
});

test('the same name with another religion is allowed', function () {
    $user = User::factory()->create();
    Parish::factory()->for($user->tree)->withReligion(Religion::RomanCatholic)->create(['name' => 'Św. Jana']);

    $this->actingAs($user)->post(route('parishes.store'), ['name' => 'Św. Jana', 'religion' => 'orthodox'])
        ->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('parishes.store'), ['name' => 'Św. Jana'])
        ->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('parishes.store'), ['name' => 'ŚW. JANA', 'religion' => 'roman_catholic'])
        ->assertInvalid('name');

    expect($user->tree->parishes()->count())->toBe(3);
});

test('the same name and religion in another tree is allowed', function () {
    $user = User::factory()->create();
    Parish::factory()->for(User::factory()->create()->tree)->withReligion(Religion::Jewish)->create(['name' => 'Św. Jana']);

    $this->actingAs($user)->post(route('parishes.store'), ['name' => 'Św. Jana', 'religion' => 'jewish'])
        ->assertSessionHasNoErrors();

    expect($user->tree->parishes()->count())->toBe(1);
});

test('storing and updating validate the fields', function (array $payload, string $field) {
    $user = User::factory()->create();
    $parish = Parish::factory()->for($user->tree)->create();

    $this->actingAs($user)->post(route('parishes.store'), $payload)->assertInvalid($field);
    $this->actingAs($user)->put(route('parishes.update', $parish), $payload)->assertInvalid($field);

    expect($user->tree->parishes()->count())->toBe(1);
})->with([
    'missing name' => [['religion' => 'orthodox'], 'name'],
    'long name' => [['name' => str_repeat('a', 256)], 'name'],
    'unknown religion' => [['name' => 'Św. Jana', 'religion' => 'pastafarian'], 'religion'],
    'non-numeric seat' => [['name' => 'Św. Jana', 'locality_id' => 'abc'], 'locality_id'],
]);

test('a seat from another tree is a validation error and nothing is saved', function () {
    $user = User::factory()->create();
    $foreignLocality = Locality::factory()->for(User::factory()->create()->tree)->create();
    $parish = Parish::factory()->for($user->tree)->create();

    $this->actingAs($user)->post(route('parishes.store'), ['name' => 'Nowa', 'locality_id' => $foreignLocality->id])->assertInvalid('locality_id');
    $this->actingAs($user)->put(route('parishes.update', $parish), ['name' => 'Zmieniona', 'locality_id' => $foreignLocality->id])->assertInvalid('locality_id');
    $this->actingAs($user)->postJson(route('parishes.inline.store'), ['name' => 'Inline', 'locality_id' => $foreignLocality->id])->assertUnprocessable();

    expect($user->tree->parishes()->count())->toBe(1);
    expect($parish->refresh()->locality_id)->toBeNull();
});

test('the inline store returns the parish as 201 JSON and a duplicate is a 422 with a name error', function () {
    $user = User::factory()->create();
    $seat = Locality::factory()->for($user->tree)->create(['name' => 'Tarnów']);

    $this->actingAs($user)->postJson(route('parishes.inline.store'), [
        'name' => 'Św. Jana',
        'religion' => 'roman_catholic',
        'locality_id' => $seat->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.label', 'Św. Jana (rzymskokatolickie)')
        ->assertJsonPath('data.locality_name', 'Tarnów')
        ->assertJsonStructure(['data' => ['id', 'name', 'religion', 'religion_label', 'locality_id', 'locality_name', 'label']]);

    $this->actingAs($user)->postJson(route('parishes.inline.store'), ['name' => 'ŚW. JANA', 'religion' => 'roman_catholic'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');

    expect($user->tree->parishes()->count())->toBe(1);
});

test('deleting a user whose parish has a seat succeeds and removes the tree parishes and localities', function () {
    $user = User::factory()->create();
    $seat = Locality::factory()->for($user->tree)->create();
    Parish::factory()->for($user->tree)->seatedIn($seat)->create();
    $treeId = $user->tree->id;

    $user->delete();

    expect(Parish::query()->where('tree_id', $treeId)->count())->toBe(0);
    expect(Locality::query()->where('tree_id', $treeId)->count())->toBe(0);
});

test('guests are redirected to the login page from parish delete and merge', function () {
    $parish = Parish::factory()->create();

    $this->delete(route('parishes.destroy', $parish))->assertRedirect(route('login'));
    $this->post(route('parishes.merge', $parish), [])->assertRedirect(route('login'));
});

test('an unused parish can be deleted and the user goes back to the list with the toast', function () {
    $user = User::factory()->create();
    $parish = Parish::factory()->for($user->tree)->create();

    $this->actingAs($user)->delete(route('parishes.destroy', $parish))
        ->assertRedirect(route('places.index', ['kind' => 'parafie']))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Usunięto parafię']);

    expect(Parish::find($parish->id))->toBeNull();
});

test('a parish that an act still names cannot be deleted', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $parish = Parish::factory()->for($user->tree)->create();
    Source::factory()->for($user->tree)->create(['parish_id' => $parish->id]);

    $this->actingAs($user)->delete(route('parishes.destroy', $parish))
        ->assertSessionHasErrors(['parish' => 'Parafia jest używana — scal ją z inną zamiast usuwać.']);

    expect(Parish::find($parish->id))->not->toBeNull();
});

test('merging moves every act of the duplicate to the target and deletes the duplicate', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $duplicate = Parish::factory()->for($user->tree)->create(['name' => 'Lubomla']);
    $target = Parish::factory()->for($user->tree)->create(['name' => 'Luboml']);
    $other = Parish::factory()->for($user->tree)->create();
    $movedAct = Source::factory()->for($user->tree)->create(['parish_id' => $duplicate->id]);
    $ownAct = Source::factory()->for($user->tree)->create(['parish_id' => $target->id]);
    $otherAct = Source::factory()->for($user->tree)->create(['parish_id' => $other->id]);

    $this->actingAs($user)->post(route('parishes.merge', $duplicate), ['target_id' => $target->id])
        ->assertRedirect(route('places.index', ['kind' => 'parafie', 'parish' => $target->id]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Scalono z parafią „Luboml”']);

    expect(Parish::find($duplicate->id))->toBeNull();
    expect($movedAct->refresh()->parish_id)->toBe($target->id);
    expect($ownAct->refresh()->parish_id)->toBe($target->id);
    expect($otherAct->refresh()->parish_id)->toBe($other->id);
    expect($target->refresh()->name)->toBe('Luboml');
});

test('merging fills the empty seat of the target from the duplicate', function () {
    $user = User::factory()->create();
    $seat = Locality::factory()->for($user->tree)->create();
    $duplicate = Parish::factory()->for($user->tree)->create(['name' => 'Lubomla', 'locality_id' => $seat->id]);
    $target = Parish::factory()->for($user->tree)->create(['name' => 'Luboml', 'locality_id' => null]);

    $this->actingAs($user)->post(route('parishes.merge', $duplicate), ['target_id' => $target->id])->assertSessionHasNoErrors();

    expect($target->refresh()->locality_id)->toBe($seat->id);
});

test('merging keeps the seat of the target when it is set', function () {
    $user = User::factory()->create();
    $targetSeat = Locality::factory()->for($user->tree)->create();
    $duplicateSeat = Locality::factory()->for($user->tree)->create();
    $duplicate = Parish::factory()->for($user->tree)->create(['name' => 'Lubomla', 'locality_id' => $duplicateSeat->id]);
    $target = Parish::factory()->for($user->tree)->create(['name' => 'Luboml', 'locality_id' => $targetSeat->id]);

    $this->actingAs($user)->post(route('parishes.merge', $duplicate), ['target_id' => $target->id])->assertSessionHasNoErrors();

    expect($target->refresh()->locality_id)->toBe($targetSeat->id);
});

test('merging takes the religion of the duplicate when the target has none and no other parish has that name and religion', function () {
    $user = User::factory()->create();
    $duplicate = Parish::factory()->for($user->tree)->withReligion(Religion::RomanCatholic)->create(['name' => 'Luboml']);
    $target = Parish::factory()->for($user->tree)->create(['name' => 'Luboml']);

    $this->actingAs($user)->post(route('parishes.merge', $duplicate), ['target_id' => $target->id])->assertSessionHasNoErrors();

    expect($target->refresh()->religion)->toBe(Religion::RomanCatholic);
});

test('merging leaves the religion empty when a third parish already has the name with that religion', function () {
    $user = User::factory()->create();
    $duplicate = Parish::factory()->for($user->tree)->withReligion(Religion::RomanCatholic)->create(['name' => 'Lubomla']);
    $target = Parish::factory()->for($user->tree)->create(['name' => 'Luboml']);
    Parish::factory()->for($user->tree)->withReligion(Religion::RomanCatholic)->create(['name' => 'LUBOML']);

    $this->actingAs($user)->post(route('parishes.merge', $duplicate), ['target_id' => $target->id])
        ->assertSessionHasNoErrors();

    expect($target->refresh()->religion)->toBeNull();
    expect(Parish::find($duplicate->id))->toBeNull();
});

test('merging parishes of different religions is allowed and the target keeps its own', function () {
    $user = User::factory()->create();
    $duplicate = Parish::factory()->for($user->tree)->withReligion(Religion::GreekCatholic)->create(['name' => 'Luboml']);
    $target = Parish::factory()->for($user->tree)->withReligion(Religion::RomanCatholic)->create(['name' => 'Luboml']);

    $this->actingAs($user)->post(route('parishes.merge', $duplicate), ['target_id' => $target->id])->assertSessionHasNoErrors();

    expect($target->refresh()->religion)->toBe(Religion::RomanCatholic);
    expect(Parish::find($duplicate->id))->toBeNull();
});

test('merging refuses a target that is foreign, missing or the duplicate itself and changes nothing', function (string $kind) {
    $user = User::factory()->create();
    $duplicate = Parish::factory()->for($user->tree)->create(['name' => 'Luboml']);
    $foreign = Parish::factory()->for(User::factory()->create()->tree)->create();
    $targetId = match ($kind) {
        'foreign' => $foreign->id,
        'missing' => 999_999,
        'itself' => $duplicate->id,
    };

    $this->actingAs($user)->post(route('parishes.merge', $duplicate), ['target_id' => $targetId])
        ->assertSessionHasErrors('target_id');

    expect(Parish::find($duplicate->id))->not->toBeNull();
})->with(['foreign', 'missing', 'itself']);
