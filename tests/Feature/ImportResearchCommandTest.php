<?php

use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

const RESEARCH_FIXTURE = 'tests/Fixtures/research-repo';

/**
 * Two accounts with persons named like the ones in the fixture repo.
 */
function arrangeOverlappingUsers(): User
{
    $author = User::factory()->create(['email' => 'autor@example.com']);
    $other = User::factory()->create(['email' => 'inny@example.com']);

    foreach ([$author, $other] as $user) {
        Person::factory()->for($user->tree)->create(['first_name' => 'Jan', 'last_name' => 'Nowak']);
        Person::factory()->for($user->tree)->create(['first_name' => 'Anna', 'last_name' => 'Wrona']);
    }

    return $author;
}

test('prints the report for the fixture repo and exits successfully', function () {
    $author = arrangeOverlappingUsers();

    $this->artisan('import:research', ['email' => $author->email, 'path' => base_path(RESEARCH_FIXTURE)])
        ->expectsOutputToContain('Pliki przejrzane: 33 (z blokiem: 31, bez bloku: 2)')
        ->expectsOutputToContain('Osoby przeczytane: 13')
        ->expectsOutputToContain('Relacje rodzic–dziecko rozpoznane: 4')
        ->expectsOutputToContain('Małżeństwa rozpoznane: 3')
        ->expectsOutputToContain('Pominięte wpisy:')
        ->expectsOutputToContain('ujmij datę w cudzysłów')
        ->expectsOutputToContain('Ostrzeżenia:')
        ->expectsOutputToContain('Nieznany klucz: kolor')
        ->expectsOutputToContain('Nic nie zostało zapisane.')
        ->assertSuccessful();
});

test('writes nothing for either account', function () {
    Storage::fake('local');
    $author = arrangeOverlappingUsers();
    $before = ownershipSnapshot();

    $this->artisan('import:research', ['email' => $author->email, 'path' => base_path(RESEARCH_FIXTURE)])->assertSuccessful();

    expect(ownershipSnapshot())->toBe($before);
});

test('lists files without a block only with -v', function () {
    $author = arrangeOverlappingUsers();
    $arguments = ['email' => $author->email, 'path' => base_path(RESEARCH_FIXTURE)];

    $this->artisan('import:research', $arguments)
        ->doesntExpectOutputToContain('Pliki bez bloku:')
        ->assertSuccessful();

    $this->artisan('import:research', [...$arguments, '-v' => true])
        ->expectsOutputToContain('Pliki bez bloku:')
        ->expectsOutputToContain('bez_bloku.md')
        ->assertSuccessful();
});

test('fails for an unknown e-mail', function () {
    $this->artisan('import:research', ['email' => 'nikt@example.com', 'path' => base_path(RESEARCH_FIXTURE)])
        ->expectsOutputToContain('Nie znaleziono konta')
        ->assertFailed();
});

test('fails for an account without a tree', function () {
    $user = User::factory()->withoutTree()->create();

    $this->artisan('import:research', ['email' => $user->email, 'path' => base_path(RESEARCH_FIXTURE)])
        ->expectsOutputToContain('nie ma drzewa')
        ->assertFailed();
});

test('fails when the repo has no drzewo directory', function () {
    $author = arrangeOverlappingUsers();

    $this->artisan('import:research', ['email' => $author->email, 'path' => base_path('tests/Fixtures')])
        ->expectsOutputToContain('Brak katalogu drzewo/')
        ->assertFailed();

    $this->artisan('import:research', ['email' => $author->email, 'path' => base_path('tests/Fixtures/nie-ma')])
        ->expectsOutputToContain('Nie znaleziono katalogu repo')
        ->assertFailed();
});
