<?php

use App\Models\Person;
use App\Models\User;

test('guests are redirected to the login page from the proband routes', function () {
    $person = Person::factory()->create();

    $this->post(route('tree.proband.attach'), ['person_id' => $person->id])->assertRedirect(route('login'));
    $this->post(route('tree.proband.store'), ['first_name' => 'Jan'])->assertRedirect(route('login'));
});

test('attach makes an existing person the proband and opens them on the tree', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();

    $response = $this->actingAs($user)
        ->post(route('tree.proband.attach'), ['person_id' => $person->id]);

    $response->assertRedirect(route('tree.index').'?person='.$person->id);
    $response->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Ustawiono probanta']);
    expect($user->tree->fresh()->root_person_id)->toBe($person->id);
});

test('store creates a person and makes them the proband in one request', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post(route('tree.proband.store'), ['first_name' => 'Anna', 'last_name' => 'Nowak', 'gender' => 'female']);

    $person = Person::where('first_name', 'Anna')->sole();

    $response->assertRedirect(route('tree.index').'?person='.$person->id);
    $response->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Dodano osobę i ustawiono probanta']);

    expect($person->tree_id)->toBe($user->tree->id);
    expect($user->tree->fresh()->root_person_id)->toBe($person->id);
});

test('attach refuses when the tree already has a proband and changes nothing', function () {
    $user = User::factory()->create();
    $proband = Person::factory()->for($user->tree)->create();
    $other = Person::factory()->for($user->tree)->create();
    $user->tree->update(['root_person_id' => $proband->id]);

    $this->actingAs($user)
        ->from(route('tree.index'))
        ->post(route('tree.proband.attach'), ['person_id' => $other->id])
        ->assertSessionHasErrors(['proband' => 'Drzewo ma już probanta.']);

    expect($user->tree->fresh()->root_person_id)->toBe($proband->id);
});

test('store refuses when the tree already has a proband and creates no person', function () {
    $user = User::factory()->create();
    $proband = Person::factory()->for($user->tree)->create();
    $user->tree->update(['root_person_id' => $proband->id]);

    $this->actingAs($user)
        ->from(route('tree.index'))
        ->post(route('tree.proband.store'), ['first_name' => 'Anna', 'last_name' => 'Nowak'])
        ->assertSessionHasErrors(['proband' => 'Drzewo ma już probanta.']);

    $this->assertDatabaseCount('people', 1);
    expect($user->tree->fresh()->root_person_id)->toBe($proband->id);
});

test('attach with a person of another tree answers 404 and changes nothing', function () {
    $user = User::factory()->create();
    $foreign = Person::factory()->create();

    $this->actingAs($user)
        ->post(route('tree.proband.attach'), ['person_id' => $foreign->id])
        ->assertNotFound();

    expect($user->tree->fresh()->root_person_id)->toBeNull();
});

test('store without a first or last name is rejected and creates no proband', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('tree.proband.store'), ['first_name' => '', 'last_name' => ''])
        ->assertSessionHasErrors(['first_name', 'last_name']);

    $this->assertDatabaseCount('people', 0);
    expect($user->tree->fresh()->root_person_id)->toBeNull();
});
