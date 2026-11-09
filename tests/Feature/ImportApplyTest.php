<?php

use App\Actions\Import\ApplyImport;
use App\Actions\Import\ClassifyRepoRecords;
use App\Actions\Import\PlanImport;
use App\Actions\Import\ReadResearchRepo;
use App\Actions\Persons\DeletePerson;
use App\Actions\Relations\CreateMarriage;
use App\Actions\Relations\CreateParentChildRelation;
use App\Enums\DateQualifier;
use App\Enums\EventType;
use App\Enums\Gender;
use App\Enums\MarriageForm;
use App\Enums\ParticipantRole;
use App\Models\Cemetery;
use App\Models\Event;
use App\Models\Locality;
use App\Models\ParentChildRelation;
use App\Models\Person;
use App\Models\RepoSnapshot;
use App\Models\Residence;
use App\Models\Source;
use App\Models\Tree;
use App\Models\User;
use App\Support\Import\ChangeApprovals;
use App\Support\Import\ConflictResolutions;
use App\Support\Import\IdentityDecisions;
use App\Support\Import\ImportDecisions;
use App\Support\Import\ImportPlan;
use App\Support\Import\ImportResult;
use App\Support\Import\PlaceDecisions;
use App\Support\Import\PlannedMatch;
use App\Support\Import\ResearchRepoReading;
use Illuminate\Support\Facades\File;
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

function planFixtureForApply(Tree $tree): ImportPlan
{
    $reading = app(ReadResearchRepo::class)->handle(base_path('tests/Fixtures/research-repo'));

    $classes = app(ClassifyRepoRecords::class)->handle($tree, $reading);

    return app(PlanImport::class)->handle($tree, $reading, $classes, ImportDecisions::forFirstImport(new IdentityDecisions(new: $classes->newPersonIds)));
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

    $result = app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals, new ConflictResolutions);

    $jan = importedPerson($tree, 'O-0001');

    expect($result)->toBeInstanceOf(ImportResult::class)
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

    $result = app(ApplyImport::class)->handle($tree, planFixtureForApply($tree), new ChangeApprovals, new ConflictResolutions);

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

    app(ApplyImport::class)->handle($tree, planFixtureForApply($tree), new ChangeApprovals, new ConflictResolutions);

    $wedding = importedEvent($tree, 'O-0001+O-0004/slub');

    expect($wedding->date_qualifier)->toBe(DateQualifier::Approximate)
        ->and($wedding->date_year)->toBe(1825);
});

test('writes relations with repo id and skips the one with a conflicting gender', function () {
    $tree = arrangeApplyUsers();

    $result = app(ApplyImport::class)->handle($tree, planFixtureForApply($tree), new ChangeApprovals, new ConflictResolutions);

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

    $result = app(ApplyImport::class)->handle($tree, planFixtureForApply($tree), new ChangeApprovals, new ConflictResolutions);

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

    app(ApplyImport::class)->handle($tree, planFixtureForApply($tree), new ChangeApprovals, new ConflictResolutions);

    expect($tree->fresh()->root_person_id)->toBeNull();
});

test('writes only into the author tree and leaves the other account untouched', function () {
    Storage::fake('local');
    $tree = arrangeApplyUsers();
    $plan = planFixtureForApply($tree);
    $before = databaseRows();

    app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals, new ConflictResolutions);

    $diff = rowDiff($before, databaseRows());

    expect(array_keys($diff['tables']))->toEqualCanonicalizing(['people', 'events', 'event_participants', 'parent_child_relations', 'repo_snapshots'])
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

test('writes a plan made for a tree that already holds a person from an import', function () {
    $tree = arrangeApplyUsers();
    $known = Person::factory()->for($tree)->create(['repo_id' => 'O-0001']);
    $plan = planFixtureForApply($tree);

    $result = app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals, new ConflictResolutions);

    expect($result->persons)->toBe(12)
        ->and($tree->people()->count())->toBe(13)
        ->and($tree->people()->where('repo_id', 'O-0001')->pluck('id')->all())->toBe([$known->id]);
});

test('writes into a tree that got a hand-entered person after planning', function () {
    $tree = arrangeApplyUsers();
    $plan = planFixtureForApply($tree);

    $added = Person::factory()->for($tree)->create(['first_name' => 'Dodana', 'last_name' => 'Ręcznie']);

    app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals, new ConflictResolutions);

    expect($tree->people()->count())->toBe(14)
        ->and($added->fresh()->repo_id)->toBeNull()
        ->and($tree->people()->whereNotNull('repo_id')->count())->toBe(13);
});

test('refuses a key the plan creates that the tree got after planning and writes nothing', function () {
    Storage::fake('local');
    $tree = arrangeApplyUsers();
    $plan = planFixtureForApply($tree);

    Person::factory()->for($tree)->create(['first_name' => 'Dodana', 'last_name' => 'Z importu', 'repo_id' => 'O-0001']);
    $before = ownershipSnapshot();

    expect(fn () => app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals, new ConflictResolutions))->toThrow(RuntimeException::class, 'Klucz repo O-0001 pojawił się w drzewie po planowaniu')
        ->and(ownershipSnapshot())->toBe($before);
});

test('rolls everything back when a step in the middle fails', function () {
    $tree = arrangeApplyUsers();
    $plan = planFixtureForApply($tree);
    $before = ownershipSnapshot();

    $this->mock(CreateMarriage::class, function ($mock): void {
        $mock->shouldReceive('handle')->andThrow(ValidationException::withMessages(['spouse_id' => 'Ta para ma już małżeństwo.']));
    });

    expect(fn () => app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals, new ConflictResolutions))->toThrow(ValidationException::class)
        ->and(ownershipSnapshot())->toBe($before)
        ->and($tree->people()->count())->toBe(0)
        ->and($tree->events()->count())->toBe(0)
        ->and(ParentChildRelation::where('tree_id', $tree->id)->count())->toBe(0);
});

/**
 * Hand-entered persons named like the fixture ones, with data the import must keep: Jan Nowak (birth 1801 at Zalesie
 * with a source and an entry number, a photo, a residence, a note), Piotr Nowak (father of Jan, birth 1762), Anna Wrona
 * (a male by mistake, a note about her married name) and a wedding of Jan and Anna in 1830 at Zalesie.
 *
 * @return array<string, Person|Event|Locality|Source>
 */
function arrangeHandEnteredTree(Tree $tree): array
{
    $zalesie = Locality::factory()->create(['tree_id' => $tree->id, 'name' => 'Zalesie']);
    $source = Source::factory()->create(['tree_id' => $tree->id]);

    $jan = Person::factory()->for($tree)->create([
        'first_name' => 'Jan',
        'last_name' => 'Nowak',
        'note' => 'Nazwisko po mężu: Kowalski',
        'photo_path' => "persons/{$tree->id}/jan.jpg",
    ]);
    $piotr = Person::factory()->for($tree)->create(['first_name' => 'Piotr', 'last_name' => 'Nowak']);
    $anna = Person::factory()->male()->for($tree)->create(['first_name' => 'Anna', 'last_name' => 'Wrona', 'note' => 'Nazwisko po mężu: Kowalska']);

    $birth = Event::factory()->for($tree)->birth()->create([
        'date_year' => 1801,
        'date_month' => null,
        'date_day' => null,
        'locality_id' => $zalesie->id,
    ]);
    $birth->participants()->attach($jan, ['role' => ParticipantRole::Subject]);
    $birth->sources()->attach($source, ['entry_number' => '47']);

    attachBirth($piotr, 1762);
    attachBirth($anna);
    Residence::factory()->create(['person_id' => $jan->id, 'locality_id' => $zalesie->id, 'year_from' => 1820]);
    linkParent($piotr, $jan);

    $wedding = attachMarriage($jan, $anna, 1830, locality: $zalesie);
    $wedding->update(['marriage_form' => MarriageForm::Religious]);

    return compact('zalesie', 'source', 'jan', 'piotr', 'anna', 'birth', 'wedding');
}

/**
 * An author and another account with the same hand-entered persons.
 *
 * @return array{0: Tree, 1: array<string, Person|Event|Locality|Source>}
 */
function arrangeHandEnteredAuthor(): array
{
    $other = User::factory()->create();
    $author = User::factory()->create();

    arrangeHandEnteredTree($other->tree);

    return [$author->tree, arrangeHandEnteredTree($author->tree)];
}

/**
 * Plans the fixture repo with Jan, Piotr and Anna matched to the hand-entered persons.
 *
 * @param  array<string, Person|Event|Locality|Source>  $scene
 */
function planFixtureWithMatches(Tree $tree, array $scene): ImportPlan
{
    $reading = app(ReadResearchRepo::class)->handle(base_path('tests/Fixtures/research-repo'));
    $classes = app(ClassifyRepoRecords::class)->handle($tree, $reading);
    $matched = ['O-0001' => $scene['jan']->id, 'O-0002' => $scene['piotr']->id, 'O-0004' => $scene['anna']->id];
    $new = array_values(array_diff($classes->newPersonIds, array_keys($matched)));

    return app(PlanImport::class)->handle($tree, $reading, $classes, ImportDecisions::forFirstImport(new IdentityDecisions($matched, [], $new)));
}

/**
 * Plans a throwaway repo built from person blocks for apply tests; the persons in `$matched` are matched, the rest new.
 *
 * @param  array<string, string>  $blocks  repo id => block body without id and version
 * @param  array<string, Person>  $matched  repo id => the tree person it is
 */
function planBlocksForApply(Tree $tree, array $blocks, array $matched = []): ImportPlan
{
    $repo = sys_get_temp_dir().'/apply-repo-'.uniqid();
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
        [],
        array_values(array_diff($classes->newPersonIds, array_keys($matched))),
    )));
}

/**
 * @return list<string>
 */
function fieldsOfFills(ImportPlan $plan): array
{
    return array_map(fn ($fill): string => $fill->field, $plan->matched[0]->fills);
}

test('replaces a block of the repo that the author pasted into a hand-entered person instead of adding a second one', function () {
    Storage::fake('local');
    [$tree, $scene] = arrangeHandEnteredAuthor();
    $scene['jan']->update(['note' => "Moja uwaga\n\nZ repo badawczego (O-0001):\nstary tekst"]);
    $plan = planFixtureWithMatches($tree, $scene);
    $note = collect($plan->matched)->first(fn ($match) => $match->person->id === 'O-0001')->note;

    app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals, new ConflictResolutions);

    expect($scene['jan']->fresh()->note)->toBe("Moja uwaga\n\n{$note}");
});

