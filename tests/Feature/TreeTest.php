<?php

use App\Actions\Trees\SetTreeRootPerson;
use App\Enums\ParticipantRole;
use App\Models\Event;
use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

test('setting the proband stores root_person_id and flashes a toast', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();

    $response = $this->actingAs($user)->put(route('tree.root-person.update', $person));

    $response->assertRedirect();
    $response->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Ustawiono probanta']);
    expect($user->tree->fresh()->root_person_id)->toBe($person->id);
});

test('setting the proband for a person from another users tree returns not found and changes nothing', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $person = Person::factory()->for($otherUser->tree)->create();

    $this->actingAs($user)->put(route('tree.root-person.update', $person))->assertNotFound();

    expect($user->tree->fresh()->root_person_id)->toBeNull();
    expect($otherUser->tree->fresh()->root_person_id)->toBeNull();
});

test('the proband action refuses a person from another tree', function () {
    $user = User::factory()->create();
    $foreign = Person::factory()->create();

    expect(fn () => app(SetTreeRootPerson::class)->handle($user->tree, $foreign))
        ->toThrow(NotFoundHttpException::class);

    expect($user->tree->fresh()->root_person_id)->toBeNull();
});

test('setting a new proband replaces the old one', function () {
    $user = User::factory()->create();
    $first = Person::factory()->for($user->tree)->create();
    $second = Person::factory()->for($user->tree)->create();

    $this->actingAs($user)->put(route('tree.root-person.update', $first));
    $this->actingAs($user)->put(route('tree.root-person.update', $second));

    expect($user->tree->fresh()->root_person_id)->toBe($second->id);
});

test('guests are redirected to the login page when setting the proband', function () {
    $person = Person::factory()->create();

    $this->put(route('tree.root-person.update', $person))->assertRedirect(route('login'));
});

test('deleting the proband person clears root_person_id', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $user->tree->update(['root_person_id' => $person->id]);

    $this->actingAs($user)->delete(route('persons.destroy', $person))->assertRedirect(route('persons.index'));

    expect(Person::find($person->id))->toBeNull();
    expect($user->tree->fresh()->root_person_id)->toBeNull();
});

test('deleting the user account succeeds while a proband is set', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $user->tree->update(['root_person_id' => $person->id]);

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasNoErrors();

    expect(User::find($user->id))->toBeNull();
    expect(Person::find($person->id))->toBeNull();
});

test('person show exposes whether the person is the proband', function () {
    $user = User::factory()->create();
    $proband = Person::factory()->for($user->tree)->create();
    $other = Person::factory()->for($user->tree)->create();
    $user->tree->update(['root_person_id' => $proband->id]);

    $this->actingAs($user)->get(personPreviewUrl($proband))
        ->assertInertia(fn ($page) => $page
            ->component('persons/index')
            ->where('personPreview.isRootPerson', true));

    $this->actingAs($user)->get(personPreviewUrl($other))
        ->assertInertia(fn ($page) => $page
            ->component('persons/index')
            ->where('personPreview.isRootPerson', false));
});

/**
 * Fetch the sheet prop of the tree page centred on the person.
 *
 * @return array<string, mixed>
 */
function sheetFor($test, User $user, Person $focus, ?string $spouse = null): array
{
    $response = $test->actingAs($user)->get(route('tree.show', ['person' => $focus->id, 'spouse' => $spouse]));

    $response->assertOk();

    return $response->viewData('page')['props']['sheet'];
}

test('guests are redirected to the login page from the tree routes', function () {
    $person = Person::factory()->create();

    $this->get(route('tree.index'))->assertRedirect(route('login'));
    $this->get(route('tree.show', $person))->assertRedirect(route('login'));
});

test('the tree index has no sheet without a proband', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('tree.index'))
        ->assertInertia(fn ($page) => $page
            ->component('tree/show')
            ->where('sheet', null)
            ->where('rootPersonId', null));
});

