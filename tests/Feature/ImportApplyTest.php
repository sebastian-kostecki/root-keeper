<?php

use App\Actions\Import\ApplyImport;
use App\Actions\Import\ClassifyRepoRecords;
use App\Actions\Import\PlanImport;
use App\Actions\Import\ReadResearchRepo;
use App\Actions\Relations\CreateMarriage;
use App\Actions\Relations\CreateParentChildRelation;
use App\Enums\DateQualifier;
use App\Enums\EventType;
use App\Enums\Gender;
use App\Enums\MarriageForm;
use App\Enums\ParticipantRole;
use App\Models\Event;
use App\Models\Locality;
use App\Models\ParentChildRelation;
use App\Models\Person;
use App\Models\RepoSnapshot;
use App\Models\Residence;
use App\Models\Source;
use App\Models\Tree;
use App\Models\User;
use App\Support\Import\ConflictResolutions;
use App\Support\Import\IdentityDecisions;
use App\Support\Import\ImportDecisions;
use App\Support\Import\ImportPlan;
use App\Support\Import\ImportResult;
use App\Support\Import\PlannedMatch;
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

    $result = app(ApplyImport::class)->handle($tree, $plan, new ConflictResolutions);

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

    $result = app(ApplyImport::class)->handle($tree, planFixtureForApply($tree), new ConflictResolutions);

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

    app(ApplyImport::class)->handle($tree, planFixtureForApply($tree), new ConflictResolutions);

    $wedding = importedEvent($tree, 'O-0001+O-0004/slub');

    expect($wedding->date_qualifier)->toBe(DateQualifier::Approximate)
        ->and($wedding->date_year)->toBe(1825);
});

test('writes relations with repo id and skips the one with a conflicting gender', function () {
    $tree = arrangeApplyUsers();

    $result = app(ApplyImport::class)->handle($tree, planFixtureForApply($tree), new ConflictResolutions);

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

    $result = app(ApplyImport::class)->handle($tree, planFixtureForApply($tree), new ConflictResolutions);

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

    app(ApplyImport::class)->handle($tree, planFixtureForApply($tree), new ConflictResolutions);

    expect($tree->fresh()->root_person_id)->toBeNull();
});

