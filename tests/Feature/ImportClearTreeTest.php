<?php

use App\Actions\Import\ClearTree;
use App\Actions\Import\PlanTreeClearance;
use App\Models\Cemetery;
use App\Models\Event;
use App\Models\Locality;
use App\Models\ParentChildRelation;
use App\Models\Parish;
use App\Models\Person;
use App\Models\PersonSurnameVariant;
use App\Models\RepoSnapshot;
use App\Models\Residence;
use App\Models\Source;
use App\Models\Tree;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * A tree with an imported person (photo, surname variant, residence, birth fact with a source), an imported
 * relation and wedding, a manual person and fact without a repo ID, two sources (one imported, one manual) with
 * scans, a gravestone list, places, and import memory of every kind (some of it marked as kept deleted).
 *
 * @return array{tree: Tree, root: Person, sources: array{imported: Source, manual: Source}, locality: Locality, parish: Parish, cemetery: Cemetery}
 */
function arrangeClearableTree(User $user): array
{
    $tree = $user->tree;

    $locality = Locality::factory()->for($tree)->create(['name' => 'Lipnica']);
    $parish = Parish::factory()->for($tree)->create(['locality_id' => $locality->id]);
    $cemetery = Cemetery::factory()->for($tree)->create(['locality_id' => $locality->id]);

    $jan = Person::factory()->for($tree)->create([
        'first_name' => 'Jan', 'last_name' => 'Nowak', 'repo_id' => 'O-0001', 'photo_path' => "persons/{$tree->id}/jan.jpg",
    ]);
    $anna = Person::factory()->for($tree)->create(['first_name' => 'Anna', 'last_name' => 'Wrona', 'repo_id' => 'O-0002']);
    $manual = Person::factory()->for($tree)->create(['first_name' => 'Ręczny', 'last_name' => 'Wpis']);
    $tree->update(['root_person_id' => $jan->id]);

    PersonSurnameVariant::factory()->create(['person_id' => $jan->id]);
    Residence::factory()->create(['person_id' => $jan->id, 'locality_id' => $locality->id]);
    ParentChildRelation::factory()->create(['tree_id' => $tree->id, 'parent_id' => $jan->id, 'child_id' => $anna->id]);

    $birth = Event::factory()->for($tree)->birth()->create(['repo_id' => 'O-0001/urodzenie']);
    $birth->participants()->attach($jan, ['role' => 'subject']);
    $wedding = attachMarriage($jan, $anna);
    $wedding->update(['repo_id' => 'O-0001+O-0002/slub']);
    $manualFact = Event::factory()->for($tree)->death()->create();
    $manualFact->participants()->attach($manual, ['role' => 'subject']);

    $imported = Source::factory()->for($tree)->create(['repo_id' => 'ZR-0001']);
    $manualSource = Source::factory()->for($tree)->create();
    $birth->sources()->attach($imported->id);
    $manualFact->sources()->attach($manualSource->id);
    $imported->buriedPeople()->attach($jan);
    $manualSource->buriedPeople()->attach($manual);

    foreach (['O-0001', 'O-0001/urodzenie', 'O-0001>O-0002', 'O-0001+O-0002/slub'] as $key) {
        RepoSnapshot::create(['tree_id' => $tree->id, 'repo_id' => $key, 'values' => ['x' => '1'], 'deletion_kept' => false]);
    }
    RepoSnapshot::create(['tree_id' => $tree->id, 'repo_id' => 'O-0003', 'values' => ['x' => '1'], 'deletion_kept' => true]);
    RepoSnapshot::create(['tree_id' => $tree->id, 'repo_id' => 'ZR-0001', 'values' => ['x' => '1'], 'deletion_kept' => false]);

    foreach ([$jan->photo_path, "persons/{$tree->id}/thumbnails/jan.jpg", $imported->path, $manualSource->path] as $path) {
        Storage::disk('local')->put($path, 'x');
    }

    return ['tree' => $tree, 'root' => $jan, 'sources' => ['imported' => $imported, 'manual' => $manualSource], 'locality' => $locality, 'parish' => $parish, 'cemetery' => $cemetery];
}

/**
 * The other account has the same names, the same place name and the same repo keys.
 *
 * @return array{tree: Tree, root: Person, sources: array{imported: Source, manual: Source}, locality: Locality, parish: Parish, cemetery: Cemetery}
 */
function arrangeOverlappingClearableTree(): array
{
    return arrangeClearableTree(User::factory()->create(['email' => 'inny@example.com']));
}

beforeEach(function () {
    Storage::fake('local');
});

test('plans the counts of what clearing removes without writing anything', function () {
    $author = arrangeClearableTree(User::factory()->create(['email' => 'autor@example.com']));
    arrangeOverlappingClearableTree();
    $before = ownershipSnapshot();

    $clearance = app(PlanTreeClearance::class)->handle($author['tree']);

    expect($clearance->persons)->toBe(3)
        ->and($clearance->events)->toBe(3)
        ->and($clearance->relations)->toBe(1)
        ->and($clearance->residences)->toBe(1)
        ->and($clearance->photos)->toBe(1)
        ->and($clearance->sourceLinks)->toBe(2)
        ->and($clearance->buriedLinks)->toBe(2)
        ->and($clearance->snapshots)->toBe(5)
        ->and($clearance->hasRecords())->toBeTrue()
        ->and(ownershipSnapshot())->toBe($before);
});

