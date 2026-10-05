<?php

use App\Actions\Localities\StoreLocality;
use App\Actions\Localities\UpdateLocality;
use App\Models\Locality;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

test('guests are redirected to the login page from localities routes', function () {
    $locality = Locality::factory()->create();

    $this->get(route('localities.index'))->assertRedirect(route('login'));
    $this->post(route('localities.store'), [])->assertRedirect(route('login'));
    $this->put(route('localities.update', $locality), [])->assertRedirect(route('login'));
});

test('the index lists only the user own localities alphabetically', function () {
    $user = User::factory()->create();
    Locality::factory()->for($user->tree)->create(['name' => 'Lwów']);
    Locality::factory()->for($user->tree)->create(['name' => 'Kraków']);
    Locality::factory()->for(User::factory()->create()->tree)->create(['name' => 'Cudza']);

    $this->actingAs($user)->get(route('localities.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('localities/index')
            ->has('localities', 2)
            ->where('localities.0.name', 'Kraków')
            ->where('localities.1.name', 'Lwów')
            ->has('localities.0', fn ($locality) => $locality->hasAll(['id', 'name', 'description']))
            ->where('filters.q', ''));
});

test('storing creates a locality in the user tree and redirects to its panel with the toast', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('localities.store'), ['name' => 'Kraków', 'description' => 'Małopolska']);

    $locality = $user->tree->localities()->firstOrFail();

    $response
        ->assertRedirect(route('localities.index', ['locality' => $locality->id]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Dodano miejscowość']);

    expect($locality->name)->toBe('Kraków');
    expect($locality->description)->toBe('Małopolska');
});

test('updating changes the name and description, redirects back with the toast and stores an empty description as null', function () {
    $user = User::factory()->create();
    $locality = Locality::factory()->for($user->tree)->create(['name' => 'Krakow', 'description' => 'Stary opis']);

    $this->actingAs($user)->from(route('localities.index', ['locality' => $locality->id]))
        ->put(route('localities.update', $locality), ['name' => 'Kraków', 'description' => ''])
        ->assertRedirect(route('localities.index', ['locality' => $locality->id]))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Zapisano miejscowość']);

    $locality->refresh();

    expect($locality->name)->toBe('Kraków');
    expect($locality->description)->toBeNull();
});

test('storing and updating validate the fields', function (array $payload, string $field) {
    $user = User::factory()->create();
    $locality = Locality::factory()->for($user->tree)->create();
    $before = $user->tree->localities()->count();

    $this->actingAs($user)->post(route('localities.store'), $payload)->assertInvalid($field);
    $this->actingAs($user)->put(route('localities.update', $locality), $payload)->assertInvalid($field);

    expect($user->tree->localities()->count())->toBe($before);
})->with([
    'missing name' => [['description' => 'Opis'], 'name'],
    'long name' => [['name' => str_repeat('a', 256)], 'name'],
    'array name' => [['name' => ['Kraków', 'Lwów']], 'name'],
    'long description' => [['name' => 'Kraków', 'description' => str_repeat('a', 5001)], 'description'],
]);

test('a name differing only in letter case from an existing one in the tree is rejected', function () {
    $user = User::factory()->create();
    Locality::factory()->for($user->tree)->create(['name' => 'Kraków']);

    $response = $this->actingAs($user)->post(route('localities.store'), ['name' => 'kraków']);

    $response->assertInvalid('name');
    expect($response->getSession()->get('errors')->getBag('default')->first('name'))->toBe('Miejscowość o tej nazwie jest już w spisie.');
    expect($user->tree->localities()->count())->toBe(1);
});

test('the same name in another tree is allowed', function () {
    $user = User::factory()->create();
    Locality::factory()->for(User::factory()->create()->tree)->create(['name' => 'Kraków']);

    $this->actingAs($user)->post(route('localities.store'), ['name' => 'Kraków'])->assertSessionHasNoErrors();

    expect($user->tree->localities()->count())->toBe(1);
});

test('updating a locality to its own name in another letter case passes', function () {
    $user = User::factory()->create();
    $locality = Locality::factory()->for($user->tree)->create(['name' => 'kraków']);

    $this->actingAs($user)->put(route('localities.update', $locality), ['name' => 'Kraków'])->assertSessionHasNoErrors();

    expect($locality->refresh()->name)->toBe('Kraków');
});

test('updating a locality to the name of another one in the tree is rejected', function () {
    $user = User::factory()->create();
    Locality::factory()->for($user->tree)->create(['name' => 'Kraków']);
    $locality = Locality::factory()->for($user->tree)->create(['name' => 'Lwów']);

    $this->actingAs($user)->put(route('localities.update', $locality), ['name' => 'KRAKÓW'])->assertInvalid('name');

    expect($locality->refresh()->name)->toBe('Lwów');
});

test('a duplicate name that slipped past validation is a name error, not a server error', function () {
    $user = User::factory()->create();
    Locality::factory()->for($user->tree)->create(['name' => 'Kraków']);
    $locality = Locality::factory()->for($user->tree)->create(['name' => 'Lwów']);

    $nameError = fn (ValidationException $exception) => expect($exception->errors())
        ->toBe(['name' => ['Miejscowość o tej nazwie jest już w spisie.']]);

    expect(fn () => app(StoreLocality::class)->handle($user->tree, 'kraków', null))->toThrow($nameError);
    expect(fn () => app(UpdateLocality::class)->handle($locality, 'KRAKÓW', null))->toThrow($nameError);

    expect($user->tree->localities()->orderBy('name')->pluck('name')->all())->toBe(['Kraków', 'Lwów']);
});

test('the panel resolver returns null without a key or with a non-numeric one', function (string $query) {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('localities.index').$query)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('localityPanel', null));
})->with([
    'no key' => [''],
    'letters' => ['?locality=abc'],
]);

