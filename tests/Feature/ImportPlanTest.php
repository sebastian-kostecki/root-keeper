<?php

use App\Actions\Import\ApplyImport;
use App\Actions\Import\ClassifyRepoRecords;
use App\Actions\Import\MatchResearchPlaces;
use App\Actions\Import\PlanImport;
use App\Actions\Import\ReadResearchRepo;
use App\Enums\EventType;
use App\Enums\Gender;
use App\Enums\ParishKind;
use App\Enums\ParishMatchKind;
use App\Enums\ParticipantRole;
use App\Enums\PlaceMatchKind;
use App\Enums\Religion;
use App\Enums\RepoKeyKind;
use App\Enums\SourceRecordType;
use App\Models\Cemetery;
use App\Models\Event;
use App\Models\Locality;
use App\Models\Parish;
use App\Models\Person;
use App\Models\Source;
use App\Models\Tree;
use App\Models\User;
use App\Support\Import\CemeteryMatches;
use App\Support\Import\ChangeApprovals;
use App\Support\Import\ConflictResolutions;
use App\Support\Import\IdentityDecisions;
use App\Support\Import\ImportDecisions;
use App\Support\Import\ImportIssue;
use App\Support\Import\ImportPlan;
use App\Support\Import\ParishMatches;
use App\Support\Import\ParishRequest;
use App\Support\Import\ParishVerdict;
use App\Support\Import\PlaceDecisions;
use App\Support\Import\PlaceMatcher;
use App\Support\Import\PlaceMatches;
use App\Support\Import\PlaceName;
use App\Support\Import\PlaceResolver;
use App\Support\Import\PlannedAlias;
use App\Support\Import\PlannedChange;
use App\Support\Import\PlannedFill;
use App\Support\Import\PlannedMatch;
use App\Support\Import\PlannedPerson;
use App\Support\Import\PlannedPlace;
use App\Support\Import\PlannedRelation;
use App\Support\Import\RepoValues;
use App\Support\Import\ResearchParish;
use App\Support\Import\ResearchRepoReading;
use App\Support\Import\SourceDecisions;
use App\Support\Import\SourceLinks;
use App\Support\Import\ValueConflict;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

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
        Pochowanie: Zalesie
        Ślub z Anna Wrona (O-0004): Zalesie; poziom B
        NOTE);
});

test('counts the surname variants of a new person without repeats', function () {
    $plan = planTemporaryRepo(arrangeEmptyAuthorAndOverlappingOther(), ['O-0001' => "imiona: Jan\nwarianty_nazwiska: [Nowakowski, NOWAKOWSKI, Nowacki, ' ']"]);

    expect($plan->noteValues->surnameVariants)->toBe(2);
});

test('leaves the note empty for a person with only surname variants', function () {
    $plan = planTemporaryRepo(arrangeEmptyAuthorAndOverlappingOther(), ['O-0001' => "imiona: Jan\nwarianty_nazwiska: [Nowakowski]"]);

    expect(plannedPerson($plan, 'O-0001')->note)->toBeNull()
        ->and($plan->snapshots['O-0001']['warianty'])->toBe('Nowakowski');
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

    expect(plannedPerson($plan, 'O-0001')->note)->toBe("Z repo badawczego (O-0001):\nZgon: poziom C\nŚlub z Ewa (O-0002): Zalesie")
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

    expect([$counts->unplacedEvents, $counts->burials, $counts->levels, $counts->eventNotes, $counts->surnameVariants])->toBe([4, 0, 2, 1, 1]);
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
        ->and($plan->snapshots['O-0001/urodzenie'])->toBe(['data' => '1801', 'miejsce' => null, 'miejsce_id' => null]);

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
        ->and($plan->snapshots['O-0001/urodzenie'])->toBe(['data' => '1802', 'miejsce' => null, 'miejsce_id' => null]);

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
        ->and($plan->snapshots['O-0001/urodzenie'])->toBe(['data' => '1801', 'miejsce' => null, 'miejsce_id' => null]);
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
            ->and($plan->snapshots['O-0001/zgon'])->toBe(['data' => '1870', 'miejsce' => null, 'miejsce_id' => null])
            ->and($plan->keptDeletedSnapshots)->toBe([])
            ->and($plan->deaths)->toBe(1);
    } else {
        expect($plan->restorations)->toBe([])
            ->and($plan->keptDeletedSnapshots)->toBe(['O-0001/zgon' => ['data' => '1870', 'miejsce' => null]])
            ->and($plan->snapshots)->not->toHaveKey('O-0001/zgon')
            ->and($plan->deaths)->toBe(0);
    }

    expect(fn () => replanBlocks($tree, $v1))->toThrow(InvalidArgumentException::class);
    expectPlanInside($plan, $tree);
})->with(['restore', 'keep']);

test('asks about the places of a person brought back after a deletion in the app and keeps what the memory recognized', function () {
    $v1 = ['O-0001' => "imiona: Jan\nurodzenie:\n  data: \"1801\"\n  miejsce: Zalesie\nzgon:\n  data: \"1870\"\n  miejsce: Bukinia"];
    [$tree, $other] = replanTwinTrees([], function (Tree $tree) use ($v1): void {
        Locality::factory()->for($tree)->create(['name' => 'Bukinia']);
        $wola = Locality::factory()->for($tree)->create(['name' => 'Wola']);
        replanFirstImport($tree, $v1);
        $tree->repoSnapshots()->where('repo_id', 'O-0001/urodzenie')->update(['values' => json_encode(['data' => '1801', 'miejsce' => 'Zalesie', 'miejsce_id' => 'L:'.$wola->id])]);
        replanPerson($tree, 'O-0001')->delete();
    });
    $bukinia = $tree->localities()->where('name', 'Bukinia')->firstOrFail();
    $wola = $tree->localities()->where('name', 'Wola')->firstOrFail();
    $v2 = ['O-0001' => str_replace('miejsce: Bukinia', 'miejsce: Bokinia', $v1['O-0001'])];
    $reading = replanReading($v2);
    $classes = app(ClassifyRepoRecords::class)->handle($tree, $reading);
    $deletions = ['O-0001' => ImportDecisions::RESTORE];

    $matches = app(MatchResearchPlaces::class)->handle($tree, $reading, $classes, new ImportDecisions(new IdentityDecisions, [], $deletions));
    $plan = app(PlanImport::class)->handle($tree, $reading, $classes, new ImportDecisions(new IdentityDecisions, [], $deletions, new PlaceDecisions(
        localities: ['bokinia' => $bukinia->id],
        aliases: ['bokinia' => true],
    )));
    $restored = $plan->restorations[0]->person;

    expect(array_keys($matches->localities))->toBe(['bokinia'])
        ->and($matches->localities['bokinia']->kind)->toBe(PlaceMatchKind::Doubtful)
        ->and(array_map(fn (Locality $locality): int => $locality->tree_id, $matches->localities['bokinia']->candidates))->each->toBe($tree->id)
        ->and($restored->deathPlace?->localityId)->toBe($bukinia->id)
        ->and($restored->birthPlace?->localityId)->toBe($wola->id)
        ->and($other->localityAliases()->count())->toBe(0);
});

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

/**
 * Localities of a tree by name.
 *
 * @param  list<string>  $names
 * @return array<string, Locality>
 */
function placeWorld(Tree $tree, array $names): array
{
    $world = [];

    foreach ($names as $name) {
        $world[$name] = Locality::factory()->for($tree)->create(['name' => $name]);
    }

    return $world;
}

/**
 * Plans a throwaway repo over the tree with the given answers about the places. Every repo person is new unless matched.
 *
 * @param  array<string, string>  $blocks  repo id => block body without id and version
 * @param  array<string, string>  $deletions
 * @param  array<string, Person>  $matched  repo id => the tree person it is
 * @param  array<string, string>  $removals
 */
function placePlan(Tree $tree, array $blocks, ?PlaceDecisions $places = null, array $deletions = [], array $matched = [], array $removals = []): ImportPlan
{
    $reading = replanReading($blocks);
    $classes = app(ClassifyRepoRecords::class)->handle($tree, $reading);
    $identity = new IdentityDecisions(
        array_map(fn (Person $person): int => $person->id, $matched),
        [],
        array_values(array_diff($classes->newPersonIds, array_keys($matched))),
    );

    return app(PlanImport::class)->handle($tree, $reading, $classes, new ImportDecisions($identity, $removals, $deletions, $places ?? new PlaceDecisions));
}

/**
 * The block of a person born in the given place, with a date.
 *
 * @return array<string, string>
 */
function bornAt(string $place): array
{
    return ['O-0001' => "imiona: Jan\nnazwisko: Nowak\nurodzenie:\n  data: \"1801\"\n  miejsce: {$place}"];
}

/**
 * Jan Nowak (O-0001) as an earlier import left him: a birth in the given locality and the memory of the repo text and
 * the id it was recognized as. `$token` `false` leaves the id out, as the imports before the place list did.
 */
function placeKnownBirth(Tree $tree, ?Locality $app, ?string $text, string|false|null $token): Event
{
    $person = Person::factory()->for($tree)->create(['first_name' => 'Jan', 'last_name' => 'Nowak', 'gender' => null, 'note' => null, 'repo_id' => 'O-0001']);
    $birth = Event::factory()->birth()->for($tree)->create([
        'repo_id' => 'O-0001/urodzenie',
        'date_year' => 1801,
        'date_month' => null,
        'date_day' => null,
        'locality_id' => $app?->id,
    ]);
    $birth->participants()->attach($person, ['role' => ParticipantRole::Subject]);

    $tree->repoSnapshots()->create(['repo_id' => 'O-0001', 'values' => ['imiona' => 'Jan', 'nazwisko' => 'Nowak', 'plec' => null, 'notatka' => null]]);
    $tree->repoSnapshots()->create(['repo_id' => 'O-0001/urodzenie', 'values' => ['data' => '1801', 'miejsce' => $text, ...($token === false ? [] : ['miejsce_id' => $token])]]);

    return $birth;
}

