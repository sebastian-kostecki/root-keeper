<?php

use App\Models\ParentChildRelation;
use App\Models\Person;
use App\Models\User;

test('guests are redirected to the login page from parent-child-relations routes', function () {
    $relation = ParentChildRelation::factory()->create();

    $this->post(route('parent-child-relations.store'), [])->assertRedirect(route('login'));
    $this->delete(route('parent-child-relations.destroy', $relation))->assertRedirect(route('login'));
});

test('adding a relation creates the row with the tree id, redirects back and flashes the toast', function () {
    $user = User::factory()->create();
    $parent = Person::factory()->for($user->tree)->create();
    $child = Person::factory()->for($user->tree)->create();

    $response = $this->from(route('persons.index'))
        ->actingAs($user)
        ->post(route('parent-child-relations.store'), ['parent_id' => $parent->id, 'child_id' => $child->id]);

    $response->assertRedirect(route('persons.index'));
    $response->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Dodano relację']);

    $this->assertDatabaseHas('parent_child_relations', [
        'tree_id' => $user->tree->id,
        'parent_id' => $parent->id,
        'child_id' => $child->id,
    ]);
});

test('a child may have two parents but a third is rejected and nothing is created', function () {
    $user = User::factory()->create();
    $parentOne = Person::factory()->for($user->tree)->create();
    $parentTwo = Person::factory()->for($user->tree)->create();
    $parentThree = Person::factory()->for($user->tree)->create();
    $child = Person::factory()->for($user->tree)->create();

    $this->actingAs($user)->post(route('parent-child-relations.store'), ['parent_id' => $parentOne->id, 'child_id' => $child->id]);
    $this->actingAs($user)->post(route('parent-child-relations.store'), ['parent_id' => $parentTwo->id, 'child_id' => $child->id]);

    $response = $this->actingAs($user)->post(route('parent-child-relations.store'), ['parent_id' => $parentThree->id, 'child_id' => $child->id]);

    $response->assertSessionHasErrors('relation');
    $this->assertDatabaseCount('parent_child_relations', 2);
});

test('linking a person to themselves is rejected on child_id', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();

    $response = $this->actingAs($user)->post(route('parent-child-relations.store'), ['parent_id' => $person->id, 'child_id' => $person->id]);

    $response->assertSessionHasErrors('child_id');
    $this->assertDatabaseCount('parent_child_relations', 0);
});

test('linking the same pair twice is rejected and one row remains', function () {
    $user = User::factory()->create();
    $parent = Person::factory()->for($user->tree)->create();
    $child = Person::factory()->for($user->tree)->create();

    $this->actingAs($user)->post(route('parent-child-relations.store'), ['parent_id' => $parent->id, 'child_id' => $child->id]);
    $response = $this->actingAs($user)->post(route('parent-child-relations.store'), ['parent_id' => $parent->id, 'child_id' => $child->id]);

    $response->assertSessionHasErrors('relation');
    $this->assertDatabaseCount('parent_child_relations', 1);
});

test('a direct cycle is rejected', function () {
    $user = User::factory()->create();
    $a = Person::factory()->for($user->tree)->create();
    $b = Person::factory()->for($user->tree)->create();

    $this->actingAs($user)->post(route('parent-child-relations.store'), ['parent_id' => $a->id, 'child_id' => $b->id]);

    $response = $this->actingAs($user)->post(route('parent-child-relations.store'), ['parent_id' => $b->id, 'child_id' => $a->id]);

    $response->assertSessionHasErrors('relation');
    $this->assertDatabaseCount('parent_child_relations', 1);
});

test('a deep cycle is rejected', function () {
    $user = User::factory()->create();
    $a = Person::factory()->for($user->tree)->create();
    $b = Person::factory()->for($user->tree)->create();
    $c = Person::factory()->for($user->tree)->create();

    $this->actingAs($user)->post(route('parent-child-relations.store'), ['parent_id' => $a->id, 'child_id' => $b->id]);
    $this->actingAs($user)->post(route('parent-child-relations.store'), ['parent_id' => $b->id, 'child_id' => $c->id]);

    $response = $this->actingAs($user)->post(route('parent-child-relations.store'), ['parent_id' => $c->id, 'child_id' => $a->id]);

    $response->assertSessionHasErrors('relation');
    $this->assertDatabaseCount('parent_child_relations', 2);
});

