<?php

use App\Enums\Gender;
use App\Models\Person;
use App\Models\Tree;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * @param  array<string, mixed>  $query
 * @return array{focus: array<string, mixed>, generations: int, root: array<string, mixed>}
 */
function ancestorsChartFor($test, User $user, Person $person, array $query = []): array
{
    $response = $test->actingAs($user)->get(route('tree.ancestors', ['person' => $person, ...$query]));

    $response->assertOk();

    return $response->viewData('page')['props']['ancestorChart'];
}

/**
 * The number of generations a node reaches: an unknown node or a person without parents counts as one.
 *
 * @param  array<string, mixed>  $node
 */
function ancestorsChartDepth(array $node): int
{
    if ($node['kind'] === 'unknown' || $node['parents'] === []) {
        return 1;
    }

    return 1 + max(array_map(ancestorsChartDepth(...), $node['parents']));
}

/**
 * Every person node of the chart, in the order it is shown.
 *
 * @param  array<string, mixed>  $node
 * @return list<array<string, mixed>>
 */
function ancestorsChartPersonNodes(array $node): array
{
    if ($node['kind'] === 'unknown') {
        return [];
    }

    $nodes = [$node];

    foreach ($node['parents'] as $parent) {
        array_push($nodes, ...ancestorsChartPersonNodes($parent));
    }

    return $nodes;
}

/**
 * Give the person two parents above them, recursively, down to the given depth.
 */
function ancestorsGrowTree(Tree $tree, Person $person, int $depth): void
{
    if ($depth === 0) {
        return;
    }

    foreach ([Gender::Male, Gender::Female] as $gender) {
        $parent = Person::factory()->for($tree)->state(['gender' => $gender])->create();
        linkParent($parent, $person);
        ancestorsGrowTree($tree, $parent, $depth - 1);
    }
}

test('the chart shows four generations by default', function () {
    $user = User::factory()->create();
    $people = [Person::factory()->for($user->tree)->create()];

    for ($i = 0; $i < 5; $i++) {
        $father = Person::factory()->for($user->tree)->male()->create();
        linkParent($father, end($people));
        $people[] = $father;
    }

    $chart = ancestorsChartFor($this, $user, $people[0]);

    expect($chart['generations'])->toBe(4);
    expect(ancestorsChartDepth($chart['root']))->toBe(4);
});

test('the top generation has no parents', function () {
    $user = User::factory()->create();
    $people = [Person::factory()->for($user->tree)->create()];

    for ($i = 0; $i < 3; $i++) {
        $father = Person::factory()->for($user->tree)->male()->create();
        linkParent($father, end($people));
        $people[] = $father;
    }

    $topNode = ancestorsChartFor($this, $user, $people[0])['root']['parents'][0]['parents'][0]['parents'][0];

    expect($topNode['person']['id'])->toBe($people[3]->id);
    expect($topNode['parents'])->toBe([]);
});

test('the chart shows the requested number of generations', function () {
    $user = User::factory()->create();
    $child = Person::factory()->for($user->tree)->create();
    ancestorsGrowTree($user->tree, $child, 5);

    $chart = ancestorsChartFor($this, $user, $child, ['generations' => 2]);

    expect($chart['generations'])->toBe(2);
    expect(ancestorsChartDepth($chart['root']))->toBe(2);
    expect($chart['root']['parents'])->toHaveCount(2);
});

test('a generations value outside 2 to 6 or not a number falls back to four', function (string $value) {
    $user = User::factory()->create();
    $child = Person::factory()->for($user->tree)->create();
    ancestorsGrowTree($user->tree, $child, 6);

    $chart = ancestorsChartFor($this, $user, $child, ['generations' => $value]);

    expect($chart['generations'])->toBe(4);
    expect(ancestorsChartDepth($chart['root']))->toBe(4);
})->with(['1', '7', 'abc']);

test('the father is shown on the left and the mother on the right, whatever the order of creation', function () {
    $user = User::factory()->create();
    $child = Person::factory()->for($user->tree)->create();
    $mother = Person::factory()->for($user->tree)->female()->create();
    $father = Person::factory()->for($user->tree)->male()->create();
    linkParent($mother, $child);
    linkParent($father, $child);

    $parents = ancestorsChartFor($this, $user, $child)['root']['parents'];

    expect($parents[0]['person']['id'])->toBe($father->id);
    expect($parents[1]['person']['id'])->toBe($mother->id);
});

