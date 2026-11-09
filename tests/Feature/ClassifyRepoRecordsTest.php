<?php

use App\Actions\Import\ClassifyRepoRecords;
use App\Actions\Import\ReadResearchRepo;
use App\Enums\EventType;
use App\Enums\ParticipantRole;
use App\Enums\RepoKeyKind;
use App\Models\Event;
use App\Models\ParentChildRelation;
use App\Models\Person;
use App\Models\Residence;
use App\Models\Source;
use App\Models\Tree;
use App\Models\User;
use App\Support\Import\RepoRecord;
use App\Support\Import\RepoRecordClasses;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Builds the same records and memory in the tree of another account and then in the tree of the author, and returns
 * the author's tree with what was built there.
 *
 * @param  Closure(Tree): mixed  $arrange
 * @return array{0: Tree, 1: mixed}
 */
function arrangeTwinTrees(Closure $arrange): array
{
    $other = User::factory()->create();
    $author = User::factory()->create();

    $arrange($other->tree);

    return [$author->tree, $arrange($author->tree)];
}

/**
 * Reads a throwaway repo and classifies it against the tree, then checks that nothing of another tree is in the result.
 *
 * @param  array<string, string>  $blocks  repo id => block body without id and version
 * @param  array<string, string>  $files  further files, file name => contents
 */
function classifyTemporaryRepo(Tree $tree, array $blocks, array $files = []): RepoRecordClasses
{
    $repo = sys_get_temp_dir().'/classify-repo-'.uniqid();
    mkdir($repo.'/drzewo', 0777, true);

    foreach ($blocks as $id => $body) {
        file_put_contents("{$repo}/drzewo/".strtolower($id).'.md', "---\nwersja: 1\nid: {$id}\n{$body}\n---\n");
    }

    foreach ($files as $name => $contents) {
        file_put_contents("{$repo}/drzewo/{$name}", $contents);
    }

    try {
        $reading = app(ReadResearchRepo::class)->handle($repo);
    } finally {
        File::deleteDirectory($repo);
    }

    $classes = app(ClassifyRepoRecords::class)->handle($tree, $reading);

    foreach ($classes->knownPersons as $person) {
        expect($person->tree_id)->toBe($tree->id);
    }

    foreach ([...$classes->deletedInApp, ...$classes->removedInRepo, ...$classes->unassessable] as $entry) {
        foreach ([$entry, ...$entry->members] as $each) {
            expect($each->record?->tree_id ?? $tree->id)->toBe($tree->id)
                ->and($each->snapshot?->tree_id ?? $tree->id)->toBe($tree->id);
        }
    }

    return $classes;
}

function recordedPerson(Tree $tree, string $repoId, ?string $first = 'Jan', array $attributes = []): Person
{
    return Person::factory()->for($tree)->create(['first_name' => $first, 'last_name' => 'Nowak', 'repo_id' => $repoId, ...$attributes]);
}

function recordedEvent(Person $person, EventType $type, ?string $repoId): Event
{
    $event = Event::factory()->for($person->tree)->create(['type' => $type, 'repo_id' => $repoId]);
    $event->participants()->attach($person, ['role' => ParticipantRole::Subject]);

    return $event;
}

function recordedRelation(Person $parent, Person $child, ?string $repoId): ParentChildRelation
{
    return ParentChildRelation::factory()->create(['tree_id' => $parent->tree_id, 'parent_id' => $parent->id, 'child_id' => $child->id, 'repo_id' => $repoId]);
}

/**
 * @param  array<string, string|null>  $values
 */
function rememberRepoValues(Tree $tree, string $key, array $values = [], bool $deletionKept = false): void
{
    $tree->repoSnapshots()->create(['repo_id' => $key, 'values' => $values, 'deletion_kept' => $deletionKept]);
}

/**
 * @param  list<RepoRecord>  $records
 * @return list<string>
 */
function repoKeysOf(array $records): array
{
    return array_map(fn (RepoRecord $record): string => $record->key, $records);
}

/**
 * @return list<string>
 */
function memberRepoKeysOf(RepoRecord $record): array
{
    $keys = repoKeysOf($record->members);
    sort($keys);

    return $keys;
}