test('hooks the repo into a hand-entered person and keeps what the app holds', function () {
    Storage::fake('local');
    [$tree, $scene] = arrangeHandEnteredAuthor();
    $plan = planFixtureWithMatches($tree, $scene);
    $note = collect($plan->matched)->first(fn ($match) => $match->person->id === 'O-0001')->note;

    $result = app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals, new ConflictResolutions);

    $jan = $scene['jan']->fresh();
    $anna = $scene['anna']->fresh();
    $birth = $scene['birth']->fresh();

    expect($jan->repo_id)->toBe('O-0001')
        ->and($jan->gender)->toBe(Gender::Male)
        ->and($jan->note)->toBe("Nazwisko po mężu: Kowalski\n\n{$note}")
        ->and($jan->photo_path)->toBe($scene['jan']->photo_path)
        ->and($jan->residences()->count())->toBe(1)
        ->and($birth->repo_id)->toBe('O-0001/urodzenie')
        ->and([$birth->date_year, $birth->date_month, $birth->date_day])->toBe([1801, 5, 12])
        ->and($birth->locality_id)->toBe($scene['zalesie']->id)
        ->and($birth->sources()->get()->map(fn (Source $source) => [$source->id, $source->pivot->entry_number])->all())->toBe([[$scene['source']->id, '47']])
        ->and($anna->note)->toStartWith("Nazwisko po mężu: Kowalska\n\nZ repo badawczego (O-0004):")
        ->and($anna->repo_id)->toBe('O-0004')
        ->and($result->matched)->toBe(3)
        ->and($result->fills)->toBe($plan->fillCount());
});

test('sets the repo id on the persons that were matched and on no other hand-entered person', function () {
    [$tree, $scene] = arrangeHandEnteredAuthor();
    $bystander = Person::factory()->for($tree)->create(['first_name' => 'Jan', 'last_name' => 'Nowak']);

    app(ApplyImport::class)->handle($tree, planFixtureWithMatches($tree, $scene), new ChangeApprovals, new ConflictResolutions);

    expect($bystander->fresh()->repo_id)->toBeNull()
        ->and($tree->people()->whereNotNull('repo_id')->count())->toBe(13)
        ->and($tree->people()->whereNull('repo_id')->count())->toBe(1);
});

test('keeps the value from the app for a conflict without an answer and for one settled for the app', function (array $choices) {
    [$tree, $scene] = arrangeHandEnteredAuthor();
    $plan = planFixtureWithMatches($tree, $scene);
    expect(array_map(fn ($conflict) => $conflict->key, $plan->conflicts))->toBe(['O-0002/urodzenie/data', 'O-0004/plec', 'O-0001+O-0004/slub/data']);

    $result = app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals, new ConflictResolutions($choices));

    $piotrBirth = $scene['piotr']->load('events')->birth();
    $wedding = $scene['wedding']->fresh();

    expect($scene['anna']->fresh()->gender)->toBe(Gender::Male)
        ->and([$piotrBirth->date_year, $piotrBirth->date_qualifier])->toBe([1762, DateQualifier::Exact])
        ->and([$wedding->date_year, $wedding->date_qualifier, $wedding->date_is_approximate])->toBe([1830, DateQualifier::Exact, false])
        ->and($wedding->repo_id)->toBe('O-0001+O-0004/slub')
        ->and($result->takenFromRepo)->toBe(0);
})->with([
    'no answers' => [[]],
    'every answer for the app' => [['O-0002/urodzenie/data' => 'app', 'O-0004/plec' => 'app', 'O-0001+O-0004/slub/data' => 'app']],
]);

test('writes the value from the repo for a conflict settled for the repo and keeps the derived flag in line', function () {
    [$tree, $scene] = arrangeHandEnteredAuthor();
    $plan = planFixtureWithMatches($tree, $scene);
    $resolutions = new ConflictResolutions([
        'O-0002/urodzenie/data' => ConflictResolutions::FROM_REPO,
        'O-0004/plec' => ConflictResolutions::FROM_REPO,
        'O-0001+O-0004/slub/data' => ConflictResolutions::FROM_REPO,
    ]);

    $result = app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals, $resolutions);

    $piotrBirth = $scene['piotr']->load('events')->birth();
    $wedding = $scene['wedding']->fresh();

    expect($scene['anna']->fresh()->gender)->toBe(Gender::Female)
        ->and([$piotrBirth->date_year, $piotrBirth->date_until_year, $piotrBirth->date_qualifier, $piotrBirth->date_is_approximate])->toBe([1760, 1765, DateQualifier::Range, false])
        ->and([$wedding->date_year, $wedding->date_qualifier, $wedding->date_is_approximate])->toBe([1825, DateQualifier::Approximate, true])
        ->and([$wedding->locality_id, $wedding->marriage_form])->toBe([$scene['zalesie']->id, MarriageForm::Religious])
        ->and($result->takenFromRepo)->toBe(3);
});

test('marks the existing birth without a second one and creates the death that was missing', function () {
    [$tree, $scene] = arrangeHandEnteredAuthor();

    $result = app(ApplyImport::class)->handle($tree, planFixtureWithMatches($tree, $scene), new ChangeApprovals, new ConflictResolutions);

    $jan = $scene['jan']->load('events');
    $death = $jan->death();

    expect($jan->events->where('type', EventType::Birth))->toHaveCount(1)
        ->and($jan->birth()->id)->toBe($scene['birth']->id)
        ->and($death)->not->toBeNull()
        ->and([$death->repo_id, $death->date_year])->toBe(['O-0001/zgon', 1870])
        ->and($death->participants()->pluck('people.id')->all())->toBe([$jan->id])
        ->and($scene['piotr']->load('events')->death()->repo_id)->toBe('O-0002/zgon')
        ->and($tree->events()->where('repo_id', 'O-0001/urodzenie')->count())->toBe(1)
        ->and($result->deaths)->toBe(2)
        ->and($result->births)->toBe(3);
});

test('marks the relation and the wedding that are in the tree and creates the new ones around matched persons', function () {
    [$tree, $scene] = arrangeHandEnteredAuthor();
    $existingRelation = $tree->parentChildRelations()->firstOrFail();

    $result = app(ApplyImport::class)->handle($tree, planFixtureWithMatches($tree, $scene), new ChangeApprovals, new ConflictResolutions);

    $marta = importedPerson($tree, 'O-0003');
    $relations = $tree->parentChildRelations()->orderBy('id')->get();

    expect($existingRelation->fresh()->repo_id)->toBe('O-0002>O-0001')
        ->and($scene['wedding']->fresh()->repo_id)->toBe('O-0001+O-0004/slub')
        ->and($tree->events()->where('type', EventType::Marriage)->count())->toBe(3)
        ->and($tree->parentChildRelations()->where('child_id', $scene['jan']->id)->orderBy('id')->pluck('parent_id')->all())->toBe([$scene['piotr']->id, $marta->id])
        ->and($relations->pluck('repo_id')->all())->toBe(['O-0002>O-0001', 'O-0003>O-0001', 'O-0005>O-0002'])
        ->and(importedEvent($tree, 'O-0002+O-0003/slub')->participants()->pluck('people.id')->sort()->values()->all())->toBe(collect([$scene['piotr']->id, $marta->id])->sort()->values()->all())
        ->and([$result->relations, $result->markedRelations, $result->marriages, $result->markedMarriages])->toBe([2, 1, 2, 1]);
});

test('leaves the relations of a matched child alone when the repo names another father', function () {
    $other = User::factory()->create();
    $author = User::factory()->create();
    $parents = [];

    foreach ([$other->tree, $author->tree] as $tree) {
        $jan = Person::factory()->for($tree)->create(['first_name' => 'Jan', 'last_name' => 'Nowak']);
        $adam = Person::factory()->male()->for($tree)->create(['first_name' => 'Adam', 'last_name' => 'Nowak']);
        linkParent($adam, $jan);
        $parents[$tree->id] = [$jan, $adam];
    }

    [$jan, $adam] = $parents[$author->tree->id];
    $plan = planBlocksForApply($author->tree, [
        'O-0001' => "imiona: Jan\nnazwisko: Nowak\nrodzice:\n  ojciec: O-0002",
        'O-0002' => "imiona: Piotr\nnazwisko: Nowak\nplec: M",
    ], ['O-0001' => $jan]);

    app(ApplyImport::class)->handle($author->tree, $plan, new ChangeApprovals, new ConflictResolutions);

    expect($author->tree->parentChildRelations()->where('child_id', $jan->id)->pluck('parent_id')->all())->toBe([$adam->id])
        ->and($plan->skippedRelations)->toHaveCount(1)
        ->and(importedPerson($author->tree, 'O-0002')->first_name)->toBe('Piotr');
});

test('changes only rows of the author tree and removes nothing when it hooks the repo into hand-entered persons', function () {
    Storage::fake('local');
    [$tree, $scene] = arrangeHandEnteredAuthor();
    $plan = planFixtureWithMatches($tree, $scene);
    $resolutions = new ConflictResolutions(['O-0002/urodzenie/data' => 'repo', 'O-0001+O-0004/slub/data' => 'repo', 'O-0004/plec' => 'repo']);
    $before = databaseRows();

    app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals, $resolutions);

    $diff = rowDiff($before, databaseRows());
    $authorPeople = $tree->people()->pluck('id')->all();
    $authorEvents = $tree->events()->pluck('id')->all();

    expect(array_keys($diff['tables']))->toEqualCanonicalizing(['cemeteries', 'people', 'events', 'event_participants', 'parent_child_relations', 'repo_snapshots'])
        ->and($diff['files'])->toBe(['removed' => [], 'added' => []]);

    foreach ($diff['tables'] as $table => $changes) {
        expect($changes['removed'])->toBe([]);

        if ($table === 'event_participants') {
            expect($changes['changed'])->toBe([])
                ->and(collect($changes['added'])->pluck('person_id')->diff($authorPeople)->all())->toBe([])
                ->and(collect($changes['added'])->pluck('event_id')->diff($authorEvents)->all())->toBe([]);

            continue;
        }

        if (in_array($table, ['repo_snapshots', 'cemeteries'], true)) {
            expect($changes['changed'])->toBe([])
                ->and(collect($changes['added'])->pluck('tree_id')->unique()->all())->toBe([$tree->id]);

            continue;
        }

        expect(collect($changes['added'])->pluck('tree_id')->unique()->all())->toBe([$tree->id])
            ->and(collect($changes['changed'])->map(fn (array $change): array => [$change['before']['tree_id'], $change['after']['tree_id']])->unique()->all())->toBe([[$tree->id, $tree->id]]);
    }

    expect(Person::where('tree_id', '!=', $tree->id)->whereNotNull('repo_id')->count())->toBe(0)
        ->and(Event::where('tree_id', '!=', $tree->id)->whereNotNull('repo_id')->count())->toBe(0)
        ->and(ParentChildRelation::where('tree_id', '!=', $tree->id)->whereNotNull('repo_id')->count())->toBe(0);
});

