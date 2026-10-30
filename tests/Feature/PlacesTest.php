<?php

use App\Enums\LocalityKind;
use App\Enums\ParishKind;
use App\Enums\Religion;
use App\Models\Cemetery;
use App\Models\Event;
use App\Models\Locality;
use App\Models\LocalityAlias;
use App\Models\Parish;
use App\Models\Person;
use App\Models\Residence;
use App\Models\Source;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('guests are redirected to the login page from the places page', function () {
    $this->get(route('places.index'))->assertRedirect(route('login'));
});

test('the old localities and parishes addresses redirect to the places page and keep the search and the panel', function (string $from, string $to) {
    $user = User::factory()->create();

    $this->actingAs($user)->get($from)->assertRedirect($to);
})->with([
    'localities keep the panel and the search' => ['/localities?locality=3&q=x', '/places?locality=3&q=x'],
    'localities without parameters' => ['/localities', '/places'],
    'localities drop a blank search' => ['/localities?q=%20%20&locality=new', '/places?locality=new'],
    'parishes open the parish tab' => ['/parishes?parish=5', '/places?kind=parafie&parish=5'],
    'parishes keep the search too' => ['/parishes?q=jan&parish=5', '/places?kind=parafie&parish=5&q=jan'],
    'parishes without parameters' => ['/parishes', '/places?kind=parafie'],
]);

test('the kind parameter selects the parish tab and anything else is the localities tab', function (array $query, string $expected) {
    $this->actingAs(User::factory()->create())->get(route('places.index', $query))->assertInertia(fn ($page) => $page
        ->where('filters.kind', $expected));
})->with([
    'no parameter' => [[], 'miejscowosci'],
    'parafie' => [['kind' => 'parafie'], 'parafie'],
    'unknown value' => [['kind' => 'akty'], 'miejscowosci'],
    'cmentarze' => [['kind' => 'cmentarze'], 'cmentarze'],
    'array' => [['kind' => ['parafie']], 'miejscowosci'],
]);

test('the panels answer 404 for a locality or a parish of another tree', function (string $query) {
    $attacker = User::factory()->create();
    $victim = User::factory()->create();
    $locality = Locality::factory()->for($victim->tree)->create();
    $parish = Parish::factory()->for($victim->tree)->create();

    $this->actingAs($attacker)->get(route('places.index', [$query => $query === 'locality' ? $locality->id : $parish->id]))
        ->assertNotFound();
})->with(['locality', 'parish']);

