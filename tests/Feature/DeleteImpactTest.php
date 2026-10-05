<?php

use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Sorted "a:b" keys of the given rows, to compare sets of pairs (event:person, parent:child, event:source).
 *
 * @param  list<array<string, mixed>>  $rows
 * @return list<string>
 */
function pairKeys(array $rows, string $first, string $second): array
{
    $keys = array_map(fn (array $row): string => $row[$first].':'.$row[$second], $rows);
    sort($keys);

    return $keys;
}

/**
 * @param  list<string>  $keys
 * @return list<string>
 */
function sortedKeys(array $keys): array
{
    sort($keys);

    return $keys;
}

/**
 * @param  list<array<string, mixed>>  $rows
 * @return list<int>
 */
function rowIds(array $rows): array
{
    $ids = array_map(fn (array $row): int => $row['id'], $rows);
    sort($ids);

    return $ids;
}

test('deleting a person removes exactly what the preview lists and nothing of bystanders, spouses or sources', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $scene = familyScene($user);
    extract($scene);

    $preview = null;
    $this->actingAs($user)->get(personPreviewUrl($focus))->assertInertia(function ($page) use (&$preview) {
        $preview = $page->toArray()['props']['personPreview']['person'];

        return $page;
    });

    $before = databaseRows();

    $this->actingAs($user)->delete(route('persons.destroy', $focus))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('persons.index'));

    $diff = rowDiff($before, databaseRows());

    // (a) the set expected from the fixture
    $deletedEvents = [$focusBirth, $focusDeath, $marriageA, $marriageB];
    $expectedRelations = sortedKeys([
        "{$focusFather->id}:{$focus->id}",
        "{$focusMother->id}:{$focus->id}",
        "{$focus->id}:{$childA->id}",
        "{$focus->id}:{$childB->id}",
        "{$focus->id}:{$childC->id}",
        "{$focus->id}:{$childUnknown->id}",
    ]);
    $expectedLinks = sortedKeys([
        "{$focusBirth->id}:{$sourceShared->id}",
        "{$marriageA->id}:{$sourceShared->id}",
        "{$marriageA->id}:{$sourceOther->id}",
    ]);

    expect(array_keys($diff['tables']))->toBe([
        'event_participants', 'event_source', 'events', 'parent_child_relations', 'people', 'trees',
    ]);
    expect(rowIds($diff['tables']['people']['removed']))->toBe([$focus->id]);
    expect(rowIds($diff['tables']['events']['removed']))->toBe(rowIds(array_map(fn ($event) => ['id' => $event->id], $deletedEvents)));
    expect(pairKeys($diff['tables']['event_participants']['removed'], 'event_id', 'person_id'))->toBe(sortedKeys([
        "{$focusBirth->id}:{$focus->id}",
        "{$focusDeath->id}:{$focus->id}",
        "{$marriageA->id}:{$focus->id}",
        "{$marriageA->id}:{$wifeA->id}",
        "{$marriageB->id}:{$focus->id}",
        "{$marriageB->id}:{$wifeB->id}",
    ]));
    expect(pairKeys($diff['tables']['event_source']['removed'], 'event_id', 'source_id'))->toBe($expectedLinks);
    expect(pairKeys($diff['tables']['parent_child_relations']['removed'], 'parent_id', 'child_id'))->toBe($expectedRelations);
    expect($diff['tables']['trees']['changed'])->toHaveCount(1);
    expect($diff['tables']['trees']['changed'][0]['before']['root_person_id'])->toBe($focus->id);
    expect($diff['tables']['trees']['changed'][0]['after']['root_person_id'])->toBeNull();
    foreach (['people', 'events', 'event_participants', 'event_source', 'parent_child_relations'] as $table) {
        expect($diff['tables'][$table]['added'])->toBe([]);
        expect($diff['tables'][$table]['changed'])->toBe([]);
    }
    expect($diff['tables']['trees']['removed'])->toBe([]);
    expect($diff['files'])->toBe(['removed' => [], 'added' => []]);

    // (b) the set the confirmation showed, equal to the fixture set (a) and so to the actual diff (c)
    $previewMarriageIds = collect($preview['marriages'])->pluck('id')->sort()->values()->all();
    expect($previewMarriageIds)->toBe(rowIds([['id' => $marriageA->id], ['id' => $marriageB->id]]));

    $previewRelationIds = collect([...$preview['parents'], ...$preview['children']])->pluck('relation_id')->sort()->values()->all();
    expect($previewRelationIds)->toBe(rowIds($diff['tables']['parent_child_relations']['removed']));

    $previewLinks = collect([$preview['birth'], $preview['death'], ...$preview['marriages']])
        ->flatMap(fn (array $event) => collect($event['sources'])->map(fn (array $source) => $event['id'].':'.$source['id']))
        ->sort()->values()->all();
    expect($previewLinks)->toBe($expectedLinks);
});

