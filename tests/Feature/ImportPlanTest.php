<?php

use App\Actions\Import\ApplyImport;
use App\Actions\Import\ClassifyRepoRecords;
use App\Actions\Import\PlanImport;
use App\Actions\Import\ReadResearchRepo;
use App\Enums\EventType;
use App\Enums\Gender;
use App\Enums\RepoKeyKind;
use App\Models\Event;
use App\Models\Person;
use App\Models\Tree;
use App\Models\User;
use App\Support\Import\ChangeApprovals;
use App\Support\Import\ConflictResolutions;
use App\Support\Import\IdentityDecisions;
use App\Support\Import\ImportDecisions;
use App\Support\Import\ImportIssue;
use App\Support\Import\ImportPlan;
use App\Support\Import\PlannedChange;
use App\Support\Import\PlannedMatch;
use App\Support\Import\PlannedPerson;
use App\Support\Import\PlannedRelation;
use App\Support\Import\ResearchRepoReading;
use App\Support\Import\ValueConflict;
use Illuminate\Support\Facades\File;

/**
 * An author with an empty tree and another account that holds persons named like the fixture ones.
 */
function arrangeEmptyAuthorAndOverlappingOther(): Tree
{
    $author = User::factory()->create();
    $other = User::factory()->create();

    foreach ([['Jan', 'Nowak'], ['Anna', 'Wrona'], ['Marta', 'Lis']] as [$first, $last]) {
        Person::factory()->for($other->tree)->create(['first_name' => $first, 'last_name' => $last]);
    }

    return $author->tree;
}

function planFixtureImport(Tree $tree): ImportPlan
{
    $reading = app(ReadResearchRepo::class)->handle(base_path('tests/Fixtures/research-repo'));

    $classes = app(ClassifyRepoRecords::class)->handle($tree, $reading);

    return app(PlanImport::class)->handle($tree, $reading, $classes, ImportDecisions::forFirstImport(new IdentityDecisions(new: $classes->newPersonIds)));
}

/**
 * Plans a throwaway repo built from person blocks, e.g. ['O-0001' => "imiona: Jan\nplec: M"]. Every repo person is new
 * unless `$matched` or `$skipped` say otherwise.
 *
 * @param  array<string, string>  $blocks  repo id => block body without id and version
 * @param  array<string, Person>  $matched  repo id => the tree person it is
 * @param  list<string>  $skipped  repo ids left out
 */
function planTemporaryRepo(Tree $tree, array $blocks, array $matched = [], array $skipped = []): ImportPlan
{
    $repo = sys_get_temp_dir().'/plan-repo-'.uniqid();
    mkdir($repo.'/drzewo', 0777, true);

    foreach ($blocks as $id => $body) {
        file_put_contents("{$repo}/drzewo/".strtolower($id).'.md', "---\nwersja: 1\nid: {$id}\n{$body}\n---\n");
    }

    try {
        $reading = app(ReadResearchRepo::class)->handle($repo);
    } finally {
        File::deleteDirectory($repo);
    }

    $classes = app(ClassifyRepoRecords::class)->handle($tree, $reading);

    return app(PlanImport::class)->handle($tree, $reading, $classes, ImportDecisions::forFirstImport(new IdentityDecisions(
        array_map(fn (Person $person): int => $person->id, $matched),
        $skipped,
        array_values(array_diff($classes->newPersonIds, array_keys($matched), $skipped)),
    )));
}

/**
 * Builds the same hand-entered persons in the tree of another account and then in the tree of the author, and returns
 * the author's tree with the persons built there.
 *
 * @param  Closure(Tree): array<string, Person>  $arrange
 * @return array{0: Tree, 1: array<string, Person>}
 */
function arrangeAuthorWithTwin(Closure $arrange): array
{
    $other = User::factory()->create();
    $author = User::factory()->create();

    $arrange($other->tree);

    return [$author->tree, $arrange($author->tree)];
}

function plannedMatch(ImportPlan $plan, string $id): PlannedMatch
{
    return collect($plan->matched)->first(fn (PlannedMatch $planned): bool => $planned->person->id === $id);
}

/**
 * @return list<string>
 */
function fieldsOf(array $items): array
{
    return array_map(fn ($item): string => $item->field, $items);
}

function plannedPerson(ImportPlan $plan, string $id): PlannedPerson
{
    return collect($plan->persons)->first(fn (PlannedPerson $planned): bool => $planned->person->id === $id);
}

/**
 * @return list<string>
 */
function relationKeys(ImportPlan $plan): array
{
    return array_map(fn (PlannedRelation $relation): string => $relation->repoId, $plan->relations);
}

/**
 * @param  list<ImportIssue>  $issues
 */
function issueMessages(array $issues): string
{
    return implode(' | ', array_map(fn (ImportIssue $issue): string => "{$issue->file}: {$issue->message}", $issues));
}

test('plans the fixture repo for an empty tree', function () {
    $plan = planFixtureImport(arrangeEmptyAuthorAndOverlappingOther());

    expect($plan->persons)->toHaveCount(13)
        ->and($plan->relations)->toHaveCount(3)
        ->and($plan->skippedRelations)->toHaveCount(1)
        ->and($plan->marriages)->toHaveCount(3)
        ->and([$plan->births, $plan->deaths, $plan->weddings])->toBe([5, 2, 3]);
});

test('does not count the persons of another account', function () {
    $tree = arrangeEmptyAuthorAndOverlappingOther();

    expect(Person::count())->toBe(3)
        ->and(planFixtureImport($tree)->persons)->toHaveCount(13);
});

test('plans a tree that already has a person from an import without a block', function () {
    $tree = arrangeEmptyAuthorAndOverlappingOther();
    Person::factory()->for($tree)->create(['first_name' => 'Jan', 'last_name' => 'Nowak', 'repo_id' => 'O-0001']);

    $plan = planFixtureImport($tree);

    expect($plan->persons)->toHaveCount(12)
        ->and(plannedPerson($plan, 'O-0002')->person->id)->toBe('O-0002')
        ->and(collect($plan->persons)->contains(fn (PlannedPerson $planned): bool => $planned->person->id === 'O-0001'))->toBeFalse()
        ->and($plan->updates)->toHaveCount(1)
        ->and($plan->updates[0]->person->id)->toBe('O-0001')
        ->and($plan->removals)->toBe([]);
});

test('does not block a tree whose persons were all entered by hand', function () {
    $tree = arrangeEmptyAuthorAndOverlappingOther();
    Person::factory()->for($tree)->create(['first_name' => 'Jan', 'last_name' => 'Nowak']);

    expect(planFixtureImport($tree)->persons)->toHaveCount(13);
});

test('skips a relation whose parent has the opposite gender', function () {
    $plan = planFixtureImport(arrangeEmptyAuthorAndOverlappingOther());

    expect(relationKeys($plan))->not->toContain('O-0003>O-0008')
        ->and(relationKeys($plan))->toBe(['O-0002>O-0001', 'O-0003>O-0001', 'O-0005>O-0002'])
        ->and(issueMessages($plan->skippedRelations))->toContain('kos_ewa.md: relacja pominięta: płeć rodzica niezgodna z rolą');
});

test('skips both relations when father and mother are the same id', function () {
    $plan = planTemporaryRepo(arrangeEmptyAuthorAndOverlappingOther(), [
        'O-0001' => "imiona: Rodzic\n",
        'O-0002' => "imiona: Dziecko\nrodzice:\n  ojciec: O-0001\n  matka: O-0001",
        'O-0003' => "imiona: Inne\nrodzice:\n  ojciec: O-0001",
    ]);

    expect(relationKeys($plan))->toBe(['O-0001>O-0003'])
        ->and($plan->skippedRelations)->toHaveCount(2)
        ->and(issueMessages($plan->skippedRelations))->toContain('ten sam id jako ojciec i matka (ojciec: O-0001)')
        ->and(issueMessages($plan->skippedRelations))->toContain('ten sam id jako ojciec i matka (matka: O-0001)');
});