/**
 * @return PlannedChange|PlannedFill|ValueConflict|null
 */
function placeItemOf(ImportPlan $plan, string $field): mixed
{
    return collect([...$plan->changes, ...$plan->conflicts, ...array_merge(...array_map(fn ($update) => $update->fills, $plan->updates)), ...array_merge(...array_map(fn ($match) => $match->fills, $plan->matched))])
        ->first(fn ($item): bool => $item->field === $field);
}

test('puts a sure locality on the created birth and drops its text from the note', function () {
    [$tree, $world] = arrangeAuthorWithTwin(fn (Tree $tree): array => placeWorld($tree, ['Zalesie']));

    $plan = placePlan($tree, ['O-0001' => "imiona: Jan\nnazwisko: Nowak\nurodzenie:\n  miejsce: Zalesie"]);
    $planned = plannedPerson($plan, 'O-0001');

    expect($planned->birthPlace->localityId)->toBe($world['Zalesie']->id)
        ->and($planned->note)->toBeNull()
        ->and($plan->snapshots['O-0001/urodzenie'])->toBe(['data' => null, 'miejsce' => 'Zalesie', 'miejsce_id' => 'L:'.$world['Zalesie']->id])
        ->and([$plan->noteValues->placedEvents, $plan->noteValues->unplacedEvents, $plan->noteValues->newLocalities])->toBe([1, 0, 0])
        ->and($plan->newLocalities)->toBe([]);
});

test('adds a new locality the author chose and plans the event with the key of its name', function () {
    [$tree] = arrangeAuthorWithTwin(fn (Tree $tree): array => placeWorld($tree, ['Wola']));

    $plan = placePlan($tree, bornAt('Bokinia'), new PlaceDecisions(localities: ['bokinia' => PlaceDecisions::NEW]));
    $planned = plannedPerson($plan, 'O-0001');

    expect($planned->birthPlace)->toEqual(new PlannedPlace('nowa: Bokinia', newLocality: 'bokinia'))
        ->and($planned->note)->toBeNull()
        ->and($plan->newLocalities)->toBe(['bokinia' => 'Bokinia'])
        ->and($plan->snapshots['O-0001/urodzenie']['miejsce_id'])->toBe('N:bokinia')
        ->and($plan->noteValues->newLocalities)->toBe(1);
});

test('leaves a place the author answered with no place in the note and plans none', function () {
    [$tree] = arrangeAuthorWithTwin(fn (Tree $tree): array => placeWorld($tree, ['Wola']));

    $plan = placePlan($tree, bornAt('Bokinia'), new PlaceDecisions(localities: ['bokinia' => PlaceDecisions::NONE]));
    $planned = plannedPerson($plan, 'O-0001');

    expect($planned->birthPlace)->toBeNull()
        ->and($planned->note)->toBe("Z repo badawczego (O-0001):\nUrodzenie: Bokinia")
        ->and($plan->newLocalities)->toBe([])
        ->and($plan->snapshots['O-0001/urodzenie']['miejsce_id'])->toBeNull()
        ->and([$plan->noteValues->placedEvents, $plan->noteValues->unplacedEvents])->toBe([0, 1]);
});

test('remembers the spelling as a variant when the author picks a doubtful candidate', function () {
    [$tree, $world] = arrangeAuthorWithTwin(fn (Tree $tree): array => placeWorld($tree, ['Bukinia']));

    $plan = placePlan($tree, bornAt('Bokinia'), new PlaceDecisions(
        localities: ['bokinia' => $world['Bukinia']->id],
        aliases: ['bokinia' => true],
    ));
    $planned = plannedPerson($plan, 'O-0001');

    expect($planned->birthPlace->localityId)->toBe($world['Bukinia']->id)
        ->and($planned->birthPlace->label)->toBe('Bukinia (w repo: Bokinia)')
        ->and($plan->aliases)->toEqual(['bokinia' => new PlannedAlias('Bokinia', $world['Bukinia']->id)])
        ->and($plan->noteValues->aliases)->toBe(1)
        ->and($planned->note)->toBeNull();
});

test('rejects answers about places that import did not ask about or that point outside the tree', function () {
    [$tree, $world] = arrangeAuthorWithTwin(fn (Tree $tree): array => placeWorld($tree, ['Zalesie', 'Bukinia']));
    $foreign = Locality::factory()->for(User::factory()->create()->tree)->create(['name' => 'Bukinia']);

    expect(fn () => placePlan($tree, bornAt('Zalesie'), new PlaceDecisions(localities: ['zalesie' => PlaceDecisions::NEW])))->toThrow(InvalidArgumentException::class)
        ->and(fn () => placePlan($tree, bornAt('Bokinia'), new PlaceDecisions(localities: ['bokinia' => $foreign->id])))->toThrow(InvalidArgumentException::class)
        ->and(fn () => placePlan($tree, bornAt('Bokinia'), new PlaceDecisions(aliases: ['bokinia' => true])))->toThrow(InvalidArgumentException::class)
        ->and(fn () => placePlan($tree, bornAt('Bokinia'), new PlaceDecisions))->toThrow(InvalidArgumentException::class, 'Brak decyzji o niepewnej miejscowości bokinia')
        ->and(fn () => placePlan($tree, bornAt('Bokinia'), new PlaceDecisions(localities: ['bokinia' => $world['Bukinia']->id])))->not->toThrow(InvalidArgumentException::class);
});

test('rejects a burial whose cemetery has to be chosen when the answers leave it out', function () {
    [$tree, $world] = arrangeAuthorWithTwin(function (Tree $tree): array {
        $world = placeWorld($tree, ['Zalesie']);
        Cemetery::factory()->in($world['Zalesie'])->create(['name' => 'Stary']);
        Cemetery::factory()->in($world['Zalesie'])->create(['name' => 'Nowy']);

        return $world;
    });
    $blocks = ['O-0001' => "imiona: Jan\nnazwisko: Nowak\npochowek:\n  miejsce: Zalesie"];

    expect(fn () => placePlan($tree, $blocks))->toThrow(InvalidArgumentException::class, 'Brak decyzji o cmentarzu zalesie|')
        ->and(fn () => placePlan($tree, $blocks, new PlaceDecisions(cemeteries: ['zalesie|' => PlaceDecisions::NONE])))->not->toThrow(InvalidArgumentException::class);
});

test('plans a burial with the cemetery of a sure locality and a death without a date', function () {
    [$tree, $world] = arrangeAuthorWithTwin(function (Tree $tree): array {
        $zalesie = Locality::factory()->for($tree)->create(['name' => 'Zalesie']);

        return ['Zalesie' => $zalesie, 'cemetery' => Cemetery::factory()->in($zalesie)->unnamed()->create()];
    });

    $plan = placePlan($tree, ['O-0001' => "imiona: Jan\nnazwisko: Nowak\npochowek:\n  data: \"1900\"\n  miejsce: Zalesie"]);
    $planned = plannedPerson($plan, 'O-0001');

    expect($planned->deathRepoId)->toBe('O-0001/zgon')
        ->and($planned->burialRepoId)->toBe('O-0001/pochowek')
        ->and($planned->burialPlace->cemeteryId)->toBe($world['cemetery']->id)
        ->and($planned->note)->toBe("Z repo badawczego (O-0001):\nPochowanie: 1900")
        ->and($plan->snapshots['O-0001/pochowek'])->toBe([
            'data' => '1900',
            'miejsce' => 'Zalesie',
            'cmentarz' => null,
            'miejsce_id' => 'L:'.$world['Zalesie']->id,
            'cmentarz_id' => 'C:'.$world['cemetery']->id,
        ])
        ->and($plan->noteValues->burials)->toBe(1);
});

test('adds a new cemetery in a new locality without a question', function () {
    [$tree] = arrangeAuthorWithTwin(fn (Tree $tree): array => placeWorld($tree, ['Wola']));

    $plan = placePlan($tree, ['O-0001' => "imiona: Jan\nnazwisko: Nowak\npochowek:\n  miejsce: Bindyga\n  cmentarz: Parafialny"], new PlaceDecisions(localities: ['bindyga' => PlaceDecisions::NEW]));
    $planned = plannedPerson($plan, 'O-0001');

    expect($planned->burialPlace->newCemetery)->toBe('bindyga|parafialny')
        ->and($plan->newLocalities)->toBe(['bindyga' => 'Bindyga'])
        ->and($plan->newCemeteries['bindyga|parafialny']->name)->toBe('Parafialny')
        ->and($plan->newCemeteries['bindyga|parafialny']->newLocality)->toBe('bindyga')
        ->and($plan->noteValues->newCemeteries)->toBe(1);
});

