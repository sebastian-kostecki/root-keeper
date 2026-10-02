<?php

use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Routes whose address carries the id of a record; another user's id must be a 404 whatever the body holds.
 *
 * @return array<string, array{0: string, 1: string, 2: Closure(Person): array<string, mixed>, 3: Closure(Person): array<string, mixed>}>
 */
function nestedPersonRoutes(): array
{
    $victimPerson = fn (Person $victim): array => ['person' => $victim->id];

    $routes = [
        'persons.parents.store' => ['post', fn (Person $own): array => ['role' => 'parent', 'first_name' => 'Anna']],
        'persons.children.attach' => ['post', fn (Person $own): array => ['child_id' => $own->id]],
        'persons.children.store' => ['post', fn (Person $own): array => ['first_name' => 'Anna']],
        'persons.spouses.attach' => ['post', fn (Person $own): array => ['spouse_id' => $own->id]],
        'persons.spouses.store' => ['post', fn (Person $own): array => ['first_name' => 'Anna']],
    ];

    $cases = [];

    foreach ($routes as $name => [$method, $validBody]) {
        $cases["$name / poprawna treść"] = [$name, $method, $victimPerson, $validBody];
        $cases["$name / pusta treść"] = [$name, $method, $victimPerson, fn (Person $own): array => []];
    }

    return $cases;
}

it('answers 404 and writes nothing when the address holds another tree\'s id', function (string $route, string $method, Closure $parameters, Closure $body) {
    Storage::fake('local');

    $attacker = User::factory()->create();
    $victim = User::factory()->create();
    $victimPerson = Person::factory()->for($victim->tree)->create();
    $ownPerson = Person::factory()->for($attacker->tree)->create();

    $before = ownershipSnapshot();

    $this->actingAs($attacker)
        ->{$method}(route($route, $parameters($victimPerson)), $body($ownPerson))
        ->assertNotFound();

    expect(ownershipSnapshot())->toBe($before);
})->with(nestedPersonRoutes());