test('skips every relation on a cycle and keeps the rest', function (array $blocks, array $kept, array $dropped) {
    $plan = planTemporaryRepo(arrangeEmptyAuthorAndOverlappingOther(), $blocks);

    expect(relationKeys($plan))->toBe($kept)
        ->and($plan->skippedRelations)->toHaveCount(count($dropped))
        ->and(issueMessages($plan->skippedRelations))->toContain('cyklu');

    foreach ($dropped as $key) {
        expect(issueMessages($plan->skippedRelations))->toContain(str_replace('>', ' → ', $key));
    }
})->with([
    'two persons' => [
        [
            'O-0001' => "imiona: A\nrodzice:\n  ojciec: O-0002",
            'O-0002' => "imiona: B\nrodzice:\n  ojciec: O-0001",
            'O-0003' => "imiona: C\nrodzice:\n  ojciec: O-0001",
        ],
        ['O-0001>O-0003'],
        ['O-0002>O-0001', 'O-0001>O-0002'],
    ],
    'three persons' => [
        [
            'O-0001' => "imiona: A\nrodzice:\n  ojciec: O-0003",
            'O-0002' => "imiona: B\nrodzice:\n  ojciec: O-0001",
            'O-0003' => "imiona: C\nrodzice:\n  ojciec: O-0002\n  matka: O-0004",
            'O-0004' => "imiona: D\nplec: K",
        ],
        ['O-0004>O-0003'],
        ['O-0003>O-0001', 'O-0001>O-0002', 'O-0002>O-0003'],
    ],
]);

test('writes the person note exactly as specified', function () {
    $plan = planFixtureImport(arrangeEmptyAuthorAndOverlappingOther());

    expect(plannedPerson($plan, 'O-0001')->note)->toBe(<<<'NOTE'
        Z repo badawczego (O-0001):
        Urodzenie: Zalesie; poziom A; 24 IV 1801 st.st.
        Pochowanie: Cmentarz w Zalesiu
        Ślub z Anna Wrona (O-0004): Zalesie; poziom B
        Warianty nazwiska: Nowakowski
        NOTE);
});

test('leaves the note empty for a person with nothing to carry over', function () {
    $plan = planFixtureImport(arrangeEmptyAuthorAndOverlappingOther());

    expect(plannedPerson($plan, 'O-0002')->note)->toBeNull()
        ->and(plannedPerson($plan, 'O-0006')->note)->toBeNull();
});

test('writes the details of an empty burial, a death and a one-sided marriage', function () {
    $plan = planTemporaryRepo(arrangeEmptyAuthorAndOverlappingOther(), [
        'O-0001' => "imiona: Jan\nzgon:\n  poziom: C\npochowek: {}\nmalzenstwa:\n  - z: O-0002\n    miejsce: Zalesie",
        'O-0002' => 'imiona: Ewa',
    ]);

    expect(plannedPerson($plan, 'O-0001')->note)->toBe("Z repo badawczego (O-0001):\nZgon: poziom C\nPochowanie: bez szczegółów\nŚlub z Ewa (O-0002): Zalesie")
        ->and(plannedPerson($plan, 'O-0002')->note)->toBe("Z repo badawczego (O-0002):\nŚlub z Jan (O-0001): Zalesie");
});

test('puts the marriage line in the notes of both spouses', function () {
    $plan = planFixtureImport(arrangeEmptyAuthorAndOverlappingOther());

    expect(plannedPerson($plan, 'O-0004')->note)->toBe("Z repo badawczego (O-0004):\nŚlub z Jan Nowak (O-0001): Zalesie; poziom B");
});

test('counts dates by qualifier and events without a date', function () {
    $plan = planFixtureImport(arrangeEmptyAuthorAndOverlappingOther());

    expect($plan->datesByQualifier)->toBe(['after' => 1, 'approximate' => 1, 'before' => 1, 'exact' => 4, 'range' => 1])
        ->and($plan->undatedEvents)->toBe(2);
});

test('counts the values that go into the person note', function () {
    $counts = planFixtureImport(arrangeEmptyAuthorAndOverlappingOther())->noteValues;

    expect([$counts->places, $counts->burials, $counts->levels, $counts->eventNotes, $counts->surnameVariants])->toBe([3, 1, 2, 1, 1]);
});

test('derives the repo ids of persons, events, relations and marriages', function () {
    $plan = planFixtureImport(arrangeEmptyAuthorAndOverlappingOther());
    $piotr = plannedPerson($plan, 'O-0002');
    $marta = plannedPerson($plan, 'O-0003');

    expect($piotr->person->id)->toBe('O-0002')
        ->and([$piotr->birthRepoId, $piotr->deathRepoId])->toBe(['O-0002/urodzenie', 'O-0002/zgon'])
        ->and([$marta->birthRepoId, $marta->deathRepoId])->toBe(['O-0003/urodzenie', null])
        ->and(relationKeys($plan))->toContain('O-0005>O-0002')
        ->and(array_map(fn ($marriage): string => $marriage->repoId, $plan->marriages))->toBe(['O-0001+O-0004/slub', 'O-0002+O-0003/slub', 'O-0007+O-0008/slub']);
});

test('suggests the person with Ahnentafel number 1 as the proband', function () {
    expect(planFixtureImport(arrangeEmptyAuthorAndOverlappingOther())->probandHint->id)->toBe('O-0001');
});

test('suggests no proband without number 1 or with two holders', function () {
    $tree = arrangeEmptyAuthorAndOverlappingOther();

    expect(planTemporaryRepo($tree, ['O-0001' => "imiona: Jan\nahnentafel: [2]"])->probandHint)->toBeNull()
        ->and(planTemporaryRepo($tree, [
            'O-0001' => "imiona: Jan\nahnentafel: [1]",
            'O-0002' => "imiona: Adam\nahnentafel: [1]",
        ])->probandHint)->toBeNull();
});

test('fills the empty fields of a matched person and reports no conflict', function () {
    [$tree, $people] = arrangeAuthorWithTwin(function (Tree $tree): array {
        $jan = Person::factory()->for($tree)->create(['first_name' => 'Jan', 'last_name' => 'Nowak']);
        attachBirth($jan);

        return ['jan' => $jan];
    });

    $plan = planTemporaryRepo($tree, [
        'O-0001' => "imiona: Jan\nnazwisko: Nowak\nplec: M\nurodzenie:\n  data: \"1801-05-12\"",
    ], ['O-0001' => $people['jan']]);
    $match = plannedMatch($plan, 'O-0001');

    expect($plan->persons)->toBe([])
        ->and($match->personId)->toBe($people['jan']->id)
        ->and(fieldsOf($match->fills))->toBe(['gender', 'birth_date'])
        ->and($match->fills[0]->value)->toBe(Gender::Male)
        ->and($match->fills[1]->valueLabel())->toBe('1801-05-12')
        ->and($match->conflicts)->toBe([])
        ->and([$match->createBirth, $match->birthRepoId])->toBe([false, 'O-0001/urodzenie'])
        ->and($plan->conflicts)->toBe([])
        ->and($plan->fillCount())->toBe(2)
        ->and($plan->skippedPersons)->toBe([]);
});

test('reports a different birth date and different given names as two conflicts with fixed keys', function () {
    [$tree, $people] = arrangeAuthorWithTwin(function (Tree $tree): array {
        $jan = Person::factory()->for($tree)->create(['first_name' => 'Jan', 'last_name' => 'Nowak']);
        attachBirth($jan, 1830);

        return ['jan' => $jan];
    });

    $plan = planTemporaryRepo($tree, [
        'O-0001' => "imiona: Jan Józef\nnazwisko: Nowak\nurodzenie:\n  data: \"1801\"",
    ], ['O-0001' => $people['jan']]);

    expect(array_map(fn ($conflict): string => $conflict->key, $plan->conflicts))->toBe(['O-0001/imiona', 'O-0001/urodzenie/data'])
        ->and($plan->conflicts)->toBe(plannedMatch($plan, 'O-0001')->conflicts)
        ->and([$plan->conflicts[0]->appLabel, $plan->conflicts[0]->repoLabel])->toBe(['Jan', 'Jan Józef'])
        ->and([$plan->conflicts[1]->appLabel, $plan->conflicts[1]->repoLabel])->toBe(['1830', '1801'])
        ->and(plannedMatch($plan, 'O-0001')->fills)->toBe([]);
});