test('adding a non-cyclic relation across generations is accepted', function () {
    $user = User::factory()->create();
    $a = Person::factory()->for($user->tree)->create();
    $b = Person::factory()->for($user->tree)->create();
    $c = Person::factory()->for($user->tree)->create();

    $this->actingAs($user)->post(route('parent-child-relations.store'), ['parent_id' => $a->id, 'child_id' => $b->id]);
    $this->actingAs($user)->post(route('parent-child-relations.store'), ['parent_id' => $b->id, 'child_id' => $c->id]);

    $response = $this->actingAs($user)->post(route('parent-child-relations.store'), ['parent_id' => $a->id, 'child_id' => $c->id]);

    $response->assertSessionDoesntHaveErrors('relation');
    $this->assertDatabaseCount('parent_child_relations', 3);
    $this->assertDatabaseHas('parent_child_relations', ['parent_id' => $a->id, 'child_id' => $c->id]);
});

test('a parent_id or child_id from another tree fails validation and creates nothing', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $ownPerson = Person::factory()->for($user->tree)->create();
    $foreignPerson = Person::factory()->for($otherUser->tree)->create();

    $response = $this->actingAs($user)->post(route('parent-child-relations.store'), ['parent_id' => $foreignPerson->id, 'child_id' => $ownPerson->id]);

    $response->assertSessionHasErrors('parent_id');
    $this->assertDatabaseCount('parent_child_relations', 0);

    $response = $this->actingAs($user)->post(route('parent-child-relations.store'), ['parent_id' => $ownPerson->id, 'child_id' => $foreignPerson->id]);

    $response->assertSessionHasErrors('child_id');
    $this->assertDatabaseCount('parent_child_relations', 0);
});

test('removing a relation deletes only that row, keeps both people and flashes the toast', function () {
    $user = User::factory()->create();
    $parent = Person::factory()->for($user->tree)->create();
    $child = Person::factory()->for($user->tree)->create();
    $otherChild = Person::factory()->for($user->tree)->create();
    $relation = ParentChildRelation::factory()->create(['tree_id' => $user->tree->id, 'parent_id' => $parent->id, 'child_id' => $child->id]);
    $otherRelation = ParentChildRelation::factory()->create(['tree_id' => $user->tree->id, 'parent_id' => $parent->id, 'child_id' => $otherChild->id]);

    $response = $this->from(route('persons.index'))
        ->actingAs($user)
        ->delete(route('parent-child-relations.destroy', $relation));

    $response->assertRedirect(route('persons.index'));
    $response->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Usunięto relację']);

    $this->assertDatabaseMissing('parent_child_relations', ['id' => $relation->id]);
    $this->assertDatabaseHas('parent_child_relations', ['id' => $otherRelation->id]);
    $this->assertDatabaseHas('people', ['id' => $parent->id]);
    $this->assertDatabaseHas('people', ['id' => $child->id]);
});

test('removing another trees relation returns not found and the row remains', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $relation = ParentChildRelation::factory()->create(['tree_id' => $otherUser->tree->id]);

    $this->actingAs($user)->delete(route('parent-child-relations.destroy', $relation))
        ->assertNotFound();

    $this->assertDatabaseHas('parent_child_relations', ['id' => $relation->id]);
});

test('deleting a person removes the relations where they are parent and where they are child, and keeps the related people', function () {
    $user = User::factory()->create();
    $grandparent = Person::factory()->for($user->tree)->create();
    $middle = Person::factory()->for($user->tree)->create();
    $grandchild = Person::factory()->for($user->tree)->create();
    ParentChildRelation::factory()->create(['tree_id' => $user->tree->id, 'parent_id' => $grandparent->id, 'child_id' => $middle->id]);
    ParentChildRelation::factory()->create(['tree_id' => $user->tree->id, 'parent_id' => $middle->id, 'child_id' => $grandchild->id]);

    $this->actingAs($user)->delete(route('persons.destroy', $middle))->assertRedirect();

    $this->assertDatabaseMissing('people', ['id' => $middle->id]);
    $this->assertDatabaseCount('parent_child_relations', 0);
    $this->assertDatabaseHas('people', ['id' => $grandparent->id]);
    $this->assertDatabaseHas('people', ['id' => $grandchild->id]);
});