test('plans no burial for a cemetery left out or a burial without a place and keeps the text and the memory', function () {
    [$tree] = arrangeAuthorWithTwin(fn (Tree $tree): array => placeWorld($tree, ['Wola']));

    $left = placePlan($tree, ['O-0001' => "imiona: Jan\nnazwisko: Nowak\npochowek:\n  data: \"1900\"\n  miejsce: Bokinia"], new PlaceDecisions(localities: ['bokinia' => PlaceDecisions::NONE]));
    $bare = placePlan($tree, ['O-0001' => "imiona: Jan\nnazwisko: Nowak\npochowek:\n  data: \"1900\""]);

    foreach ([$left, $bare] as $plan) {
        expect(plannedPerson($plan, 'O-0001')->burialRepoId)->toBeNull()
            ->and(plannedPerson($plan, 'O-0001')->burialPlace)->toBeNull()
            ->and($plan->snapshots['O-0001/pochowek']['cmentarz_id'])->toBeNull()
            ->and($plan->noteValues->burials)->toBe(0);
    }

    expect(plannedPerson($left, 'O-0001')->note)->toBe("Z repo badawczego (O-0001):\nPochowanie: 1900; Bokinia")
        ->and(plannedPerson($bare, 'O-0001')->note)->toBe("Z repo badawczego (O-0001):\nPochowanie: 1900");
});

test('does not ask again and sees no burial deleted in the app for a burial remembered without a record', function () {
    [$tree] = arrangeAuthorWithTwin(fn (Tree $tree): array => placeWorld($tree, ['Wola']));
    $blocks = ['O-0001' => "imiona: Jan\nnazwisko: Nowak\npochowek:\n  data: \"1900\"\n  miejsce: Bokinia"];
    $first = placePlan($tree, $blocks, new PlaceDecisions(localities: ['bokinia' => PlaceDecisions::NONE]));
    $person = Person::factory()->for($tree)->create(['first_name' => 'Jan', 'last_name' => 'Nowak', 'repo_id' => 'O-0001']);
    $death = Event::factory()->death()->for($tree)->create(['repo_id' => 'O-0001/zgon']);
    $death->participants()->attach($person, ['role' => ParticipantRole::Subject]);

    foreach ($first->snapshots as $key => $values) {
        $tree->repoSnapshots()->create(['repo_id' => $key, 'values' => $values]);
    }

    $reading = replanReading($blocks);
    $classes = app(ClassifyRepoRecords::class)->handle($tree, $reading);
    $second = app(PlanImport::class)->handle($tree, $reading, $classes, new ImportDecisions(new IdentityDecisions(new: $classes->newPersonIds)));

    expect($classes->deletedInApp)->toBe([])
        ->and($classes->keptDeleted)->toBe([])
        ->and($second->restorations)->toBe([])
        ->and($second->snapshots['O-0001/pochowek']['cmentarz_id'])->toBeNull();
});

test('fills the empty locality of a matched person and reports a different one as a conflict', function () {
    [$tree] = arrangeAuthorWithTwin(function (Tree $tree): array {
        $world = placeWorld($tree, ['Zalesie', 'Wola']);
        $empty = Person::factory()->for($tree)->create(['first_name' => 'Jan', 'last_name' => 'Nowak', 'gender' => null, 'note' => null]);
        $other = Person::factory()->for($tree)->create(['first_name' => 'Piotr', 'last_name' => 'Nowak', 'gender' => null, 'note' => null]);

        foreach ([[$empty, null], [$other, $world['Wola']]] as [$person, $locality]) {
            $birth = Event::factory()->birth()->for($tree)->create(['date_year' => 1801, 'date_month' => null, 'date_day' => null, 'locality_id' => $locality?->id]);
            $birth->participants()->attach($person, ['role' => ParticipantRole::Subject]);
        }

        return ['empty' => $empty, 'other' => $other, 'Zalesie' => $world['Zalesie']];
    });
    $people = [
        'empty' => $tree->people()->where('first_name', 'Jan')->firstOrFail(),
        'other' => $tree->people()->where('first_name', 'Piotr')->firstOrFail(),
    ];
    $zalesie = $tree->localities()->where('name', 'Zalesie')->firstOrFail();

    $plan = placePlan($tree, [
        'O-0001' => "imiona: Jan\nnazwisko: Nowak\nurodzenie:\n  data: \"1801\"\n  miejsce: Zalesie",
        'O-0002' => "imiona: Piotr\nnazwisko: Nowak\nurodzenie:\n  data: \"1801\"\n  miejsce: Zalesie",
    ], matched: ['O-0001' => $people['empty'], 'O-0002' => $people['other']]);

    $fill = plannedMatch($plan, 'O-0001')->fills[0];
    $conflict = plannedMatch($plan, 'O-0002')->conflicts[0];

    expect($fill)->toBeInstanceOf(PlannedFill::class)
        ->and([$fill->field, $fill->value->localityId])->toBe(['birth_place', $zalesie->id])
        ->and($conflict)->toBeInstanceOf(ValueConflict::class)
        ->and([$conflict->key, $conflict->field, $conflict->appLabel, $conflict->repoLabel, $conflict->lastRepoLabel])->toBe(['O-0002/urodzenie/miejsce', 'birth_place', 'Wola', 'Zalesie', null])
        ->and($conflict->repoValue->localityId)->toBe($zalesie->id);
});

test('plans nothing for a place the repo and the tree left alone since the last import', function () {
    [$tree] = replanPlaceTwin(fn (Tree $tree, array $world): Event => placeKnownBirth($tree, $world['Zalesie'], 'Zalesie', 'L:'.$world['Zalesie']->id), ['Zalesie']);

    $plan = placePlan($tree, bornAt('Zalesie'));

    expect($plan->changes)->toBe([])
        ->and($plan->conflicts)->toBe([])
        ->and($plan->keptManual)->toBe(0)
        ->and(placeItemOf($plan, 'birth_place'))->toBeNull();
});

/**
 * Builds the localities and the arranged state in the tree of another account and then in the tree of the author.
 *
 * @param  Closure(Tree, array<string, Locality>): mixed  $arrange
 * @param  list<string>  $names
 * @return array{0: Tree, 1: array<string, Locality>}
 */
function replanPlaceTwin(Closure $arrange, array $names): array
{
    $other = User::factory()->create();
    $author = User::factory()->create();
    $world = [];

    foreach ([$other->tree, $author->tree] as $tree) {
        $world = placeWorld($tree, $names);
        $arrange($tree, $world);
    }

    return [$author->tree, $world];
}

test('plans a place the repo changed and nobody touched in the app as a change', function () {
    [$tree, $world] = replanPlaceTwin(fn (Tree $tree, array $world): Event => placeKnownBirth($tree, $world['Zalesie'], 'Zalesie', 'L:'.$world['Zalesie']->id), ['Zalesie', 'Wola']);

    $plan = placePlan($tree, bornAt('Wola'));
    $change = placeItemOf($plan, 'birth_place');

    expect($change)->toBeInstanceOf(PlannedChange::class)
        ->and([$change->key, $change->appLabel, $change->repoLabel, $change->repoValue->localityId])->toBe(['O-0001/urodzenie/miejsce', 'Zalesie', 'Wola', $world['Wola']->id])
        ->and($plan->snapshots['O-0001/urodzenie']['miejsce_id'])->toBe('L:'.$world['Wola']->id);
});

test('plans a conflict with the previous repo place when the author and the repo both changed it', function () {
    [$tree, $world] = replanPlaceTwin(fn (Tree $tree, array $world): Event => placeKnownBirth($tree, $world['Wola'], 'Zalesie', 'L:'.$world['Zalesie']->id), ['Zalesie', 'Wola', 'Kamionka']);

    $plan = placePlan($tree, bornAt('Kamionka'));
    $conflict = placeItemOf($plan, 'birth_place');

    expect($conflict)->toBeInstanceOf(ValueConflict::class)
        ->and([$conflict->appLabel, $conflict->repoLabel, $conflict->lastRepoLabel])->toBe(['Wola', 'Kamionka', 'Zalesie'])
        ->and($plan->conflicts)->toHaveCount(1);
});

test('keeps the place changed by hand and the repo text in the note while the repo did not move', function () {
    [$tree] = replanPlaceTwin(fn (Tree $tree, array $world): Event => placeKnownBirth($tree, $world['Wola'], 'Zalesie', 'L:'.$world['Zalesie']->id), ['Zalesie', 'Wola']);

    $plan = placePlan($tree, bornAt('Zalesie'));

    expect(placeItemOf($plan, 'birth_place'))->toBeNull()
        ->and($plan->keptManual)->toBe(1)
        ->and($plan->snapshots['O-0001']['notatka'])->toBe("Z repo badawczego (O-0001):\nUrodzenie: Zalesie");
});

test('reads the remembered variant as the same place', function () {
    [$tree] = replanPlaceTwin(function (Tree $tree, array $world): Event {
        $world['Bukinia']->aliases()->create(['tree_id' => $tree->id, 'name' => 'Bokinia']);

        return placeKnownBirth($tree, $world['Bukinia'], 'Bokinia', 'L:'.$world['Bukinia']->id);
    }, ['Bukinia']);

    $plan = placePlan($tree, bornAt('Bokinia'));

    expect(placeItemOf($plan, 'birth_place'))->toBeNull()
        ->and($plan->keptManual)->toBe(0)
        ->and($plan->conflicts)->toBe([]);
});

test('fills a place from a memory that an earlier import left without an id and conflicts with a different one', function (bool $empty) {
    [$tree, $world] = replanPlaceTwin(fn (Tree $tree, array $world): Event => placeKnownBirth($tree, $empty ? null : $world['Wola'], 'Zalesie', false), ['Zalesie', 'Wola']);

    $plan = placePlan($tree, bornAt('Zalesie'));
    $item = placeItemOf($plan, 'birth_place');

    if ($empty) {
        expect($item)->toBeInstanceOf(PlannedFill::class)->and($item->value->localityId)->toBe($world['Zalesie']->id);
    } else {
        expect($item)->toBeInstanceOf(ValueConflict::class)->and($item->lastRepoLabel)->toBeNull();
    }
})->with([true, false]);