test('refuses a plan made for the tree of another account', function () {
    Storage::fake('local');
    $other = User::factory()->create();
    $author = User::factory()->create();
    $scene = arrangeHandEnteredTree($other->tree);
    arrangeHandEnteredTree($author->tree);
    $foreignPlan = planFixtureWithMatches($other->tree, $scene);
    $before = ownershipSnapshot();

    expect(fn () => app(ApplyImport::class)->handle($author->tree, $foreignPlan, new ChangeApprovals, new ConflictResolutions))->toThrow(RuntimeException::class, 'nie ma już w drzewie konta')
        ->and(ownershipSnapshot())->toBe($before);
});

test('refuses a matched person who was deleted after planning and writes nothing', function () {
    Storage::fake('local');
    [$tree, $scene] = arrangeHandEnteredAuthor();
    $plan = planFixtureWithMatches($tree, $scene);
    $scene['anna']->delete();
    $before = ownershipSnapshot();

    expect(fn () => app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals, new ConflictResolutions))->toThrow(RuntimeException::class, 'nie ma już w drzewie konta')
        ->and(ownershipSnapshot())->toBe($before);
});

test('refuses a matched person who got a repo id after planning and writes nothing', function () {
    Storage::fake('local');
    [$tree, $scene] = arrangeHandEnteredAuthor();
    $plan = planFixtureWithMatches($tree, $scene);
    $scene['jan']->update(['repo_id' => 'O-0900']);
    $before = ownershipSnapshot();

    expect(fn () => app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals, new ConflictResolutions))->toThrow(RuntimeException::class)
        ->and(ownershipSnapshot())->toBe($before);
});

test('refuses a gender from the repo that gives a child two parents of one sex since planning and writes nothing', function () {
    Storage::fake('local');
    $other = User::factory()->create();
    $author = User::factory()->create();
    $mothers = [];

    foreach ([$other->tree, $author->tree] as $tree) {
        $maria = Person::factory()->for($tree)->create(['first_name' => 'Maria', 'last_name' => 'Wrona']);
        $jan = Person::factory()->for($tree)->create(['first_name' => 'Jan', 'last_name' => 'Nowak']);
        linkParent($maria, $jan);
        $mothers[$tree->id] = [$maria, $jan];
    }

    [$maria, $jan] = $mothers[$author->tree->id];
    $plan = planBlocksForApply($author->tree, ['O-0001' => "imiona: Maria\nnazwisko: Wrona\nplec: K"], ['O-0001' => $maria]);
    expect(fieldsOfFills($plan))->toBe(['gender']);

    linkParent(Person::factory()->female()->for($author->tree)->create(), $jan);
    $before = ownershipSnapshot();

    expect(fn () => app(ApplyImport::class)->handle($author->tree, $plan, new ChangeApprovals, new ConflictResolutions))->toThrow(RuntimeException::class, "O-0001 (#{$maria->id}): ")
        ->and(fn () => app(ApplyImport::class)->handle($author->tree, $plan, new ChangeApprovals, new ConflictResolutions))->toThrow(RuntimeException::class, 'matkę')
        ->and(ownershipSnapshot())->toBe($before);
});

test('names the relation and both persons when a new relation breaks a rule of the app and writes nothing', function () {
    Storage::fake('local');
    [$tree, $scene] = arrangeHandEnteredAuthor();
    $plan = planFixtureWithMatches($tree, $scene);
    $before = ownershipSnapshot();

    $this->mock(CreateParentChildRelation::class, function ($mock): void {
        $mock->shouldReceive('handle')->andThrow(ValidationException::withMessages(['child_id' => 'Ta osoba ma już ojca.']));
    });

    $thrown = null;

    try {
        app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals, new ConflictResolutions);
    } catch (RuntimeException $exception) {
        $thrown = $exception;
    }

    expect($thrown)->toBeInstanceOf(RuntimeException::class)
        ->and($thrown->getMessage())->toStartWith('O-0003>O-0001 (#')
        ->and($thrown->getMessage())->toContain('Ta osoba ma już ojca.')
        ->and($thrown->getPrevious())->toBeInstanceOf(ValidationException::class)
        ->and(ownershipSnapshot())->toBe($before);
});

test('refuses a field filled by hand since planning and writes nothing', function (Closure $change) {
    Storage::fake('local');
    [$tree, $scene] = arrangeHandEnteredAuthor();
    $plan = planFixtureWithMatches($tree, $scene);
    expect(fieldsOfFills($plan))->toContain('gender', 'birth_date');

    $change($scene);
    $before = ownershipSnapshot();

    expect(fn () => app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals, new ConflictResolutions))->toThrow(RuntimeException::class, "Osoba Jan Nowak (#{$scene['jan']->id}) zmieniła się po planowaniu")
        ->and(ownershipSnapshot())->toBe($before)
        ->and($tree->people()->whereNotNull('repo_id')->count())->toBe(0)
        ->and(Person::where('tree_id', '!=', $tree->id)->whereNotNull('repo_id')->count())->toBe(0);
})->with([
    'gender' => [fn (array $scene) => $scene['jan']->update(['gender' => Gender::Female])],
    'birth date refined by hand' => [fn (array $scene) => $scene['birth']->update(['date_month' => 7])],
]);

test('refuses a wedding date filled by hand since planning and writes nothing', function () {
    $other = User::factory()->create();
    $author = User::factory()->create();
    $weddings = [];

    foreach ([$other->tree, $author->tree] as $tree) {
        $jan = Person::factory()->for($tree)->create(['first_name' => 'Jan', 'last_name' => 'Nowak']);
        $anna = Person::factory()->for($tree)->create(['first_name' => 'Anna', 'last_name' => 'Wrona']);
        $weddings[$tree->id] = [$jan, $anna, attachMarriage($jan, $anna)];
    }

    [$jan, $anna, $wedding] = $weddings[$author->tree->id];
    $plan = planBlocksForApply($author->tree, [
        'O-0001' => "imiona: Jan\nnazwisko: Nowak\nmalzenstwa:\n  - z: O-0002\n    data: \"1830\"",
        'O-0002' => "imiona: Anna\nnazwisko: Wrona",
    ], ['O-0001' => $jan, 'O-0002' => $anna]);
    expect($plan->marriages[0]->dateFill)->not->toBeNull();

    $wedding->update(['date_year' => 1829]);
    $before = ownershipSnapshot();

    expect(fn () => app(ApplyImport::class)->handle($author->tree, $plan, new ChangeApprovals, new ConflictResolutions))->toThrow(RuntimeException::class, 'Małżeństwo O-0001+O-0002/slub zmieniło się po planowaniu')
        ->and(ownershipSnapshot())->toBe($before);
});

test('refuses a value taken from the repo when the app value changed since planning and writes nothing', function (string $key, Closure $change) {
    Storage::fake('local');
    [$tree, $scene] = arrangeHandEnteredAuthor();
    $plan = planFixtureWithMatches($tree, $scene);
    expect(array_map(fn ($conflict) => $conflict->key, $plan->conflicts))->toContain($key);

    $change($scene);
    $before = ownershipSnapshot();

    expect(fn () => app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals, new ConflictResolutions([$key => ConflictResolutions::FROM_REPO])))->toThrow(RuntimeException::class, 'po planowaniu; nic nie zostało zapisane')
        ->and(ownershipSnapshot())->toBe($before)
        ->and($tree->people()->whereNotNull('repo_id')->count())->toBe(0)
        ->and(Person::where('tree_id', '!=', $tree->id)->whereNotNull('repo_id')->count())->toBe(0);
})->with([
    'gender' => ['O-0004/plec', fn (array $scene) => $scene['anna']->update(['gender' => null])],
    'birth date' => ['O-0002/urodzenie/data', fn (array $scene) => $scene['piotr']->load('events')->birth()->update(['date_year' => 1763])],
    'wedding date' => ['O-0001+O-0004/slub/data', fn (array $scene) => $scene['wedding']->update(['date_year' => 1831])],
]);

test('does not check a value left for the app even when it changed since planning', function () {
    [$tree, $scene] = arrangeHandEnteredAuthor();
    $plan = planFixtureWithMatches($tree, $scene);
    $scene['anna']->update(['gender' => Gender::Female]);

    $result = app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals, new ConflictResolutions(['O-0004/plec' => ConflictResolutions::FROM_APP]));

    expect($scene['anna']->fresh()->gender)->toBe(Gender::Female)
        ->and($result->takenFromRepo)->toBe(0);
});

test('refuses a plan that matches one tree person to two repo persons and writes nothing', function () {
    Storage::fake('local');
    [$tree, $scene] = arrangeHandEnteredAuthor();
    $plan = planFixtureWithMatches($tree, $scene);
    $duplicate = new PlannedMatch(...[...get_object_vars($plan->matched[1]), 'personId' => $plan->matched[0]->personId]);
    $broken = new ImportPlan(...[...get_object_vars($plan), 'matched' => [$plan->matched[0], $duplicate, $plan->matched[2]]]);
    $before = ownershipSnapshot();

    expect(fn () => app(ApplyImport::class)->handle($tree, $broken, new ChangeApprovals, new ConflictResolutions))->toThrow(RuntimeException::class, 'kilku osób z repo')
        ->and(ownershipSnapshot())->toBe($before);
});

test('rolls the matched persons back too when a step in the middle fails', function () {
    Storage::fake('local');
    [$tree, $scene] = arrangeHandEnteredAuthor();
    $plan = planFixtureWithMatches($tree, $scene);
    $before = ownershipSnapshot();

    $this->mock(CreateMarriage::class, function ($mock): void {
        $mock->shouldReceive('handle')->andThrow(ValidationException::withMessages(['spouse_id' => 'Ta para ma już małżeństwo.']));
    });

    expect(fn () => app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals, new ConflictResolutions(['O-0004/plec' => 'repo'])))->toThrow(ValidationException::class)
        ->and(ownershipSnapshot())->toBe($before)
        ->and($tree->people()->whereNotNull('repo_id')->count())->toBe(0)
        ->and($scene['anna']->fresh()->gender)->toBe(Gender::Male);
});

/**
 * The remembered repo values of a tree keyed by repo key.
 *
 * @return array<string, array<string, string|null>>
 */
function snapshotValues(Tree $tree): array
{
    return $tree->repoSnapshots()->orderBy('repo_id')->get()->mapWithKeys(fn (RepoSnapshot $snapshot): array => [$snapshot->repo_id => $snapshot->values])->all();
}