test('the tree index centres the sheet on the proband', function () {
    $user = User::factory()->create();
    $proband = Person::factory()->for($user->tree)->create();
    $user->tree->update(['root_person_id' => $proband->id]);

    $this->actingAs($user)->get(route('tree.index'))
        ->assertInertia(fn ($page) => $page
            ->component('tree/show')
            ->where('rootPersonId', $proband->id)
            ->where('sheet.focus.id', $proband->id));
});

test('the tree show page returns not found for a person from another users tree', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for(User::factory()->create()->tree)->create();

    $this->actingAs($user)->get(route('tree.show', $person))->assertNotFound();
});

test('unions are ordered marriage by date, then co-parent, then unknown, each with its children', function () {
    $user = User::factory()->create();
    $tree = $user->tree;
    $focus = Person::factory()->for($tree)->male()->create();
    $laterWife = Person::factory()->for($tree)->female()->create();
    $earlierWife = Person::factory()->for($tree)->female()->create();
    $coParent = Person::factory()->for($tree)->female()->create();

    attachMarriage($focus, $laterWife, 1900);
    attachMarriage($focus, $earlierWife, 1880);

    $childOfEarlier = Person::factory()->for($tree)->create();
    $childOfLater = Person::factory()->for($tree)->create();
    $childOfCoParent = Person::factory()->for($tree)->create();
    $childOfUnknown = Person::factory()->for($tree)->create();

    foreach ([[$earlierWife, $childOfEarlier], [$laterWife, $childOfLater], [$coParent, $childOfCoParent]] as [$other, $child]) {
        linkParent($focus, $child);
        linkParent($other, $child);
    }
    linkParent($focus, $childOfUnknown);

    $sheet = sheetFor($this, $user, $focus);

    expect(collect($sheet['unions'])->pluck('key')->all())->toBe([
        "person-{$earlierWife->id}",
        "person-{$laterWife->id}",
        "person-{$coParent->id}",
        'unknown',
    ]);
    expect(collect($sheet['unions'])->pluck('children_count')->all())->toBe([1, 1, 1, 1]);
    expect($sheet['unions'][0])->not->toHaveKey('label');
    expect($sheet['unions'][0]['partner_name'])->toBe($earlierWife->displayName());
    expect($sheet['unions'][0]['is_marriage'])->toBeTrue();
    expect($sheet['unions'][0]['marriage_year'])->toBe('1880');
    expect($sheet['unions'][2]['partner_name'])->toBe($coParent->displayName());
    expect($sheet['unions'][2]['marriage_year'])->toBeNull();
    expect($sheet['unions'][2]['is_marriage'])->toBeFalse();
    expect($sheet['unions'][3]['partner_name'])->toBeNull();
    expect($sheet['unions'][3]['is_marriage'])->toBeFalse();
    expect($sheet['selected_union'])->toBe("person-{$earlierWife->id}");

    $expectations = [
        (string) $earlierWife->id => $childOfEarlier,
        (string) $laterWife->id => $childOfLater,
        (string) $coParent->id => $childOfCoParent,
        'unknown' => $childOfUnknown,
    ];

    foreach ($expectations as $spouse => $child) {
        $selected = sheetFor($this, $user, $focus, $spouse);

        expect(collect($selected['children'])->pluck('id')->all())->toBe([$child->id]);
    }

    $unknown = sheetFor($this, $user, $focus, 'unknown');
    expect($unknown['partner'])->toBe(['kind' => 'unknown', 'label' => 'Nieznany partner']);
    expect($unknown['selected_union'])->toBe('unknown');
    expect($unknown['parents']['partner'])->toBe([['kind' => 'empty'], ['kind' => 'empty']]);
});