test('the preview returns parents and children with the relation id, ordered by birth date', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $parent = Person::factory()->for($user->tree)->create();

    $noBirth = Person::factory()->for($user->tree)->create();

    $laterYear = Person::factory()->for($user->tree)->create();
    attachBirth($laterYear, year: 1852);

    $yearAndMonth = Person::factory()->for($user->tree)->create();
    attachBirth($yearAndMonth, year: 1850, month: 3);

    $yearOnly = Person::factory()->for($user->tree)->create();
    attachBirth($yearOnly, year: 1850);

    $relationParent = ParentChildRelation::factory()->create(['tree_id' => $user->tree->id, 'parent_id' => $parent->id, 'child_id' => $person->id]);
    ParentChildRelation::factory()->create(['tree_id' => $user->tree->id, 'parent_id' => $person->id, 'child_id' => $noBirth->id]);
    ParentChildRelation::factory()->create(['tree_id' => $user->tree->id, 'parent_id' => $person->id, 'child_id' => $laterYear->id]);
    ParentChildRelation::factory()->create(['tree_id' => $user->tree->id, 'parent_id' => $person->id, 'child_id' => $yearAndMonth->id]);
    $relationYearOnly = ParentChildRelation::factory()->create(['tree_id' => $user->tree->id, 'parent_id' => $person->id, 'child_id' => $yearOnly->id]);

    $response = $this->actingAs($user)->get(personPreviewUrl($person));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('persons/index')
        ->where('personPreview.person.parents.0.id', $parent->id)
        ->where('personPreview.person.parents.0.relation_id', $relationParent->id)
        ->where('personPreview.person.children.0.id', $yearOnly->id)
        ->where('personPreview.person.children.0.relation_id', $relationYearOnly->id)
        ->where('personPreview.person.children.1.id', $yearAndMonth->id)
        ->where('personPreview.person.children.2.id', $laterYear->id)
        ->where('personPreview.person.children.3.id', $noBirth->id));
});

test('two children born on the same date are ordered by name', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();

    $zChild = Person::factory()->for($user->tree)->create(['first_name' => 'Zofia', 'last_name' => 'Nowak']);
    attachBirth($zChild, year: 1850, month: 3, day: 15);

    $aChild = Person::factory()->for($user->tree)->create(['first_name' => 'Adam', 'last_name' => 'Nowak']);
    attachBirth($aChild, year: 1850, month: 3, day: 15);

    ParentChildRelation::factory()->create(['tree_id' => $user->tree->id, 'parent_id' => $person->id, 'child_id' => $zChild->id]);
    ParentChildRelation::factory()->create(['tree_id' => $user->tree->id, 'parent_id' => $person->id, 'child_id' => $aChild->id]);

    $response = $this->actingAs($user)->get(personPreviewUrl($person));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('persons/index')
        ->where('personPreview.person.children.0.id', $aChild->id)
        ->where('personPreview.person.children.1.id', $zChild->id));
});

test('a child cannot get a second father or a second mother', function (string $gender, string $message) {
    $user = User::factory()->create();
    $first = Person::factory()->for($user->tree)->{$gender}()->create();
    $second = Person::factory()->for($user->tree)->{$gender}()->create();
    $child = Person::factory()->for($user->tree)->create();
    linkParent($first, $child);

    $response = $this->actingAs($user)->post(route('parent-child-relations.store'), ['parent_id' => $second->id, 'child_id' => $child->id]);

    $response->assertSessionHasErrors(['relation' => $message]);
    $this->assertDatabaseCount('parent_child_relations', 1);
})->with([
    'father' => ['male', 'Ta osoba ma już ojca.'],
    'mother' => ['female', 'Ta osoba ma już matkę.'],
]);