const CLASSIFY_JAN_VALUES = ['imiona' => 'Jan', 'nazwisko' => null, 'plec' => null, 'notatka' => null];

test('sorts a person with a record as known and a person without a record or memory as new', function () {
    [$tree] = arrangeTwinTrees(function (Tree $tree) {
        recordedPerson($tree, 'O-0001');
        recordedPerson($tree, 'O-0009');
    });

    $classes = classifyTemporaryRepo($tree, ['O-0001' => 'imiona: Jan', 'O-0002' => 'imiona: Anna']);

    expect(array_keys($classes->knownPersons))->toBe(['O-0001'])
        ->and($classes->newPersonIds)->toBe(['O-0002'])
        ->and(repoKeysOf($classes->removedInRepo))->toBe(['O-0009'])
        ->and($classes->deletedInApp)->toBe([])
        ->and($classes->keptDeleted)->toBe([])
        ->and($classes->goneBoth)->toBe([])
        ->and($classes->unassessable)->toBe([]);
});

test('treats a record with a repo id and no memory as known', function () {
    [$tree] = arrangeTwinTrees(function (Tree $tree) {
        $person = recordedPerson($tree, 'O-0001');
        recordedEvent($person, EventType::Birth, 'O-0001/urodzenie');
    });

    $classes = classifyTemporaryRepo($tree, ['O-0001' => "imiona: Jan\nurodzenie:\n  data: \"1801\""]);

    expect(array_keys($classes->knownPersons))->toBe(['O-0001'])
        ->and($classes->deletedInApp)->toBe([])
        ->and($classes->removedInRepo)->toBe([]);
});

test('asks about a death deleted in the app that the repo still has', function () {
    [$tree] = arrangeTwinTrees(function (Tree $tree) {
        recordedPerson($tree, 'O-0001');
        rememberRepoValues($tree, 'O-0001', CLASSIFY_JAN_VALUES);
        rememberRepoValues($tree, 'O-0001/zgon', ['data' => '1870']);
    });

    $classes = classifyTemporaryRepo($tree, ['O-0001' => "imiona: Jan\nzgon:\n  data: \"1870\""]);

    expect(repoKeysOf($classes->deletedInApp))->toBe(['O-0001/zgon'])
        ->and($classes->deletedInApp[0]->kind)->toBe(RepoKeyKind::Death)
        ->and($classes->deletedInApp[0]->record)->toBeNull()
        ->and($classes->deletedInApp[0]->snapshot->values)->toBe(['data' => '1870'])
        ->and($classes->deletedInApp[0]->personIds)->toBe(['O-0001'])
        ->and($classes->keptDeleted)->toBe([]);
});

test('lets a death stay deleted when the author chose so and the repo did not change it', function () {
    [$tree] = arrangeTwinTrees(function (Tree $tree) {
        recordedPerson($tree, 'O-0001');
        rememberRepoValues($tree, 'O-0001', CLASSIFY_JAN_VALUES);
        rememberRepoValues($tree, 'O-0001/zgon', ['data' => '1870'], deletionKept: true);
    });

    $classes = classifyTemporaryRepo($tree, ['O-0001' => "imiona: Jan\nzgon:\n  data: \"1870\""]);

    expect($classes->keptDeleted)->toBe(['O-0001/zgon'])
        ->and($classes->deletedInApp)->toBe([]);
});

test('asks again about a death left deleted when the repo changed its date', function () {
    [$tree] = arrangeTwinTrees(function (Tree $tree) {
        recordedPerson($tree, 'O-0001');
        rememberRepoValues($tree, 'O-0001', CLASSIFY_JAN_VALUES);
        rememberRepoValues($tree, 'O-0001/zgon', ['data' => '1860'], deletionKept: true);
    });

    $classes = classifyTemporaryRepo($tree, ['O-0001' => "imiona: Jan\nzgon:\n  data: \"1870\""]);

    expect(repoKeysOf($classes->deletedInApp))->toBe(['O-0001/zgon'])
        ->and($classes->keptDeleted)->toBe([]);
});

