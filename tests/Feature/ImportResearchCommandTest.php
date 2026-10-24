<?php

use App\Actions\Relations\CreateMarriage;
use App\Enums\ParticipantRole;
use App\Models\Event;
use App\Models\Locality;
use App\Models\ParentChildRelation;
use App\Models\Person;
use App\Models\Tree;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\PendingCommand;

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
        ->expectsConfirmation('Zapisać do drzewa konta autor@example.com: nowe osoby 13, dopasowane 0?', 'no')
        ->expectsOutputToContain('Nic nie zostało zapisane.')
        ->assertSuccessful();
});

test('writes nothing for either account when the author skips every doubtful match and says no', function () {
    Storage::fake('local');
    $author = arrangeOverlappingUsers();
    $before = ownershipSnapshot();

    $this->artisan('import:research', importArguments($author))
        ->expectsOutputToContain('Dopasowanie do osób w drzewie')
        ->expectsOutputToContain('nowe: 11, pewne: 0, wątpliwe: 2')
        ->expectsQuestion('Wątpliwe 1/2: Jan Nowak (O-0001) — kto to?', 'skip')
        ->expectsQuestion('Wątpliwe 2/2: Anna Wrona (O-0004) — kto to?', 'skip')
        ->expectsOutputToContain('Pominięte osoby:')
        ->expectsConfirmation('Zapisać do drzewa konta autor@example.com: nowe osoby 11, dopasowane 0?', 'no')
        ->expectsOutputToContain('Nic nie zostało zapisane.')
        ->assertSuccessful();

    expect(ownershipSnapshot())->toBe($before);
});

test('refuses without asking and writes nothing when the tree already has a person from an import', function () {
    Storage::fake('local');
    $author = arrangeOverlappingUsers();
    Person::factory()->for($author->tree)->create(['first_name' => 'Leon', 'last_name' => 'Kruk', 'repo_id' => 'O-0012']);
    $before = ownershipSnapshot();

    $this->artisan('import:research', importArguments($author))
        ->expectsOutputToContain('Ponowny import (S-04)')
        ->doesntExpectOutputToContain('Dopasowanie do osób w drzewie')
        ->assertFailed();

    expect(ownershipSnapshot())->toBe($before);
});

test('refuses when run with --no-interaction and the repo has matches to answer, and writes nothing', function () {
    Storage::fake('local');
    $author = arrangeOverlappingUsers();
    $before = ownershipSnapshot();

    $exitCode = Artisan::call('import:research', [...importArguments($author), '--no-interaction' => true]);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('Dopasowanie wymaga odpowiedzi w terminalu; nic nie zostało zapisane.')
        ->and(ownershipSnapshot())->toBe($before);
});

test('saves the persons to the author tree after a yes and suggests the proband', function () {
    Storage::fake('local');
    $author = arrangeEmptyAuthorWithOverlappingOther();

    $this->artisan('import:research', importArguments($author))
        ->expectsConfirmation('Zapisać do drzewa konta autor@example.com: nowe osoby 13, dopasowane 0?', 'yes')
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
        ->expectsConfirmation('Zapisać do drzewa konta autor@example.com: nowe osoby 13, dopasowane 0?', 'no')
        ->assertSuccessful();

    expect(ownershipSnapshot())->toBe($before);
});

test('writes nothing without asking when run with --no-interaction', function () {
    Storage::fake('local');
    $author = arrangeEmptyAuthorWithOverlappingOther();
    $before = ownershipSnapshot();

    // Artisan::call, not $this->artisan(): its console output double intercepts every question, even with --no-interaction.
    $exitCode = Artisan::call('import:research', [...importArguments($author), '--no-interaction' => true]);

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('Nic nie zostało zapisane.')
        ->and(ownershipSnapshot())->toBe($before);
});

test('has nothing to save when the repo has no persons', function () {
    $author = arrangeEmptyAuthorWithOverlappingOther();
    $before = ownershipSnapshot();
    $repo = sys_get_temp_dir().'/empty-repo-'.uniqid();
    mkdir($repo.'/drzewo', 0777, true);

    try {
        $this->artisan('import:research', ['email' => $author->email, 'path' => $repo])
            ->expectsOutputToContain('Nic do zapisania.')
            ->assertSuccessful();
    } finally {
        File::deleteDirectory($repo);
    }

    expect(ownershipSnapshot())->toBe($before);
});