test('does not conflict when the locality was renamed in the app', function () {
    [$tree, $world] = replanPlaceTwin(function (Tree $tree, array $world): Event {
        $event = placeKnownBirth($tree, $world['Zalesie'], 'Zalesie', 'L:'.$world['Zalesie']->id);
        $world['Zalesie']->update(['name' => 'Zalesie Nowe']);

        return $event;
    }, ['Zalesie']);

    $plan = placePlan($tree, bornAt('Zalesie'));

    expect(placeItemOf($plan, 'birth_place'))->toBeNull()
        ->and($plan->conflicts)->toBe([])
        ->and($plan->keptManual)->toBe(0);
});

test('reads a merged locality as unchanged when the target took the name as a variant', function () {
    [$tree] = replanPlaceTwin(function (Tree $tree, array $world): Event {
        $world['Zalesie Dolne']->aliases()->create(['tree_id' => $tree->id, 'name' => 'Zalesie']);

        return placeKnownBirth($tree, $world['Zalesie Dolne'], 'Zalesie', 'L:999999');
    }, ['Zalesie Dolne']);

    $plan = placePlan($tree, bornAt('Zalesie'));

    expect(placeItemOf($plan, 'birth_place'))->toBeNull()
        ->and($plan->conflicts)->toBe([])
        ->and($plan->snapshots['O-0001/urodzenie']['miejsce_id'])->toStartWith('L:');
});

test('takes a place the list recognizes now that an earlier import left as no place and drops its text from the note', function () {
    [$tree, $world] = replanPlaceTwin(function (Tree $tree, array $world): Event {
        $event = placeKnownBirth($tree, null, 'Zalesie', null);
        $block = "Z repo badawczego (O-0001):\nUrodzenie: Zalesie";
        $tree->people()->where('repo_id', 'O-0001')->update(['note' => $block]);
        $tree->repoSnapshots()->where('repo_id', 'O-0001')->update(['values' => ['imiona' => 'Jan', 'nazwisko' => 'Nowak', 'plec' => null, 'notatka' => $block]]);

        return $event;
    }, ['Zalesie']);

    $plan = placePlan($tree, bornAt('Zalesie'));
    $change = placeItemOf($plan, 'birth_place');
    $note = collect($plan->changes)->first(fn (PlannedChange $item): bool => $item->field === 'note');

    expect($change)->toBeInstanceOf(PlannedChange::class)
        ->and($change->repoValue->localityId)->toBe($world['Zalesie']->id)
        ->and($note->repoValue)->toBeNull()
        ->and($plan->snapshots['O-0001']['notatka'])->toBeNull();
});

test('brings back a burial deleted in the app with its cemetery or leaves it deleted, and removes a burial the repo dropped', function () {
    [$tree, $world] = replanPlaceTwin(function (Tree $tree, array $world): void {
        $cemetery = Cemetery::factory()->in($world['Zalesie'])->unnamed()->create();
        $person = Person::factory()->for($tree)->create(['first_name' => 'Jan', 'last_name' => 'Nowak', 'repo_id' => 'O-0001']);
        $death = Event::factory()->death()->for($tree)->create(['repo_id' => 'O-0001/zgon', 'date_year' => 1900]);
        $death->participants()->attach($person, ['role' => ParticipantRole::Subject]);
        $tree->repoSnapshots()->create(['repo_id' => 'O-0001', 'values' => ['imiona' => 'Jan', 'nazwisko' => 'Nowak', 'plec' => null, 'notatka' => null]]);
        $tree->repoSnapshots()->create(['repo_id' => 'O-0001/zgon', 'values' => ['data' => '1900', 'miejsce' => null, 'miejsce_id' => null]]);
        $tree->repoSnapshots()->create(['repo_id' => 'O-0001/pochowek', 'values' => ['data' => null, 'miejsce' => 'Zalesie', 'miejsce_id' => 'L:'.$world['Zalesie']->id, 'cmentarz' => null, 'cmentarz_id' => 'C:'.$cemetery->id]]);
    }, ['Zalesie']);
    $blocks = ['O-0001' => "imiona: Jan\nnazwisko: Nowak\nzgon:\n  data: \"1900\"\npochowek:\n  miejsce: Zalesie"];

    $restore = placePlan($tree, $blocks, deletions: ['O-0001/pochowek' => ImportDecisions::RESTORE]);
    $keep = placePlan($tree, $blocks, deletions: ['O-0001/pochowek' => ImportDecisions::KEEP]);
    $cemetery = $tree->cemeteries()->firstOrFail();

    expect($restore->restorations)->toHaveCount(1)
        ->and([$restore->restorations[0]->kind, $restore->restorations[0]->key])->toBe([RepoKeyKind::Burial, 'O-0001/pochowek'])
        ->and($restore->restorations[0]->place->cemeteryId)->toBe($cemetery->id)
        ->and($restore->noteValues->burials)->toBe(1)
        ->and($keep->restorations)->toBe([])
        ->and($keep->keptDeletedSnapshots)->toHaveKey('O-0001/pochowek')
        ->and($keep->snapshots)->not->toHaveKey('O-0001/pochowek');

    $burial = Event::factory()->burial()->for($tree)->create(['repo_id' => 'O-0001/pochowek', 'cemetery_id' => $cemetery->id, 'locality_id' => $cemetery->locality_id]);
    $burial->participants()->attach($tree->people()->firstOrFail(), ['role' => ParticipantRole::Subject]);
    $tree->repoSnapshots()->where('repo_id', 'O-0001/pochowek')->delete();
    $tree->repoSnapshots()->create(['repo_id' => 'O-0001/pochowek', 'values' => ['data' => null, 'miejsce' => 'Zalesie', 'miejsce_id' => 'L:'.$world['Zalesie']->id, 'cmentarz' => null, 'cmentarz_id' => 'C:'.$cemetery->id]]);

    $removed = placePlan($tree, ['O-0001' => "imiona: Jan\nnazwisko: Nowak\nzgon:\n  data: \"1900\""], removals: ['O-0001/pochowek' => ImportDecisions::DELETE]);

    expect($removed->removals)->toHaveCount(1)
        ->and([$removed->removals[0]->kind, $removed->removals[0]->recordId])->toBe([RepoKeyKind::Burial, $burial->id]);
});

test('compares the cemetery of a known burial with the three values', function () {
    [$tree, $world] = replanPlaceTwin(function (Tree $tree, array $world): void {
        $first = Cemetery::factory()->in($world['Zalesie'])->create(['name' => 'Parafialny']);
        $second = Cemetery::factory()->in($world['Zalesie'])->create(['name' => 'Wojenny']);
        $person = Person::factory()->for($tree)->create(['first_name' => 'Jan', 'last_name' => 'Nowak', 'gender' => null, 'note' => null, 'repo_id' => 'O-0001']);
        $death = Event::factory()->death()->for($tree)->create(['repo_id' => 'O-0001/zgon', 'date_year' => 1900, 'date_month' => null, 'date_day' => null]);
        $burial = Event::factory()->burial()->for($tree)->create(['repo_id' => 'O-0001/pochowek', 'cemetery_id' => $first->id, 'locality_id' => $world['Zalesie']->id]);

        foreach ([$death, $burial] as $event) {
            $event->participants()->attach($person, ['role' => ParticipantRole::Subject]);
        }

        $tree->repoSnapshots()->create(['repo_id' => 'O-0001', 'values' => ['imiona' => 'Jan', 'nazwisko' => 'Nowak', 'plec' => null, 'notatka' => null]]);
        $tree->repoSnapshots()->create(['repo_id' => 'O-0001/zgon', 'values' => ['data' => '1900', 'miejsce' => null, 'miejsce_id' => null]]);
        $tree->repoSnapshots()->create(['repo_id' => 'O-0001/pochowek', 'values' => ['data' => null, 'miejsce' => 'Zalesie', 'miejsce_id' => 'L:'.$world['Zalesie']->id, 'cmentarz' => 'Parafialny', 'cmentarz_id' => 'C:'.$first->id]]);
    }, ['Zalesie']);
    $second = $tree->cemeteries()->where('name', 'Wojenny')->firstOrFail();

    $plan = placePlan($tree, ['O-0001' => "imiona: Jan\nnazwisko: Nowak\nzgon:\n  data: \"1900\"\npochowek:\n  miejsce: Zalesie\n  cmentarz: Wojenny"]);
    $change = placeItemOf($plan, 'burial_place');

    expect($change)->toBeInstanceOf(PlannedChange::class)
        ->and([$change->key, $change->appLabel, $change->repoValue->cemeteryId])->toBe(['O-0001/pochowek/miejsce', 'Parafialny, Zalesie', $second->id])
        ->and($plan->snapshots['O-0001/pochowek']['cmentarz_id'])->toBe('C:'.$second->id);
});

test('puts the recognized place on a new wedding and drops its text from both notes', function () {
    [$tree, $world] = arrangeAuthorWithTwin(fn (Tree $tree): array => placeWorld($tree, ['Zalesie']));

    $plan = placePlan($tree, [
        'O-0001' => "imiona: Jan\nmalzenstwa:\n  - z: O-0002\n    miejsce: Zalesie",
        'O-0002' => 'imiona: Ewa',
    ]);

    expect($plan->marriages)->toHaveCount(1)
        ->and($plan->marriages[0]->place->localityId)->toBe($world['Zalesie']->id)
        ->and($plan->snapshots['O-0001+O-0002/slub']['miejsce_id'])->toBe('L:'.$world['Zalesie']->id)
        ->and(plannedPerson($plan, 'O-0001')->note)->toBeNull()
        ->and(plannedPerson($plan, 'O-0002')->note)->toBeNull();
});