test('asks about a person left deleted when the repo changed only the date of the birth, with the birth as a member', function () {
    [$tree] = arrangeTwinTrees(function (Tree $tree) {
        rememberRepoValues($tree, 'O-0001', CLASSIFY_JAN_VALUES, deletionKept: true);
        rememberRepoValues($tree, 'O-0001/urodzenie', ['data' => '1801'], deletionKept: true);
    });

    $classes = classifyTemporaryRepo($tree, ['O-0001' => "imiona: Jan\nurodzenie:\n  data: \"1801-05-12\""]);

    expect(repoKeysOf($classes->deletedInApp))->toBe(['O-0001'])
        ->and(memberRepoKeysOf($classes->deletedInApp[0]))->toBe(['O-0001/urodzenie'])
        ->and($classes->deletedInApp[0]->members[0]->snapshot->values)->toBe(['data' => '1801'])
        ->and($classes->keptDeleted)->toBe([])
        ->and($classes->newPersonIds)->toBe([]);
});

test('keeps a person left deleted as one decided key with the repo unchanged', function () {
    [$tree] = arrangeTwinTrees(function (Tree $tree) {
        rememberRepoValues($tree, 'O-0001', CLASSIFY_JAN_VALUES, deletionKept: true);
        rememberRepoValues($tree, 'O-0001/urodzenie', ['data' => '1801'], deletionKept: true);
    });

    $classes = classifyTemporaryRepo($tree, ['O-0001' => "imiona: Jan\nurodzenie:\n  data: \"1801\""]);

    expect($classes->keptDeleted)->toBe(['O-0001'])
        ->and($classes->deletedInApp)->toBe([]);
});

test('asks once about a person deleted in the app, with the relations and the wedding as members, and none of them apart', function () {
    [$tree] = arrangeTwinTrees(function (Tree $tree) {
        recordedPerson($tree, 'O-0002', 'Piotr');
        recordedPerson($tree, 'O-0003', 'Maria');
        rememberRepoValues($tree, 'O-0002', ['imiona' => 'Piotr', 'nazwisko' => null, 'plec' => null, 'notatka' => null]);
        rememberRepoValues($tree, 'O-0003', ['imiona' => 'Maria', 'nazwisko' => null, 'plec' => null, 'notatka' => null]);
        rememberRepoValues($tree, 'O-0001', CLASSIFY_JAN_VALUES);
        rememberRepoValues($tree, 'O-0001/urodzenie', ['data' => '1801']);
        rememberRepoValues($tree, 'O-0002>O-0001', []);
        rememberRepoValues($tree, 'O-0001+O-0003/slub', ['data' => '1825']);
    });

    $classes = classifyTemporaryRepo($tree, [
        'O-0001' => "imiona: Jan\nurodzenie:\n  data: \"1801\"\nrodzice:\n  ojciec: O-0002\nmalzenstwa:\n  - z: O-0003\n    data: \"1825\"",
        'O-0002' => 'imiona: Piotr',
        'O-0003' => "imiona: Maria\nmalzenstwa:\n  - z: O-0001\n    data: \"1825\"",
    ]);

    expect(repoKeysOf($classes->deletedInApp))->toBe(['O-0001'])
        ->and(memberRepoKeysOf($classes->deletedInApp[0]))->toBe(['O-0001+O-0003/slub', 'O-0001/urodzenie', 'O-0002>O-0001'])
        ->and(array_keys($classes->knownPersons))->toBe(['O-0002', 'O-0003']);
});

