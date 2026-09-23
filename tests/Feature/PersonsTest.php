<?php

use App\Enums\EventType;
use App\Enums\ParticipantRole;
use App\Models\Event;
use App\Models\Person;
use App\Models\User;

test('guests are redirected to the login page from persons routes', function () {
    $person = Person::factory()->create();

    $this->get(route('persons.index'))->assertRedirect(route('login'));
    $this->get(route('persons.create'))->assertRedirect(route('login'));
    $this->get(route('persons.show', $person))->assertRedirect(route('login'));
    $this->post(route('persons.store'), [])->assertRedirect(route('login'));
});

test('persons index shows only the people from the authenticated user tree, sorted with unnamed people last', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $zolski = Person::factory()->for($user->tree)->create(['first_name' => 'Adam', 'last_name' => 'Zolski']);
    $zbigniewAdamski = Person::factory()->for($user->tree)->create(['first_name' => 'Zbigniew', 'last_name' => 'Adamski']);
    $adamski = Person::factory()->for($user->tree)->create(['first_name' => 'Bartek', 'last_name' => 'Adamski']);
    $unnamed = Person::factory()->for($user->tree)->create(['first_name' => null, 'last_name' => null]);
    Person::factory()->for($otherUser->tree)->create(['first_name' => 'Obcy', 'last_name' => 'Człowiek']);

    $response = $this->actingAs($user)->get(route('persons.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('persons/index')
        ->has('persons', 4)
        ->where('persons.0.id', $adamski->id)
        ->where('persons.1.id', $zbigniewAdamski->id)
        ->where('persons.2.id', $zolski->id)
        ->where('persons.3.id', $unnamed->id));
});

test('persons show returns not found for a person from another users tree', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $person = Person::factory()->for($otherUser->tree)->create();

    $response = $this->actingAs($user)->get(route('persons.show', $person));

    $response->assertNotFound();
});

test('store creates a person and a birth event even when birth fields are empty', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('persons.store'), [
        'first_name' => 'Jan',
        'last_name' => 'Kowalski',
    ]);

    $person = Person::where('first_name', 'Jan')->where('last_name', 'Kowalski')->firstOrFail();

    $response->assertRedirect(route('persons.show', $person));

    expect($person->tree_id)->toBe($user->tree->id);

    $events = Event::where('tree_id', $user->tree->id)->get();
    expect($events)->toHaveCount(1);

    $birth = $events->firstWhere('type', EventType::Birth);
    expect($birth)->not->toBeNull();
    expect($birth->date_year)->toBeNull();

    $participant = $birth->participants()->firstOrFail();
    expect($participant->pivot->role)->toBe(ParticipantRole::Subject);
});

test('store creates a death event when marked deceased even with empty death fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('persons.store'), [
        'first_name' => 'Jan',
        'last_name' => 'Kowalski',
        'is_deceased' => '1',
    ])->assertSessionHasNoErrors();

    $person = Person::where('first_name', 'Jan')->firstOrFail();

    $events = Event::where('tree_id', $user->tree->id)->get();
    expect($events)->toHaveCount(2);

    $death = $events->firstWhere('type', EventType::Death);
    expect($death)->not->toBeNull();
    expect($death->date_year)->toBeNull();
});

test('store accepts a person with only one name when the other is sent empty', function (array $names) {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('persons.store'), $names);

    $response->assertSessionHasNoErrors();
    expect($user->tree->people()->count())->toBe(1);
})->with([
    'only first name' => [['first_name' => 'Maria', 'last_name' => '']],
    'only last name' => [['first_name' => '', 'last_name' => 'Kowalski']],
]);

test('store treats a JSON boolean is_deceased like the checkbox value', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('persons.store'), [
        'first_name' => 'Jan',
        'is_deceased' => true,
        'death' => ['year' => 1900],
    ])->assertRedirect();

    $death = Event::where('tree_id', $user->tree->id)->where('type', EventType::Death)->first();
    expect($death?->date_year)->toBe(1900);
});

test('store rejects a person with neither first nor last name', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('persons.store'), []);

    $response->assertSessionHasErrors(['first_name', 'last_name']);
});

test('store rejects a birth day without a month', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('persons.store'), [
        'first_name' => 'Jan',
        'birth' => ['day' => 15],
    ]);

    $response->assertSessionHasErrors('birth.month');
});

test('store rejects a birth month without a year', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('persons.store'), [
        'first_name' => 'Jan',
        'birth' => ['month' => 5],
    ]);

    $response->assertSessionHasErrors('birth.year');
});

test('store rejects an impossible calendar date', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('persons.store'), [
        'first_name' => 'Jan',
        'birth' => ['year' => 1850, 'month' => 2, 'day' => 31],
    ]);

    $response->assertSessionHasErrors('birth.day');
});

test('store reports only the invalid date part, not an impossible date', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('persons.store'), [
        'first_name' => 'Jan',
        'birth' => ['year' => 1850, 'month' => 13, 'day' => 1],
    ]);

    $response->assertSessionHasErrors('birth.month');
    $response->assertSessionDoesntHaveErrors('birth.day');
});

test('store rejects a birth year in the future', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('persons.store'), [
        'first_name' => 'Jan',
        'birth' => ['year' => (int) date('Y') + 1],
    ]);

    $response->assertSessionHasErrors('birth.year');
});

test('store rejects an approximate date without a year', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('persons.store'), [
        'first_name' => 'Jan',
        'birth' => ['is_approximate' => '1'],
    ]);

    $response->assertSessionHasErrors('birth.year');
});

test('store rejects an unknown religion', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('persons.store'), [
        'first_name' => 'Jan',
        'religion' => 'unknown',
    ]);

    $response->assertSessionHasErrors('religion');
});

test('store rejects death fields when the person is not marked deceased', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('persons.store'), [
        'first_name' => 'Jan',
        'death' => ['year' => 1900],
    ]);

    $response->assertSessionHasErrors('death.year');
});

test('validation errors are in polish', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('persons.store'), []);

    $response->assertInvalid();
    $errors = $response->getSession()->get('errors')->getBag('default');

    expect($errors->first('first_name'))->toContain('wymagane');
});