test('an unknown-sex parent never conflicts with a known-sex parent', function () {
    $user = User::factory()->create();
    $unknown = Person::factory()->for($user->tree)->create();
    $man = Person::factory()->for($user->tree)->male()->create();
    $otherMan = Person::factory()->for($user->tree)->male()->create();
    $childOne = Person::factory()->for($user->tree)->create();
    $childTwo = Person::factory()->for($user->tree)->create();

    linkParent($unknown, $childOne);
    $this->actingAs($user)->post(route('parent-child-relations.store'), ['parent_id' => $man->id, 'child_id' => $childOne->id])
        ->assertSessionHasNoErrors();

    linkParent($otherMan, $childTwo);
    $this->actingAs($user)->post(route('parent-child-relations.store'), ['parent_id' => $unknown->id, 'child_id' => $childTwo->id])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseCount('parent_child_relations', 4);
});

test('a slot role must match the sex of the chosen parent', function (string $role, ?string $gender, bool $accepted) {
    $user = User::factory()->create();
    $factory = Person::factory()->for($user->tree);
    $parent = ($gender === null ? $factory : $factory->{$gender}())->create();
    $child = Person::factory()->for($user->tree)->create();

    $response = $this->actingAs($user)->post(route('parent-child-relations.store'), [
        'parent_id' => $parent->id,
        'child_id' => $child->id,
        'role' => $role,
    ]);

    if ($accepted) {
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('parent_child_relations', 1);
    } else {
        $response->assertSessionHasErrors(['relation' => 'Wybrana osoba nie pasuje do tego miejsca na drzewie.']);
        $this->assertDatabaseCount('parent_child_relations', 0);
    }
})->with([
    'father slot, woman' => ['father', 'female', false],
    'father slot, unknown sex' => ['father', null, false],
    'father slot, man' => ['father', 'male', true],
    'mother slot, woman' => ['mother', 'female', true],
    'mother slot, man' => ['mother', 'male', false],
    'parent slot, woman' => ['parent', 'female', true],
    'parent slot, unknown sex' => ['parent', null, true],
]);

test('an unknown role is rejected', function () {
    $user = User::factory()->create();
    $parent = Person::factory()->for($user->tree)->create();
    $child = Person::factory()->for($user->tree)->create();

    $this->actingAs($user)->post(route('parent-child-relations.store'), ['parent_id' => $parent->id, 'child_id' => $child->id, 'role' => 'uncle'])
        ->assertSessionHasErrors('role');

    $this->assertDatabaseCount('parent_child_relations', 0);
});

test('linking from a tree slot redirects back to the tree with its union preserved', function () {
    $user = User::factory()->create();
    $father = Person::factory()->for($user->tree)->male()->create();
    $child = Person::factory()->for($user->tree)->create();
    $spouse = Person::factory()->for($user->tree)->female()->create();
    $treeUrl = route('tree.show', ['person' => $child->id, 'spouse' => $spouse->id]);

    $response = $this->from($treeUrl)
        ->actingAs($user)
        ->post(route('parent-child-relations.store'), ['parent_id' => $father->id, 'child_id' => $child->id, 'role' => 'father']);

    $response->assertRedirect($treeUrl);
    $response->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Dodano relację']);
});

test('guests are redirected to the login page from the create-parent route', function () {
    $child = Person::factory()->create();

    $this->post(route('persons.parents.store', $child), [])->assertRedirect(route('login'));
});

test('creating a parent from a slot stores the person with a birth event and the relation, redirects back and flashes the toast', function () {
    $user = User::factory()->create();
    $child = Person::factory()->for($user->tree)->create();
    $spouse = Person::factory()->for($user->tree)->female()->create();
    $treeUrl = route('tree.show', ['person' => $child->id, 'spouse' => $spouse->id]);

    $response = $this->from($treeUrl)
        ->actingAs($user)
        ->post(route('persons.parents.store', $child), [
            'role' => 'father',
            'first_name' => 'Józef',
            'last_name' => 'Kowalski',
            'gender' => 'male',
            'birth' => ['year' => 1900],
        ]);

    $response->assertRedirect($treeUrl);
    $response->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Dodano rodzica']);

    $father = Person::where('first_name', 'Józef')->firstOrFail();

    $this->assertDatabaseHas('parent_child_relations', [
        'tree_id' => $user->tree->id,
        'parent_id' => $father->id,
        'child_id' => $child->id,
    ]);
    $this->assertDatabaseHas('events', ['tree_id' => $user->tree->id, 'type' => 'birth', 'date_year' => 1900]);
});