test('remembers the repo values of every person, birth, death, relation and marriage it writes', function () {
    $tree = arrangeApplyUsers();
    $plan = planFixtureForApply($tree);

    app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals, new ConflictResolutions);

    $written = collect([
        ...$tree->people()->pluck('repo_id'),
        ...$tree->events()->whereNotNull('repo_id')->pluck('repo_id'),
        ...$tree->parentChildRelations()->pluck('repo_id'),
    ])->sort()->values()->all();
    $snapshots = snapshotValues($tree);
    $note = collect($plan->persons)->first(fn ($planned) => $planned->person->id === 'O-0001')->note;

    $withoutRecord = ['O-0001/pochowek'];

    expect(array_keys($snapshots))->toBe(collect([...$written, ...$withoutRecord])->sort()->values()->all())
        ->and($tree->repoSnapshots()->where('deletion_kept', true)->count())->toBe(0)
        ->and($snapshots['O-0001'])->toEqual(['imiona' => 'Jan', 'nazwisko' => 'Nowak', 'plec' => 'male', 'notatka' => $note])
        ->and($snapshots['O-0008']['plec'])->toBeNull()
        ->and($snapshots['O-0001/urodzenie'])->toBe(['data' => '1801-05-12', 'miejsce' => 'Zalesie', 'miejsce_id' => null])
        ->and($snapshots['O-0002/urodzenie'])->toBe(['data' => 'między 1760 a 1765', 'miejsce' => null, 'miejsce_id' => null])
        ->and($snapshots['O-0001/zgon'])->toBe(['data' => '1870', 'miejsce' => null, 'miejsce_id' => null])
        ->and($snapshots['O-0002/zgon'])->toBe(['data' => null, 'miejsce' => null, 'miejsce_id' => null])
        ->and($snapshots['O-0002>O-0001'])->toBe([])
        ->and($snapshots['O-0001+O-0004/slub'])->toBe(['data' => 'ok. 1825', 'miejsce' => 'Zalesie', 'miejsce_id' => null])
        ->and(RepoSnapshot::where('tree_id', '!=', $tree->id)->count())->toBe(0);
});

test('remembers the repo value and keeps the app value when the author leaves a conflict for the app', function () {
    [$tree, $scene] = arrangeHandEnteredAuthor();
    $plan = planFixtureWithMatches($tree, $scene);

    app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals, new ConflictResolutions([
        'O-0004/plec' => ConflictResolutions::FROM_APP,
        'O-0002/urodzenie/data' => ConflictResolutions::FROM_APP,
    ]));

    $snapshots = snapshotValues($tree);

    expect($scene['anna']->fresh()->gender)->toBe(Gender::Male)
        ->and($snapshots['O-0004']['plec'])->toBe('female')
        ->and($scene['piotr']->birth()->date_year)->toBe(1762)
        ->and($snapshots['O-0002/urodzenie'])->toBe(['data' => 'między 1760 a 1765', 'miejsce' => null, 'miejsce_id' => null])
        ->and(RepoSnapshot::where('tree_id', '!=', $tree->id)->count())->toBe(0);
});

test('leaves no memory of the import when the write rolls back', function () {
    $tree = arrangeApplyUsers();
    $plan = planFixtureForApply($tree);

    $this->mock(CreateMarriage::class, function ($mock): void {
        $mock->shouldReceive('handle')->andThrow(ValidationException::withMessages(['spouse_id' => 'Ta para ma już małżeństwo.']));
    });

    expect(fn () => app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals, new ConflictResolutions))->toThrow(ValidationException::class)
        ->and(RepoSnapshot::count())->toBe(0);
});

/**
 * Reads a throwaway repo made of person blocks.
 *
 * @param  array<string, string>  $blocks  repo id => block body without id and version
 */
function reimportReading(array $blocks): ResearchRepoReading
{
    $repo = sys_get_temp_dir().'/reimport-repo-'.uniqid();
    mkdir($repo.'/drzewo', 0777, true);

    foreach ($blocks as $id => $body) {
        file_put_contents("{$repo}/drzewo/".strtolower($id).'.md', "---\nwersja: 1\nid: {$id}\n{$body}\n---\n");
    }

    try {
        return app(ReadResearchRepo::class)->handle($repo);
    } finally {
        File::deleteDirectory($repo);
    }
}

/**
 * Plans a state of the repo over the tree: every new repo person is new, the rest is decided by the arguments.
 *
 * @param  array<string, string>  $blocks
 * @param  array<string, string>  $removals
 * @param  array<string, string>  $deletions
 */
function reimportPlan(Tree $tree, array $blocks, array $removals = [], array $deletions = []): ImportPlan
{
    $reading = reimportReading($blocks);
    $classes = app(ClassifyRepoRecords::class)->handle($tree, $reading);

    return app(PlanImport::class)->handle($tree, $reading, $classes, new ImportDecisions(new IdentityDecisions(new: $classes->newPersonIds), $removals, $deletions));
}

/**
 * The first import of the blocks into the tree.
 *
 * @param  array<string, string>  $blocks
 */
function reimportFirst(Tree $tree, array $blocks): void
{
    app(ApplyImport::class)->handle($tree, reimportPlan($tree, $blocks), new ChangeApprovals, new ConflictResolutions);
}

/**
 * The first import of the same blocks into the tree of another account and into the tree of the author, then the same
 * arrangement in both. Returns the tree of the author and the tree of the other account.
 *
 * @param  array<string, string>  $blocks
 * @param  (Closure(Tree): void)|null  $arrange
 * @return array{0: Tree, 1: Tree}
 */
function reimportTwins(array $blocks, ?Closure $arrange = null): array
{
    $other = User::factory()->create();
    $author = User::factory()->create();

    foreach ([$other->tree, $author->tree] as $tree) {
        reimportFirst($tree, $blocks);

        if ($arrange !== null) {
            $arrange($tree);
        }
    }

    return [$author->tree, $other->tree];
}

/**
 * Proves that the write touched only rows of the author: every changed row of a table with a tree column belongs to the
 * author, and so does every pivot row.
 *
 * @param  array{tables: array<string, array{removed: list<array<string, mixed>>, added: list<array<string, mixed>>, changed: list<array{before: array<string, mixed>, after: array<string, mixed>}>}>, files: array{removed: list<string>, added: list<string>}}  $diff
 * @param  list<int>  $eventIdsBefore
 */
function expectOnlyAuthorRowsChanged(array $diff, Tree $author, array $eventIdsBefore): void
{
    $eventIds = [...$eventIdsBefore, ...$author->events()->pluck('id')->all()];

    foreach ($diff['tables'] as $table => $changes) {
        $rows = [...$changes['removed'], ...$changes['added'], ...array_map(fn (array $change): array => $change['before'], $changes['changed']), ...array_map(fn (array $change): array => $change['after'], $changes['changed'])];

        foreach ($rows as $row) {
            if (array_key_exists('tree_id', $row)) {
                expect($row['tree_id'])->toBe($author->id);
            } elseif ($table === 'trees') {
                expect($row['id'])->toBe($author->id);
            } elseif (in_array($table, ['event_participants', 'event_source'], true)) {
                expect($eventIds)->toContain($row['event_id']);
            }
        }
    }
}

/**
 * The state of a tree that another account's import must leave alone, as plain data.
 *
 * @return array<string, mixed>
 */
function reimportTreeState(Tree $tree): array
{
    return [
        'people' => $tree->people()->orderBy('id')->get()->toArray(),
        'events' => $tree->events()->orderBy('id')->get()->toArray(),
        'relations' => $tree->parentChildRelations()->orderBy('id')->get()->toArray(),
        'snapshots' => $tree->repoSnapshots()->orderBy('id')->get()->toArray(),
    ];
}

const REIMPORT_JAN = "imiona: Jan\nnazwisko: Nowak\nplec: M\nurodzenie:\n  data: \"1801\"";

test('writes an approved change of a date, keeps the derived flag in line and remembers the repo value', function () {
    Storage::fake('local');
    [$tree, $other] = reimportTwins(['O-0001' => REIMPORT_JAN]);
    $plan = reimportPlan($tree, ['O-0001' => "imiona: Jan\nnazwisko: Nowak\nplec: M\nurodzenie:\n  data: \"ok. 1802\""]);
    $eventIds = $tree->events()->pluck('id')->all();
    $otherState = reimportTreeState($other);
    $before = databaseRows();

    $result = app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals, new ConflictResolutions);

    $birth = $tree->events()->where('repo_id', 'O-0001/urodzenie')->firstOrFail();

    expect([$birth->date_year, $birth->date_qualifier, $birth->date_is_approximate])->toBe([1802, DateQualifier::Approximate, true])
        ->and(snapshotValues($tree)['O-0001/urodzenie'])->toBe(['data' => 'ok. 1802', 'miejsce' => null, 'miejsce_id' => null])
        ->and([$result->changesTaken, $result->changesRejected])->toBe([1, 0])
        ->and(reimportTreeState($other))->toBe($otherState);

    expectOnlyAuthorRowsChanged(rowDiff($before, databaseRows()), $tree, $eventIds);
});

test('keeps the date of the app for a rejected change and still remembers the repo value', function () {
    Storage::fake('local');
    [$tree, $other] = reimportTwins(['O-0001' => REIMPORT_JAN]);
    $plan = reimportPlan($tree, ['O-0001' => "imiona: Jan\nnazwisko: Nowak\nplec: M\nurodzenie:\n  data: \"1802\""]);
    $eventIds = $tree->events()->pluck('id')->all();
    $otherState = reimportTreeState($other);
    $before = databaseRows();

    $result = app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals(['O-0001/urodzenie/data']), new ConflictResolutions);

    expect($tree->events()->where('repo_id', 'O-0001/urodzenie')->firstOrFail()->date_year)->toBe(1801)
        ->and(snapshotValues($tree)['O-0001/urodzenie'])->toBe(['data' => '1802', 'miejsce' => null, 'miejsce_id' => null])
        ->and([$result->changesTaken, $result->changesRejected])->toBe([0, 1])
        ->and(reimportTreeState($other))->toBe($otherState);

    expectOnlyAuthorRowsChanged(rowDiff($before, databaseRows()), $tree, $eventIds);
});

test('settles a conflict of a date for the app or for the repo and remembers the repo value either way', function (string $choice, int $year) {
    Storage::fake('local');
    [$tree, $other] = reimportTwins(['O-0001' => REIMPORT_JAN], fn (Tree $tree) => $tree->events()->where('repo_id', 'O-0001/urodzenie')->firstOrFail()->update(['date_year' => 1830]));
    $plan = reimportPlan($tree, ['O-0001' => "imiona: Jan\nnazwisko: Nowak\nplec: M\nurodzenie:\n  data: \"1802\""]);
    $eventIds = $tree->events()->pluck('id')->all();
    $otherState = reimportTreeState($other);
    $before = databaseRows();

    expect($plan->conflicts[0]->key)->toBe('O-0001/urodzenie/data');

    app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals, new ConflictResolutions(['O-0001/urodzenie/data' => $choice]));

    expect($tree->events()->where('repo_id', 'O-0001/urodzenie')->firstOrFail()->date_year)->toBe($year)
        ->and(snapshotValues($tree)['O-0001/urodzenie'])->toBe(['data' => '1802', 'miejsce' => null, 'miejsce_id' => null])
        ->and(reimportTreeState($other))->toBe($otherState);

    expectOnlyAuthorRowsChanged(rowDiff($before, databaseRows()), $tree, $eventIds);
})->with([
    'for the app' => [ConflictResolutions::FROM_APP, 1830],
    'for the repo' => [ConflictResolutions::FROM_REPO, 1802],
]);