const REPLAN_VARIANTS = "imiona: Jan\nnazwisko: Misiuk\nwarianty_nazwiska: [Mysiuk, Myszuk]";

/**
 * Makes the tree look like one an earlier import filled before the variants had a memory: no variants, no `warianty` in the memory.
 */
function replanForgetVariants(Tree $tree): void
{
    $tree->people()->where('repo_id', 'O-0001')->firstOrFail()->surnameVariants()->delete();
    $snapshot = $tree->repoSnapshots()->where('repo_id', 'O-0001')->firstOrFail();
    $values = $snapshot->values;
    unset($values['warianty']);
    $snapshot->update(['values' => $values]);
}

test('fills the surname variants of a known person whose memory has no variants', function () {
    [$tree, $other] = replanTwinTrees(['O-0001' => REPLAN_VARIANTS], fn (Tree $tree) => replanForgetVariants($tree));

    $plan = replanBlocks($tree, ['O-0001' => REPLAN_VARIANTS]);
    $fill = $plan->updates[0]->fills[0];

    expect($plan->updates)->toHaveCount(1)
        ->and($plan->conflicts)->toBe([])
        ->and($fill)->toBeInstanceOf(PlannedFill::class)
        ->and([$fill->field, $fill->label, $fill->appLabel, $fill->valueLabel()])->toBe(['surname_variants', 'Warianty nazwiska', null, 'Mysiuk, Myszuk'])
        ->and($fill->value->names)->toBe(['Mysiuk', 'Myszuk'])
        ->and($plan->snapshots['O-0001']['warianty'])->toBe("Mysiuk\nMyszuk");

    expectPlanInside($plan, $tree);
    expect(replanPlanOf($other)->updates)->toHaveCount(1);
});

/**
 * The plan of the same repo over the other account, to prove that it is planned from its own data only.
 */
function replanPlanOf(Tree $tree): ImportPlan
{
    return replanBlocks($tree, ['O-0001' => REPLAN_VARIANTS]);
}

test('plans a changed list of surname variants as a change when the memory equals the app', function () {
    [$tree] = replanTwinTrees(['O-0001' => REPLAN_VARIANTS]);

    $plan = replanBlocks($tree, ['O-0001' => "imiona: Jan\nnazwisko: Misiuk\nwarianty_nazwiska: [Mysiuk, Myszuk, Misiuk]"]);
    $change = $plan->changes[0];

    expect($plan->changes)->toHaveCount(1)
        ->and($change)->toBeInstanceOf(PlannedChange::class)
        ->and([$change->key, $change->field, $change->label, $change->appLabel, $change->repoLabel])->toBe(['O-0001/warianty', 'surname_variants', 'Warianty nazwiska', 'Mysiuk, Myszuk', 'Mysiuk, Myszuk, Misiuk'])
        ->and($change->repoValue->names)->toBe(['Mysiuk', 'Myszuk', 'Misiuk'])
        ->and($plan->snapshots['O-0001']['warianty'])->toBe("Mysiuk\nMyszuk\nMisiuk");

    expectPlanInside($plan, $tree);
});

test('clears the surname variants when the repo lists none', function () {
    [$tree] = replanTwinTrees(['O-0001' => REPLAN_VARIANTS]);

    $plan = replanBlocks($tree, ['O-0001' => "imiona: Jan\nnazwisko: Misiuk"]);
    $change = $plan->changes[0];

    expect($plan->changes)->toHaveCount(1)
        ->and([$change->key, $change->appLabel, $change->repoLabel, $change->repoValue])->toBe(['O-0001/warianty', 'Mysiuk, Myszuk', '—', null])
        ->and($plan->snapshots['O-0001']['warianty'])->toBeNull();
});

test('plans nothing for a list that equals the saved one after normalizing', function () {
    [$tree] = replanTwinTrees(['O-0001' => REPLAN_VARIANTS]);

    $plan = replanBlocks($tree, ['O-0001' => "imiona: Jan\nnazwisko: Misiuk\nwarianty_nazwiska: [' Mysiuk ', MYSIUK, ' ', Myszuk]"]);

    expect($plan->changes)->toBe([])
        ->and($plan->updates)->toBe([])
        ->and($plan->conflicts)->toBe([]);
});

test('reads a variant that differs only in case as a change', function () {
    [$tree] = replanTwinTrees(['O-0001' => REPLAN_VARIANTS]);

    $plan = replanBlocks($tree, ['O-0001' => "imiona: Jan\nnazwisko: Misiuk\nwarianty_nazwiska: [MYSIUK, Myszuk]"]);

    expect($plan->changes)->toHaveCount(1)
        ->and([$plan->changes[0]->appLabel, $plan->changes[0]->repoLabel])->toBe(['Mysiuk, Myszuk', 'MYSIUK, Myszuk']);
});

test('keeps the memory of the surname variants when the gender is left out by the parent rule', function () {
    $blocks = ['O-0001' => "imiona: Jan\nwarianty_nazwiska: [Mysiuk, Myszuk]", 'O-0002' => "imiona: Dziecko\nrodzice:\n  ojciec: O-0001"];
    [$tree] = replanTwinTrees($blocks, function (Tree $tree): void {
        $second = Person::factory()->for($tree)->male()->create(['first_name' => 'Adam']);
        linkParent($second, replanPerson($tree, 'O-0002'));
    });

    $plan = replanBlocks($tree, ['O-0001' => "imiona: Jan\nplec: M\nwarianty_nazwiska: [Mysiuk, Myszuk]", 'O-0002' => $blocks['O-0002']]);

    expect($plan->skippedValues)->toHaveCount(1)
        ->and($plan->snapshots['O-0001']['plec'])->toBeNull()
        ->and($plan->snapshots['O-0001']['warianty'])->toBe("Mysiuk\nMyszuk");
});

/**
 * Reads a throwaway repo of persons and sources with real image files.
 *
 * @param  array<string, string>  $blocks  repo id of a person => block body without id and version
 * @param  array<string, array{extra?: string, shade?: int, path?: string}>  $sources  repo id of a source => extra lines of its entry (indented by four spaces, ending with a new line), the shade of its scan and its path; an empty path writes an entry without a scan
 * @param  array<string, string>  $files  further files, path from the repo root => contents
 */
function srcReading(array $blocks, array $sources, array $files = []): ResearchRepoReading
{
    $repo = sys_get_temp_dir().'/src-plan-repo-'.uniqid();
    mkdir($repo.'/drzewo', 0777, true);
    mkdir($repo.'/zrodla/skany', 0777, true);

    foreach ($blocks as $id => $body) {
        file_put_contents("{$repo}/drzewo/".strtolower($id).'.md', "---\nwersja: 1\nid: {$id}\n{$body}\n---\n");
    }

    $entries = '';

    foreach (array_keys($sources) as $index => $id) {
        $spec = $sources[$id];
        $path = $spec['path'] ?? "zrodla/skany/{$id}.jpg";
        $image = imagecreatetruecolor(8, 8);
        imagefill($image, 0, 0, imagecolorallocate($image, $spec['shade'] ?? 20 + $index, 20, 20));

        if ($path !== '') {
            imagejpeg($image, "{$repo}/{$path}");
        }

        $entries .= "  - id: {$id}\n".($path === '' ? '' : "    plik: {$path}\n")."    rodzaj: akt\n".($spec['extra'] ?? '');
    }

    if ($sources !== []) {
        file_put_contents("{$repo}/zrodla/akty.md", "---\nwersja: 1\nskany:\n{$entries}---\n");
    }

    foreach ($files as $name => $contents) {
        file_put_contents("{$repo}/{$name}", $contents);
    }

    try {
        return app(ReadResearchRepo::class)->handle($repo);
    } finally {
        File::deleteDirectory($repo);
    }
}

/**
 * The block of Jan Nowak born in 1837 with links to sources.
 *
 * @param  array<string, string|null>  $links  repo id of a source => the entry number
 */
function srcPerson(array $links = [], string $extra = ''): string
{
    $block = "imiona: Jan\nnazwisko: Nowak\nurodzenie:\n  data: \"1837\"";

    if ($links !== []) {
        $block .= "\n  zrodla:";

        foreach ($links as $id => $entry) {
            $block .= "\n    - id: {$id}".($entry === null ? '' : "\n      wpis: \"{$entry}\"");
        }
    }

    return $block.$extra;
}

/**
 * The tree as an earlier import of the reading left it: Jan Nowak with a birth, the sources of the reading with their
 * memory, the links of the birth and the memory of all of it.
 *
 * @return array{person: Person, birth: Event, sources: array<string, Source>}
 */
function srcKnownTree(Tree $tree, ResearchRepoReading $reading, bool $rememberLinks = true): array
{
    $research = $reading->persons['O-0001'];
    $person = Person::factory()->for($tree)->create(['first_name' => 'Jan', 'last_name' => 'Nowak', 'gender' => null, 'note' => null, 'repo_id' => 'O-0001']);
    $birth = Event::factory()->birth()->for($tree)->create(['repo_id' => 'O-0001/urodzenie', 'date_year' => 1837, 'date_month' => null, 'date_day' => null, 'locality_id' => null]);
    $birth->participants()->attach($person, ['role' => ParticipantRole::Subject]);

    $memory = ['data' => '1837', 'miejsce' => null, 'miejsce_id' => null];

    if ($rememberLinks && $research->birth?->sources !== []) {
        $memory['zrodla'] = (new SourceLinks($research->birth->sources))->text();
    }

    $tree->repoSnapshots()->create(['repo_id' => 'O-0001', 'values' => RepoValues::person($research, null)]);
    $tree->repoSnapshots()->create(['repo_id' => 'O-0001/urodzenie', 'values' => $memory]);

    $sources = [];

    foreach ($reading->sources as $id => $source) {
        $sources[(string) $id] = Source::factory()->create([
            'tree_id' => $tree->id,
            'repo_id' => $id,
            'kind' => $source->kind,
            'record_type' => $source->recordType,
            'name' => $source->name,
            'year' => $source->year,
            'archive' => $source->archive,
            'signature' => $source->signature,
            'url' => $source->url,
            'note' => $source->note,
        ]);
        $tree->repoSnapshots()->create(['repo_id' => $id, 'values' => [...RepoValues::source($source), 'parafia_id' => null]]);
    }

    foreach ($research->birth?->sources ?? [] as $link) {
        $birth->sources()->attach($sources[$link->id], ['entry_number' => $link->entry]);
    }

    return ['person' => $person, 'birth' => $birth, 'sources' => $sources];
}

