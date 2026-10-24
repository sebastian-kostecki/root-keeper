<?php

use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
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

/**
 * The author's tree is empty; another account owns persons named like the ones in the fixture repo.
 */
function arrangeEmptyAuthorWithOverlappingOther(): User
{
    $author = User::factory()->create(['email' => 'autor@example.com']);
    $other = User::factory()->create(['email' => 'inny@example.com']);

    Person::factory()->for($other->tree)->create(['first_name' => 'Jan', 'last_name' => 'Nowak']);
    Person::factory()->for($other->tree)->create(['first_name' => 'Anna', 'last_name' => 'Wrona']);

    return $author;
}

/**
 * Replace backup:run with a stand-in that records its calls. Must run before the first $this->artisan() call.
 *
 * @return ArrayObject<int, bool> one entry per call: the value of --only-db
 */
function fakeBackupRun(int $exitCode): ArrayObject
{
    $calls = new ArrayObject;

    Artisan::command('backup:run {--only-db} {--disable-notifications}', function () use ($calls, $exitCode): int {
        $calls[] = (bool) $this->option('only-db');

        return $exitCode;
    });

    return $calls;
}

function importArguments(User $author): array
{
    return ['email' => $author->email, 'path' => base_path(RESEARCH_FIXTURE)];
}

test('prints the report for the fixture repo and exits successfully', function () {
    $author = arrangeEmptyAuthorWithOverlappingOther();

    $this->artisan('import:research', ['email' => $author->email, 'path' => base_path(RESEARCH_FIXTURE)])
        ->expectsOutputToContain('Pliki przejrzane: 33 (z blokiem: 31, bez bloku: 2)')
        ->expectsOutputToContain('Osoby przeczytane: 13')
        ->expectsOutputToContain('Relacje rodzic–dziecko rozpoznane: 4')
        ->expectsOutputToContain('Małżeństwa rozpoznane: 3')
        ->expectsOutputToContain('Pominięte wpisy:')
        ->expectsOutputToContain('ujmij datę w cudzysłów')
        ->expectsOutputToContain('Ostrzeżenia:')
        ->expectsOutputToContain('Nieznany klucz: kolor')
        ->expectsOutputToContain('Podsumowanie importu')
        ->expectsOutputToContain('Pominięte relacje:')
        ->expectsConfirmation('Zapisać 13 osób do drzewa konta autor@example.com?', 'no')
        ->expectsOutputToContain('Nic nie zostało zapisane.')
        ->assertSuccessful();
});

test('refuses without asking and writes nothing when the tree already has persons', function () {
    Storage::fake('local');
    $author = arrangeOverlappingUsers();
    $before = ownershipSnapshot();

    $this->artisan('import:research', importArguments($author))
        ->expectsOutputToContain('Drzewo konta ma już osoby')
        ->assertFailed();

    expect(ownershipSnapshot())->toBe($before);
});

test('saves the persons to the author tree after a yes and suggests the proband', function () {
    Storage::fake('local');
    $author = arrangeEmptyAuthorWithOverlappingOther();

    $this->artisan('import:research', importArguments($author))
        ->expectsConfirmation('Zapisać 13 osób do drzewa konta autor@example.com?', 'yes')
        ->expectsOutputToContain('Zapisano w drzewie')
        ->expectsOutputToContain('Ahnentafel 1: Jan Nowak (O-0001)')
        ->assertSuccessful();

    expect($author->tree->people()->count())->toBe(13)
        ->and($author->tree->root_person_id)->toBeNull();

    expect(User::where('email', 'inny@example.com')->firstOrFail()->tree->people()->count())->toBe(2);
});

test('writes nothing after a no', function () {
    Storage::fake('local');
    $author = arrangeEmptyAuthorWithOverlappingOther();
    $before = ownershipSnapshot();

    $this->artisan('import:research', importArguments($author))
        ->expectsConfirmation('Zapisać 13 osób do drzewa konta autor@example.com?', 'no')
        ->assertSuccessful();

    expect(ownershipSnapshot())->toBe($before);
});

test('stops when the database backup fails in production', function () {
    Storage::fake('local');
    $calls = fakeBackupRun(1);
    app()->detectEnvironment(fn (): string => 'production');
    $author = arrangeEmptyAuthorWithOverlappingOther();
    $before = ownershipSnapshot();

    $this->artisan('import:research', importArguments($author))
        ->expectsConfirmation('Zapisać 13 osób do drzewa konta autor@example.com?', 'yes')
        ->expectsOutputToContain('Kopia bazy nie powiodła się, nic nie zostało zapisane.')
        ->assertFailed();

    expect($calls->getArrayCopy())->toBe([true])
        ->and(ownershipSnapshot())->toBe($before);
});

test('saves after a successful database backup in production', function () {
    Storage::fake('local');
    $calls = fakeBackupRun(0);
    app()->detectEnvironment(fn (): string => 'production');
    $author = arrangeEmptyAuthorWithOverlappingOther();

    $this->artisan('import:research', importArguments($author))
        ->expectsConfirmation('Zapisać 13 osób do drzewa konta autor@example.com?', 'yes')
        ->assertSuccessful();

    expect($calls->getArrayCopy())->toBe([true])
        ->and($author->tree->people()->count())->toBe(13);
});

test('does not run a backup outside production', function () {
    Storage::fake('local');
    $calls = fakeBackupRun(0);
    $author = arrangeEmptyAuthorWithOverlappingOther();

    $this->artisan('import:research', importArguments($author))
        ->expectsConfirmation('Zapisać 13 osób do drzewa konta autor@example.com?', 'yes')
        ->assertSuccessful();

    expect($calls->getArrayCopy())->toBe([])
        ->and($author->tree->people()->count())->toBe(13);
});

test('lists files without a block only with -v', function () {
    $author = arrangeEmptyAuthorWithOverlappingOther();
    $arguments = importArguments($author);

    $this->artisan('import:research', $arguments)
        ->doesntExpectOutputToContain('Pliki bez bloku:')
        ->expectsConfirmation('Zapisać 13 osób do drzewa konta autor@example.com?', 'no')
        ->assertSuccessful();

    $this->artisan('import:research', [...$arguments, '-v' => true])
        ->expectsOutputToContain('Pliki bez bloku:')
        ->expectsOutputToContain('bez_bloku.md')
        ->expectsConfirmation('Zapisać 13 osób do drzewa konta autor@example.com?', 'no')
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