test('replaces only the repo block of a note and leaves the hand-written text around it', function () {
    Storage::fake('local');
    $v1 = ['O-0001' => REIMPORT_JAN."\n  miejsce: Zalesie"];
    [$tree, $other] = reimportTwins($v1, function (Tree $tree): void {
        $person = $tree->people()->where('repo_id', 'O-0001')->firstOrFail();
        $person->update(['note' => "Przed blokiem\n\n{$person->note}\n\nPo bloku"]);
    });
    $plan = reimportPlan($tree, ['O-0001' => REIMPORT_JAN."\n  miejsce: Wola"]);
    $eventIds = $tree->events()->pluck('id')->all();
    $otherState = reimportTreeState($other);
    $before = databaseRows();

    expect(collect($plan->changes)->pluck('key')->all())->toBe(['O-0001/notatka']);

    app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals, new ConflictResolutions);

    expect($tree->people()->where('repo_id', 'O-0001')->firstOrFail()->note)->toBe("Przed blokiem\n\nZ repo badawczego (O-0001):\nUrodzenie: Wola\n\nPo bloku")
        ->and(reimportTreeState($other))->toBe($otherState);

    expectOnlyAuthorRowsChanged(rowDiff($before, databaseRows()), $tree, $eventIds);
});

const REIMPORT_FAMILY = [
    'O-0001' => "imiona: Dziecko\nrodzice:\n  ojciec: O-0002\nurodzenie:\n  data: \"1830\"",
    'O-0002' => "imiona: Piotr\nplec: M\nurodzenie:\n  data: \"1801\"\nzgon:\n  data: \"1870\"\nmalzenstwa:\n  - z: O-0003\n    data: \"1825\"",
    'O-0003' => "imiona: Anna\nplec: K\nmalzenstwa:\n  - z: O-0002\n    data: \"1825\"",
];

const REIMPORT_WITHOUT_PIOTR = [
    'O-0001' => "imiona: Dziecko\nurodzenie:\n  data: \"1830\"",
    'O-0003' => "imiona: Anna\nplec: K",
];

/**
 * Gives Piotr (O-0002) of the family a photo, a residence, a source on his birth, a burial and the place of the proband.
 *
 * @return Closure(Tree): void
 */
function reimportDressPiotr(): Closure
{
    return function (Tree $tree): void {
        $piotr = $tree->people()->where('repo_id', 'O-0002')->firstOrFail();
        $piotr->update(['photo_path' => "persons/{$tree->id}/piotr.jpg", 'photo_thumbnail_path' => "persons/{$tree->id}/piotr-thumb.jpg"]);
        Storage::disk('local')->put("persons/{$tree->id}/piotr.jpg", 'photo');
        Storage::disk('local')->put("persons/{$tree->id}/piotr-thumb.jpg", 'thumb');
        $tree->update(['root_person_id' => $piotr->id]);
        Residence::factory()->create(['person_id' => $piotr->id]);
        $source = Source::factory()->for($tree)->create();
        $tree->events()->where('repo_id', 'O-0002/urodzenie')->firstOrFail()->sources()->attach($source, ['entry_number' => '12']);
        $burial = Event::factory()->for($tree)->burial()->create();
        $burial->participants()->attach($piotr, ['role' => ParticipantRole::Subject]);
    };
}

test('removes a person with everything that goes with it, keeps the sources and deletes the photo only after the commit', function () {
    Storage::fake('local');
    [$tree, $other] = reimportTwins(REIMPORT_FAMILY, reimportDressPiotr());
    $plan = reimportPlan($tree, REIMPORT_WITHOUT_PIOTR, removals: ['O-0002' => 'delete']);
    $eventIds = $tree->events()->pluck('id')->all();
    $otherState = reimportTreeState($other);
    $sources = Source::where('tree_id', $tree->id)->count();
    $before = databaseRows();

    $result = app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals, new ConflictResolutions);

    expect($tree->people()->where('repo_id', 'O-0002')->exists())->toBeFalse()
        ->and($tree->people()->count())->toBe(2)
        ->and($tree->events()->count())->toBe(1)
        ->and($tree->parentChildRelations()->count())->toBe(0)
        ->and(Source::where('tree_id', $tree->id)->count())->toBe($sources)
        ->and($tree->fresh()->root_person_id)->toBeNull()
        ->and(array_keys(snapshotValues($tree)))->toBe(['O-0001', 'O-0001/urodzenie', 'O-0003'])
        ->and(Storage::disk('local')->exists("persons/{$tree->id}/piotr.jpg"))->toBeFalse()
        ->and(Storage::disk('local')->exists("persons/{$tree->id}/piotr-thumb.jpg"))->toBeFalse()
        ->and(Storage::disk('local')->exists("persons/{$other->id}/piotr.jpg"))->toBeTrue()
        ->and([$result->removedPersons, $result->removedRecords, $result->detached])->toBe([1, 0, 0])
        ->and(reimportTreeState($other))->toBe($otherState);

    expectOnlyAuthorRowsChanged(rowDiff($before, databaseRows()), $tree, $eventIds);
});

test('leaves the photo of another account in place when it removes a person', function () {
    Storage::fake('local');
    [$tree, $other] = reimportTwins(REIMPORT_FAMILY, reimportDressPiotr());
    $otherPhoto = "persons/{$other->id}/piotr.jpg";
    $otherState = reimportTreeState($other);

    app(ApplyImport::class)->handle($tree, reimportPlan($tree, REIMPORT_WITHOUT_PIOTR, removals: ['O-0002' => 'delete']), new ChangeApprovals, new ConflictResolutions);

    expect(Storage::disk('local')->exists($otherPhoto))->toBeTrue()
        ->and($other->people()->where('repo_id', 'O-0002')->exists())->toBeTrue()
        ->and(reimportTreeState($other))->toBe($otherState);
});

test('detaches a person from the repo and keeps the person, its records, photo, residence and sources', function () {
    Storage::fake('local');
    [$tree, $other] = reimportTwins(REIMPORT_FAMILY, reimportDressPiotr());
    $plan = reimportPlan($tree, REIMPORT_WITHOUT_PIOTR, removals: ['O-0002' => 'detach']);
    $eventIds = $tree->events()->pluck('id')->all();
    $otherState = reimportTreeState($other);
    $before = databaseRows();
    $piotr = $tree->people()->where('repo_id', 'O-0002')->firstOrFail();

    $result = app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals, new ConflictResolutions);

    $diff = rowDiff($before, databaseRows());

    expect($piotr->fresh()->repo_id)->toBeNull()
        ->and($piotr->fresh()->photo_path)->toBe("persons/{$tree->id}/piotr.jpg")
        ->and($tree->events()->whereIn('id', $eventIds)->whereNotNull('repo_id')->pluck('repo_id')->sort()->values()->all())->toBe(['O-0001/urodzenie'])
        ->and($tree->parentChildRelations()->whereNotNull('repo_id')->count())->toBe(0)
        ->and($tree->parentChildRelations()->count())->toBe(1)
        ->and(array_keys(snapshotValues($tree)))->toBe(['O-0001', 'O-0001/urodzenie', 'O-0003'])
        ->and(Storage::disk('local')->exists("persons/{$tree->id}/piotr.jpg"))->toBeTrue()
        ->and($result->detached)->toBe(1)
        ->and(array_keys($diff['tables']))->toEqualCanonicalizing(['people', 'events', 'parent_child_relations', 'repo_snapshots'])
        ->and(reimportTreeState($other))->toBe($otherState);

    foreach (['people', 'events', 'parent_child_relations'] as $table) {
        expect($diff['tables'][$table]['removed'])->toBe([])
            ->and($diff['tables'][$table]['added'])->toBe([])
            ->and(collect($diff['tables'][$table]['changed'])->every(fn (array $change): bool => $change['after']['repo_id'] === null))->toBeTrue();
    }

    expectOnlyAuthorRowsChanged($diff, $tree, $eventIds);
});

test('detaches a birth the repo no longer has and keeps the event', function () {
    Storage::fake('local');
    [$tree, $other] = reimportTwins(['O-0001' => REIMPORT_JAN]);
    $plan = reimportPlan($tree, ['O-0001' => "imiona: Jan\nnazwisko: Nowak\nplec: M"], removals: ['O-0001/urodzenie' => 'detach']);
    $eventIds = $tree->events()->pluck('id')->all();
    $otherState = reimportTreeState($other);
    $before = databaseRows();

    app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals, new ConflictResolutions);

    $birth = $tree->events()->whereKey($eventIds[0])->firstOrFail();

    expect($tree->events()->count())->toBe(1)
        ->and($birth->repo_id)->toBeNull()
        ->and($birth->date_year)->toBe(1801)
        ->and(array_keys(snapshotValues($tree)))->toBe(['O-0001'])
        ->and(reimportTreeState($other))->toBe($otherState);

    expectOnlyAuthorRowsChanged(rowDiff($before, databaseRows()), $tree, $eventIds);
});

test('removes a death the repo no longer has together with the burial of the person and keeps the person and the birth', function () {
    Storage::fake('local');
    $v1 = ['O-0001' => REIMPORT_JAN."\nzgon:\n  data: \"1870\""];
    [$tree, $other] = reimportTwins($v1, function (Tree $tree): void {
        $burial = Event::factory()->for($tree)->burial()->create();
        $burial->participants()->attach($tree->people()->where('repo_id', 'O-0001')->firstOrFail(), ['role' => ParticipantRole::Subject]);
    });
    $plan = reimportPlan($tree, ['O-0001' => REIMPORT_JAN], removals: ['O-0001/zgon' => 'delete']);
    $eventIds = $tree->events()->pluck('id')->all();
    $otherState = reimportTreeState($other);
    $before = databaseRows();

    $result = app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals, new ConflictResolutions);

    expect($tree->events()->pluck('type')->all())->toBe([EventType::Birth])
        ->and($tree->people()->where('repo_id', 'O-0001')->exists())->toBeTrue()
        ->and(array_keys(snapshotValues($tree)))->toBe(['O-0001', 'O-0001/urodzenie'])
        ->and([$result->removedPersons, $result->removedRecords])->toBe([0, 1])
        ->and(reimportTreeState($other))->toBe($otherState);

    expectOnlyAuthorRowsChanged(rowDiff($before, databaseRows()), $tree, $eventIds);
});