/**
 * Plans a reading over a tree; every repo person that is not matched is new.
 *
 * @param  array<string, string>  $removals
 * @param  array<string, string>  $deletions
 * @param  array<string, Person>  $matched
 */
function srcPlan(Tree $tree, ResearchRepoReading $reading, array $removals = [], array $deletions = [], ?SourceDecisions $sources = null, ?PlaceDecisions $places = null, array $matched = []): ImportPlan
{
    $classes = app(ClassifyRepoRecords::class)->handle($tree, $reading);
    $identity = new IdentityDecisions(
        array_map(fn (Person $person): int => $person->id, $matched),
        [],
        array_values(array_diff($classes->newPersonIds, array_keys($matched))),
    );

    return app(PlanImport::class)->handle($tree, $reading, $classes, new ImportDecisions($identity, $removals, $deletions, $places ?? new PlaceDecisions), $sources ?? new SourceDecisions);
}

/**
 * @return list<string>
 */
function srcKeysOf(ImportPlan $plan): array
{
    $keys = array_map(fn ($item): string => $item->key, [...$plan->changes, ...$plan->conflicts]);
    sort($keys);

    return $keys;
}

/**
 * The same earlier import in the tree of another account and then in the tree of the author.
 *
 * @return array{0: Tree, 1: Tree, 2: array{person: Person, birth: Event, sources: array<string, Source>}}
 */
function srcTwinTrees(ResearchRepoReading $reading, bool $rememberLinks = true): array
{
    $other = User::factory()->create()->tree;
    srcKnownTree($other, $reading, $rememberLinks);
    $author = User::factory()->create()->tree;

    return [$author, $other, srcKnownTree($author, $reading, $rememberLinks)];
}

const SRC_ONE = ['ZR-0001' => ['extra' => "    nazwa: Akt urodzenia 15/1837\n    rok: 1837\n    archiwum: AP Przykładowe\n"]];

/**
 * Another account with the locality Lipnica, its Roman Catholic parish and its civil registry office, as the repo
 * names them.
 *
 * @return array{0: Parish, 1: Parish}
 */
function srcOtherLipnica(): array
{
    $other = User::factory()->create()->tree;
    $seat = Locality::factory()->for($other)->create(['name' => 'Lipnica']);

    return [
        Parish::factory()->seatedIn($seat)->create(['religion' => Religion::RomanCatholic]),
        Parish::factory()->seatedIn($seat)->create(['kind' => ParishKind::CivilRegistry]),
    ];
}

test('plans new sources with a sure, a new and no parish and with the links of a birth and a wedding', function () {
    $foreign = srcOtherLipnica();
    $tree = User::factory()->create()->tree;
    $seat = Locality::factory()->for($tree)->create(['name' => 'Lipnica']);
    $sure = Parish::factory()->seatedIn($seat)->create(['religion' => Religion::RomanCatholic]);
    $reading = srcReading([
        'O-0001' => srcPerson(['ZR-0001' => '15/1837', 'ZR-0002' => null], "\nmalzenstwa:\n  - z: O-0002\n    data: \"1860\"\n    zrodla:\n      - id: ZR-0003\n        wpis: \"7/1860\""),
        'O-0002' => "imiona: Anna\nplec: K",
    ], [
        'ZR-0001' => ['extra' => "    parafia:\n      miejscowosc: Lipnica\n      wyznanie: rzymskokatolickie\n"],
        'ZR-0002' => ['extra' => "    parafia:\n      miejscowosc: Lipnica\n      rodzaj: usc\n"],
        'ZR-0003' => [],
    ]);

    $plan = srcPlan($tree, $reading);
    $bySource = collect($plan->newSources)->keyBy(fn ($planned): string => $planned->source->id);

    expect($plan->newSources)->toHaveCount(3)
        ->and($bySource['ZR-0001']->parish->parishId)->toBe($sure->id)
        ->and($bySource['ZR-0002']->parish->newParish)->not->toBeNull()
        ->and($bySource['ZR-0003']->parish)->toBeNull()
        ->and($plan->newParishes)->toHaveCount(1)
        ->and(array_values($plan->newParishes)[0]->kind)->toBe(ParishKind::CivilRegistry)
        ->and(array_map(fn ($link): string => $link->id, plannedPerson($plan, 'O-0001')->birthSources))->toBe(['ZR-0001', 'ZR-0002'])
        ->and($plan->marriages[0]->sources[0]->entry)->toBe('7/1860')
        ->and($plan->snapshots['ZR-0001']['parafia_id'])->toBe('P:'.$sure->id)
        ->and($plan->snapshots['ZR-0001']['plik_hash'])->toBe($reading->sources['ZR-0001']->scanHash)
        ->and($plan->snapshots['ZR-0003']['parafia_id'])->toBeNull()
        ->and($plan->snapshots['O-0001/urodzenie']['zrodla'])->toBe('ZR-0001=15/1837;ZR-0002=')
        ->and($plan->snapshots['O-0001+O-0002/slub']['zrodla'])->toBe('ZR-0003=7/1860')
        ->and(array_values($plan->newParishes)[0]->localityId)->toBe($seat->id)
        ->and(collect($foreign)->pluck('id')->all())->not->toContain($bySource['ZR-0001']->parish->parishId);
});

test('leaves out a new parish the author answered with no parish', function () {
    srcOtherLipnica();
    $tree = User::factory()->create()->tree;
    Locality::factory()->for($tree)->create(['name' => 'Lipnica']);
    $reading = srcReading([], ['ZR-0001' => ['extra' => "    parafia:\n      miejscowosc: Lipnica\n"]]);
    $key = PlaceDecisions::parishKey(PlaceName::key('Lipnica'), null, ParishKind::Parish, null);

    $plan = srcPlan($tree, $reading, places: new PlaceDecisions(parishes: [$key => PlaceDecisions::NONE]));

    expect($plan->newSources[0]->parish)->toBeNull()
        ->and($plan->newParishes)->toBe([]);
});

test('rejects decisions about parishes and sources that import did not ask about', function () {
    [$foreignParish] = srcOtherLipnica();
    $foreignSource = Source::factory()->create(['tree_id' => $foreignParish->tree_id]);
    $tree = User::factory()->create()->tree;
    Locality::factory()->for($tree)->create(['name' => 'Lipnica']);
    $reading = srcReading([], ['ZR-0001' => ['extra' => "    parafia:\n      miejscowosc: Lipnica\n"]]);
    $other = Source::factory()->create(['tree_id' => $tree->id]);

    expect(fn () => srcPlan($tree, $reading, places: new PlaceDecisions(parishes: ['nie|ma||' => PlaceDecisions::NEW])))->toThrow(InvalidArgumentException::class)
        ->and(fn () => srcPlan($tree, $reading, sources: new SourceDecisions(['ZR-0001' => $other->id])))->toThrow(InvalidArgumentException::class)
        ->and(fn () => srcPlan($tree, $reading, sources: new SourceDecisions(['ZR-0001' => $foreignSource->id])))->toThrow(InvalidArgumentException::class)
        ->and(fn () => srcPlan($tree, $reading, sources: new SourceDecisions(['ZR-0009' => $other->id])))->toThrow(InvalidArgumentException::class);
});

test('joins a manual source to a source of the repo, fills its empty fields and keeps the other ones', function () {
    $other = User::factory()->create()->tree;
    $tree = User::factory()->create()->tree;
    $reading = srcReading(
        ['O-0001' => srcPerson(['ZR-0001' => '15/1837'])],
        ['ZR-0001' => ['extra' => "    typ: urodzenie\n    nazwa: Nazwa z repo\n    rok: 1837\n    archiwum: AP Przykładowe\n    sygnatura: 35/1\n"]],
    );

    foreach ([$other, $tree] as $each) {
        $person = Person::factory()->for($each)->create(['first_name' => 'Jan', 'last_name' => 'Nowak']);
        $birth = Event::factory()->birth()->for($each)->create(['date_year' => 1837, 'date_month' => null, 'date_day' => null]);
        $birth->participants()->attach($person, ['role' => ParticipantRole::Subject]);
        $manual = Source::factory()->create(['tree_id' => $each->id, 'name' => 'Mój skan', 'record_type' => SourceRecordType::Birth, 'archive' => null, 'year' => null]);
        $birth->sources()->attach($manual);
    }

    $plan = srcPlan($tree, $reading, sources: new SourceDecisions(['ZR-0001' => $manual->id]), matched: ['O-0001' => $person]);
    $merge = $plan->mergedSources[0];

    expect($plan->newSources)->toBe([])
        ->and($merge->sourceId)->toBe($manual->id)
        ->and(fieldsOf($merge->fills))->toBe(['year', 'archive', 'signature'])
        ->and($merge->entries)->toBe(['O-0001/urodzenie' => '15/1837'])
        ->and(fieldsOf(plannedMatch($plan, 'O-0001')->fills))->not->toContain('birth_sources')
        ->and($plan->snapshots['ZR-0001']['nazwa'])->toBe('Nazwa z repo')
        ->and($plan->snapshots['O-0001/urodzenie']['zrodla'])->toBe('ZR-0001=15/1837')
        ->and($tree->sources()->pluck('id')->all())->toContain($merge->sourceId);
});