test('removes a person without a file with its events, relations and weddings as members, and reports what goes with it', function () {
    Storage::fake('local');

    [$tree, $built] = arrangeTwinTrees(function (Tree $tree) {
        $jan = recordedPerson($tree, 'O-0001', attributes: ['photo_path' => 'photos/jan.jpg']);
        $anna = recordedPerson($tree, 'O-0002', 'Anna');
        $father = recordedPerson($tree, 'O-0003', 'Piotr');
        $handEntered = Person::factory()->for($tree)->create();
        $tree->update(['root_person_id' => $jan->id]);

        $birth = recordedEvent($jan, EventType::Birth, 'O-0001/urodzenie');
        recordedEvent($jan, EventType::Burial, null);
        $source = Source::factory()->for($tree)->create();
        $source->events()->attach($birth->id);
        $gravestone = Source::factory()->for($tree)->gravestone()->create();
        $jan->gravestones()->attach($gravestone);
        Residence::factory()->create(['person_id' => $jan->id]);
        recordedRelation($father, $jan, 'O-0003>O-0001');
        recordedRelation($jan, $handEntered, null);
        attachMarriage($jan, $anna)->update(['repo_id' => 'O-0001+O-0002/slub']);

        return $jan;
    });

    $classes = classifyTemporaryRepo($tree, ['O-0002' => 'imiona: Anna', 'O-0003' => 'imiona: Piotr']);
    $entry = $classes->removedInRepo[0];

    expect(repoKeysOf($classes->removedInRepo))->toBe(['O-0001'])
        ->and($entry->kind)->toBe(RepoKeyKind::Person)
        ->and($entry->record->id)->toBe($built->id)
        ->and(memberRepoKeysOf($entry))->toBe(['O-0001+O-0002/slub', 'O-0001/urodzenie', 'O-0003>O-0001'])
        ->and($entry->impact->photo)->toBeTrue()
        ->and($entry->impact->sourceLinks)->toBe(1)
        ->and($entry->impact->burial)->toBeTrue()
        ->and($entry->impact->otherEvents)->toBe(1)
        ->and($entry->impact->residences)->toBe(1)
        ->and($entry->impact->relations)->toBe(2)
        ->and($entry->impact->marriages)->toBe(1)
        ->and($entry->impact->gravestones)->toBe(1)
        ->and($entry->impact->proband)->toBeTrue()
        ->and(array_keys($classes->knownPersons))->toBe(['O-0002', 'O-0003'])
        ->and($classes->unassessable)->toBe([]);
});

test('reports an empty impact for a person with nothing attached', function () {
    [$tree] = arrangeTwinTrees(fn (Tree $tree) => recordedPerson($tree, 'O-0001'));

    $classes = classifyTemporaryRepo($tree, ['O-0002' => 'imiona: Anna']);

    expect($classes->removedInRepo[0]->impact->isEmpty())->toBeTrue();
});

test('lists a death removed in the repo apart, with the burial that goes with it', function () {
    [$tree] = arrangeTwinTrees(function (Tree $tree) {
        $jan = recordedPerson($tree, 'O-0001');
        $death = recordedEvent($jan, EventType::Death, 'O-0001/zgon');
        recordedEvent($jan, EventType::Burial, null);
        $source = Source::factory()->for($tree)->create();
        $source->events()->attach($death->id);
    });

    $classes = classifyTemporaryRepo($tree, ['O-0001' => 'imiona: Jan']);

    expect(array_keys($classes->knownPersons))->toBe(['O-0001'])
        ->and(repoKeysOf($classes->removedInRepo))->toBe(['O-0001/zgon'])
        ->and($classes->removedInRepo[0]->kind)->toBe(RepoKeyKind::Death)
        ->and($classes->removedInRepo[0]->members)->toBe([])
        ->and($classes->removedInRepo[0]->impact->burial)->toBeTrue()
        ->and($classes->removedInRepo[0]->impact->sourceLinks)->toBe(1);
});

test('marks a birth removed in the repo to be detached only', function () {
    [$tree] = arrangeTwinTrees(function (Tree $tree) {
        recordedEvent(recordedPerson($tree, 'O-0001'), EventType::Birth, 'O-0001/urodzenie');
    });

    $classes = classifyTemporaryRepo($tree, ['O-0001' => 'imiona: Jan']);

    expect(repoKeysOf($classes->removedInRepo))->toBe(['O-0001/urodzenie'])
        ->and($classes->removedInRepo[0]->detachOnly())->toBeTrue()
        ->and($classes->removedInRepo[0]->impact)->toBeNull();
});