test('a known parent is shown with a placeholder for the missing one', function (?Gender $gender, string $label, int $knownIndex) {
    $user = User::factory()->create();
    $child = Person::factory()->for($user->tree)->create();
    $parent = Person::factory()->for($user->tree)->state(['gender' => $gender])->create();
    linkParent($parent, $child);

    $parents = ancestorsChartFor($this, $user, $child)['root']['parents'];

    expect($parents[$knownIndex]['kind'])->toBe('person');
    expect($parents[$knownIndex]['person']['id'])->toBe($parent->id);
    expect($parents[1 - $knownIndex])->toBe(['kind' => 'unknown', 'label' => $label]);
})->with([
    'ojciec znany' => [Gender::Male, 'Nieznana matka', 0],
    'matka znana' => [Gender::Female, 'Nieznany ojciec', 1],
    'rodzic bez płci' => [null, 'Nieznany rodzic', 0],
]);

test('a placeholder has no parents of its own', function () {
    $user = User::factory()->create();
    $child = Person::factory()->for($user->tree)->create();
    $father = Person::factory()->for($user->tree)->male()->create();
    linkParent($father, $child);

    $placeholder = ancestorsChartFor($this, $user, $child)['root']['parents'][1];

    expect($placeholder)->not->toHaveKey('parents');
});

test('a person without known parents gets two placeholders below the limit', function () {
    $user = User::factory()->create();
    $child = Person::factory()->for($user->tree)->create();

    $parents = ancestorsChartFor($this, $user, $child)['root']['parents'];

    expect($parents)->toBe([
        ['kind' => 'unknown', 'label' => 'Nieznany ojciec'],
        ['kind' => 'unknown', 'label' => 'Nieznana matka'],
    ]);
});

test('a common ancestor of two lines appears twice and only that person is marked as repeated', function () {
    $user = User::factory()->create();
    $tree = $user->tree;
    $child = Person::factory()->for($tree)->create();
    $father = Person::factory()->for($tree)->male()->create();
    $mother = Person::factory()->for($tree)->female()->create();
    $commonAncestor = Person::factory()->for($tree)->male()->create();
    linkParent($father, $child);
    linkParent($mother, $child);
    linkParent($commonAncestor, $father);
    linkParent($commonAncestor, $mother);

    $nodes = ancestorsChartPersonNodes(ancestorsChartFor($this, $user, $child)['root']);

    expect(collect($nodes)->where('repeated', true)->pluck('person.id')->values()->all())
        ->toBe([$commonAncestor->id, $commonAncestor->id]);
    expect(collect($nodes)->where('repeated', false)->pluck('person.id')->all())
        ->not->toContain($commonAncestor->id);
});

test('a cycle in the stored relations does not hang the request', function () {
    $user = User::factory()->create();
    $child = Person::factory()->for($user->tree)->create();
    $parent = Person::factory()->for($user->tree)->create();
    linkParent($parent, $child);
    linkParent($child, $parent);

    $nodes = ancestorsChartPersonNodes(ancestorsChartFor($this, $user, $child)['root']);

    expect(collect($nodes)->pluck('person.id')->all())->toBe([$child->id, $parent->id, $child->id, $parent->id]);
    expect(collect($nodes)->pluck('repeated')->unique()->all())->toBe([true]);
});

test('the number of queries does not grow with the size of the tree', function () {
    $countQueries = function (int $depth) {
        $user = User::factory()->create();
        $child = Person::factory()->for($user->tree)->create();
        ancestorsGrowTree($user->tree, $child, $depth);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->get(route('tree.ancestors', ['person' => $child, 'generations' => 6]))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    expect($countQueries(5))->toBe($countQueries(0));
});

test('another tree\'s person is a 404', function () {
    $user = User::factory()->create();
    $foreign = Person::factory()->create();

    $this->actingAs($user)
        ->get(route('tree.ancestors', ['person' => $foreign]))
        ->assertNotFound();
});

test('the ancestor chart carries the photo version of each person', function () {
    $user = User::factory()->create();
    $child = Person::factory()->for($user->tree)->create([
        'photo_thumbnail_path' => "persons/{$user->tree->id}/thumbnails/dziecko.jpg",
    ]);
    $parent = Person::factory()->for($user->tree)->male()->create();
    linkParent($parent, $child);

    $chart = ancestorsChartFor($this, $user, $child);

    expect($chart['focus']['photo_version'])->toBe('dziecko')
        ->and($chart['root']['parents'][0]['person']['photo_version'])->toBeNull();
});

test('the ancestor chart carries the card name of a parent with a maiden name', function () {
    $user = User::factory()->create();
    $child = Person::factory()->for($user->tree)->create();
    $mother = Person::factory()->for($user->tree)->female()->create(['first_name' => 'Anna', 'last_name' => 'Kowalska', 'maiden_name' => 'Nowak']);
    linkParent($mother, $child);

    $chart = ancestorsChartFor($this, $user, $child);

    expect($chart['root']['parents'][1]['person']['card_name'])->toBe('Anna Nowak')
        ->and($chart['root']['parents'][1]['person']['display_name'])->toBe('Anna Kowalska z d. Nowak');
});
