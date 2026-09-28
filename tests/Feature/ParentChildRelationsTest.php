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

    $response = $this->actingAs($user)->get(route('persons.show', $person));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('persons/show')
        ->where('person.parents.0.id', $parent->id)
        ->where('person.parents.0.relation_id', $relationParent->id)
        ->where('person.children.0.id', $yearOnly->id)
        ->where('person.children.0.relation_id', $relationYearOnly->id)
        ->where('person.children.1.id', $yearAndMonth->id)
        ->where('person.children.2.id', $laterYear->id)
        ->where('person.children.3.id', $noBirth->id));
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

    $response = $this->actingAs($user)->get(route('persons.show', $person));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('persons/show')
        ->where('person.children.0.id', $aChild->id)
        ->where('person.children.1.id', $zChild->id));
});

test('person show does not include relationCandidates on a normal visit', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();

    $response = $this->actingAs($user)->get(route('persons.show', $person));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('persons/show')
        ->missing('relationCandidates'));
});

test('a partial reload of relationCandidates excludes the current person and other trees, and gives the expected reason per direction', function () {
    $user = User::factory()->create();
    $tree = $user->tree;

    $grandparent = Person::factory()->for($tree)->create();
    $parent = Person::factory()->for($tree)->create();
    $person = Person::factory()->for($tree)->create();
    $child = Person::factory()->for($tree)->create();
    $grandchild = Person::factory()->for($tree)->create();
    $parentA = Person::factory()->for($tree)->create();
    $parentB = Person::factory()->for($tree)->create();
    $twoParentsChild = Person::factory()->for($tree)->create();
    $linkable = Person::factory()->for($tree)->create();

    ParentChildRelation::factory()->create(['tree_id' => $tree->id, 'parent_id' => $grandparent->id, 'child_id' => $parent->id]);
    ParentChildRelation::factory()->create(['tree_id' => $tree->id, 'parent_id' => $parent->id, 'child_id' => $person->id]);
    ParentChildRelation::factory()->create(['tree_id' => $tree->id, 'parent_id' => $person->id, 'child_id' => $child->id]);
    ParentChildRelation::factory()->create(['tree_id' => $tree->id, 'parent_id' => $child->id, 'child_id' => $grandchild->id]);
    ParentChildRelation::factory()->create(['tree_id' => $tree->id, 'parent_id' => $parentA->id, 'child_id' => $twoParentsChild->id]);
    ParentChildRelation::factory()->create(['tree_id' => $tree->id, 'parent_id' => $parentB->id, 'child_id' => $twoParentsChild->id]);

    $otherUser = User::factory()->create();
    $foreignPerson = Person::factory()->for($otherUser->tree)->create();

    $initial = $this->actingAs($user)->get(route('persons.show', $person), ['X-Inertia' => 'true']);
    $version = $initial->headers->get('X-Inertia-Version');

    $response = $this->actingAs($user)->get(route('persons.show', $person), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $version,
        'X-Inertia-Partial-Data' => 'relationCandidates',
        'X-Inertia-Partial-Component' => 'persons/show',
    ]);

    $response->assertOk();
    $props = $response->json('props');

    expect($props)->not->toHaveKey('person');

    $candidates = collect($props['relationCandidates'])->keyBy('id');

    expect($candidates->has($person->id))->toBeFalse();
    expect($candidates->has($foreignPerson->id))->toBeFalse();

    expect($candidates[$parent->id]['as_parent_blocked_reason'])->toBe('Już jest rodzicem');
    expect($candidates[$grandchild->id]['as_parent_blocked_reason'])->toBe('Jest potomkiem tej osoby');
    expect($candidates[$child->id]['as_child_blocked_reason'])->toBe('Już jest dzieckiem');
    expect($candidates[$grandparent->id]['as_child_blocked_reason'])->toBe('Jest przodkiem tej osoby');
    expect($candidates[$twoParentsChild->id]['as_child_blocked_reason'])->toBe('Ma już dwoje rodziców');
    expect($candidates[$linkable->id]['as_parent_blocked_reason'])->toBeNull();
    expect($candidates[$linkable->id]['as_child_blocked_reason'])->toBeNull();
});