test('lists a relation and a wedding the repo dropped between two persons that stay', function () {
    [$tree] = arrangeTwinTrees(function (Tree $tree) {
        $father = recordedPerson($tree, 'O-0003', 'Piotr');
        $jan = recordedPerson($tree, 'O-0001');
        $anna = recordedPerson($tree, 'O-0002', 'Anna');
        recordedRelation($father, $jan, 'O-0003>O-0001');
        $wedding = attachMarriage($jan, $anna);
        $wedding->update(['repo_id' => 'O-0001+O-0002/slub']);
        Source::factory()->for($tree)->create()->events()->attach($wedding->id);
    });

    $classes = classifyTemporaryRepo($tree, ['O-0001' => 'imiona: Jan', 'O-0002' => 'imiona: Anna', 'O-0003' => 'imiona: Piotr']);
    $byKey = collect($classes->removedInRepo)->keyBy('key');

    expect($byKey->keys()->all())->toBe(['O-0001+O-0002/slub', 'O-0003>O-0001'])
        ->and($byKey['O-0003>O-0001']->kind)->toBe(RepoKeyKind::Relation)
        ->and($byKey['O-0003>O-0001']->impact->isEmpty())->toBeTrue()
        ->and($byKey['O-0001+O-0002/slub']->kind)->toBe(RepoKeyKind::Marriage)
        ->and($byKey['O-0001+O-0002/slub']->impact->sourceLinks)->toBe(1)
        ->and(array_keys($classes->knownPersons))->toBe(['O-0001', 'O-0002', 'O-0003']);
});

test('does not remove a person whose file was skipped with a readable id, nor its relations', function () {
    [$tree] = arrangeTwinTrees(function (Tree $tree) {
        $jan = recordedPerson($tree, 'O-0001');
        $anna = recordedPerson($tree, 'O-0002', 'Anna');
        recordedEvent($jan, EventType::Death, 'O-0001/zgon');
        recordedRelation($jan, $anna, 'O-0001>O-0002');
        rememberRepoValues($tree, 'O-0001+O-0002/slub', ['data' => '1825']);
    });

    $classes = classifyTemporaryRepo($tree, ['O-0002' => 'imiona: Anna'], [
        'jan.md' => "---\nwersja: 1\nid: O-0001\nimiona: Jan\nurodzenie:\n  data: \"1801-13-40\"\n---\n",
    ]);

    expect(array_keys($classes->knownPersons))->toBe(['O-0002'])
        ->and($classes->removedInRepo)->toBe([])
        ->and($classes->unassessable)->toBe([])
        ->and($classes->goneBoth)->toBe([])
        ->and($classes->deletedInApp)->toBe([]);
});

test('does not remove a person whose file has text before the block', function () {
    [$tree] = arrangeTwinTrees(function (Tree $tree) {
        $jan = recordedPerson($tree, 'O-0001');
        recordedRelation($jan, recordedPerson($tree, 'O-0002', 'Anna'), 'O-0001>O-0002');
    });

    $classes = classifyTemporaryRepo($tree, ['O-0002' => 'imiona: Anna'], [
        'jan.md' => "Wstęp ręczny.\n\n---\nwersja: 1\nid: O-0001\nimiona: Jan\n---\n",
    ]);

    expect(array_keys($classes->knownPersons))->toBe(['O-0002'])
        ->and($classes->removedInRepo)->toBe([])
        ->and($classes->unassessable)->toBe([]);
});

test('holds everything back when an entry was skipped without a readable id', function () {
    [$tree] = arrangeTwinTrees(function (Tree $tree) {
        $jan = recordedPerson($tree, 'O-0001');
        recordedEvent($jan, EventType::Birth, 'O-0001/urodzenie');
        rememberRepoValues($tree, 'O-0007', CLASSIFY_JAN_VALUES);
    });

    $classes = classifyTemporaryRepo($tree, ['O-0002' => 'imiona: Anna'], [
        'zepsuty.md' => "---\nwersja: 1\nid: O-0003\nimiona: [Jan\n---\n",
    ]);

    expect($classes->removedInRepo)->toBe([])
        ->and(repoKeysOf($classes->unassessable))->toBe(['O-0001'])
        ->and(memberRepoKeysOf($classes->unassessable[0]))->toBe(['O-0001/urodzenie'])
        ->and($classes->goneBoth)->toBe([])
        ->and($classes->newPersonIds)->toBe(['O-0002']);
});