test('an invalid or foreign spouse falls back to the first union', function () {
    $user = User::factory()->create();
    $focus = Person::factory()->for($user->tree)->male()->create();
    $wife = Person::factory()->for($user->tree)->female()->create();
    attachMarriage($focus, $wife, 1880);
    $foreign = Person::factory()->for(User::factory()->create()->tree)->create();

    foreach (['garbage', (string) $foreign->id, '999999', ''] as $spouse) {
        $sheet = sheetFor($this, $user, $focus, $spouse);

        expect($sheet['selected_union'])->toBe("person-{$wife->id}");
        expect($sheet['partner']['person']['id'])->toBe($wife->id);
    }
});

test('repeat marriages to the same partner form one union labelled with the earliest marriage', function () {
    $user = User::factory()->create();
    $focus = Person::factory()->for($user->tree)->male()->create();
    $wife = Person::factory()->for($user->tree)->female()->create();
    attachMarriage($focus, $wife, 1890);
    attachMarriage($focus, $wife, 1880);

    $sheet = sheetFor($this, $user, $focus);

    expect($sheet['unions'])->toHaveCount(1);
    expect($sheet['unions'][0]['partner_name'])->toBe($wife->displayName());
    expect($sheet['unions'][0]['marriage_year'])->toBe('1880');
});

test('a focus without unions has an empty partner column', function () {
    $user = User::factory()->create();
    $focus = Person::factory()->for($user->tree)->create();

    $sheet = sheetFor($this, $user, $focus);

    expect($sheet['unions'])->toBe([]);
    expect($sheet['selected_union'])->toBeNull();
    expect($sheet['partner'])->toBe(['kind' => 'empty']);
    expect($sheet['parents']['partner'])->toBe([['kind' => 'empty'], ['kind' => 'empty']]);
    expect($sheet['grandparents']['partner'])->toBe(array_fill(0, 4, ['kind' => 'empty']));
    expect($sheet['children'])->toBe([]);
    expect($sheet['focus_side'])->toBe('left');
});

test('parents are ordered male first regardless of insertion order', function (bool $motherFirst) {
    $user = User::factory()->create();
    $focus = Person::factory()->for($user->tree)->create();
    $mother = Person::factory()->for($user->tree)->female()->create();
    $father = Person::factory()->for($user->tree)->male()->create();

    foreach ($motherFirst ? [$mother, $father] : [$father, $mother] as $parent) {
        linkParent($parent, $focus);
    }

    $sheet = sheetFor($this, $user, $focus);

    expect($sheet['parents']['focus'][0]['person']['id'])->toBe($father->id);
    expect($sheet['parents']['focus'][1]['person']['id'])->toBe($mother->id);
})->with([
    'mother linked first' => [true],
    'father linked first' => [false],
]);

test('missing parents are labelled placeholders depending on the known parent', function () {
    $user = User::factory()->create();
    $tree = $user->tree;

    $noParents = Person::factory()->for($tree)->create();
    $slots = sheetFor($this, $user, $noParents)['parents']['focus'];
    expect(array_map(unknownShape(...), $slots))->toBe([
        ['kind' => 'unknown', 'label' => 'Nieznany ojciec', 'role' => 'father', 'parent_id' => $noParents->id],
        ['kind' => 'unknown', 'label' => 'Nieznana matka', 'role' => 'mother', 'parent_id' => $noParents->id],
    ]);

    $withMother = Person::factory()->for($tree)->create();
    $mother = Person::factory()->for($tree)->female()->create();
    linkParent($mother, $withMother);
    $slots = sheetFor($this, $user, $withMother)['parents']['focus'];
    expect(unknownShape($slots[0]))->toBe(['kind' => 'unknown', 'label' => 'Nieznany ojciec', 'role' => 'father', 'parent_id' => $withMother->id]);
    expect($slots[1]['person']['id'])->toBe($mother->id);

    $withFather = Person::factory()->for($tree)->create();
    $father = Person::factory()->for($tree)->male()->create();
    linkParent($father, $withFather);
    $slots = sheetFor($this, $user, $withFather)['parents']['focus'];
    expect($slots[0]['person']['id'])->toBe($father->id);
    expect(unknownShape($slots[1]))->toBe(['kind' => 'unknown', 'label' => 'Nieznana matka', 'role' => 'mother', 'parent_id' => $withFather->id]);

    $withUnknownGender = Person::factory()->for($tree)->create();
    $parent = Person::factory()->for($tree)->create();
    linkParent($parent, $withUnknownGender);
    $slots = sheetFor($this, $user, $withUnknownGender)['parents']['focus'];
    expect($slots[0]['person']['id'])->toBe($parent->id);
    expect(unknownShape($slots[1]))->toBe(['kind' => 'unknown', 'label' => 'Nieznany rodzic', 'role' => 'parent', 'parent_id' => $withUnknownGender->id]);
});

