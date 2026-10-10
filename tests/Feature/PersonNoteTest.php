<?php

use App\Models\Person;
use App\Models\User;

test('a multi-paragraph note is saved verbatim and comes back in the person preview', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $note = "Pierwszy akapit.\n\nDrugi akapit z łącznikiem – i znakami.\nTrzeci wiersz.";

    $this->actingAs($user)->put(route('persons.note.update', $person), ['note' => $note])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($person->refresh()->note)->toBe($note);

    $this->actingAs($user)->get(personPreviewUrl($person))
        ->assertInertia(fn ($page) => $page
            ->where('personPreview.person.note', $note));
});

test('an empty note clears the stored note to null', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create(['note' => 'Stara notatka']);

    $this->actingAs($user)->put(route('persons.note.update', $person), ['note' => ''])
        ->assertSessionHasNoErrors();

    expect($person->refresh()->note)->toBeNull();
});

test('a note of 5000 characters is accepted', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();

    $this->actingAs($user)->put(route('persons.note.update', $person), ['note' => str_repeat('a', 5000)])
        ->assertSessionHasNoErrors();

    expect($person->refresh()->note)->toHaveLength(5000);
});

test('a note of 5001 characters is rejected on the note field', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create(['note' => 'Zostaje']);

    $this->actingAs($user)->put(route('persons.note.update', $person), ['note' => str_repeat('a', 5001)])
        ->assertSessionHasErrors('note');

    expect($person->refresh()->note)->toBe('Zostaje');
});

test('a full person save without a note field leaves the note unchanged', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create([
        'first_name' => 'Anna',
        'last_name' => 'Kowalska',
        'note' => 'Notatka do zachowania',
    ]);

    $this->actingAs($user)->put(route('persons.update', $person), [
        'first_name' => 'Anna',
        'last_name' => 'Nowak',
        'birth' => ['year' => 1950],
    ])->assertSessionHasNoErrors();

    expect($person->refresh()->last_name)->toBe('Nowak');
    expect($person->note)->toBe('Notatka do zachowania');
});

test('the people index does not carry the note on each person', function () {
    $user = User::factory()->create();
    Person::factory()->for($user->tree)->create(['note' => 'Tajna notatka']);

    $this->actingAs($user)->get(route('persons.index'))
        ->assertInertia(fn ($page) => $page
            ->has('persons', 1)
            ->where('persons', fn ($persons) => collect($persons)->every(fn ($person) => ! array_key_exists('note', (array) $person))));
});
