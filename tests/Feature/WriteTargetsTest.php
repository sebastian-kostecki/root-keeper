<?php

use App\Models\Cemetery;
use App\Models\Locality;
use App\Models\Person;
use App\Models\Residence;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * GET a page as the user and return one Inertia prop, exactly as the client receives it.
 *
 * @return array<string, mixed>
 */
function readInertiaProp(mixed $test, User $user, string $url, string $prop): array
{
    $value = null;

    $test->actingAs($user)->get($url)->assertOk()->assertInertia(function ($page) use (&$value, $prop) {
        $value = $page->toArray()['props'][$prop];

        return $page;
    });

    return $value;
}

/**
 * @param  list<string>  $keys
 * @return list<string>
 */
function writeSortedKeys(array $keys): array
{
    sort($keys);

    return $keys;
}

/**
 * The `spouse` query value that selects the given scene person's union (or the unknown-partner union).
 *
 * @param  array<string, mixed>  $scene
 */
function unionQuery(array $scene, string $spouse): string
{
    return $spouse === 'unknown' ? 'unknown' : (string) $scene[$spouse]->id;
}

/**
 * The request a tree dialog sends for one write case, built from the `sheet` prop the way the dialogs build it,
 * and the rows that request must add (by content; `new` stands for the one created person).
 *
 * @param  array<string, mixed>  $sheet
 * @param  array<string, mixed>  $scene
 * @return array{url: string, data: array<string, mixed>, expected: array<string, list<string>>}
 */
function treeWritePlan(int $case, array $sheet, array $scene): array
{
    $id = fn (string $key): string => (string) $scene[$key]->id;
    $pair = $sheet['add_child']['parents'];
    $otherParentId = $pair[1]['id'] ?? null;
    $parentSlot = fn (int $index): array => $sheet['parents']['partner'][$index]['parent_of'];
    $genderOf = ['father' => 'male', 'mother' => 'female'];

    switch ($case) {
        case 1:
        case 2:
            return [
                'url' => route('persons.children.attach', $pair[0]['id']),
                'data' => ['child_id' => $scene['loneMan']->id, 'other_parent_id' => $otherParentId],
                'expected' => ['parent_child_relations' => $case === 1
                    ? writeSortedKeys([$id('focus').':'.$id('loneMan'), $id('wifeB').':'.$id('loneMan')])
                    : [$id('focus').':'.$id('loneMan')]],
            ];

        case 3:
            return [
                'url' => route('persons.children.store', $pair[0]['id']),
                'data' => [
                    'first_name' => 'Nowe',
                    'last_name' => $sheet['add_child']['default_last_name'],
                    ...($otherParentId === null ? [] : ['other_parent_id' => $otherParentId]),
                ],
                'expected' => [
                    'people' => ['new'],
                    'events' => ['birth'],
                    'event_participants' => ['birth:new'],
                    'parent_child_relations' => writeSortedKeys([$id('focus').':new', $id('coParent').':new']),
                ],
            ];

        case 4:
            return [
                'url' => route('persons.spouses.attach', $sheet['focus']['id']),
                'data' => ['spouse_id' => $scene['loneWoman']->id],
                'expected' => [
                    'events' => ['marriage'],
                    'event_participants' => writeSortedKeys(['marriage:'.$id('focus'), 'marriage:'.$id('loneWoman')]),
                ],
            ];

        case 5:
            return [
                'url' => route('persons.spouses.store', $sheet['focus']['id']),
                'data' => ['first_name' => 'Nowa', 'last_name' => 'Żona', 'gender' => 'female'],
                'expected' => [
                    'people' => ['new'],
                    'events' => ['birth', 'marriage'],
                    'event_participants' => writeSortedKeys(['birth:new', 'marriage:'.$id('focus'), 'marriage:new']),
                ],
            ];

        case 6:
            $slot = $parentSlot(0);

            return [
                'url' => route('parent-child-relations.store'),
                'data' => ['parent_id' => $scene['loneMan']->id, 'child_id' => $slot['id'], 'role' => $slot['role']],
                'expected' => ['parent_child_relations' => [$id('loneMan').':'.$id('wifeB')]],
            ];

        case 7:
            $slot = $parentSlot(1);

            return [
                'url' => route('persons.parents.store', $slot['id']),
                'data' => ['first_name' => 'Nowa', 'last_name' => 'Matka', 'gender' => $genderOf[$slot['role']], 'role' => $slot['role']],
                'expected' => [
                    'people' => ['new'],
                    'events' => ['birth'],
                    'event_participants' => ['birth:new'],
                    'parent_child_relations' => ['new:'.$id('wifeB')],
                ],
            ];

        case 8:
            expect($sheet['unmarried_parents']['focus'])->toBeTrue();
            [$first, $second] = $sheet['parents']['focus'];

            return [
                'url' => route('marriages.store'),
                'data' => ['person_id' => $first['person']['id'], 'spouse_id' => $second['person']['id']],
                'expected' => [
                    'events' => ['marriage'],
                    'event_participants' => writeSortedKeys(['marriage:'.$id('focusFather'), 'marriage:'.$id('focusMother')]),
                ],
            ];

        default:
            $selected = collect($sheet['unions'])->firstWhere('key', $sheet['selected_union']);
            expect($selected['is_marriage'])->toBeFalse();
            expect($sheet['partner']['kind'])->toBe('person');

            return [
                'url' => route('marriages.store'),
                'data' => ['person_id' => $sheet['focus']['id'], 'spouse_id' => $sheet['partner']['person']['id']],
                'expected' => [
                    'events' => ['marriage'],
                    'event_participants' => writeSortedKeys(['marriage:'.$id('focus'), 'marriage:'.$id('coParent')]),
                ],
            ];
    }
}