/**
 * Reduce an unknown slot to its kind, label and the slot target's person and role.
 *
 * @param  array<string, mixed>  $slot
 * @return array<string, mixed>
 */
function unknownShape(array $slot): array
{
    return [
        'kind' => $slot['kind'],
        'label' => $slot['label'],
        'role' => $slot['parent_of']['role'] ?? null,
        'parent_id' => $slot['parent_of']['id'] ?? null,
    ];
}

test('a parent slot excludes the person, their parents and their descendants', function () {
    $user = User::factory()->create();
    $tree = $user->tree;
    $focus = Person::factory()->for($tree)->create();
    $father = Person::factory()->for($tree)->male()->create();
    $child = Person::factory()->for($tree)->create();
    $grandchild = Person::factory()->for($tree)->create();
    $stranger = Person::factory()->for($tree)->create();

    linkParent($father, $focus);
    linkParent($focus, $child);
    linkParent($child, $grandchild);

    $motherSlot = sheetFor($this, $user, $focus)['parents']['focus'][1];

    expect($motherSlot['parent_of']['display_name'])->toBe($focus->displayName());
    expect($motherSlot['parent_of']['last_name'])->toBe($focus->last_name);
    expect($motherSlot['parent_of']['excluded_ids'])->toEqualCanonicalizing([$focus->id, $father->id, $child->id, $grandchild->id]);
    expect($motherSlot['parent_of']['excluded_ids'])->not->toContain($stranger->id);
});

test('a grandparent slot belongs to the known parent above it', function () {
    $user = User::factory()->create();
    $tree = $user->tree;
    $focus = Person::factory()->for($tree)->create();
    $father = Person::factory()->for($tree)->male()->create();

    linkParent($father, $focus);

    $grandparents = sheetFor($this, $user, $focus)['grandparents']['focus'];

    expect(unknownShape($grandparents[0]))->toBe(['kind' => 'unknown', 'label' => 'Nieznany ojciec', 'role' => 'father', 'parent_id' => $father->id]);
    expect(unknownShape($grandparents[1]))->toBe(['kind' => 'unknown', 'label' => 'Nieznana matka', 'role' => 'mother', 'parent_id' => $father->id]);
});

test('the person candidates are an optional prop ordered like the person list, with gender', function () {
    $user = User::factory()->create();
    $tree = $user->tree;
    $focus = Person::factory()->for($tree)->create(['first_name' => 'Jan', 'last_name' => 'Kowalski']);
    $noSurname = Person::factory()->for($tree)->male()->create(['first_name' => 'Adam', 'last_name' => null]);
    $woman = Person::factory()->for($tree)->female()->create(['first_name' => 'Anna', 'last_name' => 'Nowak']);
    Person::factory()->for(User::factory()->create()->tree)->create();

    $this->actingAs($user)->get(route('tree.show', $focus))
        ->assertInertia(fn ($page) => $page
            ->component('tree/show')
            ->missing('personCandidates'));

    $version = $this->actingAs($user)->get(route('tree.show', $focus), ['X-Inertia' => 'true'])->headers->get('X-Inertia-Version');

    $response = $this->actingAs($user)->get(route('tree.show', $focus), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $version,
        'X-Inertia-Partial-Data' => 'personCandidates',
        'X-Inertia-Partial-Component' => 'tree/show',
    ]);

    $response->assertOk();

    $candidates = $response->json('props.personCandidates');

    expect(collect($candidates)->pluck('id')->all())->toBe([$focus->id, $woman->id, $noSurname->id]);
    expect(collect($candidates)->pluck('gender')->all())->toBe([null, 'female', 'male']);
});