test('writes only into the author tree and leaves the other account untouched', function () {
    Storage::fake('local');
    $tree = arrangeApplyUsers();
    $plan = planFixtureForApply($tree);
    $before = databaseRows();

    app(ApplyImport::class)->handle($tree, $plan, new ConflictResolutions);

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

test('refuses a tree that already holds a person from an import', function () {
    $tree = arrangeApplyUsers();
    Person::factory()->for($tree)->create(['repo_id' => 'O-0001']);
    $plan = planFixtureForApply($tree);
    $before = ownershipSnapshot();

    expect(fn () => app(ApplyImport::class)->handle($tree, $plan, new ConflictResolutions))->toThrow(RuntimeException::class, 'Drzewo konta ma już osoby z importu')
        ->and(ownershipSnapshot())->toBe($before);
});

test('writes into a tree that got a hand-entered person after planning', function () {
    $tree = arrangeApplyUsers();
    $plan = planFixtureForApply($tree);

    $added = Person::factory()->for($tree)->create(['first_name' => 'Dodana', 'last_name' => 'Ręcznie']);

    app(ApplyImport::class)->handle($tree, $plan, new ConflictResolutions);

    expect($tree->people()->count())->toBe(14)
        ->and($added->fresh()->repo_id)->toBeNull()
        ->and($tree->people()->whereNotNull('repo_id')->count())->toBe(13);
});

test('refuses a tree that got a person from an import after planning and writes nothing', function () {
    $tree = arrangeApplyUsers();
    $plan = planFixtureForApply($tree);

    Person::factory()->for($tree)->create(['first_name' => 'Dodana', 'last_name' => 'Z importu', 'repo_id' => 'O-0900']);
    $before = ownershipSnapshot();

    expect(fn () => app(ApplyImport::class)->handle($tree, $plan, new ConflictResolutions))->toThrow(RuntimeException::class, 'Drzewo konta ma już osoby z importu')
        ->and(ownershipSnapshot())->toBe($before);
});

test('rolls everything back when a step in the middle fails', function () {
    $tree = arrangeApplyUsers();
    $plan = planFixtureForApply($tree);
    $before = ownershipSnapshot();

    $this->mock(CreateMarriage::class, function ($mock): void {
        $mock->shouldReceive('handle')->andThrow(ValidationException::withMessages(['spouse_id' => 'Ta para ma już małżeństwo.']));
    });

    expect(fn () => app(ApplyImport::class)->handle($tree, $plan, new ConflictResolutions))->toThrow(ValidationException::class)
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

test('hooks the repo into a hand-entered person and keeps what the app holds', function () {
    Storage::fake('local');
    [$tree, $scene] = arrangeHandEnteredAuthor();
    $plan = planFixtureWithMatches($tree, $scene);
    $note = collect($plan->matched)->first(fn ($match) => $match->person->id === 'O-0001')->note;

    $result = app(ApplyImport::class)->handle($tree, $plan, new ConflictResolutions);

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

    app(ApplyImport::class)->handle($tree, planFixtureWithMatches($tree, $scene), new ConflictResolutions);

    expect($bystander->fresh()->repo_id)->toBeNull()
        ->and($tree->people()->whereNotNull('repo_id')->count())->toBe(13)
        ->and($tree->people()->whereNull('repo_id')->count())->toBe(1);
});

test('keeps the value from the app for a conflict without an answer and for one settled for the app', function (array $choices) {
    [$tree, $scene] = arrangeHandEnteredAuthor();
    $plan = planFixtureWithMatches($tree, $scene);
    expect(array_map(fn ($conflict) => $conflict->key, $plan->conflicts))->toBe(['O-0002/urodzenie/data', 'O-0004/plec', 'O-0001+O-0004/slub/data']);

    $result = app(ApplyImport::class)->handle($tree, $plan, new ConflictResolutions($choices));

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

    $result = app(ApplyImport::class)->handle($tree, $plan, $resolutions);

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

    $result = app(ApplyImport::class)->handle($tree, planFixtureWithMatches($tree, $scene), new ConflictResolutions);

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

    $result = app(ApplyImport::class)->handle($tree, planFixtureWithMatches($tree, $scene), new ConflictResolutions);

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

    app(ApplyImport::class)->handle($author->tree, $plan, new ConflictResolutions);

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

    app(ApplyImport::class)->handle($tree, $plan, $resolutions);

    $diff = rowDiff($before, databaseRows());
    $authorPeople = $tree->people()->pluck('id')->all();
    $authorEvents = $tree->events()->pluck('id')->all();

    expect(array_keys($diff['tables']))->toEqualCanonicalizing(['people', 'events', 'event_participants', 'parent_child_relations', 'repo_snapshots'])
        ->and($diff['files'])->toBe(['removed' => [], 'added' => []]);

    foreach ($diff['tables'] as $table => $changes) {
        expect($changes['removed'])->toBe([]);

        if ($table === 'event_participants') {
            expect($changes['changed'])->toBe([])
                ->and(collect($changes['added'])->pluck('person_id')->diff($authorPeople)->all())->toBe([])
                ->and(collect($changes['added'])->pluck('event_id')->diff($authorEvents)->all())->toBe([]);

            continue;
        }

        if ($table === 'repo_snapshots') {
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

    expect(fn () => app(ApplyImport::class)->handle($author->tree, $foreignPlan, new ConflictResolutions))->toThrow(RuntimeException::class, 'nie ma już w drzewie konta')
        ->and(ownershipSnapshot())->toBe($before);
});

test('refuses a matched person who was deleted after planning and writes nothing', function () {
    Storage::fake('local');
    [$tree, $scene] = arrangeHandEnteredAuthor();
    $plan = planFixtureWithMatches($tree, $scene);
    $scene['anna']->delete();
    $before = ownershipSnapshot();

    expect(fn () => app(ApplyImport::class)->handle($tree, $plan, new ConflictResolutions))->toThrow(RuntimeException::class, 'nie ma już w drzewie konta')
        ->and(ownershipSnapshot())->toBe($before);
});

test('refuses a matched person who got a repo id after planning and writes nothing', function () {
    Storage::fake('local');
    [$tree, $scene] = arrangeHandEnteredAuthor();
    $plan = planFixtureWithMatches($tree, $scene);
    $scene['jan']->update(['repo_id' => 'O-0900']);
    $before = ownershipSnapshot();

    expect(fn () => app(ApplyImport::class)->handle($tree, $plan, new ConflictResolutions))->toThrow(RuntimeException::class)
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

    expect(fn () => app(ApplyImport::class)->handle($author->tree, $plan, new ConflictResolutions))->toThrow(RuntimeException::class, "O-0001 (#{$maria->id}): ")
        ->and(fn () => app(ApplyImport::class)->handle($author->tree, $plan, new ConflictResolutions))->toThrow(RuntimeException::class, 'matkę')
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
        app(ApplyImport::class)->handle($tree, $plan, new ConflictResolutions);
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

    expect(fn () => app(ApplyImport::class)->handle($tree, $plan, new ConflictResolutions))->toThrow(RuntimeException::class, "Osoba Jan Nowak (#{$scene['jan']->id}) zmieniła się po planowaniu")
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

    expect(fn () => app(ApplyImport::class)->handle($author->tree, $plan, new ConflictResolutions))->toThrow(RuntimeException::class, 'Małżeństwo O-0001+O-0002/slub zmieniło się po planowaniu')
        ->and(ownershipSnapshot())->toBe($before);
});

test('refuses a value taken from the repo when the app value changed since planning and writes nothing', function (string $key, Closure $change) {
    Storage::fake('local');
    [$tree, $scene] = arrangeHandEnteredAuthor();
    $plan = planFixtureWithMatches($tree, $scene);
    expect(array_map(fn ($conflict) => $conflict->key, $plan->conflicts))->toContain($key);

    $change($scene);
    $before = ownershipSnapshot();

    expect(fn () => app(ApplyImport::class)->handle($tree, $plan, new ConflictResolutions([$key => ConflictResolutions::FROM_REPO])))->toThrow(RuntimeException::class, 'po planowaniu; nic nie zostało zapisane')
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

    $result = app(ApplyImport::class)->handle($tree, $plan, new ConflictResolutions(['O-0004/plec' => ConflictResolutions::FROM_APP]));

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

    expect(fn () => app(ApplyImport::class)->handle($tree, $broken, new ConflictResolutions))->toThrow(RuntimeException::class, 'kilku osób z repo')
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

    expect(fn () => app(ApplyImport::class)->handle($tree, $plan, new ConflictResolutions(['O-0004/plec' => 'repo'])))->toThrow(ValidationException::class)
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

    app(ApplyImport::class)->handle($tree, $plan, new ConflictResolutions);

    $written = collect([
        ...$tree->people()->pluck('repo_id'),
        ...$tree->events()->whereNotNull('repo_id')->pluck('repo_id'),
        ...$tree->parentChildRelations()->pluck('repo_id'),
    ])->sort()->values()->all();
    $snapshots = snapshotValues($tree);
    $note = collect($plan->persons)->first(fn ($planned) => $planned->person->id === 'O-0001')->note;

    expect(array_keys($snapshots))->toBe($written)
        ->and($tree->repoSnapshots()->where('deletion_kept', true)->count())->toBe(0)
        ->and($snapshots['O-0001'])->toEqual(['imiona' => 'Jan', 'nazwisko' => 'Nowak', 'plec' => 'male', 'notatka' => $note])
        ->and($snapshots['O-0008']['plec'])->toBeNull()
        ->and($snapshots['O-0001/urodzenie'])->toBe(['data' => '1801-05-12'])
        ->and($snapshots['O-0002/urodzenie'])->toBe(['data' => 'między 1760 a 1765'])
        ->and($snapshots['O-0001/zgon'])->toBe(['data' => '1870'])
        ->and($snapshots['O-0002/zgon'])->toBe(['data' => null])
        ->and($snapshots['O-0002>O-0001'])->toBe([])
        ->and($snapshots['O-0001+O-0004/slub'])->toBe(['data' => 'ok. 1825'])
        ->and(RepoSnapshot::where('tree_id', '!=', $tree->id)->count())->toBe(0);
});

test('remembers the repo value and keeps the app value when the author leaves a conflict for the app', function () {
    [$tree, $scene] = arrangeHandEnteredAuthor();
    $plan = planFixtureWithMatches($tree, $scene);

    app(ApplyImport::class)->handle($tree, $plan, new ConflictResolutions([
        'O-0004/plec' => ConflictResolutions::FROM_APP,
        'O-0002/urodzenie/data' => ConflictResolutions::FROM_APP,
    ]));

    $snapshots = snapshotValues($tree);

    expect($scene['anna']->fresh()->gender)->toBe(Gender::Male)
        ->and($snapshots['O-0004']['plec'])->toBe('female')
        ->and($scene['piotr']->birth()->date_year)->toBe(1762)
        ->and($snapshots['O-0002/urodzenie'])->toBe(['data' => 'między 1760 a 1765'])
        ->and(RepoSnapshot::where('tree_id', '!=', $tree->id)->count())->toBe(0);
});

test('leaves no memory of the import when the write rolls back', function () {
    $tree = arrangeApplyUsers();
    $plan = planFixtureForApply($tree);

    $this->mock(CreateMarriage::class, function ($mock): void {
        $mock->shouldReceive('handle')->andThrow(ValidationException::withMessages(['spouse_id' => 'Ta para ma już małżeństwo.']));
    });

    expect(fn () => app(ApplyImport::class)->handle($tree, $plan, new ConflictResolutions))->toThrow(ValidationException::class)
        ->and(RepoSnapshot::count())->toBe(0);
});