test('plans nothing for sources and links that neither the repo nor the tree changed', function () {
    $reading = srcReading(['O-0001' => srcPerson(['ZR-0001' => '15/1837'])], SRC_ONE);
    [$tree] = srcTwinTrees($reading);

    $plan = srcPlan($tree, $reading);

    expect($plan->sourceUpdates)->toBe([])
        ->and($plan->newSources)->toBe([])
        ->and($plan->changes)->toBe([])
        ->and($plan->conflicts)->toBe([])
        ->and($plan->updates)->toBe([])
        ->and($plan->keptManual)->toBe(0)
        ->and($plan->changedSnapshots($tree->repoSnapshots()->get()->keyBy('repo_id')->all()))->toBe([]);
});

test('plans a changed description of a source nobody touched as a change and ignores the sources of another account', function () {
    $first = srcReading(['O-0001' => srcPerson(['ZR-0001' => '15/1837'])], SRC_ONE);
    [$tree, $other, $built] = srcTwinTrees($first);
    $second = srcReading(['O-0001' => srcPerson(['ZR-0001' => '15/1837'])], ['ZR-0001' => ['extra' => "    nazwa: Nowa nazwa\n    rok: 1837\n    archiwum: AP Przykładowe\n"]]);

    $plan = srcPlan($tree, $second);

    expect(srcKeysOf($plan))->toBe(['ZR-0001/nazwa'])
        ->and($plan->changes[0]->appLabel)->toBe('Akt urodzenia 15/1837')
        ->and($plan->changes[0]->repoLabel)->toBe('Nowa nazwa')
        ->and($plan->sourceUpdates)->toHaveCount(1)
        ->and($plan->sourceUpdates[0]->sourceId)->toBe($built['sources']['ZR-0001']->id)
        ->and($plan->sourceUpdates[0]->sourceId)->not->toBe($other->sources()->first()->id)
        ->and($plan->snapshots['ZR-0001']['nazwa'])->toBe('Nowa nazwa');
});

test('keeps a source description changed by hand while the repo did not move and reports a conflict when both moved', function () {
    $first = srcReading(['O-0001' => srcPerson(['ZR-0001' => '15/1837'])], SRC_ONE);
    [$tree, , $built] = srcTwinTrees($first);
    $built['sources']['ZR-0001']->update(['name' => 'Ręczna nazwa']);

    $kept = srcPlan($tree, $first);
    $second = srcReading(['O-0001' => srcPerson(['ZR-0001' => '15/1837'])], ['ZR-0001' => ['extra' => "    nazwa: Nowa nazwa\n    rok: 1837\n    archiwum: AP Przykładowe\n"]]);
    $conflict = srcPlan($tree, $second);

    expect($kept->sourceUpdates)->toBe([])
        ->and($kept->keptManual)->toBe(1)
        ->and($conflict->conflicts)->toHaveCount(1)
        ->and($conflict->conflicts[0]->key)->toBe('ZR-0001/nazwa')
        ->and($conflict->conflicts[0]->appLabel)->toBe('Ręczna nazwa')
        ->and($conflict->conflicts[0]->lastRepoLabel)->toBe('Akt urodzenia 15/1837');
});

test('plans a new scan as a change by its hash and a moved scan only in the memory', function () {
    Storage::fake('local');
    $first = srcReading(['O-0001' => srcPerson(['ZR-0001' => '15/1837'])], SRC_ONE);
    [$tree, , $built] = srcTwinTrees($first);
    $image = imagecreatetruecolor(8, 8);
    imagefill($image, 0, 0, imagecolorallocate($image, 20, 20, 20));
    ob_start();
    imagejpeg($image);
    $importCopy = (string) ob_get_clean();
    Storage::disk('local')->put($built['sources']['ZR-0001']->path, $importCopy);

    expect(hash('sha256', $importCopy))->toBe($first->sources['ZR-0001']->scanHash);

    $newScan = srcReading(['O-0001' => srcPerson(['ZR-0001' => '15/1837'])], ['ZR-0001' => [...SRC_ONE['ZR-0001'], 'shade' => 200]]);
    $moved = srcReading(['O-0001' => srcPerson(['ZR-0001' => '15/1837'])], ['ZR-0001' => [...SRC_ONE['ZR-0001'], 'path' => 'zrodla/skany/inna.jpg']]);

    $changed = srcPlan($tree, $newScan);
    $same = srcPlan($tree, $moved);

    expect($changed->changes)->toHaveCount(1)
        ->and($changed->changes[0]->key)->toBe('ZR-0001/plik')
        ->and($changed->changes[0]->field)->toBe('file')
        ->and($changed->changes[0]->repoValue)->toBe('zrodla/skany/ZR-0001.jpg')
        ->and($changed->snapshots['ZR-0001']['plik_hash'])->toBe($newScan->sources['ZR-0001']->scanHash)
        ->and($same->changes)->toBe([])
        ->and($same->sourceUpdates)->toBe([])
        ->and($same->snapshots['ZR-0001']['plik'])->toBe('zrodla/skany/inna.jpg')
        ->and($same->snapshots['ZR-0001']['plik_hash'])->toBe($moved->sources['ZR-0001']->scanHash);
});

test('plans a new, a removed and a changed link of a birth as a change of one field', function (array $links, string $repoLabel) {
    $sources = [...SRC_ONE, 'ZR-0002' => ['extra' => "    nazwa: Drugi akt\n"]];
    $first = srcReading(['O-0001' => srcPerson(['ZR-0001' => '15/1837'])], $sources);
    [$tree] = srcTwinTrees($first);
    $second = srcReading(['O-0001' => srcPerson($links)], $sources);

    $plan = srcPlan($tree, $second);

    expect(srcKeysOf($plan))->toBe(['O-0001/urodzenie/zrodla'])
        ->and($plan->changes[0]->field)->toBe('birth_sources')
        ->and($plan->changes[0]->appLabel)->toBe('ZR-0001 (wpis 15/1837)')
        ->and($plan->changes[0]->repoLabel)->toBe($repoLabel)
        ->and($plan->changes[0]->repoValue)->toBeInstanceOf(SourceLinks::class)
        ->and($plan->snapshots['O-0001/urodzenie']['zrodla'])->toBe($plan->changes[0]->repoValue->text());
})->with([
    'a new link' => [['ZR-0001' => '15/1837', 'ZR-0002' => null], 'ZR-0001 (wpis 15/1837), ZR-0002'],
    'a removed link' => [[], PlanImport::EMPTY_LABEL],
    'a changed entry' => [['ZR-0001' => '16/1837'], 'ZR-0001 (wpis 16/1837)'],
]);

test('never reports a link the author added by hand as a conflict and never removes it', function () {
    $reading = srcReading(['O-0001' => srcPerson(['ZR-0001' => '15/1837'])], SRC_ONE);
    [$tree, , $built] = srcTwinTrees($reading);
    $manual = Source::factory()->create(['tree_id' => $tree->id]);
    $built['birth']->sources()->attach($manual, ['entry_number' => '99']);

    $plan = srcPlan($tree, $reading);

    expect($plan->changes)->toBe([])
        ->and($plan->conflicts)->toBe([])
        ->and($plan->snapshots['O-0001/urodzenie']['zrodla'])->toBe('ZR-0001=15/1837')
        ->and($built['birth']->sources()->count())->toBe(2);
});

test('fills the links of a birth that has none from the repo when the memory has no links', function () {
    $first = srcReading(['O-0001' => srcPerson()], SRC_ONE);
    [$tree] = srcTwinTrees($first);

    $plan = srcPlan($tree, srcReading(['O-0001' => srcPerson(['ZR-0001' => '15/1837'])], SRC_ONE));

    expect($plan->updates[0]->fills[0]->field)->toBe('birth_sources')
        ->and($plan->updates[0]->fills[0]->valueLabel())->toBe('ZR-0001 (wpis 15/1837)');
});

test('does not compare the links of an event that names a skipped source and keeps its memory', function () {
    $first = srcReading(['O-0001' => srcPerson(['ZR-0001' => '15/1837'])], SRC_ONE);
    [$tree] = srcTwinTrees($first);
    $second = srcReading(['O-0001' => srcPerson(['ZR-0001' => '16/1837', 'ZR-0002' => null])], [...SRC_ONE, 'ZR-0002' => ['path' => '']]);

    $plan = srcPlan($tree, $second);

    expect($second->skippedSourceIds())->toBe(['ZR-0002'])
        ->and(srcKeysOf($plan))->toBe([])
        ->and($plan->removals)->toBe([])
        ->and($plan->snapshots['O-0001/urodzenie']['zrodla'])->toBe('ZR-0001=15/1837');
});

