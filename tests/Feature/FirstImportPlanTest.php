<?php

use App\Actions\Import\PlanFirstImport;
use App\Actions\Import\ReadResearchRepo;
use App\Models\Person;
use App\Models\Tree;
use App\Models\User;
use App\Support\Import\FirstImportPlan;
use App\Support\Import\ImportIssue;
use App\Support\Import\PlannedPerson;
use App\Support\Import\PlannedRelation;
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

function planFixtureImport(Tree $tree): FirstImportPlan
{
    $reading = app(ReadResearchRepo::class)->handle(base_path('tests/Fixtures/research-repo'));

    return app(PlanFirstImport::class)->handle($tree, $reading);
}

/**
 * Plans a throwaway repo built from person blocks, e.g. ['O-0001' => "imiona: Jan\nplec: M"].
 *
 * @param  array<string, string>  $blocks  repo id => block body without id and version
 */
function planTemporaryRepo(Tree $tree, array $blocks): FirstImportPlan
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

    return app(PlanFirstImport::class)->handle($tree, $reading);
}

function plannedPerson(FirstImportPlan $plan, string $id): PlannedPerson
{
    return collect($plan->persons)->first(fn (PlannedPerson $planned): bool => $planned->person->id === $id);
}

/**
 * @return list<string>
 */
function relationKeys(FirstImportPlan $plan): array
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

    expect($plan->blockedReason)->toBeNull()
        ->and($plan->persons)->toHaveCount(13)
        ->and($plan->relations)->toHaveCount(3)
        ->and($plan->skippedRelations)->toHaveCount(1)
        ->and($plan->marriages)->toHaveCount(3)
        ->and([$plan->births, $plan->deaths, $plan->weddings])->toBe([5, 2, 3]);
});

test('does not count the persons of another account', function () {
    $tree = arrangeEmptyAuthorAndOverlappingOther();

    expect(Person::count())->toBe(3)
        ->and(planFixtureImport($tree)->blockedReason)->toBeNull();
});

test('blocks a tree that already has a person', function () {
    $tree = arrangeEmptyAuthorAndOverlappingOther();
    Person::factory()->for($tree)->create(['first_name' => 'Jan', 'last_name' => 'Nowak']);

    $plan = planFixtureImport($tree);

    expect($plan->blockedReason)->toContain('Drzewo konta ma już osoby')
        ->and($plan->persons)->toHaveCount(13);
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