test('a generic parent slot accepts a person without a sex', function () {
    $user = User::factory()->create();
    $child = Person::factory()->for($user->tree)->create();
    $peopleBefore = Person::count();

    $this->actingAs($user)
        ->post(route('persons.parents.store', $child), ['role' => 'parent', 'first_name' => 'Anna'])
        ->assertSessionHasNoErrors();

    expect(Person::count())->toBe($peopleBefore + 1);
    $this->assertDatabaseCount('parent_child_relations', 1);
});

test('the sex of a new parent must match the slot role and nothing is created otherwise', function (string $role, ?string $gender) {
    $user = User::factory()->create();
    $child = Person::factory()->for($user->tree)->create();
    $peopleBefore = Person::count();

    $response = $this->actingAs($user)
        ->post(route('persons.parents.store', $child), ['role' => $role, 'first_name' => 'Anna', 'gender' => $gender]);

    $response->assertSessionHasErrors(['gender' => 'Płeć musi pasować do miejsca na drzewie.']);
    expect(Person::count())->toBe($peopleBefore);
    $this->assertDatabaseCount('parent_child_relations', 0);
})->with([
    'father slot, woman' => ['father', 'female'],
    'father slot, unknown sex' => ['father', null],
    'mother slot, man' => ['mother', 'male'],
]);

test('a same-sex rule failure on the relation rolls the new person back', function () {
    $user = User::factory()->create();
    $child = Person::factory()->for($user->tree)->create();
    linkParent(Person::factory()->for($user->tree)->male()->create(), $child);
    $peopleBefore = Person::count();

    $response = $this->actingAs($user)
        ->post(route('persons.parents.store', $child), ['role' => 'parent', 'first_name' => 'Józef', 'gender' => 'male']);

    $response->assertSessionHasErrors(['relation' => 'Ta osoba ma już ojca.']);
    expect(Person::count())->toBe($peopleBefore);
    $this->assertDatabaseCount('parent_child_relations', 1);
});

test('a child with two parents cannot get a third and the person is not created', function () {
    $user = User::factory()->create();
    $child = Person::factory()->for($user->tree)->create();
    linkParent(Person::factory()->for($user->tree)->create(), $child);
    linkParent(Person::factory()->for($user->tree)->create(), $child);
    $peopleBefore = Person::count();

    $this->actingAs($user)
        ->post(route('persons.parents.store', $child), ['role' => 'parent', 'first_name' => 'Anna'])
        ->assertSessionHasErrors('relation');

    expect(Person::count())->toBe($peopleBefore);
    $this->assertDatabaseCount('parent_child_relations', 2);
});

test('creating a parent for another trees child returns not found and creates nothing', function () {
    $user = User::factory()->create();
    $foreignChild = Person::factory()->for(User::factory()->create()->tree)->create();
    $peopleBefore = Person::count();

    $this->actingAs($user)
        ->post(route('persons.parents.store', $foreignChild), ['role' => 'parent', 'first_name' => 'Anna'])
        ->assertNotFound();

    expect(Person::count())->toBe($peopleBefore);
    $this->assertDatabaseCount('parent_child_relations', 0);
});

test('guests are redirected to the login page from the attach-child route', function () {
    $person = Person::factory()->create();

    $this->post(route('persons.children.attach', $person), [])->assertRedirect(route('login'));
});

test('attaching a child to a pair links both parents, redirects back to the union and flashes the toast', function () {
    $user = User::factory()->create();
    $focus = Person::factory()->for($user->tree)->male()->create();
    $partner = Person::factory()->for($user->tree)->female()->create();
    $child = Person::factory()->for($user->tree)->create();
    $treeUrl = route('tree.show', ['person' => $focus->id, 'spouse' => $partner->id]);

    $response = $this->from($treeUrl)
        ->actingAs($user)
        ->post(route('persons.children.attach', $focus), ['child_id' => $child->id, 'other_parent_id' => $partner->id]);

    $response->assertRedirect($treeUrl);
    $response->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Dodano dziecko']);
    $this->assertDatabaseCount('parent_child_relations', 2);
    $this->assertDatabaseHas('parent_child_relations', ['parent_id' => $focus->id, 'child_id' => $child->id, 'tree_id' => $user->tree->id]);
    $this->assertDatabaseHas('parent_child_relations', ['parent_id' => $partner->id, 'child_id' => $child->id, 'tree_id' => $user->tree->id]);
});

