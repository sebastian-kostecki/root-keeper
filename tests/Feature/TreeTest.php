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

    $this->actingAs($user)->get(route('persons.show', $proband))
        ->assertInertia(fn ($page) => $page
            ->component('persons/show')
            ->where('isRootPerson', true));

    $this->actingAs($user)->get(route('persons.show', $other))
        ->assertInertia(fn ($page) => $page
            ->component('persons/show')
            ->where('isRootPerson', false));
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
    expect($sheet['unions'][0]['label'])->toBe("{$earlierWife->displayName()} · ślub 1880");
    expect($sheet['unions'][0]['is_marriage'])->toBeTrue();
    expect($sheet['unions'][0]['marriage_year'])->toBe('1880');
    expect($sheet['unions'][2]['label'])->toBe("{$coParent->displayName()} · bez ślubu");
    expect($sheet['unions'][2]['is_marriage'])->toBeFalse();
    expect($sheet['unions'][3]['label'])->toBe('Nieznany partner');
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
    expect($sheet['unions'][0]['label'])->toBe("{$wife->displayName()} · ślub 1880");
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
    expect(sheetFor($this, $user, $noParents)['parents']['focus'])->toBe([
        ['kind' => 'unknown', 'label' => 'Nieznany ojciec'],
        ['kind' => 'unknown', 'label' => 'Nieznana matka'],
    ]);

    $withMother = Person::factory()->for($tree)->create();
    $mother = Person::factory()->for($tree)->female()->create();
    linkParent($mother, $withMother);
    $slots = sheetFor($this, $user, $withMother)['parents']['focus'];
    expect($slots[0])->toBe(['kind' => 'unknown', 'label' => 'Nieznany ojciec']);
    expect($slots[1]['person']['id'])->toBe($mother->id);

    $withFather = Person::factory()->for($tree)->create();
    $father = Person::factory()->for($tree)->male()->create();
    linkParent($father, $withFather);
    $slots = sheetFor($this, $user, $withFather)['parents']['focus'];
    expect($slots[0]['person']['id'])->toBe($father->id);
    expect($slots[1])->toBe(['kind' => 'unknown', 'label' => 'Nieznana matka']);

    $withUnknownGender = Person::factory()->for($tree)->create();
    $parent = Person::factory()->for($tree)->create();
    linkParent($parent, $withUnknownGender);
    $slots = sheetFor($this, $user, $withUnknownGender)['parents']['focus'];
    expect($slots[0]['person']['id'])->toBe($parent->id);
    expect($slots[1])->toBe(['kind' => 'unknown', 'label' => 'Nieznany rodzic']);
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
    expect($grandparents[1])->toBe(['kind' => 'unknown', 'label' => 'Nieznana matka']);
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