test('brings a death deleted in the app back from the repo or leaves it deleted', function (string $decision) {
    Storage::fake('local');
    $v1 = ['O-0001' => REIMPORT_JAN."\nzgon:\n  data: \"1870\""];
    [$tree, $other] = reimportTwins($v1, fn (Tree $tree) => $tree->events()->where('repo_id', 'O-0001/zgon')->firstOrFail()->delete());
    $plan = reimportPlan($tree, $v1, deletions: ['O-0001/zgon' => $decision]);
    $eventIds = $tree->events()->pluck('id')->all();
    $otherState = reimportTreeState($other);
    $before = databaseRows();

    $result = app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals, new ConflictResolutions);

    $death = $tree->events()->where('type', EventType::Death)->first();
    $snapshot = $tree->repoSnapshots()->where('repo_id', 'O-0001/zgon')->firstOrFail();

    if ($decision === 'restore') {
        expect([$death->repo_id, $death->date_year])->toBe(['O-0001/zgon', 1870])
            ->and($death->participants()->pluck('people.id')->all())->toBe([$tree->people()->where('repo_id', 'O-0001')->firstOrFail()->id])
            ->and($snapshot->deletion_kept)->toBeFalse()
            ->and([$result->restored, $result->keptDeleted])->toBe([1, 0]);
    } else {
        expect($death)->toBeNull()
            ->and($snapshot->deletion_kept)->toBeTrue()
            ->and($snapshot->values)->toBe(['data' => '1870', 'miejsce' => null])
            ->and([$result->restored, $result->keptDeleted])->toBe([0, 1]);
    }

    expect(reimportTreeState($other))->toBe($otherState);
    expectOnlyAuthorRowsChanged(rowDiff($before, databaseRows()), $tree, $eventIds);
})->with(['restore', 'keep']);

test('brings a person deleted in the app back with its birth and its pair', function () {
    Storage::fake('local');
    [$tree, $other] = reimportTwins(REIMPORT_FAMILY, fn (Tree $tree) => app(DeletePerson::class)->handle($tree->people()->where('repo_id', 'O-0002')->firstOrFail()));
    $plan = reimportPlan($tree, REIMPORT_FAMILY, deletions: ['O-0002' => 'restore']);
    $eventIds = $tree->events()->pluck('id')->all();
    $otherState = reimportTreeState($other);
    $before = databaseRows();

    $result = app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals, new ConflictResolutions);

    $piotr = $tree->people()->where('repo_id', 'O-0002')->firstOrFail();

    expect($piotr->first_name)->toBe('Piotr')
        ->and($tree->events()->whereIn('repo_id', ['O-0002/urodzenie', 'O-0002/zgon', 'O-0002+O-0003/slub'])->count())->toBe(3)
        ->and($tree->parentChildRelations()->where('repo_id', 'O-0002>O-0001')->firstOrFail()->parent_id)->toBe($piotr->id)
        ->and($tree->repoSnapshots()->where('deletion_kept', true)->count())->toBe(0)
        ->and($result->restored)->toBeGreaterThanOrEqual(1)
        ->and(reimportTreeState($other))->toBe($otherState);

    expectOnlyAuthorRowsChanged(rowDiff($before, databaseRows()), $tree, $eventIds);
});

test('counts a person left deleted as one kept record, not one per key', function () {
    Storage::fake('local');
    [$tree, $other] = reimportTwins(REIMPORT_FAMILY, fn (Tree $tree) => app(DeletePerson::class)->handle($tree->people()->where('repo_id', 'O-0002')->firstOrFail()));
    $plan = reimportPlan($tree, REIMPORT_FAMILY, deletions: ['O-0002' => 'keep']);
    $otherState = reimportTreeState($other);

    $result = app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals, new ConflictResolutions);

    expect(count($plan->keptDeletedSnapshots))->toBeGreaterThan(1)
        ->and($result->keptDeleted)->toBe(1)
        ->and(reimportTreeState($other))->toBe($otherState);
});

test('deletes the old pair and creates the pair with the new father in one write', function () {
    Storage::fake('local');
    $v1 = ['O-0001' => "imiona: Dziecko\nrodzice:\n  ojciec: O-0002", 'O-0002' => "imiona: Piotr\nplec: M", 'O-0003' => "imiona: Paweł\nplec: M"];
    [$tree, $other] = reimportTwins($v1);
    $plan = reimportPlan($tree, ['O-0001' => "imiona: Dziecko\nrodzice:\n  ojciec: O-0003", 'O-0002' => $v1['O-0002'], 'O-0003' => $v1['O-0003']], removals: ['O-0002>O-0001' => 'delete']);
    $eventIds = $tree->events()->pluck('id')->all();
    $otherState = reimportTreeState($other);
    $before = databaseRows();

    $result = app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals, new ConflictResolutions);

    $pairs = $tree->parentChildRelations()->get();

    expect($pairs)->toHaveCount(1)
        ->and($pairs[0]->repo_id)->toBe('O-0003>O-0001')
        ->and($pairs[0]->parent_id)->toBe($tree->people()->where('repo_id', 'O-0003')->firstOrFail()->id)
        ->and($result->removedRecords)->toBe(1)
        ->and($result->relations)->toBe(1)
        ->and(array_keys(snapshotValues($tree)))->not->toContain('O-0002>O-0001')
        ->and(reimportTreeState($other))->toBe($otherState);

    expectOnlyAuthorRowsChanged(rowDiff($before, databaseRows()), $tree, $eventIds);
});

test('refuses a hand change made after planning and writes nothing, not even the removals', function () {
    Storage::fake('local');
    $v2 = REIMPORT_WITHOUT_PIOTR;
    $v2['O-0001'] = "imiona: Dziecko\nurodzenie:\n  data: \"1831\"";
    [$tree, $other] = reimportTwins(REIMPORT_FAMILY, reimportDressPiotr());
    $plan = reimportPlan($tree, $v2, removals: ['O-0002' => 'delete']);

    expect(collect($plan->changes)->pluck('key')->all())->toBe(['O-0001/urodzenie/data']);

    $tree->events()->where('repo_id', 'O-0001/urodzenie')->firstOrFail()->update(['date_year' => 1850]);
    $before = ownershipSnapshot();

    expect(fn () => app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals, new ConflictResolutions))->toThrow(RuntimeException::class, 'zmieniła się po planowaniu; nic nie zostało zapisane')
        ->and(ownershipSnapshot())->toBe($before)
        ->and($tree->people()->where('repo_id', 'O-0002')->exists())->toBeTrue()
        ->and(Storage::disk('local')->exists("persons/{$tree->id}/piotr.jpg"))->toBeTrue();
});

test('refuses a record to remove that disappeared since planning and writes nothing', function () {
    Storage::fake('local');
    [$tree] = reimportTwins(REIMPORT_FAMILY, reimportDressPiotr());
    $plan = reimportPlan($tree, REIMPORT_WITHOUT_PIOTR, removals: ['O-0002' => 'delete']);
    $tree->people()->where('repo_id', 'O-0002')->firstOrFail()->update(['repo_id' => null]);
    $before = ownershipSnapshot();

    expect(fn () => app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals, new ConflictResolutions))->toThrow(RuntimeException::class, 'zmieniła się po planowaniu')
        ->and(ownershipSnapshot())->toBe($before);
});

test('refuses a removal whose effects changed since planning and writes nothing', function () {
    Storage::fake('local');
    [$tree, $other] = reimportTwins(REIMPORT_FAMILY, reimportDressPiotr());
    $plan = reimportPlan($tree, REIMPORT_WITHOUT_PIOTR, removals: ['O-0002' => 'delete']);
    Residence::factory()->create(['person_id' => $tree->people()->where('repo_id', 'O-0002')->firstOrFail()->id]);
    $before = ownershipSnapshot();

    expect(fn () => app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals, new ConflictResolutions))->toThrow(RuntimeException::class, 'Skutki usunięcia O-0002 zmieniły się po planowaniu; nic nie zostało zapisane')
        ->and(ownershipSnapshot())->toBe($before)
        ->and($tree->people()->where('repo_id', 'O-0002')->exists())->toBeTrue()
        ->and(Storage::disk('local')->exists("persons/{$tree->id}/piotr.jpg"))->toBeTrue();
});

test('refuses a write when the memory of the last import moved since planning', function () {
    Storage::fake('local');
    [$tree] = reimportTwins(['O-0001' => REIMPORT_JAN]);
    $plan = reimportPlan($tree, ['O-0001' => "imiona: Jan\nnazwisko: Nowak\nplec: M\nurodzenie:\n  data: \"1802\""]);
    $tree->repoSnapshots()->where('repo_id', 'O-0001/urodzenie')->update(['values' => json_encode(['data' => '1900'], JSON_THROW_ON_ERROR)]);
    $before = ownershipSnapshot();

    expect(fn () => app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals, new ConflictResolutions))->toThrow(RuntimeException::class, 'Pamięć ostatniego importu zmieniła się po planowaniu')
        ->and(ownershipSnapshot())->toBe($before);
});

test('rolls back the removal of a person, its photo and the memory when a later step fails', function () {
    Storage::fake('local');
    $v2 = [
        'O-0001' => "imiona: Dziecko\nurodzenie:\n  data: \"1830\"\nmalzenstwa:\n  - z: O-0003\n    data: \"1850\"",
        'O-0003' => "imiona: Anna\nplec: K\nmalzenstwa:\n  - z: O-0001\n    data: \"1850\"",
    ];
    [$tree, $other] = reimportTwins(REIMPORT_FAMILY, reimportDressPiotr());
    $plan = reimportPlan($tree, $v2, removals: ['O-0002' => 'delete']);
    $before = ownershipSnapshot();
    $snapshots = snapshotValues($tree);

    expect($plan->marriages)->toHaveCount(1);

    $this->mock(CreateMarriage::class, function ($mock): void {
        $mock->shouldReceive('handle')->andThrow(ValidationException::withMessages(['spouse_id' => 'Ta para ma już małżeństwo.']));
    });

    expect(fn () => app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals, new ConflictResolutions))->toThrow(ValidationException::class)
        ->and(ownershipSnapshot())->toBe($before)
        ->and($tree->people()->where('repo_id', 'O-0002')->exists())->toBeTrue()
        ->and($tree->parentChildRelations()->where('repo_id', 'O-0002>O-0001')->exists())->toBeTrue()
        ->and(Storage::disk('local')->exists("persons/{$tree->id}/piotr.jpg"))->toBeTrue()
        ->and(snapshotValues($tree))->toBe($snapshots)
        ->and($other->people()->where('repo_id', 'O-0002')->exists())->toBeTrue();
});

test('takes a changed wedding date and clears a date the repo dropped', function () {
    Storage::fake('local');
    $both = fn (string $date): array => [
        'O-0001' => "imiona: Jan\nmalzenstwa:\n  - z: O-0002\n    data: \"{$date}\"\nurodzenie:\n  data: \"1801\"",
        'O-0002' => "imiona: Anna\nmalzenstwa:\n  - z: O-0001\n    data: \"{$date}\"",
    ];
    [$tree, $other] = reimportTwins($both('1825'));
    $plan = reimportPlan($tree, [
        'O-0001' => "imiona: Jan\nmalzenstwa:\n  - z: O-0002\n    data: \"1826\"\nurodzenie:\n  data: \"1801\"",
        'O-0002' => "imiona: Anna\nmalzenstwa:\n  - z: O-0001\n    data: \"1826\"",
    ]);
    $otherState = reimportTreeState($other);

    $result = app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals, new ConflictResolutions);

    $wedding = $tree->events()->where('repo_id', 'O-0001+O-0002/slub')->firstOrFail();

    expect($wedding->date_year)->toBe(1826)
        ->and(snapshotValues($tree)['O-0001+O-0002/slub'])->toBe(['data' => '1826', 'miejsce' => null, 'miejsce_id' => null])
        ->and([$result->changesTaken, $result->markedMarriages])->toBe([1, 0])
        ->and(reimportTreeState($other))->toBe($otherState);
});

