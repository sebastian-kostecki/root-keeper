<?php

use App\Enums\Religion;
use App\Models\Locality;
use App\Models\Parish;
use App\Models\User;

test('guests are redirected to the login page from parishes routes', function () {
    $parish = Parish::factory()->create();

    $this->get(route('parishes.index'))->assertRedirect(route('login'));
    $this->post(route('parishes.store'), [])->assertRedirect(route('login'));
    $this->put(route('parishes.update', $parish), [])->assertRedirect(route('login'));
    $this->postJson(route('parishes.inline.store'), [])->assertUnauthorized();
});

test('the index lists only the user own parishes alphabetically', function () {
    $user = User::factory()->create();
    $seat = Locality::factory()->for($user->tree)->create(['name' => 'Tarnów']);
    Parish::factory()->for($user->tree)->create(['name' => 'Św. Jana']);
    Parish::factory()->for($user->tree)->withReligion(Religion::RomanCatholic)->seatedIn($seat)->create(['name' => 'Katedralna']);
    Parish::factory()->for(User::factory()->create()->tree)->create(['name' => 'Cudza']);

    $this->actingAs($user)->get(route('parishes.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('parishes/index')
            ->has('parishes', 2)
            ->where('parishes.0.name', 'Katedralna')
            ->where('parishes.0.label', 'Katedralna (rzymskokatolickie)')
            ->where('parishes.0.locality_name', 'Tarnów')
            ->where('parishes.1.label', 'Św. Jana')
            ->has('religions', count(Religion::cases()))
            ->has('localities', 1)
            ->where('localities.0.name', 'Tarnów')
            ->where('filters.q', '')
            ->where('parishPanel', null));
});

test('the panel opens for a new parish and for an existing one', function () {
    $user = User::factory()->create();
    $parish = Parish::factory()->for($user->tree)->create();

    $this->actingAs($user)->get(route('parishes.index', ['parish' => 'new']))
        ->assertInertia(fn ($page) => $page->where('parishPanel.parish', null));

    $this->actingAs($user)->get(route('parishes.index', ['parish' => $parish->id]))
        ->assertInertia(fn ($page) => $page
            ->where('parishPanel.parish.id', $parish->id)
            ->where('parishPanel.parish.name', $parish->name));
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
        ->assertRedirect(route('parishes.index', ['parish' => $parish->id]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Dodano parafię']);

    expect($parish->name)->toBe('Św. Jana');
    expect($parish->religion)->toBe(Religion::RomanCatholic);
    expect($parish->locality_id)->toBe($seat->id);
});

test('updating changes the fields, redirects back with the toast and clears religion and seat', function () {
    $user = User::factory()->create();
    $seat = Locality::factory()->for($user->tree)->create();
    $parish = Parish::factory()->for($user->tree)->withReligion(Religion::Orthodox)->seatedIn($seat)->create(['name' => 'Jana']);

    $this->actingAs($user)->from(route('parishes.index', ['parish' => $parish->id]))
        ->put(route('parishes.update', $parish), ['name' => 'Św. Jana', 'religion' => '', 'locality_id' => ''])
        ->assertRedirect(route('parishes.index', ['parish' => $parish->id]))
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