test('forgets a memory that neither the repo nor the tree has any more', function () {
    [$tree] = arrangeTwinTrees(function (Tree $tree) {
        rememberRepoValues($tree, 'O-0007', CLASSIFY_JAN_VALUES);
        rememberRepoValues($tree, 'O-0007/zgon', ['data' => '1870']);
        rememberRepoValues($tree, 'O-0007>O-0001', []);
    });

    $classes = classifyTemporaryRepo($tree, ['O-0001' => 'imiona: Jan']);

    expect($classes->goneBoth)->toBe(['O-0007', 'O-0007/zgon', 'O-0007>O-0001'])
        ->and($classes->newPersonIds)->toBe(['O-0001'])
        ->and($classes->deletedInApp)->toBe([])
        ->and($classes->removedInRepo)->toBe([]);
});

test('ignores the records and the memory of another account', function () {
    $other = User::factory()->create();
    $author = User::factory()->create();
    $jan = recordedPerson($other->tree, 'O-0001', attributes: ['photo_path' => 'photos/jan.jpg']);
    recordedPerson($other->tree, 'O-0009');
    recordedEvent($jan, EventType::Birth, 'O-0001/urodzenie');
    rememberRepoValues($other->tree, 'O-0001', CLASSIFY_JAN_VALUES, deletionKept: true);
    rememberRepoValues($other->tree, 'O-0002', CLASSIFY_JAN_VALUES);
    rememberRepoValues($other->tree, 'O-0007', CLASSIFY_JAN_VALUES);

    $classes = classifyTemporaryRepo($author->tree, ['O-0001' => 'imiona: Jan', 'O-0002' => 'imiona: Anna']);

    expect($classes->newPersonIds)->toBe(['O-0001', 'O-0002'])
        ->and($classes->knownPersons)->toBe([])
        ->and($classes->deletedInApp)->toBe([])
        ->and($classes->keptDeleted)->toBe([])
        ->and($classes->removedInRepo)->toBe([])
        ->and($classes->goneBoth)->toBe([])
        ->and($classes->unassessable)->toBe([]);
});

test('does not remove, ask about or forget a wedding the reading dropped with a warning', function () {
    [$tree] = arrangeTwinTrees(function (Tree $tree) {
        $jan = recordedPerson($tree, 'O-0001');
        $anna = recordedPerson($tree, 'O-0002', 'Anna');
        $ewa = recordedPerson($tree, 'O-0003', 'Ewa');
        $adam = recordedPerson($tree, 'O-0004', 'Adam');
        attachMarriage($jan, $anna)->update(['repo_id' => 'O-0001+O-0002/slub']);
        attachMarriage($ewa, $adam)->update(['repo_id' => 'O-0003+O-0004/slub']);
        rememberRepoValues($tree, 'O-0001+O-0002/slub', ['data' => '1825']);
        rememberRepoValues($tree, 'O-0003+O-0004/slub', ['data' => '1830']);
        rememberRepoValues($tree, 'O-0005+O-0006/slub', ['data' => '1840']);
    });

    $classes = classifyTemporaryRepo($tree, [
        'O-0001' => "imiona: Jan\nmalzenstwa:\n  - z: O-0002\n    data: \"1825\"",
        'O-0002' => "imiona: Anna\nmalzenstwa:\n  - z: O-0001\n    data: \"1826\"",
        'O-0003' => "imiona: Ewa\nmalzenstwa:\n  - z: O-0004\n  - z: O-0004",
        'O-0004' => 'imiona: Adam',
        'O-0005' => "imiona: Kaja\nmalzenstwa:\n  - z: O-0006\n    data: \"1840\"",
        'O-0006' => "imiona: Leon\nmalzenstwa:\n  - z: O-0005\n    data: \"1841\"",
    ]);

    expect(array_keys($classes->knownPersons))->toBe(['O-0001', 'O-0002', 'O-0003', 'O-0004'])
        ->and($classes->removedInRepo)->toBe([])
        ->and($classes->deletedInApp)->toBe([])
        ->and($classes->keptDeleted)->toBe([])
        ->and($classes->goneBoth)->toBe([])
        ->and($classes->unassessable)->toBe([]);
});