test('attaching a child that already has the focus as parent adds only the partner link', function () {
    $user = User::factory()->create();
    $focus = Person::factory()->for($user->tree)->male()->create();
    $partner = Person::factory()->for($user->tree)->female()->create();
    $child = Person::factory()->for($user->tree)->create();
    linkParent($focus, $child);

    $this->actingAs($user)
        ->post(route('persons.children.attach', $focus), ['child_id' => $child->id, 'other_parent_id' => $partner->id])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseCount('parent_child_relations', 2);
    $this->assertDatabaseHas('parent_child_relations', ['parent_id' => $partner->id, 'child_id' => $child->id]);
});

test('attaching a child of both parents is rejected as an existing relation', function () {
    $user = User::factory()->create();
    $focus = Person::factory()->for($user->tree)->male()->create();
    $partner = Person::factory()->for($user->tree)->female()->create();
    $child = Person::factory()->for($user->tree)->create();
    linkParent($focus, $child);
    linkParent($partner, $child);

    $this->actingAs($user)
        ->post(route('persons.children.attach', $focus), ['child_id' => $child->id, 'other_parent_id' => $partner->id])
        ->assertSessionHasErrors(['relation' => 'Ta relacja już istnieje.']);

    $this->assertDatabaseCount('parent_child_relations', 2);
});

test('a child with an outside parent is rejected for a pair and the first link is rolled back', function () {
    $user = User::factory()->create();
    $focus = Person::factory()->for($user->tree)->male()->create();
    $partner = Person::factory()->for($user->tree)->female()->create();
    $child = Person::factory()->for($user->tree)->create();
    linkParent(Person::factory()->for($user->tree)->create(), $child);

    $this->actingAs($user)
        ->post(route('persons.children.attach', $focus), ['child_id' => $child->id, 'other_parent_id' => $partner->id])
        ->assertSessionHasErrors(['relation' => 'Ta osoba ma już dwoje rodziców.']);

    $this->assertDatabaseCount('parent_child_relations', 1);
    $this->assertDatabaseMissing('parent_child_relations', ['parent_id' => $focus->id]);
});

test('a lone parent keeps the other parent of an attached child', function () {
    $user = User::factory()->create();
    $focus = Person::factory()->for($user->tree)->male()->create();
    $mother = Person::factory()->for($user->tree)->female()->create();
    $child = Person::factory()->for($user->tree)->create();
    linkParent($mother, $child);

    $this->actingAs($user)
        ->post(route('persons.children.attach', $focus), ['child_id' => $child->id])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseCount('parent_child_relations', 2);
    $this->assertDatabaseHas('parent_child_relations', ['parent_id' => $mother->id, 'child_id' => $child->id]);
    $this->assertDatabaseHas('parent_child_relations', ['parent_id' => $focus->id, 'child_id' => $child->id]);
});

test('a lone father cannot attach a child who already has a father', function () {
    $user = User::factory()->create();
    $focus = Person::factory()->for($user->tree)->male()->create();
    $child = Person::factory()->for($user->tree)->create();
    linkParent(Person::factory()->for($user->tree)->male()->create(), $child);

    $this->actingAs($user)
        ->post(route('persons.children.attach', $focus), ['child_id' => $child->id])
        ->assertSessionHasErrors(['relation' => 'Ta osoba ma już ojca.']);

    $this->assertDatabaseCount('parent_child_relations', 1);
});

test('attaching the parent of the focus as its child is rejected as a cycle', function () {
    $user = User::factory()->create();
    $focus = Person::factory()->for($user->tree)->create();
    $parent = Person::factory()->for($user->tree)->create();
    linkParent($parent, $focus);

    $this->actingAs($user)
        ->post(route('persons.children.attach', $focus), ['child_id' => $parent->id])
        ->assertSessionHasErrors('relation');

    $this->assertDatabaseCount('parent_child_relations', 1);
});

