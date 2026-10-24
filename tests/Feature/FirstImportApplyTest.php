<?php

use App\Actions\Import\ApplyFirstImport;
use App\Actions\Import\PlanFirstImport;
use App\Actions\Import\ReadResearchRepo;
use App\Actions\Relations\CreateMarriage;
use App\Enums\DateQualifier;
use App\Enums\EventType;
use App\Enums\Gender;
use App\Enums\ParticipantRole;
use App\Models\Event;
use App\Models\ParentChildRelation;
use App\Models\Person;
use App\Models\Tree;
use App\Models\User;
use App\Support\Import\FirstImportPlan;
use App\Support\Import\FirstImportResult;
use App\Support\Import\IdentityDecisions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * An author with an empty tree and another account that holds persons named like the fixture ones.
 */
function arrangeApplyUsers(): Tree
{
    $author = User::factory()->create();
    $other = User::factory()->create();

    foreach ([['Jan', 'Nowak'], ['Anna', 'Wrona'], ['Marta', 'Lis']] as [$first, $last]) {
        $person = Person::factory()->for($other->tree)->create(['first_name' => $first, 'last_name' => $last]);
        attachBirth($person, 1801);
    }

    $parent = Person::factory()->for($other->tree)->create(['first_name' => 'Piotr', 'last_name' => 'Nowak']);
    linkParent($parent, Person::where('first_name', 'Jan')->firstOrFail());

    return $author->tree;
}

function planFixtureForApply(Tree $tree): FirstImportPlan
{
    $reading = app(ReadResearchRepo::class)->handle(base_path('tests/Fixtures/research-repo'));

    return app(PlanFirstImport::class)->handle($tree, $reading, IdentityDecisions::allNew($reading));
}

function importedPerson(Tree $tree, string $repoId): Person
{
    return $tree->people()->where('repo_id', $repoId)->firstOrFail();
}

function importedEvent(Tree $tree, string $repoId): Event
{
    return $tree->events()->where('repo_id', $repoId)->firstOrFail();
}

test('writes persons with repo id, names, gender and note', function () {
    $tree = arrangeApplyUsers();
    $plan = planFixtureForApply($tree);

    $result = app(ApplyFirstImport::class)->handle($tree, $plan);

    $jan = importedPerson($tree, 'O-0001');

    expect($result)->toBeInstanceOf(FirstImportResult::class)
        ->and($result->persons)->toBe(13)
        ->and($tree->people()->count())->toBe(13)
        ->and($jan->first_name)->toBe('Jan')
        ->and($jan->last_name)->toBe('Nowak')
        ->and($jan->gender)->toBe(Gender::Male)
        ->and($jan->note)->toBe(collect($plan->persons)->first(fn ($planned) => $planned->person->id === 'O-0001')->note)
        ->and($jan->note)->toContain('Z repo badawczego (O-0001):')
        ->and(importedPerson($tree, 'O-0008')->gender)->toBeNull();
});

test('writes births and deaths with every kind of date and the person as subject', function () {
    $tree = arrangeApplyUsers();

    $result = app(ApplyFirstImport::class)->handle($tree, planFixtureForApply($tree));

    $birth = importedEvent($tree, 'O-0001/urodzenie');
    $range = importedEvent($tree, 'O-0002/urodzenie');
    $before = importedEvent($tree, 'O-0003/urodzenie');
    $after = importedEvent($tree, 'O-0005/urodzenie');
    $death = importedEvent($tree, 'O-0001/zgon');
    $undatedDeath = importedEvent($tree, 'O-0002/zgon');

    expect($birth->type)->toBe(EventType::Birth)
        ->and([$birth->date_year, $birth->date_month, $birth->date_day, $birth->date_qualifier])->toBe([1801, 5, 12, DateQualifier::Exact])
        ->and([$range->date_year, $range->date_until_year, $range->date_qualifier])->toBe([1760, 1765, DateQualifier::Range])
        ->and([$before->date_year, $before->date_qualifier])->toBe([1770, DateQualifier::Before])
        ->and([$after->date_year, $after->date_qualifier])->toBe([1730, DateQualifier::After])
        ->and($death->type)->toBe(EventType::Death)
        ->and($death->date_year)->toBe(1870)
        ->and($undatedDeath->type)->toBe(EventType::Death)
        ->and([$undatedDeath->date_year, $undatedDeath->date_month, $undatedDeath->date_day, $undatedDeath->date_until_year])->toBe([null, null, null, null])
        ->and($result->births)->toBe(5)
        ->and($result->deaths)->toBe(2);

    $participants = $birth->participants()->get();

    expect($participants)->toHaveCount(1)
        ->and($participants->first()->id)->toBe(importedPerson($tree, 'O-0001')->id)
        ->and($participants->first()->pivot->role)->toBe(ParticipantRole::Subject);
});

test('writes approximate dates through the qualifier', function () {
    $tree = arrangeApplyUsers();

    app(ApplyFirstImport::class)->handle($tree, planFixtureForApply($tree));

    $wedding = importedEvent($tree, 'O-0001+O-0004/slub');

    expect($wedding->date_qualifier)->toBe(DateQualifier::Approximate)
        ->and($wedding->date_year)->toBe(1825);
});