test('still removes a wedding the repo really dropped while another one is only unreadable', function () {
    [$tree] = arrangeTwinTrees(function (Tree $tree) {
        $jan = recordedPerson($tree, 'O-0001');
        $anna = recordedPerson($tree, 'O-0002', 'Anna');
        $ewa = recordedPerson($tree, 'O-0003', 'Ewa');
        attachMarriage($jan, $anna)->update(['repo_id' => 'O-0001+O-0002/slub']);
        attachMarriage($jan, $ewa)->update(['repo_id' => 'O-0001+O-0003/slub']);
    });

    $classes = classifyTemporaryRepo($tree, [
        'O-0001' => "imiona: Jan\nmalzenstwa:\n  - z: O-0002\n    data: \"1825\"",
        'O-0002' => "imiona: Anna\nmalzenstwa:\n  - z: O-0001\n    data: \"1826\"",
        'O-0003' => 'imiona: Ewa',
    ]);

    expect(repoKeysOf($classes->removedInRepo))->toBe(['O-0001+O-0003/slub']);
});

test('asks about a burial deleted in the app that the repo still has', function () {
    [$tree] = arrangeTwinTrees(function (Tree $tree) {
        recordedPerson($tree, 'O-0001');
        rememberRepoValues($tree, 'O-0001');
        rememberRepoValues($tree, 'O-0001/pochowek', ['data' => null, 'miejsce' => 'Zalesie', 'miejsce_id' => 'L:7', 'cmentarz' => null, 'cmentarz_id' => 'C:3']);
    });

    $classes = classifyTemporaryRepo($tree, ['O-0001' => "imiona: Jan\nnazwisko: Nowak\npochowek:\n  miejsce: Zalesie"]);

    expect(repoKeysOf($classes->deletedInApp))->toBe(['O-0001/pochowek'])
        ->and($classes->deletedInApp[0]->kind)->toBe(RepoKeyKind::Burial)
        ->and($classes->keptDeleted)->toBe([]);
});

test('does not read a burial remembered without a record as deleted in the app and forgets it with the repo entry', function () {
    [$tree] = arrangeTwinTrees(function (Tree $tree) {
        recordedPerson($tree, 'O-0001');
        rememberRepoValues($tree, 'O-0001');
        rememberRepoValues($tree, 'O-0001/pochowek', ['data' => null, 'miejsce' => 'Bokinia', 'miejsce_id' => null, 'cmentarz' => null, 'cmentarz_id' => null]);
    });

    $kept = classifyTemporaryRepo($tree, ['O-0001' => "imiona: Jan\nnazwisko: Nowak\npochowek:\n  miejsce: Bokinia"]);
    $dropped = classifyTemporaryRepo($tree, ['O-0001' => "imiona: Jan\nnazwisko: Nowak"]);

    expect($kept->deletedInApp)->toBe([])
        ->and($kept->keptDeleted)->toBe([])
        ->and($kept->goneBoth)->toBe([])
        ->and($dropped->goneBoth)->toBe(['O-0001/pochowek']);
});

test('lists a burial removed in the repo apart, with nothing attached', function () {
    [$tree] = arrangeTwinTrees(function (Tree $tree) {
        $jan = recordedPerson($tree, 'O-0001');
        recordedEvent($jan, EventType::Burial, 'O-0001/pochowek');
    });

    $classes = classifyTemporaryRepo($tree, ['O-0001' => 'imiona: Jan']);

    expect(repoKeysOf($classes->removedInRepo))->toBe(['O-0001/pochowek'])
        ->and($classes->removedInRepo[0]->kind)->toBe(RepoKeyKind::Burial)
        ->and($classes->removedInRepo[0]->detachOnly())->toBeFalse()
        ->and($classes->removedInRepo[0]->impact->isEmpty())->toBeTrue();
});

test('does not ask again about a deleted birth when only the remembered place id differs from the repo values', function () {
    [$tree] = arrangeTwinTrees(function (Tree $tree) {
        recordedPerson($tree, 'O-0001');
        rememberRepoValues($tree, 'O-0001');
        rememberRepoValues($tree, 'O-0001/urodzenie', ['data' => '1801', 'miejsce' => 'Zalesie', 'miejsce_id' => 'L:7'], deletionKept: true);
    });

    $classes = classifyTemporaryRepo($tree, ['O-0001' => "imiona: Jan\nnazwisko: Nowak\nurodzenie:\n  data: \"1801\"\n  miejsce: Zalesie"]);

    expect($classes->deletedInApp)->toBe([])
        ->and($classes->keptDeleted)->toBe(['O-0001/urodzenie']);
});
