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

    $middle->delete();

    $this->assertDatabaseCount('parent_child_relations', 0);
    $this->assertDatabaseHas('people', ['id' => $grandparent->id]);
    $this->assertDatabaseHas('people', ['id' => $grandchild->id]);
});