/**
 * The rows a diff added, described by content: created people as `new`, events by type, participants as
 * `type:person`, relations as `parent:child`. Tables without added rows are left out.
 *
 * @param  array<string, mixed>  $diff
 * @return array<string, list<string>>
 */
function describeAddedRows(array $diff): array
{
    $added = fn (string $table): array => $diff['tables'][$table]['added'] ?? [];

    $newPeople = array_column($added('people'), 'id');
    $person = fn (int $id): string => in_array($id, $newPeople, true) ? 'new' : (string) $id;
    $eventTypes = array_column($added('events'), 'type', 'id');

    $described = [
        'people' => array_map(fn (array $row): string => 'new', $added('people')),
        'events' => array_map(fn (array $row): string => $row['type'], $added('events')),
        'event_participants' => array_map(fn (array $row): string => $eventTypes[$row['event_id']].':'.$person($row['person_id']), $added('event_participants')),
        'parent_child_relations' => array_map(fn (array $row): string => $person($row['parent_id']).':'.$person($row['child_id']), $added('parent_child_relations')),
        'event_source' => array_map(fn (array $row): string => $row['event_id'].':'.$row['source_id'], $added('event_source')),
    ];

    return array_map('writeSortedKeys', array_filter($described, fn (array $keys): bool => $keys !== []));
}

dataset('unions of the focus person', [
    'marriage A' => ['wifeA', ['focus', 'wifeA']],
    'marriage B' => ['wifeB', ['focus', 'wifeB']],
    'co-parent without marriage' => ['coParent', ['focus', 'coParent']],
    'unknown partner' => ['unknown', ['focus']],
]);

test('the sheet offers the pair of the selected union as the parents of a new child', function (string $spouse, array $expectedParents) {
    Storage::fake('local');

    $user = User::factory()->create();
    $scene = familyScene($user);

    $sheet = readInertiaProp($this, $user, route('tree.show', ['person' => $scene['focus'], 'spouse' => unionQuery($scene, $spouse)]), 'sheet');

    expect(array_column($sheet['add_child']['parents'], 'id'))
        ->toBe(array_map(fn (string $key): int => $scene[$key]->id, $expectedParents));
})->with('unions of the focus person');

