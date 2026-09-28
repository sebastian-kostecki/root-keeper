<?php

use App\Models\Person;
use App\Models\User;

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