test('the panel resolver returns an empty panel for new and the locality data for an own id', function () {
    $user = User::factory()->create();
    $locality = Locality::factory()->for($user->tree)->create(['name' => 'Lipnica', 'description' => 'Opis']);

    $this->actingAs($user)->get(route('localities.index', ['locality' => 'new']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('localityPanel.locality', null));

    $this->actingAs($user)->get(route('localities.index', ['locality' => $locality->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('localityPanel.locality.id', $locality->id)
            ->where('localityPanel.locality.name', 'Lipnica')
            ->where('localityPanel.locality.description', 'Opis'));
});

test('the panel resolver answers 404 for a missing id', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('localities.index', ['locality' => 999999]))->assertNotFound();
});

test('the index search query is trimmed, cut to 100 characters and empty for a non-string', function (array $query, string $expected) {
    $this->actingAs(User::factory()->create())->get(route('localities.index', $query))->assertInertia(fn ($page) => $page
        ->where('filters.q', $expected));
})->with([
    'trimmed' => [['q' => '  łódź  '], 'łódź'],
    'cut' => [['q' => str_repeat('a', 120)], str_repeat('a', 100)],
    'array' => [['q' => ['łódź']], ''],
]);

test('a partial reload of the filters with an open panel runs no localities query', function () {
    $user = User::factory()->create();
    $locality = Locality::factory()->for($user->tree)->create();

    $version = $this->actingAs($user)->get(route('localities.index'), ['X-Inertia' => 'true'])->headers->get('X-Inertia-Version');

    DB::enableQueryLog();

    $response = $this->actingAs($user)->get(route('localities.index', ['locality' => $locality->id, 'q' => 'łódź']), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $version,
        'X-Inertia-Partial-Data' => 'filters',
        'X-Inertia-Partial-Component' => 'localities/index',
    ]);

    $localityQueries = collect(DB::getQueryLog())->filter(fn (array $log): bool => str_contains($log['query'], '"localities"'));

    $response->assertOk();
    expect($response->json('props.filters.q'))->toBe('łódź');
    expect($response->json('props'))->not->toHaveKey('localities')->not->toHaveKey('localityPanel');
    expect($localityQueries)->toBeEmpty();
});