test('the index lists only the user own localities alphabetically', function () {
    $user = User::factory()->create();
    Locality::factory()->for($user->tree)->create(['name' => 'Lwów']);
    Locality::factory()->for($user->tree)->create(['name' => 'Kraków']);
    Locality::factory()->for(User::factory()->create()->tree)->create(['name' => 'Cudza']);

    $this->actingAs($user)->get(route('places.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('places/index')
            ->has('localities', 2)
            ->where('localities.0.name', 'Kraków')
            ->where('localities.1.name', 'Lwów')
            ->has('localities.0', fn ($locality) => $locality->hasAll(['id', 'name', 'kind', 'kind_label', 'latitude', 'longitude', 'historical_affiliation', 'description', 'is_defunct', 'defunct_note', 'aliases', 'usage']))
            ->where('filters.q', ''));
});

test('the list, the panel and the page carry the locality details and the kind options', function () {
    $user = User::factory()->create();
    $locality = Locality::factory()->for($user->tree)->defunct('Zajęta pod zalew 1975')->create([
        'kind' => LocalityKind::Village,
        'latitude' => 50.0614,
        'longitude' => 19.9366,
        'historical_affiliation' => 'Galicja',
    ]);

    $expected = fn ($page, string $path) => $page
        ->where("{$path}.kind", 'village')
        ->where("{$path}.kind_label", 'wieś')
        ->where("{$path}.latitude", 50.0614)
        ->where("{$path}.longitude", 19.9366)
        ->where("{$path}.historical_affiliation", 'Galicja')
        ->where("{$path}.is_defunct", true)
        ->where("{$path}.defunct_note", 'Zajęta pod zalew 1975');

    $this->actingAs($user)->get(route('places.index'))->assertInertia(fn ($page) => $expected($page, 'localities.0')
        ->where('localityKinds', LocalityKind::options()));

    $this->actingAs($user)->get(route('places.index', ['locality' => $locality->id]))
        ->assertInertia(fn ($page) => $expected($page, 'localityPanel.locality'));
});

test('the list and the panel carry the usage counts of every kind of reference', function () {
    $user = User::factory()->create();
    $locality = Locality::factory()->for($user->tree)->create();
    $person = Person::factory()->for($user->tree)->create();

    Event::factory()->for($user->tree)->birth()->create(['locality_id' => $locality->id]);
    Parish::factory()->for($user->tree)->seatedIn($locality)->create();
    Residence::factory()->for($person)->create(['tree_id' => $user->tree->id, 'locality_id' => $locality->id]);
    Cemetery::factory()->in($locality)->create();

    $this->actingAs($user)->get(route('places.index'))->assertInertia(fn ($page) => $page
        ->where('localities.0.usage.facts', 1)
        ->where('localities.0.usage.parishes', 1)
        ->where('localities.0.usage.residences', 1)
        ->where('localities.0.usage.cemeteries', 1)
        ->where('localities.0.usage.total', 4)
        ->missing('localities.0.usage.gravestones'));

    $this->actingAs($user)->get(route('places.index', ['locality' => $locality->id]))->assertInertia(fn ($page) => $page
        ->where('localityPanel.locality.usage.facts', 1)
        ->where('localityPanel.locality.usage.parishes', 1)
        ->where('localityPanel.locality.usage.residences', 1)
        ->where('localityPanel.locality.usage.cemeteries', 1)
        ->where('localityPanel.locality.usage.total', 4)
        ->missing('localityPanel.locality.usage.gravestones'));
});

test('the panel resolver returns null without a key or with a non-numeric one', function (string $query) {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('places.index').$query)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('localityPanel', null));
})->with([
    'no key' => [''],
    'letters' => ['?locality=abc'],
]);

test('the panel resolver returns an empty panel for new and the locality data for an own id', function () {
    $user = User::factory()->create();
    $locality = Locality::factory()->for($user->tree)->create(['name' => 'Lipnica', 'description' => 'Opis']);

    $this->actingAs($user)->get(route('places.index', ['locality' => 'new']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('localityPanel.locality', null));

    $this->actingAs($user)->get(route('places.index', ['locality' => $locality->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('localityPanel.locality.id', $locality->id)
            ->where('localityPanel.locality.name', 'Lipnica')
            ->where('localityPanel.locality.description', 'Opis'));
});

test('the panel resolver answers 404 for a missing id', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('places.index', ['locality' => 999999]))->assertNotFound();
});

test('the parish panel resolver returns null for a non-numeric key and answers 404 for a missing id', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('places.index', ['kind' => 'parafie', 'parish' => 'abc']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('parishPanel', null));

    $this->actingAs($user)->get(route('places.index', ['kind' => 'parafie', 'parish' => 999999]))->assertNotFound();
});

test('the index search query is trimmed, cut to 100 characters and empty for a non-string', function (array $query, string $expected) {
    $this->actingAs(User::factory()->create())->get(route('places.index', $query))->assertInertia(fn ($page) => $page
        ->where('filters.q', $expected));
})->with([
    'trimmed' => [['q' => '  łódź  '], 'łódź'],
    'cut' => [['q' => str_repeat('a', 120)], str_repeat('a', 100)],
    'array' => [['q' => ['łódź']], ''],
]);