test('the partner block of a union with a parentless spouse offers empty father and mother slots of that spouse', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $scene = familyScene($user);

    $sheet = readInertiaProp($this, $user, route('tree.show', ['person' => $scene['focus'], 'spouse' => $scene['wifeB']->id]), 'sheet');

    expect(collect($sheet['parents']['partner'])->pluck('kind')->all())->toBe(['unknown', 'unknown']);
    expect(collect($sheet['parents']['partner'])->pluck('parent_of.id')->all())->toBe([$scene['wifeB']->id, $scene['wifeB']->id]);
    expect(collect($sheet['parents']['partner'])->pluck('parent_of.role')->all())->toBe(['father', 'mother']);
});

test('a tree write built from the sheet props adds exactly the expected rows and changes nothing else', function (int $case, string $spouse) {
    Storage::fake('local');

    $user = User::factory()->create();
    $scene = familyScene($user);

    $sheet = readInertiaProp($this, $user, route('tree.show', ['person' => $scene['focus'], 'spouse' => unionQuery($scene, $spouse)]), 'sheet');
    $plan = treeWritePlan($case, $sheet, $scene);

    $before = databaseRows();

    $this->actingAs($user)->post($plan['url'], $plan['data'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $diff = rowDiff($before, databaseRows());

    expect(describeAddedRows($diff))->toBe($plan['expected']);
    expect(array_keys($diff['tables']))->toEqualCanonicalizing(array_keys($plan['expected']));

    foreach ($diff['tables'] as $table) {
        expect($table['removed'])->toBe([]);
        expect($table['changed'])->toBe([]);
    }

    expect($diff['files'])->toBe(['removed' => [], 'added' => []]);
})->with([
    'existing child with the other parent set (wife B)' => [1, 'wifeB'],
    'existing child with the other parent null (unknown partner)' => [2, 'unknown'],
    'new child of the co-parent pair' => [3, 'coParent'],
    'existing spouse of the focus person' => [4, 'wifeB'],
    'new spouse of the focus person' => [5, 'wifeB'],
    'existing person as the father slot of wife B' => [6, 'wifeB'],
    'new person as the mother slot of wife B' => [7, 'wifeB'],
    'marry the unmarried parents of the focus person' => [8, 'wifeA'],
    'marry the unmarried centre couple' => [9, 'coParent'],
]);

test('editing a person in the preview changes only that person and the birth fact in place', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $scene = familyScene($user);
    extract($scene);

    $event = fn (?array $fact, array $overrides = []): array => $fact === null ? [] : [
        ...array_intersect_key($fact, array_flip(['year', 'month', 'day', 'is_approximate'])),
        'locality_id' => $fact['locality']['id'] ?? null,
        ...$overrides,
    ];

    $village = Locality::factory()->for($user->tree)->create();
    Residence::factory()->for($focus)->create(['tree_id' => $user->tree->id, 'locality_id' => $village->id, 'year_from' => 1900]);
    Residence::factory()->for($focus)->create(['tree_id' => $user->tree->id, 'locality_id' => $village->id]);

    $form = readInertiaProp($this, $user, personPreviewUrl($focus), 'personPreview')['personForm'];

    $before = databaseRows();

    $this->actingAs($user)->put(route('persons.update', $focus), [
        ...array_intersect_key($form, array_flip(['first_name', 'last_name', 'maiden_name', 'gender', 'religion', 'is_deceased'])),
        'birth' => $event($form['birth'], ['year' => 1851]),
        'death' => $event($form['death']),
        'residences' => array_map(fn (array $row): array => [
            'locality_id' => $row['locality']['id'],
            'year_from' => $row['year_from'],
            'year_to' => $row['year_to'],
        ], $form['residences']),
    ])->assertSessionHasNoErrors()->assertRedirect();

    $diff = rowDiff($before, databaseRows());

    expect(array_keys($diff['tables']))->toBe(['events']);

    expect(array_column(array_column($diff['tables']['events']['changed'], 'after'), 'id'))->toBe([$focusBirth->id]);
    expect($diff['tables']['events']['changed'][0]['before']['date_year'])->toBe(1850);
    expect($diff['tables']['events']['changed'][0]['after']['date_year'])->toBe(1851);

    foreach ($diff['tables'] as $table) {
        expect($table['removed'])->toBe([]);
        expect($table['added'])->toBe([]);
    }

    expect($diff['files'])->toBe(['removed' => [], 'added' => []]);
});

test('saving a note from the preview changes only that person people row', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create(['note' => 'Stara notatka']);
    Person::factory()->for($user->tree)->create(['note' => 'Cudza notatka']);

    $before = databaseRows();

    $this->actingAs($user)->put(route('persons.note.update', $person), ['note' => "Nowy akapit.\n\nDrugi."])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $diff = rowDiff($before, databaseRows());

    expect(array_keys($diff['tables']))->toBe(['people']);
    expect($diff['tables']['people']['added'])->toBe([]);
    expect($diff['tables']['people']['removed'])->toBe([]);
    expect($diff['tables']['people']['changed'])->toHaveCount(1);

    $change = $diff['tables']['people']['changed'][0];

    expect($change['before']['id'])->toBe($person->id);
    expect($change['after']['note'])->toBe("Nowy akapit.\n\nDrugi.");
    // updated_at is bumped but may land in the same second as the previous write.
    $changedColumns = array_keys(array_diff_assoc($change['after'], $change['before']));
    expect($changedColumns)->toContain('note');
    expect(array_diff($changedColumns, ['note', 'updated_at']))->toBe([]);
    expect($diff['files'])->toBe(['removed' => [], 'added' => []]);
});