test('reads a more precise repo date as a fill and any other difference as a conflict', function (?int $month, ?int $day, string $repoDate, string $outcome) {
    [$tree, $people] = arrangeAuthorWithTwin(function (Tree $tree) use ($month, $day): array {
        $jan = Person::factory()->for($tree)->create(['first_name' => 'Jan', 'last_name' => 'Nowak']);
        attachBirth($jan, 1801, $month, $day);

        return ['jan' => $jan];
    });

    $plan = planTemporaryRepo($tree, [
        'O-0001' => "imiona: Jan\nnazwisko: Nowak\nurodzenie:\n  data: \"{$repoDate}\"",
    ], ['O-0001' => $people['jan']]);
    $match = plannedMatch($plan, 'O-0001');

    expect([count($match->fills), count($match->conflicts)])->toBe(match ($outcome) {
        'fill' => [1, 0],
        'conflict' => [0, 1],
        default => [0, 0],
    });
})->with([
    'year only, repo adds month and day' => [null, null, '1801-05-12', 'fill'],
    'month only, repo adds the day' => [5, null, '1801-05-12', 'fill'],
    'another month' => [4, null, '1801-05-12', 'conflict'],
    'another day' => [5, 11, '1801-05-12', 'conflict'],
    'repo has less than the tree' => [5, 12, '1801-05', 'conflict'],
    'another kind of date' => [null, null, 'ok. 1801', 'conflict'],
    'the same date' => [5, 12, '1801-05-12', 'same'],
]);

test('compares names after trimming and merging spaces and keeps case and Polish letters', function (string $appName, string $repoName, bool $conflict) {
    [$tree, $people] = arrangeAuthorWithTwin(fn (Tree $tree): array => ['jan' => Person::factory()->for($tree)->create(['first_name' => $appName, 'last_name' => 'Nowak'])]);

    $plan = planTemporaryRepo($tree, ['O-0001' => "imiona: \"{$repoName}\"\nnazwisko: Nowak"], ['O-0001' => $people['jan']]);

    expect($plan->conflicts)->toHaveCount($conflict ? 1 : 0)
        ->and(plannedMatch($plan, 'O-0001')->fills)->toBe([]);
})->with([
    'only spaces differ' => ['Jan  Józef', 'Jan Józef ', false],
    'Polish letter' => ['Łukasz', 'Lukasz', true],
    'case' => ['jan', 'Jan', true],
]);

test('plans a death the tree lacks and leaves a death the repo lacks', function () {
    [$tree, $people] = arrangeAuthorWithTwin(function (Tree $tree): array {
        $jan = Person::factory()->for($tree)->create(['first_name' => 'Jan', 'last_name' => 'Nowak']);
        $anna = Person::factory()->for($tree)->create(['first_name' => 'Anna', 'last_name' => 'Wrona']);
        attachBirth($jan, 1801);
        attachBirth($anna, 1805);
        attachDeath($anna, 1870);

        return ['jan' => $jan, 'anna' => $anna];
    });

    $plan = planTemporaryRepo($tree, [
        'O-0001' => "imiona: Jan\nnazwisko: Nowak\nzgon:\n  data: 1870",
        'O-0002' => "imiona: Anna\nnazwisko: Wrona",
    ], ['O-0001' => $people['jan'], 'O-0002' => $people['anna']]);
    $jan = plannedMatch($plan, 'O-0001');
    $anna = plannedMatch($plan, 'O-0002');

    expect([$jan->createDeath, $jan->deathRepoId])->toBe([true, 'O-0001/zgon'])
        ->and([$jan->createBirth, $jan->birthRepoId])->toBe([false, null])
        ->and([$anna->createDeath, $anna->deathRepoId, $anna->fills, $anna->conflicts])->toBe([false, null, [], []])
        ->and($plan->deaths)->toBe(1)
        ->and($plan->births)->toBe(0);
});

test('plans a birth for a matched person who has none', function () {
    [$tree, $people] = arrangeAuthorWithTwin(fn (Tree $tree): array => ['jan' => Person::factory()->for($tree)->create(['first_name' => 'Jan', 'last_name' => 'Nowak'])]);

    $plan = planTemporaryRepo($tree, [
        'O-0001' => "imiona: Jan\nnazwisko: Nowak\nurodzenie:\n  data: 1801",
    ], ['O-0001' => $people['jan']]);
    $match = plannedMatch($plan, 'O-0001');

    expect([$match->createBirth, $match->birthRepoId, $match->fills])->toBe([true, 'O-0001/urodzenie', []])
        ->and($plan->births)->toBe(1)
        ->and($plan->datesByQualifier)->toBe(['exact' => 1]);
});

test('marks a relation that is already in the tree', function () {
    [$tree, $people] = arrangeAuthorWithTwin(function (Tree $tree): array {
        $jan = Person::factory()->for($tree)->create(['first_name' => 'Jan', 'last_name' => 'Nowak']);
        $piotr = Person::factory()->for($tree)->create(['first_name' => 'Piotr', 'last_name' => 'Nowak']);
        linkParent($piotr, $jan);

        return ['jan' => $jan, 'piotr' => $piotr];
    });

    $plan = planTemporaryRepo($tree, [
        'O-0001' => "imiona: Jan\nnazwisko: Nowak\nrodzice:\n  ojciec: O-0002",
        'O-0002' => "imiona: Piotr\nnazwisko: Nowak\nplec: M",
    ], ['O-0001' => $people['jan'], 'O-0002' => $people['piotr']]);
    $existing = $tree->parentChildRelations()->firstOrFail();

    expect($plan->relations)->toHaveCount(1)
        ->and([$plan->relations[0]->repoId, $plan->relations[0]->existingId])->toBe(['O-0002>O-0001', $existing->id])
        ->and($plan->skippedRelations)->toBe([]);
});

test('skips a relation for a child who already has another father in the tree', function () {
    [$tree, $people] = arrangeAuthorWithTwin(function (Tree $tree): array {
        $jan = Person::factory()->for($tree)->create(['first_name' => 'Jan', 'last_name' => 'Nowak']);
        $adam = Person::factory()->for($tree)->male()->create(['first_name' => 'Adam', 'last_name' => 'Nowak']);
        linkParent($adam, $jan);

        return ['jan' => $jan];
    });

    $plan = planTemporaryRepo($tree, [
        'O-0001' => "imiona: Jan\nnazwisko: Nowak\nrodzice:\n  ojciec: O-0002",
        'O-0002' => "imiona: Piotr\nnazwisko: Nowak\nplec: M",
    ], ['O-0001' => $people['jan']]);

    expect($plan->relations)->toBe([])
        ->and($plan->persons)->toHaveCount(1)
        ->and(issueMessages($plan->skippedRelations))->toContain('O-0002 → O-0001 (Jan Nowak, #'.$people['jan']->id.')')
        ->and(issueMessages($plan->skippedRelations))->toContain('w drzewie inny ojciec: Adam Nowak');
});

test('skips a relation for a child who already has another mother in the tree', function () {
    [$tree, $people] = arrangeAuthorWithTwin(function (Tree $tree): array {
        $jan = Person::factory()->for($tree)->create(['first_name' => 'Jan', 'last_name' => 'Nowak']);
        $ewa = Person::factory()->for($tree)->female()->create(['first_name' => 'Ewa', 'last_name' => 'Nowak']);
        linkParent($ewa, $jan);

        return ['jan' => $jan];
    });

    $plan = planTemporaryRepo($tree, [
        'O-0001' => "imiona: Jan\nnazwisko: Nowak\nrodzice:\n  matka: O-0002",
        'O-0002' => "imiona: Maria\nnazwisko: Wrona\nplec: K",
    ], ['O-0001' => $people['jan']]);

    expect($plan->relations)->toBe([])
        ->and(issueMessages($plan->skippedRelations))->toContain('w drzewie inna matka: Ewa Nowak')
        ->and(issueMessages($plan->skippedRelations))->not->toContain('inny matka');
});

test('skips a relation for a child who already has two parents in the tree', function () {
    [$tree, $people] = arrangeAuthorWithTwin(function (Tree $tree): array {
        $jan = Person::factory()->for($tree)->create(['first_name' => 'Jan', 'last_name' => 'Nowak']);
        linkParent(Person::factory()->for($tree)->create(['first_name' => 'Adam']), $jan);
        linkParent(Person::factory()->for($tree)->create(['first_name' => 'Ewa']), $jan);

        return ['jan' => $jan];
    });

    $plan = planTemporaryRepo($tree, [
        'O-0001' => "imiona: Jan\nnazwisko: Nowak\nrodzice:\n  ojciec: O-0002",
        'O-0002' => "imiona: Piotr\nnazwisko: Nowak",
    ], ['O-0001' => $people['jan']]);

    expect($plan->relations)->toBe([])
        ->and(issueMessages($plan->skippedRelations))->toContain('dziecko ma już dwoje rodziców');
});