test('writes relations with repo id and skips the one with a conflicting gender', function () {
    $tree = arrangeApplyUsers();

    $result = app(ApplyFirstImport::class)->handle($tree, planFixtureForApply($tree));

    $keys = $tree->parentChildRelations()->orderBy('repo_id')->pluck('repo_id')->all();
    $relation = $tree->parentChildRelations()->where('repo_id', 'O-0002>O-0001')->firstOrFail();

    expect($keys)->toBe(['O-0002>O-0001', 'O-0003>O-0001', 'O-0005>O-0002'])
        ->and($keys)->not->toContain('O-0003>O-0008')
        ->and($result->relations)->toBe(3)
        ->and($relation->parent_id)->toBe(importedPerson($tree, 'O-0002')->id)
        ->and($relation->child_id)->toBe(importedPerson($tree, 'O-0001')->id);
});

test('writes marriages with both spouses, the date and repo id', function () {
    $tree = arrangeApplyUsers();

    $result = app(ApplyFirstImport::class)->handle($tree, planFixtureForApply($tree));

    $wedding = importedEvent($tree, 'O-0002+O-0003/slub');
    $spouses = $wedding->participants()->get();

    expect($wedding->type)->toBe(EventType::Marriage)
        ->and($wedding->date_year)->toBe(1798)
        ->and($spouses->pluck('id')->sort()->values()->all())->toBe(collect([importedPerson($tree, 'O-0002')->id, importedPerson($tree, 'O-0003')->id])->sort()->values()->all())
        ->and($spouses->every(fn (Person $person): bool => $person->pivot->role === ParticipantRole::Spouse))->toBeTrue()
        ->and($tree->events()->where('type', EventType::Marriage)->count())->toBe(3)
        ->and($result->marriages)->toBe(3)
        ->and($result->events)->toBe(10);
});

test('leaves the proband unset', function () {
    $tree = arrangeApplyUsers();

    app(ApplyFirstImport::class)->handle($tree, planFixtureForApply($tree));

    expect($tree->fresh()->root_person_id)->toBeNull();
});

test('writes only into the author tree and leaves the other account untouched', function () {
    Storage::fake('local');
    $tree = arrangeApplyUsers();
    $plan = planFixtureForApply($tree);
    $before = databaseRows();

    app(ApplyFirstImport::class)->handle($tree, $plan);

    $diff = rowDiff($before, databaseRows());

    expect(array_keys($diff['tables']))->toEqualCanonicalizing(['people', 'events', 'event_participants', 'parent_child_relations'])
        ->and($diff['files'])->toBe(['removed' => [], 'added' => []]);

    foreach ($diff['tables'] as $table => $changes) {
        expect($changes['removed'])->toBe([])
            ->and($changes['changed'])->toBe([]);

        if ($table !== 'event_participants') {
            expect(collect($changes['added'])->pluck('tree_id')->unique()->all())->toBe([$tree->id]);
        }
    }

    $addedIds = fn (string $table): array => collect($diff['tables'][$table]['added'])->pluck('id')->all();
    $participants = collect($diff['tables']['event_participants']['added']);

    expect($participants->pluck('person_id')->diff($addedIds('people'))->all())->toBe([])
        ->and($participants->pluck('event_id')->diff($addedIds('events'))->all())->toBe([]);

    expect(Person::where('tree_id', '!=', $tree->id)->count())->toBe(4)
        ->and(Person::where('tree_id', '!=', $tree->id)->whereNotNull('repo_id')->count())->toBe(0);
});

test('refuses a plan that is blocked', function () {
    $tree = arrangeApplyUsers();
    Person::factory()->for($tree)->create();
    $plan = planFixtureForApply($tree);
    $before = ownershipSnapshot();

    expect(fn () => app(ApplyFirstImport::class)->handle($tree, $plan))->toThrow(RuntimeException::class, 'Drzewo konta ma już osoby')
        ->and(ownershipSnapshot())->toBe($before);
});

test('refuses a tree that got a person after planning and writes nothing', function () {
    $tree = arrangeApplyUsers();
    $plan = planFixtureForApply($tree);
    expect($plan->blockedReason)->toBeNull();

    Person::factory()->for($tree)->create(['first_name' => 'Dodana', 'last_name' => 'Ręcznie']);
    $before = ownershipSnapshot();

    expect(fn () => app(ApplyFirstImport::class)->handle($tree, $plan))->toThrow(RuntimeException::class)
        ->and(ownershipSnapshot())->toBe($before)
        ->and($tree->people()->whereNotNull('repo_id')->count())->toBe(0);
});

test('rolls everything back when a step in the middle fails', function () {
    $tree = arrangeApplyUsers();
    $plan = planFixtureForApply($tree);
    $before = ownershipSnapshot();

    $this->mock(CreateMarriage::class, function ($mock): void {
        $mock->shouldReceive('handle')->andThrow(ValidationException::withMessages(['spouse_id' => 'Ta para ma już małżeństwo.']));
    });

    expect(fn () => app(ApplyFirstImport::class)->handle($tree, $plan))->toThrow(ValidationException::class)
        ->and(ownershipSnapshot())->toBe($before)
        ->and($tree->people()->count())->toBe(0)
        ->and($tree->events()->count())->toBe(0)
        ->and(ParentChildRelation::where('tree_id', $tree->id)->count())->toBe(0);
});