test('replacing a person residence list changes only that person residence rows', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $scene = familyScene($user);
    extract($scene);

    $village = Locality::factory()->for($user->tree)->create();
    $town = Locality::factory()->for($user->tree)->create();
    $focusResidence = Residence::factory()->for($focus)->create(['tree_id' => $user->tree->id, 'locality_id' => $village->id]);
    $otherResidence = Residence::factory()->for($focusFather)->create(['tree_id' => $user->tree->id, 'locality_id' => $village->id]);

    $form = readInertiaProp($this, $user, personPreviewUrl($focus), 'personPreview')['personForm'];

    $before = databaseRows();

    $this->actingAs($user)->put(route('persons.update', $focus), [
        ...array_intersect_key($form, array_flip(['first_name', 'last_name', 'maiden_name', 'gender', 'religion', 'is_deceased'])),
        'birth' => ['year' => $form['birth']['year'], 'month' => $form['birth']['month'], 'day' => $form['birth']['day'], 'locality_id' => null],
        'death' => ['year' => $form['death']['year'], 'locality_id' => null],
        'residences' => [['locality_id' => $town->id, 'year_from' => 1900, 'year_to' => null]],
    ])->assertSessionHasNoErrors()->assertRedirect();

    $diff = rowDiff($before, databaseRows());

    expect(array_keys($diff['tables']))->toBe(['residences']);
    expect(array_column($diff['tables']['residences']['removed'], 'id'))->toBe([$focusResidence->id]);
    expect($diff['tables']['residences']['added'])->toHaveCount(1);
    expect($diff['tables']['residences']['added'][0]['person_id'])->toBe($focus->id);
    expect($diff['tables']['residences']['changed'])->toBe([]);
    expect(Residence::query()->whereKey($otherResidence->id)->exists())->toBeTrue();
});