test('a tree without persons and facts has nothing to clear', function () {
    $user = User::factory()->create();
    RepoSnapshot::create(['tree_id' => $user->tree->id, 'repo_id' => 'O-0009', 'values' => [], 'deletion_kept' => true]);

    $clearance = app(PlanTreeClearance::class)->handle($user->tree);

    expect($clearance->hasRecords())->toBeFalse()
        ->and($clearance->snapshots)->toBe(1);
});

test('clearing removes the persons, facts and import memory of people and facts of the tree', function () {
    $author = arrangeClearableTree(User::factory()->create(['email' => 'autor@example.com']));
    $tree = $author['tree'];
    $expected = app(PlanTreeClearance::class)->handle($tree);

    $clearance = app(ClearTree::class)->handle($tree);

    expect($clearance)->toEqual($expected)
        ->and($tree->people()->count())->toBe(0)
        ->and($tree->events()->count())->toBe(0)
        ->and($tree->parentChildRelations()->count())->toBe(0)
        ->and(Residence::query()->where('tree_id', $tree->id)->count())->toBe(0)
        ->and(PersonSurnameVariant::query()->where('tree_id', $tree->id)->count())->toBe(0)
        ->and(DB::table('event_source')->count())->toBe(0)
        ->and(DB::table('event_participants')->count())->toBe(0)
        ->and(DB::table('buried_people')->count())->toBe(0)
        ->and($tree->repoSnapshots()->pluck('repo_id')->all())->toBe(['ZR-0001'])
        ->and($tree->fresh()->root_person_id)->toBeNull()
        ->and(Storage::disk('local')->exists("persons/{$tree->id}/jan.jpg"))->toBeFalse()
        ->and(Storage::disk('local')->exists("persons/{$tree->id}/thumbnails/jan.jpg"))->toBeFalse();
});

test('clearing keeps sources with their scans, source memory and places', function () {
    $author = arrangeClearableTree(User::factory()->create(['email' => 'autor@example.com']));

    app(ClearTree::class)->handle($author['tree']);

    expect($author['tree']->sources()->count())->toBe(2)
        ->and(Storage::disk('local')->exists($author['sources']['imported']->path))->toBeTrue()
        ->and(Storage::disk('local')->exists($author['sources']['manual']->path))->toBeTrue()
        ->and($author['tree']->repoSnapshots()->where('repo_id', 'ZR-0001')->exists())->toBeTrue()
        ->and($author['tree']->localities()->count())->toBe(1)
        ->and($author['tree']->parishes()->count())->toBe(1)
        ->and($author['tree']->cemeteries()->count())->toBe(1);
});

test('clearing leaves the other account untouched', function () {
    $author = arrangeClearableTree(User::factory()->create(['email' => 'autor@example.com']));
    $other = arrangeOverlappingClearableTree();
    $otherTree = $other['tree'];
    $before = [
        'people' => $otherTree->people()->orderBy('id')->get()->toArray(),
        'events' => $otherTree->events()->orderBy('id')->get()->toArray(),
        'relations' => $otherTree->parentChildRelations()->count(),
        'snapshots' => $otherTree->repoSnapshots()->orderBy('id')->get()->toArray(),
        'files' => Storage::disk('local')->allFiles("persons/{$otherTree->id}"),
        'buried' => DB::table('buried_people')->whereIn('person_id', $otherTree->people()->select('id'))->count(),
        'root' => $otherTree->fresh()->root_person_id,
    ];

    app(ClearTree::class)->handle($author['tree']);

    expect($otherTree->people()->orderBy('id')->get()->toArray())->toBe($before['people'])
        ->and($otherTree->events()->orderBy('id')->get()->toArray())->toBe($before['events'])
        ->and($otherTree->parentChildRelations()->count())->toBe($before['relations'])
        ->and($otherTree->repoSnapshots()->orderBy('id')->get()->toArray())->toBe($before['snapshots'])
        ->and(Storage::disk('local')->allFiles("persons/{$otherTree->id}"))->toBe($before['files'])
        ->and($before['files'])->not->toBeEmpty()
        ->and(DB::table('buried_people')->whereIn('person_id', $otherTree->people()->select('id'))->count())->toBe($before['buried'])
        ->and($otherTree->fresh()->root_person_id)->toBe($before['root']);
});

test('a rolled back transaction keeps the rows and the photo files', function () {
    $author = arrangeClearableTree(User::factory()->create(['email' => 'autor@example.com']));
    $tree = $author['tree'];

    try {
        DB::transaction(function () use ($tree): void {
            app(ClearTree::class)->handle($tree);

            throw new RuntimeException('later step failed');
        });
    } catch (RuntimeException) {
    }

    expect($tree->people()->count())->toBe(3)
        ->and($tree->events()->count())->toBe(3)
        ->and($tree->repoSnapshots()->count())->toBe(6)
        ->and(Storage::disk('local')->exists("persons/{$tree->id}/jan.jpg"))->toBeTrue()
        ->and(Storage::disk('local')->exists("persons/{$tree->id}/thumbnails/jan.jpg"))->toBeTrue();
});