test('attaching the focus person as its own child is rejected on child_id', function () {
    $user = User::factory()->create();
    $focus = Person::factory()->for($user->tree)->create();

    $this->actingAs($user)
        ->post(route('persons.children.attach', $focus), ['child_id' => $focus->id])
        ->assertSessionHasErrors('child_id');

    $this->assertDatabaseCount('parent_child_relations', 0);
});

test('a child_id or other_parent_id from another tree fails validation and creates nothing', function (string $field) {
    $user = User::factory()->create();
    $focus = Person::factory()->for($user->tree)->create();
    $own = Person::factory()->for($user->tree)->create();
    $foreign = Person::factory()->for(User::factory()->create()->tree)->create();
    $payload = $field === 'child_id'
        ? ['child_id' => $foreign->id, 'other_parent_id' => $own->id]
        : ['child_id' => $own->id, 'other_parent_id' => $foreign->id];

    $this->actingAs($user)
        ->post(route('persons.children.attach', $focus), $payload)
        ->assertSessionHasErrors($field);

    $this->assertDatabaseCount('parent_child_relations', 0);
})->with(['child' => ['child_id'], 'other parent' => ['other_parent_id']]);

test('attaching a child to another users person returns not found and creates nothing', function () {
    $user = User::factory()->create();
    $foreignFocus = Person::factory()->for(User::factory()->create()->tree)->create();
    $child = Person::factory()->for($user->tree)->create();

    $this->actingAs($user)
        ->post(route('persons.children.attach', $foreignFocus), ['child_id' => $child->id])
        ->assertNotFound();

    $this->assertDatabaseCount('parent_child_relations', 0);
});

test('guests are redirected to the login page from the create-child route', function () {
    $person = Person::factory()->create();

    $this->post(route('persons.children.store', $person), [])->assertRedirect(route('login'));
});

test('creating a child for a pair stores the person with a birth event and both relations, redirects back and flashes the toast', function () {
    $user = User::factory()->create();
    $focus = Person::factory()->for($user->tree)->male()->create();
    $partner = Person::factory()->for($user->tree)->female()->create();
    $treeUrl = route('tree.show', ['person' => $focus->id, 'spouse' => $partner->id]);

    $response = $this->from($treeUrl)
        ->actingAs($user)
        ->post(route('persons.children.store', $focus), [
            'first_name' => 'Marta',
            'last_name' => 'Kowalska',
            'gender' => 'female',
            'birth' => ['year' => 1930],
            'other_parent_id' => $partner->id,
        ]);

    $response->assertRedirect($treeUrl);
    $response->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Dodano dziecko']);

    $child = Person::where('first_name', 'Marta')->firstOrFail();

    $this->assertDatabaseCount('parent_child_relations', 2);
    $this->assertDatabaseHas('parent_child_relations', ['tree_id' => $user->tree->id, 'parent_id' => $focus->id, 'child_id' => $child->id]);
    $this->assertDatabaseHas('parent_child_relations', ['tree_id' => $user->tree->id, 'parent_id' => $partner->id, 'child_id' => $child->id]);
    $this->assertDatabaseHas('events', ['tree_id' => $user->tree->id, 'type' => 'birth', 'date_year' => 1930]);
});

test('creating a child for a single parent stores one relation', function () {
    $user = User::factory()->create();
    $focus = Person::factory()->for($user->tree)->create();

    $this->actingAs($user)
        ->post(route('persons.children.store', $focus), ['first_name' => 'Marta'])
        ->assertSessionHasNoErrors();

    $child = Person::where('first_name', 'Marta')->firstOrFail();

    $this->assertDatabaseCount('parent_child_relations', 1);
    $this->assertDatabaseHas('parent_child_relations', ['parent_id' => $focus->id, 'child_id' => $child->id]);
});