test('reports a failure in the middle of the write and writes nothing', function () {
    Storage::fake('local');
    Exceptions::fake();
    $author = arrangeEmptyAuthorWithOverlappingOther();
    $before = ownershipSnapshot();

    $this->mock(CreateMarriage::class, function ($mock): void {
        $mock->shouldReceive('handle')->andThrow(new RuntimeException('Zapis małżeństwa nie powiódł się.'));
    });

    $this->artisan('import:research', importArguments($author))
        ->expectsConfirmation('Zapisać do drzewa konta autor@example.com: nowe osoby 13, dopasowane 0?', 'yes')
        ->expectsOutputToContain('Zapis małżeństwa nie powiódł się.')
        ->expectsOutputToContain('Nic nie zostało zapisane.')
        ->assertFailed();

    Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception->getMessage() === 'Zapis małżeństwa nie powiódł się.');
    expect(ownershipSnapshot())->toBe($before);
});

test('stops when the database backup fails in production', function () {
    Storage::fake('local');
    $calls = fakeBackupRun(1);
    app()->detectEnvironment(fn (): string => 'production');
    $author = arrangeEmptyAuthorWithOverlappingOther();
    $before = ownershipSnapshot();

    $this->artisan('import:research', importArguments($author))
        ->expectsConfirmation('Zapisać do drzewa konta autor@example.com: nowe osoby 13, dopasowane 0?', 'yes')
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
        ->expectsConfirmation('Zapisać do drzewa konta autor@example.com: nowe osoby 13, dopasowane 0?', 'yes')
        ->assertSuccessful();

    expect($calls->getArrayCopy())->toBe([true])
        ->and($author->tree->people()->count())->toBe(13);
});

test('does not run a backup outside production', function () {
    Storage::fake('local');
    $calls = fakeBackupRun(0);
    $author = arrangeEmptyAuthorWithOverlappingOther();

    $this->artisan('import:research', importArguments($author))
        ->expectsConfirmation('Zapisać do drzewa konta autor@example.com: nowe osoby 13, dopasowane 0?', 'yes')
        ->assertSuccessful();

    expect($calls->getArrayCopy())->toBe([])
        ->and($author->tree->people()->count())->toBe(13);
});