test('editing a marriage from the preview changes only that marriage event and keeps its source links', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $scene = familyScene($user);
    extract($scene);

    $preview = readInertiaProp($this, $user, personPreviewUrl($focus), 'personPreview');
    $marriage = collect($preview['person']['marriages'])->firstWhere('spouse.id', $wifeA->id);

    $before = databaseRows();

    $this->actingAs($user)->put(route('marriages.update', $marriage['id']), [
        'marriage' => [...$marriage['date_parts'], 'year' => 1881, 'locality_id' => $marriage['locality_id']],
    ])->assertSessionHasNoErrors()->assertRedirect();

    $diff = rowDiff($before, databaseRows());

    expect(array_keys($diff['tables']))->toBe(['events']);
    expect(array_column(array_column($diff['tables']['events']['changed'], 'after'), 'id'))->toBe([$marriageA->id]);
    expect($diff['tables']['events']['changed'][0]['before']['date_year'])->toBe(1880);
    expect($diff['tables']['events']['changed'][0]['after']['date_year'])->toBe(1881);
    expect($diff['tables']['events']['removed'])->toBe([]);
    expect($diff['tables']['events']['added'])->toBe([]);
    expect($diff['files'])->toBe(['removed' => [], 'added' => []]);
});

test('linking a source to a marriage from the preview adds exactly that one link', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $scene = familyScene($user);
    extract($scene);

    $preview = readInertiaProp($this, $user, personPreviewUrl($focus), 'personPreview');
    $marriage = collect($preview['person']['marriages'])->firstWhere('spouse.id', $wifeB->id);

    $before = databaseRows();

    $this->actingAs($user)->post(route('events.sources.store', $marriage['id']), ['source_id' => $sourceBystander->id])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $diff = rowDiff($before, databaseRows());

    expect(array_keys($diff['tables']))->toBe(['event_source']);
    expect(describeAddedRows($diff))->toBe(['event_source' => ["{$marriageB->id}:{$sourceBystander->id}"]]);
    expect($diff['tables']['event_source']['removed'])->toBe([]);
    expect($diff['tables']['event_source']['changed'])->toBe([]);
    expect($diff['files'])->toBe(['removed' => [], 'added' => []]);
});

test('unlinking a source from a marriage removes only that link and keeps the other facts confirmed', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $scene = familyScene($user);
    extract($scene);

    $preview = readInertiaProp($this, $user, personPreviewUrl($focus), 'personPreview');
    $marriage = collect($preview['person']['marriages'])->firstWhere('spouse.id', $wifeA->id);
    $source = collect($marriage['sources'])->firstWhere('id', $sourceShared->id);

    $before = databaseRows();

    $this->actingAs($user)->delete(route('events.sources.destroy', [$marriage['id'], $source['id']]))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $diff = rowDiff($before, databaseRows());

    expect(array_keys($diff['tables']))->toBe(['event_source']);
    expect($diff['tables']['event_source']['added'])->toBe([]);
    expect($diff['tables']['event_source']['changed'])->toBe([]);
    expect(array_map(fn (array $row): string => $row['event_id'].':'.$row['source_id'], $diff['tables']['event_source']['removed']))
        ->toBe(["{$marriageA->id}:{$sourceShared->id}"]);
    expect($diff['files'])->toBe(['removed' => [], 'added' => []]);
});

