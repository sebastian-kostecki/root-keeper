<?php

use App\Enums\Gender;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

function moveMaidenNamesMigration(): Migration
{
    static $migration = null;

    return $migration ??= require database_path('migrations/2026_10_21_152703_move_maiden_names_to_last_name.php');
}

/**
 * @param  array<string, mixed>  $attributes
 */
function personWithSurnames(array $attributes, ?User $user = null): Person
{
    return Person::factory()->for(($user ?? User::factory()->create())->tree)->create($attributes + [
        'first_name' => 'Anna',
        'last_name' => null,
        'maiden_name' => null,
        'note' => null,
    ]);
}

test('a woman with two surnames takes the birth surname and keeps the old one at the end of the note', function () {
    $person = personWithSurnames([
        'gender' => Gender::Female,
        'last_name' => 'Nowak',
        'maiden_name' => 'Kowalska',
        'note' => 'Córka młynarza.',
    ]);

    moveMaidenNamesMigration()->up();

    $person->refresh();
    expect($person->last_name)->toBe('Kowalska');
    expect($person->maiden_name)->toBe('Kowalska');
    expect($person->note)->toBe("Córka młynarza.\n\nNazwisko po mężu: Nowak");
});

test('an empty note receives just the line', function () {
    $person = personWithSurnames(['gender' => Gender::Female, 'last_name' => 'Nowak', 'maiden_name' => 'Kowalska']);
    $blank = personWithSurnames(['gender' => Gender::Female, 'last_name' => 'Nowak', 'maiden_name' => 'Kowalska', 'note' => '']);

    moveMaidenNamesMigration()->up();

    expect($person->refresh()->note)->toBe('Nazwisko po mężu: Nowak');
    expect($blank->refresh()->note)->toBe('Nazwisko po mężu: Nowak');
});

test('a person of unknown gender gets the married-name line', function () {
    $person = personWithSurnames(['gender' => null, 'last_name' => 'Nowak', 'maiden_name' => 'Kowalska']);

    moveMaidenNamesMigration()->up();

    $person->refresh();
    expect($person->last_name)->toBe('Kowalska');
    expect($person->note)->toBe('Nazwisko po mężu: Nowak');
});

test('a man with two different surnames gets the neutral line', function () {
    $person = personWithSurnames(['gender' => Gender::Male, 'last_name' => 'Nowak', 'maiden_name' => 'Kowalski']);

    moveMaidenNamesMigration()->up();

    $person->refresh();
    expect($person->last_name)->toBe('Kowalski');
    expect($person->maiden_name)->toBe('Kowalski');
    expect($person->note)->toBe('Wcześniej zapisane nazwisko: Nowak');
});

test('people with equal, single or no surnames stay unchanged', function () {
    $same = personWithSurnames(['gender' => Gender::Female, 'last_name' => 'Nowak', 'maiden_name' => 'NOWAK', 'note' => 'Bez zmian.']);
    $single = personWithSurnames(['last_name' => 'Nowak']);
    $none = personWithSurnames([]);

    moveMaidenNamesMigration()->up();

    expect($same->refresh()->only(['last_name', 'maiden_name', 'note']))
        ->toBe(['last_name' => 'Nowak', 'maiden_name' => 'NOWAK', 'note' => 'Bez zmian.']);
    expect($single->refresh()->only(['last_name', 'maiden_name', 'note']))
        ->toBe(['last_name' => 'Nowak', 'maiden_name' => null, 'note' => null]);
    expect($none->refresh()->only(['last_name', 'maiden_name', 'note']))
        ->toBe(['last_name' => null, 'maiden_name' => null, 'note' => null]);
});

test('a person in another tree is migrated the same way', function () {
    $person = personWithSurnames(['gender' => Gender::Female, 'last_name' => 'Nowak', 'maiden_name' => 'Kowalska', 'note' => 'Córka młynarza.']);
    $other = personWithSurnames(['gender' => Gender::Female, 'last_name' => 'Wójcik', 'maiden_name' => 'Lis'], User::factory()->create());

    moveMaidenNamesMigration()->up();

    expect($person->refresh()->last_name)->toBe('Kowalska');
    $other->refresh();
    expect($other->last_name)->toBe('Lis');
    expect($other->maiden_name)->toBe('Lis');
    expect($other->note)->toBe('Nazwisko po mężu: Wójcik');
});

test('running the migration twice does not append another line', function () {
    $person = personWithSurnames(['gender' => Gender::Female, 'last_name' => 'Nowak', 'maiden_name' => 'Kowalska']);

    moveMaidenNamesMigration()->up();
    moveMaidenNamesMigration()->up();

    expect($person->refresh()->note)->toBe('Nazwisko po mężu: Nowak');
    expect(DB::table('people')->where('id', $person->id)->value('last_name'))->toBe('Kowalska');
});