test('skips a parent whose gender in the tree gives the child two parents of one sex', function () {
    [$tree, $people] = arrangeAuthorWithTwin(fn (Tree $tree): array => ['pawel' => Person::factory()->for($tree)->male()->create(['first_name' => 'Paweł', 'last_name' => 'Lis'])]);

    $plan = planTemporaryRepo($tree, [
        'O-0001' => "imiona: Jan\nrodzice:\n  ojciec: O-0002\n  matka: O-0003",
        'O-0002' => "imiona: Piotr\nplec: M",
        'O-0003' => "imiona: Paweł\nnazwisko: Lis",
    ], ['O-0003' => $people['pawel']]);

    expect(relationKeys($plan))->toBe(['O-0002>O-0001'])
        ->and(issueMessages($plan->skippedRelations))->toContain('O-0003 (Paweł Lis, #'.$people['pawel']->id.') → O-0001')
        ->and(issueMessages($plan->skippedRelations))->toContain('dwoje rodziców tej samej płci');
});

test('skips a relation that would close a cycle through a relation of the tree', function () {
    [$tree, $people] = arrangeAuthorWithTwin(function (Tree $tree): array {
        $a = Person::factory()->for($tree)->create(['first_name' => 'Adam', 'last_name' => 'Nowak']);
        $b = Person::factory()->for($tree)->create(['first_name' => 'Bartosz', 'last_name' => 'Nowak']);
        linkParent($b, $a);

        return ['a' => $a, 'b' => $b];
    });

    $plan = planTemporaryRepo($tree, [
        'O-0001' => "imiona: Adam\nnazwisko: Nowak",
        'O-0002' => "imiona: Bartosz\nnazwisko: Nowak\nrodzice:\n  ojciec: O-0001",
    ], ['O-0001' => $people['a'], 'O-0002' => $people['b']]);

    expect($plan->relations)->toBe([])
        ->and(issueMessages($plan->skippedRelations))->toContain('utworzyłaby cykl');
});

test('skips the relations and weddings of a skipped person and writes no wedding line for it', function () {
    $tree = arrangeEmptyAuthorAndOverlappingOther();
    $blocks = [
        'O-0001' => "imiona: Jan\nrodzice:\n  ojciec: O-0002\nmalzenstwa:\n  - z: O-0003\n    miejsce: Zalesie",
        'O-0002' => "imiona: Piotr\nplec: M",
        'O-0003' => "imiona: Anna\nplec: K",
    ];

    $plan = planTemporaryRepo($tree, $blocks, skipped: ['O-0002', 'O-0003']);

    expect(array_map(fn (ImportIssue $issue): string => $issue->message, $plan->skippedPersons))->toBe(['osoba pominięta przy dopasowaniu', 'osoba pominięta przy dopasowaniu'])
        ->and(array_map(fn (PlannedPerson $planned): string => $planned->person->id, $plan->persons))->toBe(['O-0001'])
        ->and($plan->relations)->toBe([])
        ->and(issueMessages($plan->skippedRelations))->toContain('osoba pominięta przy dopasowaniu (O-0002)')
        ->and($plan->marriages)->toBe([])
        ->and(issueMessages($plan->skippedMarriages))->toContain('O-0001+O-0003/slub')
        ->and(plannedPerson($plan, 'O-0001')->note)->toBeNull();

    expect(plannedPerson(planTemporaryRepo($tree, $blocks), 'O-0001')->note)->toContain('Ślub z Anna (O-0003): Zalesie');
});

test('marks a wedding of a matched pair that is already in the tree and reports its date', function (?int $treeYear, string $outcome) {
    [$tree, $people] = arrangeAuthorWithTwin(function (Tree $tree) use ($treeYear): array {
        $jan = Person::factory()->for($tree)->create(['first_name' => 'Jan', 'last_name' => 'Nowak']);
        $anna = Person::factory()->for($tree)->create(['first_name' => 'Anna', 'last_name' => 'Wrona']);
        $wedding = attachMarriage($jan, $anna, $treeYear);

        return ['jan' => $jan, 'anna' => $anna, 'wedding' => $wedding];
    });

    $plan = planTemporaryRepo($tree, [
        'O-0001' => "imiona: Jan\nnazwisko: Nowak\nmalzenstwa:\n  - z: O-0002\n    data: ok. 1825",
        'O-0002' => "imiona: Anna\nnazwisko: Wrona",
    ], ['O-0001' => $people['jan'], 'O-0002' => $people['anna']]);
    $wedding = $plan->marriages[0];

    expect($plan->marriages)->toHaveCount(1)
        ->and($wedding->existingEventId)->toBe($people['wedding']->id)
        ->and($plan->weddings)->toBe(0);

    if ($outcome === 'conflict') {
        expect($wedding->dateFill)->toBeNull()
            ->and($wedding->dateConflict->key)->toBe('O-0001+O-0002/slub/data')
            ->and([$wedding->dateConflict->appLabel, $wedding->dateConflict->repoLabel])->toBe(['1830', 'ok. 1825'])
            ->and($plan->conflicts)->toBe([$wedding->dateConflict]);
    } else {
        expect($wedding->dateConflict)->toBeNull()
            ->and($wedding->dateFill->valueLabel())->toBe('ok. 1825')
            ->and($plan->fillCount())->toBe(1);
    }
})->with([
    'another date in the tree' => [1830, 'conflict'],
    'no date in the tree' => [null, 'fill'],
]);

test('plans a new wedding when only one spouse is matched', function () {
    [$tree, $people] = arrangeAuthorWithTwin(fn (Tree $tree): array => ['jan' => Person::factory()->for($tree)->create(['first_name' => 'Jan', 'last_name' => 'Nowak'])]);

    $plan = planTemporaryRepo($tree, [
        'O-0001' => "imiona: Jan\nnazwisko: Nowak\nmalzenstwa:\n  - z: O-0002",
        'O-0002' => "imiona: Anna\nnazwisko: Wrona",
    ], ['O-0001' => $people['jan']]);

    expect($plan->marriages)->toHaveCount(1)
        ->and($plan->marriages[0]->existingEventId)->toBeNull()
        ->and($plan->weddings)->toBe(1);
});

test('drops a repo gender that would give a child two parents of one sex', function (?Gender $appGender) {
    [$tree, $people] = arrangeAuthorWithTwin(function (Tree $tree) use ($appGender): array {
        $maria = Person::factory()->for($tree)->create(['first_name' => 'Maria', 'last_name' => 'Wrona', 'gender' => $appGender]);
        $ewa = Person::factory()->for($tree)->female()->create(['first_name' => 'Ewa', 'last_name' => 'Lis']);
        $jan = Person::factory()->for($tree)->create(['first_name' => 'Jan', 'last_name' => 'Nowak']);
        linkParent($maria, $jan);
        linkParent($ewa, $jan);

        return ['maria' => $maria];
    });

    $plan = planTemporaryRepo($tree, ['O-0001' => "imiona: Maria\nnazwisko: Wrona\nplec: K"], ['O-0001' => $people['maria']]);

    expect($plan->conflicts)->toBe([])
        ->and(plannedMatch($plan, 'O-0001')->fills)->toBe([])
        ->and($plan->skippedPersons)->toBe([])
        ->and($plan->skippedValues)->toHaveCount(1)
        ->and(issueMessages($plan->skippedValues))->toContain('płeć z repo pominięta: Kobieta (O-0001)')
        ->and(issueMessages($plan->skippedValues))->toContain('Ewa Lis')
        ->and($plan->snapshots['O-0001'])->not->toHaveKey('plec')
        ->and($plan->snapshots['O-0001'])->toMatchArray(['imiona' => 'Maria', 'nazwisko' => 'Wrona']);
})->with([
    'a conflict' => [Gender::Male],
    'a fill' => [null],
]);

test('keeps a gender conflict that breaks no rule', function () {
    [$tree, $people] = arrangeAuthorWithTwin(fn (Tree $tree): array => ['maria' => Person::factory()->for($tree)->male()->create(['first_name' => 'Maria', 'last_name' => 'Wrona'])]);

    $plan = planTemporaryRepo($tree, ['O-0001' => "imiona: Maria\nnazwisko: Wrona\nplec: K"], ['O-0001' => $people['maria']]);

    expect($plan->skippedValues)->toBe([])
        ->and($plan->conflicts)->toHaveCount(1)
        ->and([$plan->conflicts[0]->key, $plan->conflicts[0]->appLabel, $plan->conflicts[0]->repoLabel])->toBe(['O-0001/plec', 'Mężczyzna', 'Kobieta'])
        ->and($plan->conflicts[0]->repoValue)->toBe(Gender::Female);
});