test('creating a child without a first name creates nothing', function () {
    $user = User::factory()->create();
    $focus = Person::factory()->for($user->tree)->create();
    $peopleBefore = Person::count();

    $this->actingAs($user)
        ->post(route('persons.children.store', $focus), ['first_name' => ''])
        ->assertSessionHasErrors('first_name');

    expect(Person::count())->toBe($peopleBefore);
    $this->assertDatabaseCount('parent_child_relations', 0);
});

test('an other_parent_id from another tree fails validation and creates nothing', function () {
    $user = User::factory()->create();
    $focus = Person::factory()->for($user->tree)->create();
    $foreign = Person::factory()->for(User::factory()->create()->tree)->create();
    $peopleBefore = Person::count();

    $this->actingAs($user)
        ->post(route('persons.children.store', $focus), ['first_name' => 'Marta', 'other_parent_id' => $foreign->id])
        ->assertSessionHasErrors('other_parent_id');

    expect(Person::count())->toBe($peopleBefore);
    $this->assertDatabaseCount('parent_child_relations', 0);
});

test('a pair of the same known sex is rejected and the new person and the first link are rolled back', function () {
    $user = User::factory()->create();
    $focus = Person::factory()->for($user->tree)->male()->create();
    $otherMan = Person::factory()->for($user->tree)->male()->create();
    $peopleBefore = Person::count();

    $this->actingAs($user)
        ->post(route('persons.children.store', $focus), ['first_name' => 'Marta', 'other_parent_id' => $otherMan->id])
        ->assertSessionHasErrors(['relation' => 'Ta osoba ma już ojca.']);

    expect(Person::count())->toBe($peopleBefore);
    $this->assertDatabaseCount('parent_child_relations', 0);
});

test('the route person cannot be their own other parent when creating a child', function () {
    $user = User::factory()->create();
    $focus = Person::factory()->for($user->tree)->create();
    $peopleBefore = Person::count();

    $this->actingAs($user)
        ->post(route('persons.children.store', $focus), ['first_name' => 'Marta', 'other_parent_id' => $focus->id])
        ->assertSessionHasErrors('other_parent_id');

    expect(Person::count())->toBe($peopleBefore);
    $this->assertDatabaseCount('parent_child_relations', 0);
});

test('the route person cannot be their own other parent when attaching a child', function () {
    $user = User::factory()->create();
    $focus = Person::factory()->for($user->tree)->create();
    $child = Person::factory()->for($user->tree)->create();

    $this->actingAs($user)
        ->post(route('persons.children.attach', $focus), ['child_id' => $child->id, 'other_parent_id' => $focus->id])
        ->assertSessionHasErrors('other_parent_id');

    $this->assertDatabaseCount('parent_child_relations', 0);
});

test('the attached child cannot also be the other parent', function () {
    $user = User::factory()->create();
    $focus = Person::factory()->for($user->tree)->create();
    $child = Person::factory()->for($user->tree)->create();

    $this->actingAs($user)
        ->post(route('persons.children.attach', $focus), ['child_id' => $child->id, 'other_parent_id' => $child->id])
        ->assertSessionHasErrors('child_id');

    $this->assertDatabaseCount('parent_child_relations', 0);
});

test('attaching a child to a pair of the same known sex is rejected and nothing is linked', function () {
    $user = User::factory()->create();
    $focus = Person::factory()->for($user->tree)->male()->create();
    $otherMan = Person::factory()->for($user->tree)->male()->create();
    $child = Person::factory()->for($user->tree)->create();

    $this->actingAs($user)
        ->post(route('persons.children.attach', $focus), ['child_id' => $child->id, 'other_parent_id' => $otherMan->id])
        ->assertSessionHasErrors(['relation' => 'Ta osoba ma już ojca.']);

    $this->assertDatabaseCount('parent_child_relations', 0);
});

test('creating a child for another users person returns not found and creates nothing', function () {
    $user = User::factory()->create();
    $foreignFocus = Person::factory()->for(User::factory()->create()->tree)->create();
    $peopleBefore = Person::count();

    $this->actingAs($user)
        ->post(route('persons.children.store', $foreignFocus), ['first_name' => 'Marta'])
        ->assertNotFound();

    expect(Person::count())->toBe($peopleBefore);
    $this->assertDatabaseCount('parent_child_relations', 0);
});