test('deleting a source detaches exactly the facts the confirmation listed and removes only its own files', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $scene = familyScene($user);
    extract($scene);

    $confirms = null;
    $this->actingAs($user)->get(route('sources.index', ['source' => $sourceShared->id]))->assertInertia(function ($page) use (&$confirms) {
        $confirms = $page->toArray()['props']['sourcePanel']['confirms'];

        return $page;
    });

    $listed = collect($confirms)
        ->map(fn (array $row): string => $row['event_id'].':'.$row['person']['id'].':'.($row['is_last_source'] ? 'last' : 'kept'))
        ->sort()->values()->all();

    expect($listed)->toBe(sortedKeys([
        "{$focusBirth->id}:{$focus->id}:last",
        "{$marriageA->id}:{$focus->id}:kept",
        "{$marriageA->id}:{$wifeA->id}:kept",
        "{$bystanderBirth->id}:{$bystander->id}:last",
    ]));

    $before = databaseRows();

    $this->actingAs($user)->delete(route('sources.destroy', $sourceShared))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('sources.index'));

    $diff = rowDiff($before, databaseRows());

    expect(array_keys($diff['tables']))->toBe(['event_source', 'sources']);
    expect(rowIds($diff['tables']['sources']['removed']))->toBe([$sourceShared->id]);
    expect(pairKeys($diff['tables']['event_source']['removed'], 'event_id', 'source_id'))->toBe(sortedKeys([
        "{$focusBirth->id}:{$sourceShared->id}",
        "{$marriageA->id}:{$sourceShared->id}",
        "{$bystanderBirth->id}:{$sourceShared->id}",
    ]));
    foreach (['sources', 'event_source'] as $table) {
        expect($diff['tables'][$table]['added'])->toBe([]);
        expect($diff['tables'][$table]['changed'])->toBe([]);
    }
    expect($diff['files']['removed'])->toBe(sortedKeys([$sourceShared->path, $sourceShared->thumbnail_path]));
    expect($diff['files']['added'])->toBe([]);

    $remainingLinks = collect(databaseRows()['tables']['event_source'])->countBy('event_id');
    foreach ($confirms as $row) {
        expect($remainingLinks->get($row['event_id'], 0) >= 1)->toBe(! $row['is_last_source']);
    }
});

test('deleting a marriage removes only that marriage event with its participants and source links', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $scene = familyScene($user);
    extract($scene);

    $before = databaseRows();

    $this->actingAs($user)->delete(route('marriages.destroy', $marriageB))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $diff = rowDiff($before, databaseRows());

    expect(array_keys($diff['tables']))->toBe(['event_participants', 'events']);
    expect(rowIds($diff['tables']['events']['removed']))->toBe([$marriageB->id]);
    expect(pairKeys($diff['tables']['event_participants']['removed'], 'event_id', 'person_id'))->toBe(sortedKeys([
        "{$marriageB->id}:{$focus->id}",
        "{$marriageB->id}:{$wifeB->id}",
    ]));
    foreach (['events', 'event_participants'] as $table) {
        expect($diff['tables'][$table]['added'])->toBe([]);
        expect($diff['tables'][$table]['changed'])->toBe([]);
    }
    expect($diff['files'])->toBe(['removed' => [], 'added' => []]);
});

test('deleting a parent-child relation removes only that relation and keeps both people and the other parent link', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $scene = familyScene($user);
    extract($scene);

    $relation = $focus->children()->where('people.id', $childC->id)->firstOrFail()->pivot;
    $relationId = $relation->id;

    $before = databaseRows();

    $this->actingAs($user)->delete(route('parent-child-relations.destroy', $relationId))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $diff = rowDiff($before, databaseRows());

    expect(array_keys($diff['tables']))->toBe(['parent_child_relations']);
    expect($diff['tables']['parent_child_relations']['added'])->toBe([]);
    expect($diff['tables']['parent_child_relations']['changed'])->toBe([]);
    expect(pairKeys($diff['tables']['parent_child_relations']['removed'], 'parent_id', 'child_id'))->toBe(["{$focus->id}:{$childC->id}"]);
    expect($diff['files'])->toBe(['removed' => [], 'added' => []]);
});