test('the tree page exposes the person form options', function () {
    $user = User::factory()->create();
    $focus = Person::factory()->for($user->tree)->create();

    $this->actingAs($user)->get(route('tree.show', $focus))
        ->assertInertia(fn ($page) => $page
            ->has('genders', 2)
            ->has('religions', 6));
});

test('grandparents are filled above known parents and empty above placeholders', function () {
    $user = User::factory()->create();
    $tree = $user->tree;
    $focus = Person::factory()->for($tree)->create();
    $father = Person::factory()->for($tree)->male()->create();
    $grandfather = Person::factory()->for($tree)->male()->create();

    linkParent($father, $focus);
    linkParent($grandfather, $father);

    $grandparents = sheetFor($this, $user, $focus)['grandparents']['focus'];

    expect($grandparents[0]['person']['id'])->toBe($grandfather->id);
    expect(unknownShape($grandparents[1]))->toBe(['kind' => 'unknown', 'label' => 'Nieznana matka', 'role' => 'mother', 'parent_id' => $father->id]);
    expect($grandparents[2])->toBe(['kind' => 'empty']);
    expect($grandparents[3])->toBe(['kind' => 'empty']);
});

test('the partner block carries the partners parents and grandparents', function () {
    $user = User::factory()->create();
    $tree = $user->tree;
    $focus = Person::factory()->for($tree)->male()->create();
    $wife = Person::factory()->for($tree)->female()->create();
    $wifeFather = Person::factory()->for($tree)->male()->create();
    $wifeGrandmother = Person::factory()->for($tree)->female()->create();

    attachMarriage($focus, $wife, 1900);
    linkParent($wifeFather, $wife);
    linkParent($wifeGrandmother, $wifeFather);

    $sheet = sheetFor($this, $user, $focus);

    expect($sheet['parents']['partner'][0]['person']['id'])->toBe($wifeFather->id);
    expect($sheet['grandparents']['partner'][1]['person']['id'])->toBe($wifeGrandmother->id);
});

test('the focus sits right only next to a partner of a lower gender rank', function () {
    $user = User::factory()->create();
    $tree = $user->tree;

    $woman = Person::factory()->for($tree)->female()->create();
    $husband = Person::factory()->for($tree)->male()->create();
    attachMarriage($woman, $husband, 1900);
    expect(sheetFor($this, $user, $woman)['focus_side'])->toBe('right');
    expect(sheetFor($this, $user, $husband)['focus_side'])->toBe('left');

    $man = Person::factory()->for($tree)->male()->create();
    $wife = Person::factory()->for($tree)->female()->create();
    attachMarriage($man, $wife, 1900);
    expect(sheetFor($this, $user, $man)['focus_side'])->toBe('left');

    $loner = Person::factory()->for($tree)->female()->create();
    expect(sheetFor($this, $user, $loner)['focus_side'])->toBe('left');

    $motherOfUnknown = Person::factory()->for($tree)->female()->create();
    $child = Person::factory()->for($tree)->create();
    linkParent($motherOfUnknown, $child);
    $sheet = sheetFor($this, $user, $motherOfUnknown);
    expect($sheet['partner']['kind'])->toBe('unknown');
    expect($sheet['focus_side'])->toBe('left');
});