test('a partial reload of the filters with an open panel runs no places query', function () {
    $user = User::factory()->create();
    $locality = Locality::factory()->for($user->tree)->create();

    $version = $this->actingAs($user)->get(route('places.index'), ['X-Inertia' => 'true'])->headers->get('X-Inertia-Version');

    DB::enableQueryLog();

    $response = $this->actingAs($user)->get(route('places.index', ['locality' => $locality->id, 'q' => 'łódź']), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $version,
        'X-Inertia-Partial-Data' => 'filters',
        'X-Inertia-Partial-Component' => 'places/index',
    ]);

    $localityQueries = collect(DB::getQueryLog())->filter(fn (array $log): bool => str_contains($log['query'], '"localities"') || str_contains($log['query'], '"parishes"'));

    $response->assertOk();
    expect($response->json('props.filters.q'))->toBe('łódź');
    expect($response->json('props'))->not->toHaveKey('localities')->not->toHaveKey('localityPanel');
    expect($localityQueries)->toBeEmpty();
});

test('the index lists only the user own parishes alphabetically', function () {
    $user = User::factory()->create();
    $seat = Locality::factory()->for($user->tree)->create(['name' => 'Tarnów']);
    $plock = Locality::factory()->for($user->tree)->create(['name' => 'Płock']);
    Parish::factory()->for($user->tree)->seatedIn($plock)->dedicatedTo('św. Jana')->create();
    Parish::factory()->for($user->tree)->withReligion(Religion::RomanCatholic)->seatedIn($seat)->dedicatedTo('Katedralna')->create();
    Parish::factory()->for(User::factory()->create()->tree)->create();

    $this->actingAs($user)->get(route('places.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('places/index')
            ->has('parishes', 2)
            ->where('parishes.0.label', 'Płock św. Jana')
            ->where('parishes.0.name', null)
            ->where('parishes.0.kind', 'parish')
            ->where('parishes.0.dedication', 'św. Jana')
            ->where('parishes.1.label', 'Tarnów Katedralna (rzym.-kat.)')
            ->where('parishes.1.locality_name', 'Tarnów')
            ->has('religions', count(Religion::cases()))
            ->has('parishKinds', count(ParishKind::cases()))
            ->has('localities', 2)
            ->where('localities.1.name', 'Tarnów')
            ->where('filters.q', '')
            ->where('parishPanel', null));
});

test('the panel opens for a new parish and for an existing one', function () {
    $user = User::factory()->create();
    $parish = Parish::factory()->for($user->tree)->create();

    $this->actingAs($user)->get(route('places.index', ['parish' => 'new']))
        ->assertInertia(fn ($page) => $page->where('parishPanel.parish', null));

    $this->actingAs($user)->get(route('places.index', ['kind' => 'parafie', 'parish' => $parish->id]))
        ->assertInertia(fn ($page) => $page
            ->where('parishPanel.parish.id', $parish->id)
            ->where('parishPanel.parish.name', $parish->name));
});

test('the list and the panel carry the number of acts that name the parish', function () {
    $user = User::factory()->create();
    $parish = Parish::factory()->for($user->tree)->create(['name' => 'Luboml']);
    Source::factory()->for($user->tree)->create(['parish_id' => $parish->id]);
    Source::factory()->for($user->tree)->create(['parish_id' => $parish->id]);

    $this->actingAs($user)->get(route('places.index'))
        ->assertInertia(fn ($page) => $page->where('parishes.0.sources_count', 2));

    $this->actingAs($user)->get(route('places.index', ['kind' => 'parafie', 'parish' => $parish->id]))
        ->assertInertia(fn ($page) => $page->where('parishPanel.parish.sources_count', 2));
});

test('the panels answer 404 for a cemetery of another tree', function () {
    $attacker = User::factory()->create();
    $cemetery = Cemetery::factory()->for(User::factory()->create()->tree)->create();

    $this->actingAs($attacker)->get(route('places.index', ['kind' => 'cmentarze', 'cemetery' => $cemetery->id]))->assertNotFound();
});

test('the index lists only the user own cemeteries with their usage counts', function () {
    $user = User::factory()->create();
    $locality = Locality::factory()->for($user->tree)->create(['name' => 'Luboml']);
    $cemetery = Cemetery::factory()->in($locality)->unnamed()->create();
    Source::factory()->for($user->tree)->gravestone()->create(['cemetery_id' => $cemetery->id]);
    Event::factory()->burial()->atCemetery($cemetery)->create();
    Event::factory()->burial()->atCemetery($cemetery)->create();
    Cemetery::factory()->for(User::factory()->create()->tree)->create();

    $this->actingAs($user)->get(route('places.index', ['kind' => 'cmentarze']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('cemeteries', 1)
            ->where('cemeteries.0.label', 'cmentarz Luboml')
            ->where('cemeteries.0.locality_name', 'Luboml')
            ->where('cemeteries.0.sources_count', 1)
            ->where('cemeteries.0.burials_count', 2)
            ->has('cemeteryManagers', 4)
            ->where('cemeteryPanel', null));
});

test('the cemetery panel resolver returns an empty panel for new, the cemetery for an own id and null or 404 otherwise', function () {
    $user = User::factory()->create();
    $cemetery = Cemetery::factory()->for($user->tree)->create(['name' => 'Cmentarz parafialny']);

    $this->actingAs($user)->get(route('places.index', ['kind' => 'cmentarze', 'cemetery' => 'new']))
        ->assertInertia(fn ($page) => $page->where('cemeteryPanel.cemetery', null));

    $this->actingAs($user)->get(route('places.index', ['kind' => 'cmentarze', 'cemetery' => $cemetery->id]))
        ->assertInertia(fn ($page) => $page
            ->where('cemeteryPanel.cemetery.id', $cemetery->id)
            ->where('cemeteryPanel.cemetery.name', 'Cmentarz parafialny')
            ->where('cemeteryPanel.cemetery.sources_count', 0));

    $this->actingAs($user)->get(route('places.index', ['cemetery' => 'abc']))
        ->assertInertia(fn ($page) => $page->where('cemeteryPanel', null));

    $this->actingAs($user)->get(route('places.index', ['cemetery' => 999999]))->assertNotFound();
});

test('a partial reload of the filters runs no cemetery query', function () {
    $user = User::factory()->create();

    $version = $this->actingAs($user)->get(route('places.index'), ['X-Inertia' => 'true'])->headers->get('X-Inertia-Version');

    DB::enableQueryLog();

    $response = $this->actingAs($user)->get(route('places.index', ['kind' => 'cmentarze', 'q' => 'łódź']), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $version,
        'X-Inertia-Partial-Data' => 'filters',
        'X-Inertia-Partial-Component' => 'places/index',
    ]);

    $queries = collect(DB::getQueryLog())->filter(fn (array $log): bool => str_contains($log['query'], '"cemeteries"'));

    $response->assertOk();
    expect($response->json('props'))->not->toHaveKey('cemeteries')->not->toHaveKey('cemeteryPanel');
    expect($queries)->toBeEmpty();
});

test('the places page lists the name variants of a locality, sorted, and never those of another tree', function () {
    $user = User::factory()->create();
    $locality = Locality::factory()->for($user->tree)->create(['name' => 'Bukinia']);
    $plain = Locality::factory()->for($user->tree)->create(['name' => 'Zalesie']);
    LocalityAlias::factory()->create(['tree_id' => $user->tree->id, 'locality_id' => $locality->id, 'name' => 'Bukini']);
    LocalityAlias::factory()->create(['tree_id' => $user->tree->id, 'locality_id' => $locality->id, 'name' => 'Bokinia']);

    $other = User::factory()->create();
    $foreign = Locality::factory()->for($other->tree)->create(['name' => 'Bukinia']);
    LocalityAlias::factory()->create(['tree_id' => $other->tree->id, 'locality_id' => $foreign->id, 'name' => 'Cudzy wariant']);

    $this->actingAs($user)->get(route('places.index', ['locality' => $locality->id]))->assertInertia(fn ($page) => $page
        ->where('localities.0.aliases', ['Bokinia', 'Bukini'])
        ->where('localities.1.aliases', [])
        ->where('localityPanel.locality.aliases', ['Bokinia', 'Bukini']));

    expect($plain->aliases()->count())->toBe(0);
});