/**
 * Zalesie with an unnamed cemetery and Bukinia in a tree; the other account also remembers "Bokinia" as a variant of Bukinia.
 *
 * @return array{zalesie: Locality, bukinia: Locality, cemetery: Cemetery}
 */
function arrangeApplyPlaceWorld(Tree $tree, bool $withVariant): array
{
    $zalesie = Locality::factory()->for($tree)->create(['name' => 'Zalesie']);
    $bukinia = Locality::factory()->for($tree)->create(['name' => 'Bukinia']);

    if ($withVariant) {
        $bukinia->aliases()->create(['tree_id' => $tree->id, 'name' => 'Bokinia']);
    }

    return ['zalesie' => $zalesie, 'bukinia' => $bukinia, 'cemetery' => Cemetery::factory()->in($zalesie)->unnamed()->create()];
}

/**
 * The tree of another account and the tree of the author with the same places, the first import of the blocks into both
 * and then the same arrangement in both.
 *
 * @param  array<string, string>  $blocks  blocks to import first; none leaves both trees without repo persons
 * @param  (Closure(Tree, array{zalesie: Locality, bukinia: Locality, cemetery: Cemetery}): void)|null  $arrange
 * @return array{0: Tree, 1: Tree, 2: array{zalesie: Locality, bukinia: Locality, cemetery: Cemetery}}
 */
function applyPlaceTwins(array $blocks = [], ?Closure $arrange = null): array
{
    $other = User::factory()->create();
    $author = User::factory()->create();
    $world = [];

    foreach ([$other->tree, $author->tree] as $tree) {
        $world = arrangeApplyPlaceWorld($tree, $tree->is($other->tree));

        if ($blocks !== []) {
            reimportFirst($tree, $blocks);
        }

        if ($arrange !== null) {
            $arrange($tree, $world);
        }
    }

    return [$author->tree, $other->tree, $world];
}

/**
 * Plans a throwaway repo over the tree with the author's answers about the places; every repo person is new.
 *
 * @param  array<string, string>  $blocks
 * @param  array<string, string>  $deletions
 * @param  array<string, string>  $removals
 */
function applyPlacePlan(Tree $tree, array $blocks, ?PlaceDecisions $places = null, array $deletions = [], array $removals = []): ImportPlan
{
    $reading = reimportReading($blocks);
    $classes = app(ClassifyRepoRecords::class)->handle($tree, $reading);

    return app(PlanImport::class)->handle($tree, $reading, $classes, new ImportDecisions(new IdentityDecisions(new: $classes->newPersonIds), $removals, $deletions, $places ?? new PlaceDecisions));
}

const APPLY_PLACE_PERSON = "imiona: Jan\nnazwisko: Nowak\nurodzenie:\n  data: \"1801\"\n  miejsce: Bokinia\nzgon:\n  data: \"1870\"\n  miejsce: Bindyga";

test('adds each new locality, cemetery and variant once however many events name them', function () {
    Storage::fake('local');
    [$tree, $other, $world] = applyPlaceTwins();
    $blocks = [
        'O-0001' => APPLY_PLACE_PERSON."\npochowek:\n  data: \"1870\"\n  miejsce: Bindyga\n  cmentarz: Parafialny",
        'O-0002' => str_replace('Jan', 'Anna', APPLY_PLACE_PERSON),
    ];
    $plan = applyPlacePlan($tree, $blocks, new PlaceDecisions(
        localities: ['bokinia' => $world['bukinia']->id, 'bindyga' => PlaceDecisions::NEW],
        aliases: ['bokinia' => true],
    ));
    $eventIds = $tree->events()->pluck('id')->all();
    $before = databaseRows();

    $result = app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals, new ConflictResolutions);

    $bindyga = $tree->localities()->where('name', 'Bindyga')->firstOrFail();
    $cemetery = $tree->cemeteries()->where('name', 'Parafialny')->firstOrFail();
    $births = $tree->events()->where('type', EventType::Birth)->pluck('locality_id')->unique()->all();
    $deaths = $tree->events()->where('type', EventType::Death)->pluck('locality_id')->unique()->all();
    $burial = $tree->events()->where('type', EventType::Burial)->firstOrFail();
    $snapshots = snapshotValues($tree);

    expect($tree->localities()->count())->toBe(3)
        ->and($tree->cemeteries()->count())->toBe(2)
        ->and($cemetery->locality_id)->toBe($bindyga->id)
        ->and($tree->localityAliases()->pluck('name', 'locality_id')->all())->toBe([$world['bukinia']->id => 'Bokinia'])
        ->and($births)->toBe([$world['bukinia']->id])
        ->and($deaths)->toBe([$bindyga->id])
        ->and([$burial->repo_id, $burial->cemetery_id, $burial->locality_id])->toBe(['O-0001/pochowek', $cemetery->id, $bindyga->id])
        ->and([$result->newLocalities, $result->newCemeteries, $result->aliases, $result->burials])->toBe([1, 1, 1, 1])
        ->and($snapshots['O-0001/urodzenie']['miejsce_id'])->toBe('L:'.$world['bukinia']->id)
        ->and($snapshots['O-0001/zgon']['miejsce_id'])->toBe('L:'.$bindyga->id)
        ->and($snapshots['O-0001/pochowek'])->toMatchArray(['miejsce_id' => 'L:'.$bindyga->id, 'cmentarz_id' => 'C:'.$cemetery->id])
        ->and($other->localities()->count())->toBe(2)
        ->and($other->cemeteries()->count())->toBe(1)
        ->and($other->localityAliases()->pluck('name')->all())->toBe(['Bokinia'])
        ->and($other->events()->count())->toBe(0);

    expectOnlyAuthorRowsChanged(rowDiff($before, databaseRows()), $tree, $eventIds);
});

test('adds a new locality only when an item of the write uses it', function (string $choice, bool $created) {
    Storage::fake('local');
    $first = ['O-0001' => "imiona: Jan\nnazwisko: Nowak\nurodzenie:\n  data: \"1801\"\n  miejsce: Zalesie"];
    [$tree, $other] = applyPlaceTwins($first, function (Tree $tree, array $world): void {
        $tree->events()->where('repo_id', 'O-0001/urodzenie')->firstOrFail()->update(['locality_id' => $world['bukinia']->id]);
    });
    $plan = applyPlacePlan($tree, ['O-0001' => "imiona: Jan\nnazwisko: Nowak\nurodzenie:\n  data: \"1801\"\n  miejsce: Bindyga"], new PlaceDecisions(localities: ['bindyga' => PlaceDecisions::NEW]));
    $approvals = new ChangeApprovals;
    $resolutions = new ConflictResolutions;

    expect($plan->conflicts)->toHaveCount(1)
        ->and($plan->newLocalities)->toBe(['bindyga' => 'Bindyga']);

    if ($choice === 'app') {
        $resolutions = new ConflictResolutions(['O-0001/urodzenie/miejsce' => ConflictResolutions::FROM_APP]);
    } elseif ($choice === 'repo') {
        $resolutions = new ConflictResolutions(['O-0001/urodzenie/miejsce' => ConflictResolutions::FROM_REPO]);
    }

    $eventIds = $tree->events()->pluck('id')->all();
    $before = databaseRows();

    $result = app(ApplyImport::class)->handle($tree, $plan, $approvals, $resolutions);

    $birth = $tree->events()->where('repo_id', 'O-0001/urodzenie')->firstOrFail();
    $bindyga = $tree->localities()->where('name', 'Bindyga')->first();
    $memory = snapshotValues($tree)['O-0001/urodzenie'];

    expect($bindyga !== null)->toBe($created)
        ->and($result->newLocalities)->toBe($created ? 1 : 0)
        ->and($birth->locality_id)->toBe($created ? $bindyga->id : $tree->localities()->where('name', 'Bukinia')->firstOrFail()->id)
        ->and($memory['miejsce'])->toBe('Bindyga')
        ->and($memory['miejsce_id'])->toBe($created ? 'L:'.$bindyga->id : null)
        ->and($tree->localities()->count())->toBe($created ? 3 : 2)
        ->and($other->localities()->count())->toBe(2);

    expectOnlyAuthorRowsChanged(rowDiff($before, databaseRows()), $tree, $eventIds);
})->with([
    'conflict settled for the app' => ['app', false],
    'conflict without an answer' => ['none', false],
    'conflict settled for the repo' => ['repo', true],
]);

test('does not add a new locality whose only change the author rejected', function () {
    Storage::fake('local');
    $first = ['O-0001' => "imiona: Jan\nnazwisko: Nowak\nurodzenie:\n  data: \"1801\"\n  miejsce: Zalesie"];
    [$tree, $other] = applyPlaceTwins($first);
    $plan = applyPlacePlan($tree, ['O-0001' => "imiona: Jan\nnazwisko: Nowak\nurodzenie:\n  data: \"1801\"\n  miejsce: Bindyga"], new PlaceDecisions(localities: ['bindyga' => PlaceDecisions::NEW]));
    $localities = $tree->localities()->count();

    expect(collect($plan->changes)->pluck('key')->all())->toBe(['O-0001/urodzenie/miejsce']);

    $result = app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals(['O-0001/urodzenie/miejsce']), new ConflictResolutions);

    $birth = $tree->events()->where('repo_id', 'O-0001/urodzenie')->firstOrFail();

    expect($tree->localities()->count())->toBe($localities)
        ->and($result->newLocalities)->toBe(0)
        ->and($birth->locality_id)->toBe($tree->localities()->where('name', 'Zalesie')->firstOrFail()->id)
        ->and(snapshotValues($tree)['O-0001/urodzenie']['miejsce_id'])->toBeNull()
        ->and($other->localities()->count())->toBe($localities);
});