test('the lifespan label covers approximate, missing and unknown years', function () {
    $user = User::factory()->create();
    $tree = $user->tree;

    $aliveKnownBirth = Person::factory()->for($tree)->create();
    attachBirth($aliveKnownBirth, 1850);
    $sheet = sheetFor($this, $user, $aliveKnownBirth);
    expect($sheet['focus']['lifespan'])->toBe('1850 –');
    expect($sheet['focus']['is_deceased'])->toBeFalse();

    $approximate = Person::factory()->for($tree)->create();
    $birth = Event::factory()->for($tree)->birth()->approximate()->create([
        'date_year' => 1850, 'date_month' => null, 'date_day' => null,
    ]);
    $birth->participants()->attach($approximate, ['role' => ParticipantRole::Subject]);
    attachDeath($approximate, 1912);
    $sheet = sheetFor($this, $user, $approximate);
    expect($sheet['focus']['lifespan'])->toBe('ok. 1850 – 1912');
    expect($sheet['focus']['is_deceased'])->toBeTrue();

    $unknownBirth = Person::factory()->for($tree)->create();
    attachDeath($unknownBirth, 1912);
    expect(sheetFor($this, $user, $unknownBirth)['focus']['lifespan'])->toBe('? – 1912');

    $deathWithoutYear = Person::factory()->for($tree)->create();
    attachBirth($deathWithoutYear, 1850);
    attachDeath($deathWithoutYear, null);
    expect(sheetFor($this, $user, $deathWithoutYear)['focus']['lifespan'])->toBe('1850 – ?');

    $nothing = Person::factory()->for($tree)->create();
    expect(sheetFor($this, $user, $nothing)['focus']['lifespan'])->toBeNull();
});