test('setting an entry number on a link changes only that one event_source row', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $scene = familyScene($user);
    extract($scene);

    $preview = readInertiaProp($this, $user, personPreviewUrl($focus), 'personPreview');
    $marriage = collect($preview['person']['marriages'])->firstWhere('spouse.id', $wifeA->id);
    $source = collect($marriage['sources'])->firstWhere('id', $sourceShared->id);

    $before = databaseRows();

    $this->actingAs($user)->patch(route('events.sources.update', [$marriage['id'], $source['id']]), ['entry_number' => '95'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $diff = rowDiff($before, databaseRows());

    expect(array_keys($diff['tables']))->toBe(['event_source']);
    expect($diff['tables']['event_source']['added'])->toBe([]);
    expect($diff['tables']['event_source']['removed'])->toBe([]);
    expect($diff['tables']['event_source']['changed'])->toHaveCount(1);

    $change = $diff['tables']['event_source']['changed'][0];

    expect($change['before']['event_id'])->toBe($marriageA->id);
    expect($change['before']['source_id'])->toBe($sourceShared->id);
    // updated_at is bumped by withTimestamps but may land in the same second as creation.
    $changedColumns = array_keys(array_diff_assoc($change['after'], $change['before']));
    expect($changedColumns)->toContain('entry_number');
    expect(array_diff($changedColumns, ['entry_number', 'updated_at']))->toBe([]);
    expect($change['after']['entry_number'])->toBe('95');
    expect($diff['files'])->toBe(['removed' => [], 'added' => []]);
});

test('clearing the legacy entry number of a source changes only that one sources.entry_number value', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $scene = familyScene($user);
    extract($scene);

    $sourceShared->update(['entry_number' => '47']);

    $before = databaseRows();

    $this->actingAs($user)->delete(route('sources.legacy-entry-number.destroy', $sourceShared))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $diff = rowDiff($before, databaseRows());

    expect(array_keys($diff['tables']))->toBe(['sources']);
    expect($diff['tables']['sources']['added'])->toBe([]);
    expect($diff['tables']['sources']['removed'])->toBe([]);
    expect($diff['tables']['sources']['changed'])->toHaveCount(1);

    $change = $diff['tables']['sources']['changed'][0];

    expect($change['before']['id'])->toBe($sourceShared->id);
    expect($change['before']['entry_number'])->toBe('47');
    expect($change['after']['entry_number'])->toBeNull();
    expect(array_diff(array_keys(array_diff_assoc($change['after'], $change['before'])), ['entry_number', 'updated_at']))->toBe([]);
    expect($diff['files'])->toBe(['removed' => [], 'added' => []]);
});

test('saving a gravestone touches only its source row, its buried list and the burial it fills in', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $scene = familyScene($user);
    extract($scene);

    $cemetery = Cemetery::factory()->for($user->tree)->unnamed()->create();

    $before = databaseRows();

    $this->actingAs($user)->put(route('sources.update', $sourceOther), [
        'kind' => 'gravestone',
        'cemetery_id' => $cemetery->id,
        'buried_person_ids' => [$focus->id, $loneMan->id],
    ])->assertSessionHasNoErrors()->assertRedirect();

    $diff = rowDiff($before, databaseRows());

    expect(array_keys($diff['tables']))->toEqualCanonicalizing(['sources', 'buried_people', 'events', 'event_participants']);

    expect(array_column($diff['tables']['sources']['changed'], 'after'))->toHaveCount(1);
    expect($diff['tables']['sources']['changed'][0]['after']['id'])->toBe($sourceOther->id);
    expect($diff['tables']['sources']['changed'][0]['after']['cemetery_id'])->toBe($cemetery->id);

    expect(writeSortedKeys(array_map(fn (array $row): string => $row['source_id'].':'.$row['person_id'], $diff['tables']['buried_people']['added'])))
        ->toBe(writeSortedKeys(["{$sourceOther->id}:{$focus->id}", "{$sourceOther->id}:{$loneMan->id}"]));

    expect(array_column($diff['tables']['events']['added'], 'type'))->toBe(['burial']);
    expect($diff['tables']['events']['added'][0]['locality_id'])->toBe($cemetery->locality_id);
    expect($diff['tables']['events']['added'][0]['cemetery_id'])->toBe($cemetery->id);
    expect(array_column($diff['tables']['event_participants']['added'], 'person_id'))->toBe([$focus->id]);

    foreach ($diff['tables'] as $table) {
        expect($table['removed'])->toBe([]);
    }

    expect($diff['tables']['events']['changed'])->toBe([]);
    expect($diff['tables']['event_participants']['changed'])->toBe([]);
    expect($diff['tables']['buried_people']['changed'])->toBe([]);
    expect($diff['files'])->toBe(['removed' => [], 'added' => []]);
});