test('puts the locality on the wedding, the burial with its cemetery and locality, and adds the death the burial needs', function () {
    Storage::fake('local');
    [$tree, $other, $world] = applyPlaceTwins();
    $blocks = [
        'O-0001' => "imiona: Jan\nnazwisko: Nowak\nurodzenie:\n  data: \"1801\"\n  miejsce: Zalesie\npochowek:\n  data: \"1870\"\n  miejsce: Zalesie\nmalzenstwa:\n  - z: O-0002\n    data: \"1825\"\n    miejsce: Zalesie",
        'O-0002' => "imiona: Anna\nnazwisko: Wrona\nmalzenstwa:\n  - z: O-0001\n    data: \"1825\"\n    miejsce: Zalesie",
    ];
    $plan = applyPlacePlan($tree, $blocks);
    $eventIds = $tree->events()->pluck('id')->all();
    $before = databaseRows();

    $result = app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals, new ConflictResolutions);

    $birth = importedEvent($tree, 'O-0001/urodzenie');
    $death = importedEvent($tree, 'O-0001/zgon');
    $burial = importedEvent($tree, 'O-0001/pochowek');
    $wedding = importedEvent($tree, 'O-0001+O-0002/slub');

    expect($birth->locality_id)->toBe($world['zalesie']->id)
        ->and([$death->date_year, $death->locality_id])->toBe([null, null])
        ->and($death->participants()->pluck('people.id')->all())->toBe([importedPerson($tree, 'O-0001')->id])
        ->and($burial->type)->toBe(EventType::Burial)
        ->and([$burial->date_year, $burial->cemetery_id, $burial->locality_id])->toBe([1870, $world['cemetery']->id, $world['zalesie']->id])
        ->and($burial->participants()->pluck('people.id')->all())->toBe([importedPerson($tree, 'O-0001')->id])
        ->and($wedding->locality_id)->toBe($world['zalesie']->id)
        ->and(importedPerson($tree, 'O-0001')->note)->toBe("Z repo badawczego (O-0001):\nPochowanie: 1870")
        ->and([$result->burials, $result->deaths, $result->newLocalities, $result->newCemeteries])->toBe([1, 1, 0, 0])
        ->and($other->events()->count())->toBe(0);

    expectOnlyAuthorRowsChanged(rowDiff($before, databaseRows()), $tree, $eventIds);
});

test('leaves the locality of the event and the repo text in the note for a place the author answered with no place', function () {
    Storage::fake('local');
    $first = ['O-0001' => "imiona: Jan\nnazwisko: Nowak\nurodzenie:\n  data: \"1801\""];
    [$tree, $other] = applyPlaceTwins($first, function (Tree $tree, array $world): void {
        $tree->events()->where('repo_id', 'O-0001/urodzenie')->firstOrFail()->update(['locality_id' => $world['zalesie']->id]);
    });
    $plan = applyPlacePlan($tree, ['O-0001' => "imiona: Jan\nnazwisko: Nowak\nurodzenie:\n  data: \"1801\"\n  miejsce: Bindyga"], new PlaceDecisions(localities: ['bindyga' => PlaceDecisions::NONE]));
    $eventIds = $tree->events()->pluck('id')->all();
    $before = databaseRows();

    $result = app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals, new ConflictResolutions);

    expect($tree->events()->where('repo_id', 'O-0001/urodzenie')->firstOrFail()->locality_id)->toBe($tree->localities()->where('name', 'Zalesie')->firstOrFail()->id)
        ->and(importedPerson($tree, 'O-0001')->note)->toContain('Urodzenie: Bindyga')
        ->and($tree->localities()->count())->toBe(2)
        ->and($result->newLocalities)->toBe(0)
        ->and(snapshotValues($tree)['O-0001/urodzenie'])->toBe(['data' => '1801', 'miejsce' => 'Bindyga', 'miejsce_id' => null]);

    expectOnlyAuthorRowsChanged(rowDiff($before, databaseRows()), $tree, $eventIds);
});

test('creates no burial for a cemetery the author left out but adds the death and remembers the burial', function () {
    Storage::fake('local');
    [$tree, $other] = applyPlaceTwins();
    $plan = applyPlacePlan($tree, ['O-0001' => "imiona: Jan\nnazwisko: Nowak\npochowek:\n  data: \"1900\"\n  miejsce: Bindyga"], new PlaceDecisions(localities: ['bindyga' => PlaceDecisions::NONE]));

    $result = app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals, new ConflictResolutions);

    expect($tree->events()->pluck('type')->all())->toBe([EventType::Death])
        ->and($tree->cemeteries()->count())->toBe(1)
        ->and($result->burials)->toBe(0)
        ->and(snapshotValues($tree)['O-0001/pochowek'])->toBe(['data' => '1900', 'miejsce' => 'Bindyga', 'cmentarz' => null, 'miejsce_id' => null, 'cmentarz_id' => null])
        ->and(importedPerson($tree, 'O-0001')->note)->toContain('Pochowanie: 1900; Bindyga')
        ->and($other->events()->count())->toBe(0);
});

test('refuses a plan whose place changed since planning and writes nothing', function (Closure $change, string $message) {
    Storage::fake('local');
    [$tree, $other, $world] = applyPlaceTwins();
    $blocks = ['O-0001' => "imiona: Jan\nnazwisko: Nowak\nurodzenie:\n  data: \"1801\"\n  miejsce: Zalesie\nzgon:\n  data: \"1870\"\n  miejsce: Kołemczyce"];
    $plan = applyPlacePlan($tree, $blocks, new PlaceDecisions(localities: ['kolemczyce' => PlaceDecisions::NEW]));
    $change($tree, $world);
    $before = ownershipSnapshot();

    expect(fn () => app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals, new ConflictResolutions))->toThrow(RuntimeException::class, $message)
        ->and(ownershipSnapshot())->toBe($before)
        ->and($other->localities()->count())->toBe(2);
})->with([
    'a locality of the plan deleted' => [fn (Tree $tree, array $world) => $world['zalesie']->cemeteries()->delete() + $world['zalesie']->delete(), 'Miejsce Zalesie zmieniło się po planowaniu'],
    'the new locality added by hand' => [fn (Tree $tree) => Locality::factory()->for($tree)->create(['name' => 'Kołemczyce']), 'Miejsce Kołemczyce zmieniło się po planowaniu'],
    'the new locality added without diacritics' => [fn (Tree $tree) => Locality::factory()->for($tree)->create(['name' => 'Kolemczyce']), 'Miejsce Kołemczyce zmieniło się po planowaniu'],
    'the new locality taken by a variant' => [fn (Tree $tree, array $world) => $world['bukinia']->aliases()->create(['tree_id' => $tree->id, 'name' => 'KOŁEMCZYCE']), 'Miejsce Kołemczyce zmieniło się po planowaniu'],
]);

test('leaves no new locality, cemetery or variant when a step in the middle fails', function () {
    Storage::fake('local');
    [$tree, $other, $world] = applyPlaceTwins();
    $blocks = [
        'O-0001' => "imiona: Jan\nnazwisko: Nowak\nurodzenie:\n  data: \"1801\"\n  miejsce: Bokinia\npochowek:\n  data: \"1870\"\n  miejsce: Bindyga\nmalzenstwa:\n  - z: O-0002\n    data: \"1825\"",
        'O-0002' => "imiona: Anna\nnazwisko: Wrona\nmalzenstwa:\n  - z: O-0001\n    data: \"1825\"",
    ];
    $plan = applyPlacePlan($tree, $blocks, new PlaceDecisions(
        localities: ['bokinia' => $world['bukinia']->id, 'bindyga' => PlaceDecisions::NEW],
        aliases: ['bokinia' => true],
    ));
    $before = ownershipSnapshot();

    expect($plan->aliases)->toHaveCount(1)
        ->and($plan->newCemeteries)->toHaveCount(1);

    $this->mock(CreateMarriage::class, function ($mock): void {
        $mock->shouldReceive('handle')->andThrow(ValidationException::withMessages(['spouse_id' => 'Ta para ma już małżeństwo.']));
    });

    expect(fn () => app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals, new ConflictResolutions))->toThrow(ValidationException::class)
        ->and(ownershipSnapshot())->toBe($before)
        ->and($tree->localities()->count())->toBe(2)
        ->and($tree->cemeteries()->count())->toBe(1)
        ->and($tree->localityAliases()->count())->toBe(0)
        ->and($tree->events()->count())->toBe(0);
});

test('does not duplicate places when the same plan is written a second time', function () {
    Storage::fake('local');
    [$tree, $other, $world] = applyPlaceTwins();
    $plan = applyPlacePlan($tree, ['O-0001' => APPLY_PLACE_PERSON."\npochowek:\n  miejsce: Bindyga\n  cmentarz: Parafialny"], new PlaceDecisions(
        localities: ['bokinia' => $world['bukinia']->id, 'bindyga' => PlaceDecisions::NEW],
        aliases: ['bokinia' => true],
    ));

    app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals, new ConflictResolutions);
    $after = ownershipSnapshot();

    expect(fn () => app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals, new ConflictResolutions))->toThrow(RuntimeException::class)
        ->and(ownershipSnapshot())->toBe($after)
        ->and($tree->localities()->count())->toBe(3)
        ->and($tree->cemeteries()->count())->toBe(2)
        ->and($tree->localityAliases()->count())->toBe(1);
});

const APPLY_PLACE_BURIED = "imiona: Jan\nnazwisko: Nowak\nzgon:\n  data: \"1870\"\npochowek:\n  data: \"1870\"\n  miejsce: Zalesie";

test('removes a burial the repo dropped and keeps the death and the burials of the other account', function () {
    Storage::fake('local');
    [$tree, $other, $world] = applyPlaceTwins(['O-0001' => APPLY_PLACE_BURIED]);

    expect($tree->events()->where('type', EventType::Burial)->firstOrFail()->cemetery_id)->toBe($world['cemetery']->id);

    $plan = applyPlacePlan($tree, ['O-0001' => "imiona: Jan\nnazwisko: Nowak\nzgon:\n  data: \"1870\""], removals: ['O-0001/pochowek' => 'delete']);
    $result = app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals, new ConflictResolutions);

    expect($tree->events()->where('type', EventType::Burial)->count())->toBe(0)
        ->and($tree->events()->where('type', EventType::Death)->count())->toBe(1)
        ->and($result->removedRecords)->toBe(1)
        ->and($other->events()->where('type', EventType::Burial)->count())->toBe(1);
});

test('brings back a burial deleted in the app as a burial with its cemetery and locality', function () {
    Storage::fake('local');
    [$tree, $other, $world] = applyPlaceTwins(['O-0001' => APPLY_PLACE_BURIED], fn (Tree $tree) => $tree->events()->where('type', EventType::Burial)->firstOrFail()->delete());
    $plan = applyPlacePlan($tree, ['O-0001' => APPLY_PLACE_BURIED], deletions: ['O-0001/pochowek' => 'restore']);

    $result = app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals, new ConflictResolutions);

    $burial = $tree->events()->where('type', EventType::Burial)->firstOrFail();

    expect([$burial->repo_id, $burial->cemetery_id, $burial->locality_id])->toBe(['O-0001/pochowek', $world['cemetery']->id, $world['zalesie']->id])
        ->and($tree->events()->where('type', EventType::Death)->count())->toBe(1)
        ->and([$result->restored, $result->burials])->toBe([1, 1])
        ->and($other->events()->where('type', EventType::Burial)->count())->toBe(0);
});