test('leaves the links of a detached, a removed and a kept deleted source out of the plan', function (string $mode) {
    $sources = [...SRC_ONE, 'ZR-0002' => ['extra' => "    nazwa: Drugi akt\n"]];
    $first = srcReading(['O-0001' => srcPerson(['ZR-0001' => '15/1837', 'ZR-0002' => '2'])], $sources);
    [$tree, $other, $built] = srcTwinTrees($first);
    $removals = [];
    $deletions = [];

    if ($mode === 'kept deleted') {
        $built['sources']['ZR-0002']->delete();
        $deletions = ['ZR-0002' => ImportDecisions::KEEP];
        $second = $first;
    } else {
        $removals = ['ZR-0002' => $mode === 'detached' ? ImportDecisions::DETACH : ImportDecisions::DELETE];
        $second = srcReading(['O-0001' => srcPerson(['ZR-0001' => '15/1837'])], SRC_ONE);
    }

    $plan = srcPlan($tree, $second, $removals, $deletions);

    expect($plan->changes)->toBe([])
        ->and($plan->conflicts)->toBe([])
        ->and($plan->restorations)->toBe([])
        ->and($plan->snapshots['O-0001/urodzenie']['zrodla'])->toBe('ZR-0001=15/1837');

    if ($mode !== 'kept deleted') {
        expect($plan->removals)->toHaveCount(1)
            ->and($plan->removals[0]->key)->toBe('ZR-0002')
            ->and($plan->removals[0]->kind)->toBe(RepoKeyKind::Source)
            ->and($plan->removals[0]->impact?->sourceLinks)->toBe($mode === 'removed' ? 1 : null)
            ->and($plan->forgottenSnapshots)->toContain('ZR-0002')
            ->and($other->sources()->where('repo_id', 'ZR-0002')->exists())->toBeTrue();
    } else {
        expect($plan->keptDeletedSnapshots)->toHaveKey('ZR-0002');
    }
})->with(['detached', 'removed', 'kept deleted']);

test('brings back a source deleted in the app with the links of the events that cite it', function () {
    $sources = [...SRC_ONE, 'ZR-0002' => ['extra' => "    nazwa: Drugi akt\n"]];
    $reading = srcReading(['O-0001' => srcPerson(['ZR-0001' => '15/1837', 'ZR-0002' => '2'])], $sources);
    [$tree, , $built] = srcTwinTrees($reading);
    $built['sources']['ZR-0002']->delete();

    $plan = srcPlan($tree, $reading, deletions: ['ZR-0002' => ImportDecisions::RESTORE]);

    expect($plan->restorations)->toHaveCount(1)
        ->and($plan->restorations[0]->kind)->toBe(RepoKeyKind::Source)
        ->and($plan->restorations[0]->source->source->id)->toBe('ZR-0002')
        ->and($plan->restorations[0]->source->entries)->toBe(['O-0001/urodzenie' => '2'])
        ->and($plan->changes)->toBe([])
        ->and($plan->snapshots['O-0001/urodzenie']['zrodla'])->toBe('ZR-0001=15/1837;ZR-0002=2');
});

test('adds no memory of links to a tree whose repo has no sources', function () {
    $reading = srcReading(['O-0001' => srcPerson()], []);
    [$tree] = srcTwinTrees($reading);

    $plan = srcPlan($tree, $reading);

    expect($plan->snapshots['O-0001/urodzenie'])->toBe(['data' => '1837', 'miejsce' => null, 'miejsce_id' => null])
        ->and($plan->changedSnapshots($tree->repoSnapshots()->get()->keyBy('repo_id')->all()))->toBe([]);
});

test('plans the links of a wedding and compares them with the three values', function () {
    $wedding = fn (array $links): string => srcPerson([], "\nmalzenstwa:\n  - z: O-0002\n    data: \"1860\"\n    zrodla:".implode('', array_map(fn ($id, $entry): string => "\n      - id: {$id}\n        wpis: \"{$entry}\"", array_keys($links), $links)));
    $sources = ['ZR-0001' => []];
    $first = srcReading(['O-0001' => $wedding(['ZR-0001' => '7/1860']), 'O-0002' => "imiona: Anna\nplec: K"], $sources);
    [$tree, , $built] = srcTwinTrees($first);
    $marriage = Event::factory()->for($tree)->create(['type' => EventType::Marriage, 'repo_id' => 'O-0001+O-0002/slub', 'date_year' => 1860, 'date_month' => null, 'date_day' => null, 'locality_id' => null]);
    $spouse = Person::factory()->for($tree)->create(['first_name' => 'Anna', 'last_name' => null, 'gender' => Gender::Female, 'repo_id' => 'O-0002']);
    $marriage->participants()->attach([$built['person']->id => ['role' => ParticipantRole::Spouse], $spouse->id => ['role' => ParticipantRole::Spouse]]);
    $marriage->sources()->attach($built['sources']['ZR-0001'], ['entry_number' => '7/1860']);
    $tree->repoSnapshots()->create(['repo_id' => 'O-0001+O-0002/slub', 'values' => ['data' => '1860', 'miejsce' => null, 'miejsce_id' => null, 'zrodla' => 'ZR-0001=7/1860']]);
    $tree->repoSnapshots()->create(['repo_id' => 'O-0002', 'values' => RepoValues::person($first->persons['O-0002'], null)]);

    $same = srcPlan($tree, $first);
    $changed = srcPlan($tree, srcReading(['O-0001' => $wedding(['ZR-0001' => '8/1860']), 'O-0002' => "imiona: Anna\nplec: K"], $sources));

    expect(srcKeysOf($same))->toBe([])
        ->and($same->marriages)->toBe([])
        ->and(srcKeysOf($changed))->toBe(['O-0001+O-0002/slub/zrodla'])
        ->and($changed->marriages[0]->sourceChange->field)->toBe('marriage_sources');
});

test('resolves a parish by token, by the answers and by the memory without leaving the tree', function () {
    $other = User::factory()->create()->tree;
    $otherSeat = Locality::factory()->for($other)->create(['name' => 'Lipnica']);
    $foreign = Parish::factory()->seatedIn($otherSeat)->create(['religion' => Religion::RomanCatholic]);

    $tree = User::factory()->create()->tree;
    $seat = Locality::factory()->for($tree)->create(['name' => 'Lipnica']);
    $own = Parish::factory()->seatedIn($seat)->create(['religion' => Religion::RomanCatholic, 'dedication' => 'św. Jana']);
    $second = Parish::factory()->seatedIn($seat)->create(['religion' => Religion::RomanCatholic, 'dedication' => 'św. Anny']);
    $localities = $tree->localities()->get()->keyBy('id')->all();
    $parishes = $tree->parishes()->with('locality')->get()->keyBy('id')->all();

    $parish = fn (?Religion $religion = Religion::RomanCatholic, ?string $dedication = null): ResearchParish => new ResearchParish('Lipnica', $religion, ParishKind::Parish, $dedication);
    $key = fn (?Religion $religion, ?string $dedication = null): string => PlaceDecisions::parishKey(PlaceName::key('Lipnica'), $religion, ParishKind::Parish, PlaceName::key($dedication));
    $request = fn (?Religion $religion, ?string $dedication = null): ParishRequest => new ParishRequest(PlaceName::key('Lipnica'), $religion, ParishKind::Parish, PlaceName::key($dedication), $dedication, []);
    $resolver = fn (array $verdicts, PlaceDecisions $decisions = new PlaceDecisions): PlaceResolver => new PlaceResolver(
        new PlaceMatcher($localities, []),
        $localities,
        [],
        new PlaceMatches,
        new CemeteryMatches,
        $decisions,
        $parishes,
        new ParishMatches($verdicts),
    );

    $sure = $resolver([$key(Religion::RomanCatholic, 'św. Jana') => new ParishVerdict($request(Religion::RomanCatholic, 'św. Jana'), ParishMatchKind::Sure, $seat->id, $own)]);
    $choice = new ParishVerdict($request(Religion::RomanCatholic), ParishMatchKind::Choice, $seat->id, null, [$own, $second]);
    $chosen = $resolver([$key(Religion::RomanCatholic) => $choice], new PlaceDecisions(parishes: [$key(Religion::RomanCatholic) => $second->id]));
    $unanswered = $resolver([$key(Religion::RomanCatholic) => $choice]);
    $newVerdict = new ParishVerdict($request(Religion::GreekCatholic), ParishMatchKind::New, $seat->id);
    $added = $resolver([$key(Religion::GreekCatholic) => $newVerdict]);
    $declined = $resolver([$key(Religion::GreekCatholic) => $newVerdict], new PlaceDecisions(parishes: [$key(Religion::GreekCatholic) => PlaceDecisions::NONE]));
    $remembering = $resolver([]);

    expect($sure->parish($parish(Religion::RomanCatholic, 'św. Jana'), null)->token)->toBe('P:'.$own->id)
        ->and($sure->parish($parish(Religion::RomanCatholic, 'św. Jana'), null)->place->parishId)->toBe($own->id)
        ->and($chosen->parish($parish(), null)->token)->toBe('P:'.$second->id)
        ->and($unanswered->parish($parish(), null))->toBeNull()
        ->and($added->parish($parish(Religion::GreekCatholic), null)->isNew())->toBeTrue()
        ->and($added->newParishes)->toHaveCount(1)
        ->and($declined->parish($parish(Religion::GreekCatholic), null))->toBeNull()
        ->and($declined->newParishes)->toBe([])
        ->and($remembering->parish($parish(), 'P:'.$own->id)->token)->toBe('P:'.$own->id)
        ->and($remembering->parish($parish(), 'P:'.$foreign->id))->toBeNull()
        ->and($remembering->parish(null, 'P:'.$own->id))->toBeNull()
        ->and($remembering->tokenExists('P:'.$own->id))->toBeTrue()
        ->and($remembering->tokenExists('P:'.$foreign->id))->toBeFalse()
        ->and($remembering->tokenLabel('P:'.$own->id))->toBe($own->label())
        ->and($remembering->tokenLabel('P:'.$foreign->id))->toBeNull();
});