test('lists files without a block only with -v', function () {
    $author = arrangeEmptyAuthorWithOverlappingOther();
    $arguments = importArguments($author);

    $this->artisan('import:research', $arguments)
        ->doesntExpectOutputToContain('Pliki bez bloku:')
        ->expectsConfirmation('Zapisać do drzewa konta autor@example.com: nowe osoby 13, dopasowane 0?', 'no')
        ->assertSuccessful();

    $this->artisan('import:research', [...$arguments, '-v' => true])
        ->expectsOutputToContain('Pliki bez bloku:')
        ->expectsOutputToContain('bez_bloku.md')
        ->expectsConfirmation('Zapisać do drzewa konta autor@example.com: nowe osoby 13, dopasowane 0?', 'no')
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

/**
 * Jan Nowak (male, birth 1801-04, son of Piotr Nowak) and Piotr Nowak (male, no dates): against the fixture repo Jan is
 * a sure match through his birth year and father, with a conflicting birth month, and Piotr has no element in common.
 *
 * @return array{jan: Person, piotr: Person}
 */
function arrangeCommandMatchTree(Tree $tree): array
{
    $jan = Person::factory()->male()->for($tree)->create(['first_name' => 'Jan', 'last_name' => 'Nowak']);
    $piotr = Person::factory()->male()->for($tree)->create(['first_name' => 'Piotr', 'last_name' => 'Nowak']);

    attachBirth($jan, 1801, 4);
    linkParent($piotr, $jan);

    return compact('jan', 'piotr');
}

/**
 * The author and another account with the same hand-entered persons.
 *
 * @return array{0: User, 1: array{jan: Person, piotr: Person}, 2: User}
 */
function arrangeCommandMatchAuthor(): array
{
    $other = User::factory()->create(['email' => 'inny@example.com']);
    $author = User::factory()->create(['email' => 'autor@example.com']);

    arrangeCommandMatchTree($other->tree);

    return [$author, arrangeCommandMatchTree($author->tree), $other];
}

/**
 * The command up to the doubtful match: the sure one approved together.
 */
function matchCommandUntilDoubtful(User $author): PendingCommand
{
    return test()->artisan('import:research', importArguments($author))
        ->expectsOutputToContain('nowe: 11, pewne: 1, wątpliwe: 1')
        ->expectsOutputToContain('Pewne dopasowania:')
        ->expectsOutputToContain('rok urodzenia, ojciec')
        ->expectsChoice('Pewne dopasowania (1): co zrobić?', 'all', ['all' => 'Zatwierdź wszystkie', 'each' => 'Przejrzyj pojedynczo'], true);
}

/**
 * @return array<string, string> the options of the question about Piotr Nowak (O-0002)
 */
function piotrOptions(Person $piotr): array
{
    return [
        "person:{$piotr->id}" => "To Piotr Nowak (#{$piotr->id}, ur. brak daty)",
        'new' => 'Inna osoba — dodaj jako nową',
        'skip' => 'Pomiń — nie importuj teraz',
    ];
}

const BIRTH_CONFLICT_QUESTION = 'Konflikt 1/1: Jan Nowak (O-0001) — Data urodzenia';

const BIRTH_CONFLICT_OPTIONS = ['app' => 'Zostaw z aplikacji: 1801-04', 'repo' => 'Weź z repo: 1801-05-12'];

test('hooks the repo into the author hand-entered persons after sure, doubtful and conflict answers and leaves the other account alone', function () {
    Storage::fake('local');
    [$author, $scene, $other] = arrangeCommandMatchAuthor();
    $otherPeopleBefore = Person::where('tree_id', $other->tree->id)->orderBy('id')->get()->toArray();
    $otherEventsBefore = Event::where('tree_id', $other->tree->id)->orderBy('id')->get()->toArray();

    matchCommandUntilDoubtful($author)
        ->expectsOutputToContain('Wątpliwe 1/1: Piotr Nowak (O-0002)')
        ->expectsOutputToContain('Kandydat 1 (#'.$scene['piotr']->id.')')
        ->expectsOutputToContain('Sprzeczne')
        ->expectsChoice('Wątpliwe 1/1: Piotr Nowak (O-0002) — kto to?', "person:{$scene['piotr']->id}", piotrOptions($scene['piotr']), true)
        ->expectsOutputToContain('Osoby: nowe 11, dopasowane 2, pominięte 0')
        ->expectsOutputToContain('Konflikty wartości do rozstrzygnięcia: 1')
        ->expectsChoice(BIRTH_CONFLICT_QUESTION, 'repo', BIRTH_CONFLICT_OPTIONS, true)
        ->expectsConfirmation('Zapisać do drzewa konta autor@example.com: nowe osoby 11, dopasowane 2?', 'yes')
        ->expectsOutputToContain('Osoby: nowe 11, dopasowane 2')
        ->expectsOutputToContain('Import nie ustawia probanta')
        ->assertSuccessful();

    $tree = $author->tree;
    $birth = $scene['jan']->fresh()->birth();

    expect($tree->people()->count())->toBe(13)
        ->and($scene['jan']->fresh()->repo_id)->toBe('O-0001')
        ->and($scene['piotr']->fresh()->repo_id)->toBe('O-0002')
        ->and([$birth->date_year, $birth->date_month, $birth->date_day])->toBe([1801, 5, 12])
        ->and($birth->repo_id)->toBe('O-0001/urodzenie')
        ->and($tree->parentChildRelations()->where('repo_id', 'O-0002>O-0001')->count())->toBe(1)
        ->and(ParentChildRelation::where('tree_id', $tree->id)->where('child_id', $scene['jan']->id)->count())->toBe(2);

    expect(Person::where('tree_id', $other->tree->id)->orderBy('id')->get()->toArray())->toBe($otherPeopleBefore)
        ->and(Event::where('tree_id', $other->tree->id)->orderBy('id')->get()->toArray())->toBe($otherEventsBefore)
        ->and(Person::where('tree_id', $other->tree->id)->whereNotNull('repo_id')->count())->toBe(0)
        ->and($other->tree->people()->count())->toBe(2);
});

test('keeps the value from the app when the author settles the conflict that way', function () {
    Storage::fake('local');
    [$author, $scene] = arrangeCommandMatchAuthor();

    matchCommandUntilDoubtful($author)
        ->expectsQuestion('Wątpliwe 1/1: Piotr Nowak (O-0002) — kto to?', "person:{$scene['piotr']->id}")
        ->expectsQuestion(BIRTH_CONFLICT_QUESTION, 'app')
        ->expectsConfirmation('Zapisać do drzewa konta autor@example.com: nowe osoby 11, dopasowane 2?', 'yes')
        ->assertSuccessful();

    $birth = $scene['jan']->fresh()->birth();

    expect([$birth->date_year, $birth->date_month, $birth->date_day])->toBe([1801, 4, null])
        ->and($scene['jan']->fresh()->repo_id)->toBe('O-0001');
});

test('adds a doubtful repo person as a new person next to the hand-entered one', function () {
    Storage::fake('local');
    [$author, $scene] = arrangeCommandMatchAuthor();

    matchCommandUntilDoubtful($author)
        ->expectsQuestion('Wątpliwe 1/1: Piotr Nowak (O-0002) — kto to?', 'new')
        ->expectsQuestion(BIRTH_CONFLICT_QUESTION, 'app')
        ->expectsConfirmation('Zapisać do drzewa konta autor@example.com: nowe osoby 12, dopasowane 1?', 'yes')
        ->assertSuccessful();

    $piotrs = $author->tree->people()->where('first_name', 'Piotr')->orderBy('id')->get();

    expect($piotrs)->toHaveCount(2)
        ->and($piotrs[0]->id)->toBe($scene['piotr']->id)
        ->and($piotrs[0]->repo_id)->toBeNull()
        ->and($piotrs[1]->repo_id)->toBe('O-0002')
        ->and($author->tree->people()->count())->toBe(14);
});

test('leaves out a doubtful repo person and its relations and lists it among the skipped persons', function () {
    Storage::fake('local');
    [$author, $scene] = arrangeCommandMatchAuthor();

    matchCommandUntilDoubtful($author)
        ->expectsQuestion('Wątpliwe 1/1: Piotr Nowak (O-0002) — kto to?', 'skip')
        ->expectsOutputToContain('Osoby: nowe 11, dopasowane 1, pominięte 1')
        ->expectsOutputToContain('Pominięte osoby:')
        ->expectsOutputToContain('osoba pominięta przy dopasowaniu')
        ->expectsQuestion(BIRTH_CONFLICT_QUESTION, 'repo')
        ->expectsConfirmation('Zapisać do drzewa konta autor@example.com: nowe osoby 11, dopasowane 1?', 'yes')
        ->assertSuccessful();

    $tree = $author->tree;

    expect($tree->people()->count())->toBe(13)
        ->and($tree->people()->where('repo_id', 'O-0002')->exists())->toBeFalse()
        ->and($scene['piotr']->fresh()->repo_id)->toBeNull()
        ->and($tree->parentChildRelations()->whereIn('repo_id', ['O-0002>O-0001', 'O-0005>O-0002'])->count())->toBe(0)
        ->and($tree->parentChildRelations()->where('repo_id', 'O-0003>O-0001')->count())->toBe(1);
});

test('walks through the sure match one by one before the doubtful ones', function () {
    Storage::fake('local');
    [$author, $scene] = arrangeCommandMatchAuthor();

    $this->artisan('import:research', importArguments($author))
        ->expectsChoice('Pewne dopasowania (1): co zrobić?', 'each', ['all' => 'Zatwierdź wszystkie', 'each' => 'Przejrzyj pojedynczo'], true)
        ->expectsOutputToContain('Pewne 1/1: Jan Nowak (O-0001)')
        ->expectsChoice('Pewne 1/1: Jan Nowak (O-0001) — kto to?', "person:{$scene['jan']->id}", [
            "person:{$scene['jan']->id}" => "To Jan Nowak (#{$scene['jan']->id}, ur. 1801-04)",
            'new' => 'Inna osoba — dodaj jako nową',
            'skip' => 'Pomiń — nie importuj teraz',
        ], true)
        ->expectsQuestion('Wątpliwe 1/1: Piotr Nowak (O-0002) — kto to?', 'skip')
        ->expectsQuestion(BIRTH_CONFLICT_QUESTION, 'app')
        ->expectsConfirmation('Zapisać do drzewa konta autor@example.com: nowe osoby 11, dopasowane 1?', 'no')
        ->assertSuccessful();
});

test('does not offer a tree person who was chosen before for another repo person', function () {
    Storage::fake('local');
    $other = User::factory()->create(['email' => 'inny@example.com']);
    $author = User::factory()->create(['email' => 'autor@example.com']);
    $people = [];

    foreach ([$other, $author] as $user) {
        $zalesie = Locality::factory()->create(['tree_id' => $user->tree->id, 'name' => 'Zalesie']);
        $jan = Person::factory()->male()->for($user->tree)->create(['first_name' => 'Jan', 'last_name' => 'Nowak']);
        $birth = Event::factory()->for($user->tree)->birth()->create([
            'date_year' => 1801,
            'date_month' => null,
            'date_day' => null,
            'locality_id' => $zalesie->id,
        ]);
        $birth->participants()->attach($jan, ['role' => ParticipantRole::Subject]);
        $people[$user->email] = $jan;
    }

    $repo = commandTemporaryRepo([
        'O-0001' => "imiona: Jan\nnazwisko: Nowak\nplec: M\nurodzenie:\n  data: 1801\n  miejsce: Zalesie",
        'O-0003' => "imiona: Jan\nnazwisko: Nowak\nplec: M\nurodzenie:\n  data: 1830",
    ]);

    try {
        $this->artisan('import:research', ['email' => $author->email, 'path' => $repo])
            ->expectsOutputToContain('nowe: 0, pewne: 1, wątpliwe: 1')
            ->expectsChoice('Pewne dopasowania (1): co zrobić?', 'each', ['all' => 'Zatwierdź wszystkie', 'each' => 'Przejrzyj pojedynczo'], true)
            ->expectsChoice('Pewne 1/1: Jan Nowak (O-0001) — kto to?', "person:{$people['autor@example.com']->id}", [
                "person:{$people['autor@example.com']->id}" => "To Jan Nowak (#{$people['autor@example.com']->id}, ur. 1801)",
                'new' => 'Inna osoba — dodaj jako nową',
                'skip' => 'Pomiń — nie importuj teraz',
            ], true)
            ->expectsOutputToContain('— wybrany')
            ->expectsChoice('Wątpliwe 1/1: Jan Nowak (O-0003) — kto to?', 'new', [
                'new' => 'Inna osoba — dodaj jako nową',
                'skip' => 'Pomiń — nie importuj teraz',
            ], true)
            ->expectsConfirmation('Zapisać do drzewa konta autor@example.com: nowe osoby 1, dopasowane 1?', 'yes')
            ->assertSuccessful();
    } finally {
        File::deleteDirectory($repo);
    }

    expect($author->tree->people()->orderBy('id')->pluck('repo_id')->all())->toBe(['O-0001', 'O-0003'])
        ->and($other->tree->people()->pluck('repo_id')->all())->toBe([null]);
});

test('stops after a yes when the database backup fails in production and the tree has matches', function () {
    Storage::fake('local');
    $calls = fakeBackupRun(1);
    app()->detectEnvironment(fn (): string => 'production');
    [$author, $scene] = arrangeCommandMatchAuthor();
    $before = ownershipSnapshot();

    matchCommandUntilDoubtful($author)
        ->expectsQuestion('Wątpliwe 1/1: Piotr Nowak (O-0002) — kto to?', "person:{$scene['piotr']->id}")
        ->expectsQuestion(BIRTH_CONFLICT_QUESTION, 'repo')
        ->expectsConfirmation('Zapisać do drzewa konta autor@example.com: nowe osoby 11, dopasowane 2?', 'yes')
        ->expectsOutputToContain('Kopia bazy nie powiodła się, nic nie zostało zapisane.')
        ->assertFailed();

    expect($calls->getArrayCopy())->toBe([true])
        ->and(ownershipSnapshot())->toBe($before);
});

test('writes nothing after a no at the end of the match walkthrough', function () {
    Storage::fake('local');
    [$author, $scene] = arrangeCommandMatchAuthor();
    $before = ownershipSnapshot();

    matchCommandUntilDoubtful($author)
        ->expectsQuestion('Wątpliwe 1/1: Piotr Nowak (O-0002) — kto to?', "person:{$scene['piotr']->id}")
        ->expectsQuestion(BIRTH_CONFLICT_QUESTION, 'repo')
        ->expectsConfirmation('Zapisać do drzewa konta autor@example.com: nowe osoby 11, dopasowane 2?', 'no')
        ->expectsOutputToContain('Nic nie zostało zapisane.')
        ->assertSuccessful();

    expect(ownershipSnapshot())->toBe($before);
});

/**
 * A throwaway repo of person blocks, for matches the fixture repo cannot give.
 *
 * @param  array<string, string>  $blocks  repo id => block body without id and version
 */
function commandTemporaryRepo(array $blocks): string
{
    $repo = sys_get_temp_dir().'/command-repo-'.uniqid();
    mkdir($repo.'/drzewo', 0777, true);

    foreach ($blocks as $id => $body) {
        file_put_contents("{$repo}/drzewo/".strtolower($id).'.md', "---\nwersja: 1\nid: {$id}\n{$body}\n---\n");
    }

    return $repo;
}