test('appends the repo block to the note of a matched person', function () {
    [$tree, $people] = arrangeAuthorWithTwin(fn (Tree $tree): array => ['jan' => Person::factory()->for($tree)->create(['first_name' => 'Jan', 'last_name' => 'Nowak', 'note' => 'Moja notatka'])]);

    $plan = planTemporaryRepo($tree, [
        'O-0001' => "imiona: Jan\nnazwisko: Nowak\nurodzenie:\n  miejsce: Zalesie\n  poziom: A",
    ], ['O-0001' => $people['jan']]);

    expect(plannedMatch($plan, 'O-0001')->note)->toBe("Z repo badawczego (O-0001):\nUrodzenie: Zalesie; poziom A");
});

test('rejects decisions that point outside the tree or use a tree person twice or miss a repo person', function () {
    $other = User::factory()->create();
    $author = User::factory()->create();
    $foreign = Person::factory()->for($other->tree)->create(['first_name' => 'Jan', 'last_name' => 'Nowak']);
    $own = Person::factory()->for($author->tree)->create(['first_name' => 'Jan', 'last_name' => 'Nowak']);
    $blocks = ['O-0001' => "imiona: Jan\nnazwisko: Nowak", 'O-0002' => "imiona: Jan\nnazwisko: Nowak"];

    expect(fn () => planTemporaryRepo($author->tree, $blocks, ['O-0001' => $foreign]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => planTemporaryRepo($author->tree, $blocks, ['O-0001' => $own, 'O-0002' => $own]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => planTemporaryRepo($author->tree, $blocks, ['O-0001' => $own], ['O-0001']))->toThrow(InvalidArgumentException::class);

    $reading = app(ReadResearchRepo::class)->handle(base_path('tests/Fixtures/research-repo'));

    $classes = app(ClassifyRepoRecords::class)->handle($author->tree, $reading);

    expect(fn () => app(PlanImport::class)->handle($author->tree, $reading, $classes, ImportDecisions::forFirstImport(new IdentityDecisions(new: ['O-0001']))))->toThrow(InvalidArgumentException::class);
});

/**
 * Reads a throwaway repo made of person blocks.
 *
 * @param  array<string, string>  $blocks  repo id => block body without id and version
 * @param  array<string, string>  $files  further files, file name => contents
 */
function replanReading(array $blocks, array $files = []): ResearchRepoReading
{
    $repo = sys_get_temp_dir().'/replan-repo-'.uniqid();
    mkdir($repo.'/drzewo', 0777, true);

    foreach ($blocks as $id => $body) {
        file_put_contents("{$repo}/drzewo/".strtolower($id).'.md', "---\nwersja: 1\nid: {$id}\n{$body}\n---\n");
    }

    foreach ($files as $name => $contents) {
        file_put_contents("{$repo}/drzewo/{$name}", $contents);
    }

    try {
        return app(ReadResearchRepo::class)->handle($repo);
    } finally {
        File::deleteDirectory($repo);
    }
}

/**
 * Imports the blocks into an empty tree through the planner and the writer, every person new.
 *
 * @param  array<string, string>  $blocks
 */
function replanFirstImport(Tree $tree, array $blocks): void
{
    $reading = replanReading($blocks);
    $classes = app(ClassifyRepoRecords::class)->handle($tree, $reading);
    $plan = app(PlanImport::class)->handle($tree, $reading, $classes, ImportDecisions::forFirstImport(new IdentityDecisions(new: $classes->newPersonIds)));

    app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals, new ConflictResolutions);
}

/**
 * The first import of the same blocks into the tree of another account and into the tree of the author, then the same
 * change in the app in both. Returns the author's tree and the tree of the other account.
 *
 * @param  array<string, string>  $blocks
 * @param  (Closure(Tree): void)|null  $arrange
 * @return array{0: Tree, 1: Tree}
 */
function replanTwinTrees(array $blocks, ?Closure $arrange = null): array
{
    $other = User::factory()->create();
    $author = User::factory()->create();

    foreach ([$other->tree, $author->tree] as $tree) {
        replanFirstImport($tree, $blocks);

        if ($arrange !== null) {
            $arrange($tree);
        }
    }

    return [$author->tree, $other->tree];
}

/**
 * Plans a later state of the repo over a tree that an earlier import filled.
 *
 * @param  array<string, string>  $blocks
 * @param  array<string, string>  $removals
 * @param  array<string, string>  $deletions
 * @param  array<string, string>  $files
 */
function replanBlocks(Tree $tree, array $blocks, array $removals = [], array $deletions = [], ?IdentityDecisions $identity = null, array $files = []): ImportPlan
{
    $reading = replanReading($blocks, $files);
    $classes = app(ClassifyRepoRecords::class)->handle($tree, $reading);

    return app(PlanImport::class)->handle(
        $tree,
        $reading,
        $classes,
        new ImportDecisions($identity ?? new IdentityDecisions(new: $classes->newPersonIds), $removals, $deletions),
    );
}

function replanPerson(Tree $tree, string $repoId): Person
{
    return $tree->people()->where('repo_id', $repoId)->with('events')->firstOrFail();
}

function replanEvent(Tree $tree, string $repoId): Event
{
    return $tree->events()->where('repo_id', $repoId)->firstOrFail();
}

/**
 * Proves that every record of the tree that the plan names is a record of this tree and not of the other account.
 */
function expectPlanInside(ImportPlan $plan, Tree $tree): void
{
    $people = $tree->people()->pluck('id')->all();
    $events = $tree->events()->pluck('id')->all();
    $relations = $tree->parentChildRelations()->pluck('id')->all();

    foreach ([...$plan->updates, ...$plan->matched] as $planned) {
        expect($people)->toContain($planned->personId);
    }

    foreach ($plan->restorations as $restoration) {
        if ($restoration->personId !== null) {
            expect($people)->toContain($restoration->personId);
        }
    }

    foreach ([...$plan->removals, ...$plan->detachedBirths] as $removal) {
        $ids = match ($removal->kind) {
            RepoKeyKind::Person => $people,
            RepoKeyKind::Relation => $relations,
            default => $events,
        };

        expect($ids)->toContain($removal->recordId);
    }

    foreach ($plan->relations as $relation) {
        if ($relation->existingId !== null) {
            expect($relations)->toContain($relation->existingId);
        }
    }

    foreach ($plan->marriages as $marriage) {
        if ($marriage->existingEventId !== null) {
            expect($events)->toContain($marriage->existingEventId);
        }
    }
}

const REPLAN_JAN = "imiona: Jan\nnazwisko: Nowak\nplec: M\nurodzenie:\n  data: \"1801\"";

const REPLAN_FAMILY = [
    'O-0001' => "imiona: Jan\nnazwisko: Nowak\nplec: M\nurodzenie:\n  data: \"1801\"\nmalzenstwa:\n  - z: O-0003\n    data: \"1825\"",
    'O-0002' => "imiona: Dziecko\nrodzice:\n  ojciec: O-0001",
    'O-0003' => "imiona: Anna\nplec: K\nmalzenstwa:\n  - z: O-0001\n    data: \"1825\"",
];

test('plans nothing for a repo and a tree that neither moved since the last import', function () {
    [$tree, $other] = replanTwinTrees(REPLAN_FAMILY);

    $plan = replanBlocks($tree, REPLAN_FAMILY);

    expect($plan->persons)->toBe([])
        ->and($plan->matched)->toBe([])
        ->and($plan->updates)->toBe([])
        ->and($plan->changes)->toBe([])
        ->and($plan->conflicts)->toBe([])
        ->and($plan->relations)->toBe([])
        ->and($plan->marriages)->toBe([])
        ->and($plan->removals)->toBe([])
        ->and($plan->detachedBirths)->toBe([])
        ->and($plan->restorations)->toBe([])
        ->and($plan->unassessable)->toBe([])
        ->and($plan->skippedValues)->toBe([])
        ->and($plan->keptManual)->toBe(0)
        ->and($plan->forgottenSnapshots)->toBe([])
        ->and($plan->keptDeletedSnapshots)->toBe([])
        ->and($plan->snapshots)->toHaveKeys(['O-0001', 'O-0001/urodzenie', 'O-0002', 'O-0003', 'O-0001>O-0002', 'O-0001+O-0003/slub'])
        ->and($plan->snapshots['O-0001/urodzenie'])->toBe(['data' => '1801']);

    expectPlanInside($plan, $tree);
    expect($other->repoSnapshots()->count())->toBe($tree->repoSnapshots()->count());
});

test('plans a birth date the repo changed and nobody touched in the app as a change', function () {
    [$tree] = replanTwinTrees(['O-0001' => REPLAN_JAN]);

    $plan = replanBlocks($tree, ['O-0001' => "imiona: Jan\nnazwisko: Nowak\nplec: M\nurodzenie:\n  data: \"1802\""]);
    $change = $plan->changes[0];

    expect($plan->changes)->toHaveCount(1)
        ->and($change)->toBeInstanceOf(PlannedChange::class)
        ->and([$change->key, $change->field, $change->label, $change->person])->toBe(['O-0001/urodzenie/data', 'birth_date', 'Data urodzenia', 'Jan Nowak (O-0001)'])
        ->and([$change->appLabel, $change->repoLabel])->toBe(['1801', '1802'])
        ->and($change->repoValue->year)->toBe(1802)
        ->and($plan->updates)->toHaveCount(1)
        ->and($plan->updates[0]->changes)->toBe([$change])
        ->and($plan->conflicts)->toBe([])
        ->and($plan->snapshots['O-0001/urodzenie'])->toBe(['data' => '1802']);

    expectPlanInside($plan, $tree);
});

test('plans a changed given name and a cleared field as changes', function () {
    [$tree] = replanTwinTrees(['O-0001' => REPLAN_JAN]);

    $plan = replanBlocks($tree, ['O-0001' => "imiona: Jan Józef\nplec: M\nurodzenie:\n  data: \"1801\""]);
    $byKey = collect($plan->changes)->keyBy('key');

    expect($byKey->keys()->all())->toBe(['O-0001/imiona', 'O-0001/nazwisko'])
        ->and([$byKey['O-0001/imiona']->appLabel, $byKey['O-0001/imiona']->repoLabel, $byKey['O-0001/imiona']->repoValue])->toBe(['Jan', 'Jan Józef', 'Jan Józef'])
        ->and([$byKey['O-0001/nazwisko']->appLabel, $byKey['O-0001/nazwisko']->repoLabel, $byKey['O-0001/nazwisko']->repoValue])->toBe(['Nowak', '—', null]);
});

test('reports a value changed on both sides as a conflict that names what the repo held before', function () {
    [$tree] = replanTwinTrees(['O-0001' => REPLAN_JAN], function (Tree $tree): void {
        $birth = replanEvent($tree, 'O-0001/urodzenie');
        $birth->update(['date_year' => 1830]);
    });

    $plan = replanBlocks($tree, ['O-0001' => "imiona: Jan\nnazwisko: Nowak\nplec: M\nurodzenie:\n  data: \"1802\""]);
    $conflict = $plan->conflicts[0];

    expect($plan->conflicts)->toHaveCount(1)
        ->and($conflict)->toBeInstanceOf(ValueConflict::class)
        ->and([$conflict->key, $conflict->appLabel, $conflict->repoLabel, $conflict->lastRepoLabel])->toBe(['O-0001/urodzenie/data', '1830', '1802', '1801'])
        ->and($plan->changes)->toBe([])
        ->and($plan->updates[0]->conflicts)->toBe([$conflict]);

    expectPlanInside($plan, $tree);
});

test('keeps a value changed only in the app and counts it', function () {
    [$tree] = replanTwinTrees(['O-0001' => REPLAN_JAN], function (Tree $tree): void {
        replanEvent($tree, 'O-0001/urodzenie')->update(['date_year' => 1830]);
        replanPerson($tree, 'O-0001')->update(['last_name' => 'Kowalski']);
    });

    $plan = replanBlocks($tree, ['O-0001' => REPLAN_JAN]);

    expect($plan->changes)->toBe([])
        ->and($plan->conflicts)->toBe([])
        ->and($plan->updates)->toBe([])
        ->and($plan->keptManual)->toBe(2)
        ->and($plan->snapshots['O-0001/urodzenie'])->toBe(['data' => '1801']);
});

test('reads a record without a memory of the last import as a conflict without a previous value', function () {
    [$tree] = replanTwinTrees(['O-0001' => REPLAN_JAN], function (Tree $tree): void {
        $tree->repoSnapshots()->delete();
        replanEvent($tree, 'O-0001/urodzenie')->update(['date_year' => 1830]);
    });

    $plan = replanBlocks($tree, ['O-0001' => REPLAN_JAN]);

    expect($plan->conflicts)->toHaveCount(1)
        ->and([$plan->conflicts[0]->key, $plan->conflicts[0]->appLabel, $plan->conflicts[0]->repoLabel, $plan->conflicts[0]->lastRepoLabel])->toBe(['O-0001/urodzenie/data', '1830', '1801', null])
        ->and($plan->changes)->toBe([]);
});

test('compares the note block as a field and leaves the rest of the note alone', function (?string $appNote, string $outcome) {
    $v1 = "imiona: Jan\nurodzenie:\n  miejsce: Zalesie";
    $v2 = "imiona: Jan\nurodzenie:\n  miejsce: Lipno";
    [$tree] = replanTwinTrees(['O-0001' => $v1], function (Tree $tree) use ($appNote): void {
        $person = replanPerson($tree, 'O-0001');
        $block = $person->note;

        $person->update(['note' => $appNote === null ? $block : str_replace('{block}', $block, $appNote)]);
    });

    $plan = replanBlocks($tree, ['O-0001' => $v2]);
    $item = $plan->updates[0]->changes[0] ?? $plan->updates[0]->conflicts[0];

    expect($item->key)->toBe('O-0001/notatka')
        ->and($item->field)->toBe('note')
        ->and($item->repoValue)->toBe("Z repo badawczego (O-0001):\nUrodzenie: Lipno")
        ->and($item->appLabel)->toBe("Z repo badawczego (O-0001):\nUrodzenie: Zalesie".($outcome === 'conflict' ? "\nMój dopisek" : ''))
        ->and(count($plan->changes))->toBe($outcome === 'change' ? 1 : 0)
        ->and(count($plan->conflicts))->toBe($outcome === 'conflict' ? 1 : 0);
})->with([
    'block untouched' => [null, 'change'],
    'manual text outside the block' => ["Moja notatka\n\n{block}", 'change'],
    'manual line inside the block' => ["{block}\nMój dopisek", 'conflict'],
]);

test('drops a gender change that would give a child two parents of one sex and keeps the memory of the old one', function () {
    [$tree] = replanTwinTrees(['O-0001' => 'imiona: Jan', 'O-0002' => "imiona: Dziecko\nrodzice:\n  ojciec: O-0001"], function (Tree $tree): void {
        $second = Person::factory()->for($tree)->male()->create(['first_name' => 'Adam']);
        linkParent($second, replanPerson($tree, 'O-0002'));
    });

    $plan = replanBlocks($tree, ['O-0001' => "imiona: Jan\nplec: M", 'O-0002' => "imiona: Dziecko\nrodzice:\n  ojciec: O-0001"]);

    expect($plan->skippedValues)->toHaveCount(1)
        ->and($plan->skippedValues[0]->message)->toContain('płeć z repo pominięta: Mężczyzna (O-0001)')
        ->and($plan->updates)->toBe([])
        ->and($plan->changes)->toBe([])
        ->and($plan->snapshots['O-0001'])->toHaveKey('plec')
        ->and($plan->snapshots['O-0001']['plec'])->toBeNull();
});

test('takes a gender change that breaks no rule', function () {
    [$tree] = replanTwinTrees(['O-0001' => "imiona: Maria\nplec: M"]);

    $plan = replanBlocks($tree, ['O-0001' => "imiona: Maria\nplec: K"]);

    expect($plan->skippedValues)->toBe([])
        ->and($plan->changes)->toHaveCount(1)
        ->and([$plan->changes[0]->key, $plan->changes[0]->appLabel, $plan->changes[0]->repoLabel, $plan->changes[0]->repoValue])->toBe(['O-0001/plec', 'Mężczyzna', 'Kobieta', Gender::Female])
        ->and($plan->snapshots['O-0001']['plec'])->toBe('female');
});

test('marks a death the app holds without a repo id and creates one the app lacks', function (?int $appYear, string $expected) {
    [$tree] = replanTwinTrees(['O-0001' => 'imiona: Jan', 'O-0002' => 'imiona: Anna'], function (Tree $tree) use ($appYear): void {
        attachDeath(replanPerson($tree, 'O-0001'), $appYear);
    });

    $plan = replanBlocks($tree, ['O-0001' => "imiona: Jan\nzgon:\n  data: \"1870\"", 'O-0002' => "imiona: Anna\nzgon:\n  data: \"1870\""]);
    $jan = collect($plan->updates)->first(fn ($update): bool => $update->person->id === 'O-0001');
    $anna = collect($plan->updates)->first(fn ($update): bool => $update->person->id === 'O-0002');

    expect([$anna->createDeath, $anna->deathRepoId])->toBe([true, 'O-0002/zgon'])
        ->and($jan->createDeath)->toBeFalse()
        ->and($jan->deathRepoId)->toBe('O-0001/zgon')
        ->and(match ($expected) {
            'same' => [$jan->fills, $jan->conflicts],
            'fill' => [count($jan->fills), $jan->conflicts],
            'conflict' => [$jan->fills, count($jan->conflicts)],
        })->toBe(match ($expected) {
            'same' => [[], []],
            'fill' => [1, []],
            'conflict' => [[], 1],
        })
        ->and($jan->conflicts[0]->lastRepoLabel ?? null)->toBeNull()
        ->and($plan->snapshots)->toHaveKeys(['O-0001/zgon', 'O-0002/zgon'])
        ->and($plan->deaths)->toBe(1);
})->with([
    'the same year' => [1870, 'same'],
    'another year' => [1871, 'conflict'],
    'no date' => [null, 'fill'],
]);

test('plans the new father after the old relation is deleted and skips it when the old relation is only detached', function (string $decision) {
    $v1 = ['O-0001' => "imiona: Dziecko\nrodzice:\n  ojciec: O-0002", 'O-0002' => "imiona: Piotr\nplec: M", 'O-0003' => "imiona: Paweł\nplec: M"];
    [$tree] = replanTwinTrees($v1);

    $plan = replanBlocks($tree, ['O-0001' => "imiona: Dziecko\nrodzice:\n  ojciec: O-0003", 'O-0002' => $v1['O-0002'], 'O-0003' => $v1['O-0003']], removals: ['O-0002>O-0001' => $decision]);

    expect(count($plan->removals))->toBe(1)
        ->and([$plan->removals[0]->key, $plan->removals[0]->kind, $plan->removals[0]->decision])->toBe(['O-0002>O-0001', RepoKeyKind::Relation, $decision])
        ->and($plan->forgottenSnapshots)->toBe(['O-0002>O-0001']);

    if ($decision === 'delete') {
        expect(relationKeys($plan))->toBe(['O-0003>O-0001'])
            ->and($plan->skippedRelations)->toBe([])
            ->and($plan->removals[0]->impact->isEmpty())->toBeTrue();
    } else {
        expect($plan->relations)->toBe([])
            ->and(issueMessages($plan->skippedRelations))->toContain('w drzewie inny ojciec: Piotr')
            ->and($plan->removals[0]->impact)->toBeNull();
    }

    expectPlanInside($plan, $tree);
})->with(['delete', 'detach']);

test('plans the removal of a person with the effects the classification counted and forgets its keys', function () {
    $v1 = [
        'O-0001' => "imiona: Dziecko\nrodzice:\n  ojciec: O-0002\nurodzenie:\n  data: \"1830\"",
        'O-0002' => "imiona: Piotr\nplec: M\nurodzenie:\n  data: \"1801\"",
    ];
    [$tree] = replanTwinTrees($v1, function (Tree $tree): void {
        $piotr = replanPerson($tree, 'O-0002');
        $piotr->update(['photo_path' => 'photos/piotr.jpg']);
        $tree->update(['root_person_id' => $piotr->id]);
    });

    $plan = replanBlocks($tree, ['O-0001' => "imiona: Dziecko\nurodzenie:\n  data: \"1830\""], removals: ['O-0002' => 'delete']);
    $removal = $plan->removals[0];

    expect($plan->removals)->toHaveCount(1)
        ->and([$removal->key, $removal->kind, $removal->decision])->toBe(['O-0002', RepoKeyKind::Person, 'delete'])
        ->and($removal->recordId)->toBe(replanPerson($tree, 'O-0002')->id)
        ->and($removal->memberKeys)->toBe(['O-0002/urodzenie', 'O-0002>O-0001'])
        ->and($removal->impact->photo)->toBeTrue()
        ->and($removal->impact->proband)->toBeTrue()
        ->and($removal->impact->relations)->toBe(1)
        ->and($plan->forgottenSnapshots)->toBe(['O-0002', 'O-0002/urodzenie', 'O-0002>O-0001'])
        ->and($plan->relations)->toBe([])
        ->and($plan->snapshots)->not->toHaveKeys(['O-0002', 'O-0002>O-0001']);

    expectPlanInside($plan, $tree);
});

test('detaches a person without effects and lists a removed birth as detached only', function () {
    $v1 = ['O-0001' => REPLAN_JAN, 'O-0002' => "imiona: Anna\nurodzenie:\n  data: \"1805\""];
    [$tree] = replanTwinTrees($v1);

    $plan = replanBlocks($tree, ['O-0001' => 'imiona: Jan'], removals: ['O-0002' => 'detach', 'O-0001/urodzenie' => 'detach']);

    expect(collect($plan->removals)->map(fn ($removal): array => [$removal->key, $removal->decision, $removal->impact])->all())->toBe([['O-0002', 'detach', null]])
        ->and(collect($plan->detachedBirths)->map(fn ($removal): array => [$removal->key, $removal->kind, $removal->decision])->all())->toBe([['O-0001/urodzenie', RepoKeyKind::Birth, 'detach']])
        ->and($plan->forgottenSnapshots)->toBe(['O-0001/urodzenie', 'O-0002', 'O-0002/urodzenie'])
        ->and(fn () => replanBlocks($tree, ['O-0001' => 'imiona: Jan'], removals: ['O-0002' => 'detach', 'O-0001/urodzenie' => 'delete']))->toThrow(InvalidArgumentException::class);

    expectPlanInside($plan, $tree);
});

test('restores a death deleted in the app from the repo values or leaves it deleted with the repo values remembered', function (string $decision) {
    $v1 = ['O-0001' => "imiona: Jan\nzgon:\n  data: \"1870\""];
    [$tree] = replanTwinTrees($v1, function (Tree $tree): void {
        replanEvent($tree, 'O-0001/zgon')->delete();
    });

    $plan = replanBlocks($tree, $v1, deletions: ['O-0001/zgon' => $decision]);

    if ($decision === 'restore') {
        $restoration = $plan->restorations[0];

        expect($plan->restorations)->toHaveCount(1)
            ->and([$restoration->key, $restoration->kind, $restoration->personId])->toBe(['O-0001/zgon', RepoKeyKind::Death, replanPerson($tree, 'O-0001')->id])
            ->and($restoration->event->date->year)->toBe(1870)
            ->and($plan->snapshots['O-0001/zgon'])->toBe(['data' => '1870'])
            ->and($plan->keptDeletedSnapshots)->toBe([])
            ->and($plan->deaths)->toBe(1);
    } else {
        expect($plan->restorations)->toBe([])
            ->and($plan->keptDeletedSnapshots)->toBe(['O-0001/zgon' => ['data' => '1870']])
            ->and($plan->snapshots)->not->toHaveKey('O-0001/zgon')
            ->and($plan->deaths)->toBe(0);
    }

    expect(fn () => replanBlocks($tree, $v1))->toThrow(InvalidArgumentException::class);
    expectPlanInside($plan, $tree);
})->with(['restore', 'keep']);

test('restores a person deleted in the app as a new person with its note and its events', function () {
    $v1 = ['O-0001' => "imiona: Jan\nurodzenie:\n  data: \"1801\"\n  miejsce: Zalesie"];
    [$tree] = replanTwinTrees($v1, function (Tree $tree): void {
        replanPerson($tree, 'O-0001')->delete();
    });

    $plan = replanBlocks($tree, $v1, deletions: ['O-0001' => 'restore']);
    $restoration = $plan->restorations[0];

    expect($plan->persons)->toBe([])
        ->and($plan->restorations)->toHaveCount(1)
        ->and([$restoration->key, $restoration->kind])->toBe(['O-0001', RepoKeyKind::Person])
        ->and($restoration->person->note)->toBe("Z repo badawczego (O-0001):\nUrodzenie: Zalesie")
        ->and($restoration->person->birthRepoId)->toBe('O-0001/urodzenie')
        ->and($plan->snapshots)->toHaveKeys(['O-0001', 'O-0001/urodzenie'])
        ->and($plan->births)->toBe(1);
});

test('does not plan a relation the author left deleted', function () {
    $v1 = ['O-0001' => 'imiona: Dziecko', 'O-0002' => "imiona: Piotr\nplec: M"];
    $v1['O-0001'] = "imiona: Dziecko\nrodzice:\n  ojciec: O-0002";
    [$tree] = replanTwinTrees($v1, function (Tree $tree): void {
        $tree->parentChildRelations()->where('repo_id', 'O-0002>O-0001')->delete();
        $tree->repoSnapshots()->where('repo_id', 'O-0002>O-0001')->update(['deletion_kept' => true]);
    });

    $plan = replanBlocks($tree, $v1);

    expect($plan->relations)->toBe([])
        ->and($plan->skippedRelations)->toBe([])
        ->and($plan->restorations)->toBe([])
        ->and($plan->snapshots)->not->toHaveKey('O-0002>O-0001')
        ->and(fn () => replanBlocks($tree, $v1, deletions: ['O-0002>O-0001' => 'keep']))->toThrow(InvalidArgumentException::class);
});

test('skips a new relation and a new wedding with a person left deleted, without any memory for them', function () {
    $v1 = ['O-0001' => 'imiona: Jan', 'O-0002' => "imiona: Anna\nplec: K"];
    [$tree] = replanTwinTrees($v1, function (Tree $tree): void {
        replanPerson($tree, 'O-0002')->delete();
    });
    $v2 = [
        'O-0001' => "imiona: Jan\nmalzenstwa:\n  - z: O-0002\n    data: \"1825\"",
        'O-0002' => "imiona: Anna\nplec: K",
        'O-0003' => "imiona: Dziecko\nrodzice:\n  matka: O-0002",
    ];

    $plan = replanBlocks($tree, $v2, deletions: ['O-0002' => 'keep']);

    expect(issueMessages($plan->skippedRelations))->toContain('osoba usunięta w aplikacji (O-0002)')
        ->and(issueMessages($plan->skippedMarriages))->toContain('osoba usunięta w aplikacji (O-0002)')
        ->and($plan->relations)->toBe([])
        ->and($plan->marriages)->toBe([])
        ->and(array_map(fn (PlannedPerson $planned): string => $planned->person->id, $plan->persons))->toBe(['O-0003'])
        ->and($plan->snapshots)->not->toHaveKeys(['O-0002>O-0003', 'O-0001+O-0002/slub'])
        ->and($plan->keptDeletedSnapshots)->toHaveKey('O-0002');
});

test('reports a removal candidate as unassessable when an entry was skipped without a readable id', function () {
    [$tree] = replanTwinTrees(['O-0001' => 'imiona: Jan', 'O-0002' => 'imiona: Anna']);

    $plan = replanBlocks($tree, ['O-0001' => 'imiona: Jan'], files: ['zepsuty.md' => "---\nwersja: 1\nid: O-0003\nimiona: [Jan\n---\n"]);

    expect($plan->removals)->toBe([])
        ->and($plan->unassessable)->toHaveCount(1)
        ->and($plan->unassessable[0]->file)->toBe('O-0002')
        ->and($plan->unassessable[0]->message)->toContain('nie do oceny')
        ->and($plan->forgottenSnapshots)->toBe([]);
});

test('matches a new repo person to a person entered by hand in the app', function () {
    [$tree] = replanTwinTrees(['O-0001' => 'imiona: Jan'], function (Tree $tree): void {
        Person::factory()->for($tree)->create(['first_name' => 'Anna', 'last_name' => 'Wrona']);
    });
    $hand = $tree->people()->whereNull('repo_id')->firstOrFail();

    $plan = replanBlocks($tree, ['O-0001' => 'imiona: Jan', 'O-0002' => "imiona: Anna\nnazwisko: Wrona"], identity: new IdentityDecisions(matched: ['O-0002' => $hand->id]));

    expect($plan->persons)->toBe([])
        ->and($plan->matched)->toHaveCount(1)
        ->and([$plan->matched[0]->person->id, $plan->matched[0]->personId])->toBe(['O-0002', $hand->id])
        ->and($plan->matched[0]->birthRepoId)->toBeNull()
        ->and($plan->snapshots)->toHaveKey('O-0002');

    expectPlanInside($plan, $tree);
});

test('rejects decisions that miss a removal, a deletion or a new person, or use an unknown answer', function () {
    $v1 = ['O-0001' => "imiona: Jan\nzgon:\n  data: \"1870\"", 'O-0002' => 'imiona: Anna'];
    [$tree] = replanTwinTrees($v1, function (Tree $tree): void {
        replanEvent($tree, 'O-0001/zgon')->delete();
    });
    $v2 = ['O-0001' => "imiona: Jan\nzgon:\n  data: \"1870\"", 'O-0003' => 'imiona: Ewa'];

    expect(fn () => replanBlocks($tree, $v2, deletions: ['O-0001/zgon' => 'keep']))->toThrow(InvalidArgumentException::class)
        ->and(fn () => replanBlocks($tree, $v2, removals: ['O-0002' => 'detach']))->toThrow(InvalidArgumentException::class)
        ->and(fn () => replanBlocks($tree, $v2, removals: ['O-0002' => 'detach'], deletions: ['O-0001/zgon' => 'maybe']))->toThrow(InvalidArgumentException::class)
        ->and(fn () => replanBlocks($tree, $v2, removals: ['O-0002' => 'detach'], deletions: ['O-0001/zgon' => 'keep'], identity: new IdentityDecisions))->toThrow(InvalidArgumentException::class)
        ->and(count(replanBlocks($tree, $v2, removals: ['O-0002' => 'detach'], deletions: ['O-0001/zgon' => 'keep'])->removals))->toBe(1);
});

test('plans a wedding date the repo changed as a change and keeps a known wedding without news out of the plan', function () {
    $both = fn (string $date): array => [
        'O-0001' => "imiona: Jan\nmalzenstwa:\n  - z: O-0002\n    data: \"{$date}\"",
        'O-0002' => "imiona: Anna\nmalzenstwa:\n  - z: O-0001\n    data: \"{$date}\"",
    ];
    [$tree] = replanTwinTrees($both('1825'));

    $plan = replanBlocks($tree, $both('1826'));
    $wedding = $plan->marriages[0];

    expect($plan->marriages)->toHaveCount(1)
        ->and($wedding->known)->toBeTrue()
        ->and($wedding->existingEventId)->toBe(replanEvent($tree, 'O-0001+O-0002/slub')->id)
        ->and($wedding->dateChange->key)->toBe('O-0001+O-0002/slub/data')
        ->and([$wedding->dateChange->appLabel, $wedding->dateChange->repoLabel])->toBe(['1825', '1826'])
        ->and($plan->changes)->toBe([$wedding->dateChange])
        ->and($plan->weddings)->toBe(0)
        ->and(replanBlocks($tree, $both('1825'))->marriages)->toBe([]);

    expectPlanInside($plan, $tree);
});

test('marks a new wedding between persons who already have a wedding without a repo id', function () {
    [$tree] = replanTwinTrees(['O-0001' => 'imiona: Jan', 'O-0002' => 'imiona: Anna'], function (Tree $tree): void {
        $wedding = attachMarriage(replanPerson($tree, 'O-0001'), replanPerson($tree, 'O-0002'), 1830);
    });

    $plan = replanBlocks($tree, [
        'O-0001' => "imiona: Jan\nmalzenstwa:\n  - z: O-0002\n    data: \"1825\"",
        'O-0002' => 'imiona: Anna',
    ]);
    $wedding = $plan->marriages[0];

    expect($wedding->known)->toBeFalse()
        ->and($wedding->existingEventId)->toBe($tree->events()->where('type', EventType::Marriage)->firstOrFail()->id)
        ->and($wedding->dateConflict->lastRepoLabel)->toBeNull()
        ->and($plan->weddings)->toBe(0);

    expectPlanInside($plan, $tree);
});