test('the sheet query count does not grow with the size of the tree', function () {
    $countQueries = function (int $extraPeople) {
        $user = User::factory()->create();
        $tree = $user->tree;
        $focus = Person::factory()->for($tree)->male()->create();
        $wife = Person::factory()->for($tree)->female()->create();
        $coParent = Person::factory()->for($tree)->female()->create();
        attachMarriage($focus, $wife, 1880);
        attachBirth($focus, 1850);

        for ($i = 0; $i < $extraPeople; $i++) {
            $child = Person::factory()->for($tree)->create();
            attachBirth($child, 1900 + $i);
            linkParent($focus, $child);
            linkParent($i % 2 === 0 ? $wife : $coParent, $child);

            $father = Person::factory()->for($tree)->male()->create();
            attachBirth($father, 1800 + $i);
            linkParent($father, $i % 2 === 0 ? $wife : $coParent);

            $extraCoParent = Person::factory()->for($tree)->female()->create();
            attachBirth($extraCoParent, 1860 + $i);
            $halfSibling = Person::factory()->for($tree)->create();
            linkParent($focus, $halfSibling);
            linkParent($extraCoParent, $halfSibling);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->get(route('tree.show', $focus))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    expect($countQueries(12))->toBe($countQueries(2));
});

test('a partner union adds the pair as parents and hides the people who cannot be their child', function () {
    $user = User::factory()->create();
    $tree = $user->tree;
    $focus = Person::factory()->for($tree)->male()->create(['last_name' => 'Kowalski']);
    $partner = Person::factory()->for($tree)->female()->create(['last_name' => 'Nowak']);
    $focusParent = Person::factory()->for($tree)->create();
    $sharedChild = Person::factory()->for($tree)->create();
    $soloChild = Person::factory()->for($tree)->create();
    $outsideChild = Person::factory()->for($tree)->create();
    $stranger = Person::factory()->for($tree)->create();
    attachMarriage($focus, $partner, 1950);
    linkParent($focusParent, $focus);
    linkParent($focus, $sharedChild);
    linkParent($partner, $sharedChild);
    linkParent($focus, $soloChild);
    linkParent($focus, $outsideChild);
    linkParent(Person::factory()->for($tree)->create(), $outsideChild);

    $addChild = sheetFor($this, $user, $focus, (string) $partner->id)['add_child'];

    expect(collect($addChild['parents'])->pluck('id')->all())->toBe([$focus->id, $partner->id]);
    expect($addChild['default_last_name'])->toBe('Kowalski');
    expect($addChild['excluded_ids'])->toContain($focus->id, $partner->id, $focusParent->id, $sharedChild->id);
    expect($addChild['blocked_ids'])->toBe([$outsideChild->id]);
    expect($addChild['excluded_ids'])->not->toContain($stranger->id, $soloChild->id, $outsideChild->id);
});

test('the unknown union and a person without a union add the focus alone and exclude their children', function () {
    $user = User::factory()->create();
    $tree = $user->tree;
    $focus = Person::factory()->for($tree)->create();
    $child = Person::factory()->for($tree)->create();
    linkParent($focus, $child);

    $unknown = sheetFor($this, $user, $focus, 'unknown')['add_child'];

    expect(collect($unknown['parents'])->pluck('id')->all())->toBe([$focus->id]);
    expect($unknown['excluded_ids'])->toContain($focus->id, $child->id);

    $loner = Person::factory()->for($tree)->create();
    $none = sheetFor($this, $user, $loner)['add_child'];

    expect(collect($none['parents'])->pluck('id')->all())->toBe([$loner->id]);
    expect($none['excluded_ids'])->toBe([$loner->id]);
    expect($none['blocked_ids'])->toBe([]);
});

test('a lone parent is blocked by another parent of the same known sex only', function () {
    $user = User::factory()->create();
    $tree = $user->tree;
    $focus = Person::factory()->for($tree)->male()->create();
    $withFather = Person::factory()->for($tree)->create();
    $withMother = Person::factory()->for($tree)->create();
    $withUnknownSexParent = Person::factory()->for($tree)->create();
    linkParent(Person::factory()->for($tree)->male()->create(), $withFather);
    linkParent(Person::factory()->for($tree)->female()->create(), $withMother);
    linkParent(Person::factory()->for($tree)->create(), $withUnknownSexParent);

    $addChild = sheetFor($this, $user, $focus)['add_child'];

    expect($addChild['blocked_ids'])->toBe([$withFather->id]);
});

test('a pair is blocked for a child whose kept parent has the sex of the newly added one', function () {
    $user = User::factory()->create();
    $tree = $user->tree;
    $focus = Person::factory()->for($tree)->male()->create();
    $partner = Person::factory()->for($tree)->male()->create();
    $womanFocus = Person::factory()->for($tree)->female()->create();
    $womanPartner = Person::factory()->for($tree)->female()->create();
    $keepsFocus = Person::factory()->for($tree)->create();
    $keepsWoman = Person::factory()->for($tree)->create();
    attachMarriage($focus, $partner, 1950);
    attachMarriage($womanFocus, $womanPartner, 1950);
    linkParent($focus, $keepsFocus);
    linkParent($womanFocus, $keepsWoman);

    $menPair = sheetFor($this, $user, $focus, (string) $partner->id)['add_child'];
    $womenPair = sheetFor($this, $user, $womanFocus, (string) $womanPartner->id)['add_child'];

    expect($menPair['blocked_ids'])->toContain($keepsFocus->id)
        ->and($womenPair['blocked_ids'])->toContain($keepsWoman->id);
});

test('a mixed pair keeps a child of one parent selectable', function () {
    $user = User::factory()->create();
    $tree = $user->tree;
    $focus = Person::factory()->for($tree)->male()->create();
    $partner = Person::factory()->for($tree)->female()->create();
    $keepsFocus = Person::factory()->for($tree)->create();
    attachMarriage($focus, $partner, 1950);
    linkParent($focus, $keepsFocus);

    expect(sheetFor($this, $user, $focus, (string) $partner->id)['add_child']['blocked_ids'])->toBe([]);
});

test('the default surname is the first mans, else the focus persons', function () {
    $user = User::factory()->create();
    $tree = $user->tree;
    $woman = Person::factory()->for($tree)->female()->create(['last_name' => 'Nowak']);
    $man = Person::factory()->for($tree)->male()->create(['last_name' => 'Kowalski']);
    $otherWoman = Person::factory()->for($tree)->female()->create(['last_name' => 'Wiśniewska']);
    attachMarriage($woman, $man, 1950);
    attachMarriage($man, $otherWoman, 1960);

    expect(sheetFor($this, $user, $woman)['add_child']['default_last_name'])->toBe('Kowalski');
    expect(sheetFor($this, $user, $man, (string) $otherWoman->id)['add_child']['default_last_name'])->toBe('Kowalski');

    $twin = Person::factory()->for($tree)->female()->create(['last_name' => 'Zielińska']);
    attachMarriage($otherWoman, $twin, 1970);

    expect(sheetFor($this, $user, $otherWoman, (string) $twin->id)['add_child']['default_last_name'])->toBe('Wiśniewska');
});

test('the spouse exclusions cover the centre, spouses, ancestors and descendants only', function () {
    $user = User::factory()->create();
    $tree = $user->tree;
    $focus = Person::factory()->for($tree)->male()->create();
    $spouse = Person::factory()->for($tree)->female()->create();
    $parent = Person::factory()->for($tree)->create();
    $grandparent = Person::factory()->for($tree)->create();
    $child = Person::factory()->for($tree)->create();
    $grandchild = Person::factory()->for($tree)->create();
    $sibling = Person::factory()->for($tree)->create();
    $coParent = Person::factory()->for($tree)->female()->create();
    $coParentChild = Person::factory()->for($tree)->create();
    $stranger = Person::factory()->for($tree)->create();

    attachMarriage($focus, $spouse, 1950);
    linkParent($parent, $focus);
    linkParent($grandparent, $parent);
    linkParent($parent, $sibling);
    linkParent($focus, $child);
    linkParent($child, $grandchild);
    linkParent($focus, $coParentChild);
    linkParent($coParent, $coParentChild);

    $excluded = sheetFor($this, $user, $focus)['add_spouse']['excluded_ids'];

    expect($excluded)->toContain($focus->id, $spouse->id, $parent->id, $grandparent->id, $child->id, $grandchild->id);
    expect($excluded)->not->toContain($sibling->id, $coParent->id, $stranger->id);
    expect($excluded)->toBe(array_values(array_unique($excluded)));
});

test('two known unmarried parents of the centre can be married, a married or incomplete pair cannot', function () {
    $user = User::factory()->create();
    $tree = $user->tree;
    $father = Person::factory()->for($tree)->male()->create();
    $mother = Person::factory()->for($tree)->female()->create();
    $focus = Person::factory()->for($tree)->create();
    linkParent($father, $focus);
    linkParent($mother, $focus);

    expect(sheetFor($this, $user, $focus)['unmarried_parents'])->toBe(['focus' => true, 'partner' => false]);

    attachMarriage($father, $mother, 1900);
    expect(sheetFor($this, $user, $focus)['unmarried_parents']['focus'])->toBeFalse();

    $lonelyChild = Person::factory()->for($tree)->create();
    linkParent(Person::factory()->for($tree)->male()->create(), $lonelyChild);
    expect(sheetFor($this, $user, $lonelyChild)['unmarried_parents']['focus'])->toBeFalse();
});

test('the partner block marks the partners unmarried parents only when there is a partner', function () {
    $user = User::factory()->create();
    $tree = $user->tree;
    $focus = Person::factory()->for($tree)->male()->create();
    $wife = Person::factory()->for($tree)->female()->create();
    $wifeFather = Person::factory()->for($tree)->male()->create();
    $wifeMother = Person::factory()->for($tree)->female()->create();
    linkParent($wifeFather, $wife);
    linkParent($wifeMother, $wife);

    expect(sheetFor($this, $user, $focus)['unmarried_parents'])->toBe(['focus' => false, 'partner' => false]);

    attachMarriage($focus, $wife, 1900);
    expect(sheetFor($this, $user, $focus)['unmarried_parents'])->toBe(['focus' => false, 'partner' => true]);
});
