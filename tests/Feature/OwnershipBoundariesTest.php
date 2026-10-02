<?php

use App\Enums\ParticipantRole;
use App\Models\Event;
use App\Models\ParentChildRelation;
use App\Models\Person;
use App\Models\Source;
use App\Models\User;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/**
 * Records of the two trees a route case is built from: the victim's (whose ids go into the address)
 * and the attacker's own (whose ids make the body "valid").
 *
 * @return array<string, mixed>
 */
function ownershipRecords(User $attacker, User $victim): array
{
    $victimPerson = Person::factory()->for($victim->tree)->create();
    $victimSpouse = Person::factory()->for($victim->tree)->create();
    $victimChild = Person::factory()->for($victim->tree)->create();

    $victimSource = Source::factory()->for($victim->tree)->withThumbnail()->create();
    Storage::disk('local')->put($victimSource->path, 'original-bytes');
    Storage::disk('local')->put($victimSource->thumbnail_path, 'thumbnail-bytes');

    $victimEvent = Event::factory()->for($victim->tree)->create();
    $victimEvent->participants()->attach($victimPerson, ['role' => ParticipantRole::Subject]);
    $victimEvent->sources()->attach($victimSource);

    $victimMarriage = attachMarriage($victimPerson, $victimSpouse, 1900);
    $victimMarriage->sources()->attach($victimSource);

    $ownEvent = Event::factory()->for($attacker->tree)->create();
    $ownSource = Source::factory()->for($attacker->tree)->create();

    // Cross-linked on purpose so that events.sources.destroy has a pivot row to (wrongly) remove.
    $victimEvent->sources()->attach($ownSource);
    $ownEvent->sources()->attach($victimSource);

    return [
        'victimPerson' => $victimPerson,
        'victimSource' => $victimSource,
        'victimEvent' => $victimEvent,
        'victimMarriage' => $victimMarriage,
        'victimRelation' => ParentChildRelation::factory()->create([
            'tree_id' => $victim->tree->id,
            'parent_id' => $victimPerson->id,
            'child_id' => $victimChild->id,
        ]),
        'ownPerson' => Person::factory()->for($attacker->tree)->create(),
        'ownSource' => $ownSource,
        'ownEvent' => $ownEvent,
    ];
}

/**
 * Every route with a record id in the address; another tree's id must be a 404 whatever the body holds.
 * One entry per case: route name, HTTP method, closure building the address parameters, closure building the body.
 * The completeness guard below reads the same list.
 *
 * @return array<string, array{0: string, 1: string, 2: Closure(array<string, mixed>): array<string, mixed>, 3: Closure(array<string, mixed>): array<string, mixed>}>
 */
function ownershipRouteCases(): array
{
    $noBody = fn (array $r): array => [];

    $cases = [];

    $withBodies = [
        ['persons.parents.store', 'post', fn (array $r): array => ['person' => $r['victimPerson']->id], fn (array $r): array => ['role' => 'parent', 'first_name' => 'Anna']],
        ['persons.children.attach', 'post', fn (array $r): array => ['person' => $r['victimPerson']->id], fn (array $r): array => ['child_id' => $r['ownPerson']->id]],
        ['persons.children.store', 'post', fn (array $r): array => ['person' => $r['victimPerson']->id], fn (array $r): array => ['first_name' => 'Anna']],
        ['persons.spouses.attach', 'post', fn (array $r): array => ['person' => $r['victimPerson']->id], fn (array $r): array => ['spouse_id' => $r['ownPerson']->id]],
        ['persons.spouses.store', 'post', fn (array $r): array => ['person' => $r['victimPerson']->id], fn (array $r): array => ['first_name' => 'Anna']],
        ['persons.update', 'put', fn (array $r): array => ['person' => $r['victimPerson']->id], fn (array $r): array => ['first_name' => 'Hacked']],
        ['sources.update', 'put', fn (array $r): array => ['source' => $r['victimSource']->id], fn (array $r): array => ['kind' => 'other', 'name' => 'Przejęte']],
        ['events.sources.store', 'post', fn (array $r): array => ['event' => $r['victimEvent']->id], fn (array $r): array => ['source_id' => $r['ownSource']->id]],
        ['marriages.update', 'put', fn (array $r): array => ['marriage' => $r['victimMarriage']->id], fn (array $r): array => ['marriage' => ['year' => 1950]]],
    ];

    foreach ($withBodies as [$name, $method, $parameters, $validBody]) {
        $cases["$name / poprawna treść"] = [$name, $method, $parameters, $validBody];
        $cases["$name / pusta treść"] = [$name, $method, $parameters, $noBody];
    }

    $withoutBody = [
        ['tree.show', 'get', fn (array $r): array => ['person' => $r['victimPerson']->id]],
        ['sources.show', 'get', fn (array $r): array => ['source' => $r['victimSource']->id]],
        ['sources.file', 'get', fn (array $r): array => ['source' => $r['victimSource']->id]],
        ['sources.thumbnail', 'get', fn (array $r): array => ['source' => $r['victimSource']->id]],
        ['tree.root-person.update', 'put', fn (array $r): array => ['person' => $r['victimPerson']->id]],
        ['persons.destroy', 'delete', fn (array $r): array => ['person' => $r['victimPerson']->id]],
        ['sources.destroy', 'delete', fn (array $r): array => ['source' => $r['victimSource']->id]],
        ['parent-child-relations.destroy', 'delete', fn (array $r): array => ['parent_child_relation' => $r['victimRelation']->id]],
        ['marriages.destroy', 'delete', fn (array $r): array => ['marriage' => $r['victimMarriage']->id]],
    ];

    foreach ($withoutBody as [$name, $method, $parameters]) {
        $cases["$name / brak treści"] = [$name, $method, $parameters, $noBody];
    }

    $cases['events.sources.destroy / cudze wydarzenie i źródło atakującego'] = [
        'events.sources.destroy', 'delete',
        fn (array $r): array => ['event' => $r['victimEvent']->id, 'source' => $r['ownSource']->id],
        $noBody,
    ];
    $cases['events.sources.destroy / wydarzenie atakującego i cudze źródło'] = [
        'events.sources.destroy', 'delete',
        fn (array $r): array => ['event' => $r['ownEvent']->id, 'source' => $r['victimSource']->id],
        $noBody,
    ];

    return $cases;
}

it('answers 404 and writes nothing when the address holds another tree\'s id', function (string $route, string $method, Closure $parameters, Closure $body) {
    Storage::fake('local');

    $attacker = User::factory()->create();
    $victim = User::factory()->create();
    $records = ownershipRecords($attacker, $victim);

    $before = ownershipSnapshot();

    $this->actingAs($attacker)
        ->{$method}(route($route, $parameters($records)), $body($records))
        ->assertNotFound();

    expect(ownershipSnapshot())->toBe($before);
})->with(ownershipRouteCases());

it('has a case for every controller route that carries an id in the address', function () {
    $routed = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (LaravelRoute $route): bool => str_starts_with(ltrim($route->getActionName(), '\\'), 'App\\Http\\Controllers')
            && str_contains($route->uri(), '{'))
        ->map(fn (LaravelRoute $route): ?string => $route->getName())
        ->unique()
        ->sort()
        ->values()
        ->all();

    $covered = collect(ownershipRouteCases())->pluck(0)->unique()->sort()->values()->all();

    $missing = array_values(array_diff($routed, $covered));
    $surplus = array_values(array_diff($covered, $routed));

    expect([$missing, $surplus])->toBe([[], []], 'missing: '.implode(', ', $missing).'; surplus: '.implode(', ', $surplus));
});
