<?php

use App\Actions\Import\ApplyImport;
use App\Actions\Import\ClassifyRepoRecords;
use App\Actions\Import\PlanImport;
use App\Actions\Import\ReadResearchRepo;
use App\Actions\Relations\CreateMarriage;
use App\Enums\ParishKind;
use App\Enums\ParticipantRole;
use App\Enums\Religion;
use App\Enums\SourceRecordType;
use App\Models\Cemetery;
use App\Models\Event;
use App\Models\Locality;
use App\Models\ParentChildRelation;
use App\Models\Parish;
use App\Models\Person;
use App\Models\PersonSurnameVariant;
use App\Models\RepoSnapshot;
use App\Models\Source;
use App\Models\Tree;
use App\Models\User;
use App\Support\Import\ChangeApprovals;
use App\Support\Import\ConflictResolutions;
use App\Support\Import\IdentityDecisions;
use App\Support\Import\ImportDecisions;
use App\Support\Import\PlaceDecisions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
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

/**
 * The question that asks for the final consent, with the counts of the plan.
 */
function saveQuestion(int $new, int $matched, int $memory, int $changes = 0, int $removals = 0, string $sources = ''): string
{
    $sourcesText = $sources === '' ? '' : ", źródła {$sources}";

    return "Zapisać do drzewa konta autor@example.com: nowe osoby {$new}, dopasowane {$matched}, zmiany {$changes}, usunięcia {$removals}, pamięć importu {$memory}{$sourcesText}?";
}

const PLACES_MODE_OPTIONS = ['all' => 'Zatwierdź wszystkie', 'each' => 'Przejrzyj pojedynczo'];

function newPlacesQuestion(int $count): string
{
    return "Nowe miejscowości ({$count}): co zrobić?";
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
        ->expectsOutput('Warianty nazwiska przy osobach: 1')
        ->doesntExpectOutputToContain('(S-09)')
        ->expectsChoice(newPlacesQuestion(2), 'all', PLACES_MODE_OPTIONS, true)
        ->expectsConfirmation(saveQuestion(13, 0, 27), 'no')
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
        ->expectsChoice(newPlacesQuestion(1), 'all', PLACES_MODE_OPTIONS, true)
        ->expectsConfirmation(saveQuestion(11, 0, 19), 'no')
        ->expectsOutputToContain('Nic nie zostało zapisane.')
        ->assertSuccessful();

    expect(ownershipSnapshot())->toBe($before);
});

test('refuses when run with --no-interaction and the repo has matches to answer, and writes nothing', function () {
    Storage::fake('local');
    $author = arrangeOverlappingUsers();
    $before = ownershipSnapshot();

    $exitCode = Artisan::call('import:research', [...importArguments($author), '--no-interaction' => true]);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('Import wymaga odpowiedzi w terminalu; nic nie zostało zapisane.')
        ->and(ownershipSnapshot())->toBe($before);
});

test('saves the persons to the author tree after a yes and suggests the proband', function () {
    Storage::fake('local');
    $author = arrangeEmptyAuthorWithOverlappingOther();

    $this->artisan('import:research', importArguments($author))
        ->expectsChoice(newPlacesQuestion(2), 'all', PLACES_MODE_OPTIONS, true)
        ->expectsConfirmation(saveQuestion(13, 0, 27), 'yes')
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
        ->expectsChoice(newPlacesQuestion(2), 'all', PLACES_MODE_OPTIONS, true)
        ->expectsConfirmation(saveQuestion(13, 0, 27), 'no')
        ->assertSuccessful();

    expect(ownershipSnapshot())->toBe($before);
});

test('refuses new places when run with --no-interaction and writes nothing', function () {
    Storage::fake('local');
    $author = arrangeEmptyAuthorWithOverlappingOther();
    $before = ownershipSnapshot();

    // Artisan::call, not $this->artisan(): its console output double intercepts every question, even with --no-interaction.
    $exitCode = Artisan::call('import:research', [...importArguments($author), '--no-interaction' => true]);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('Import wymaga odpowiedzi w terminalu; nic nie zostało zapisane.')
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
        ->expectsChoice(newPlacesQuestion(2), 'all', PLACES_MODE_OPTIONS, true)
        ->expectsConfirmation(saveQuestion(13, 0, 27), 'yes')
        ->expectsOutputToContain('Zapis małżeństwa nie powiódł się.')
        ->expectsOutputToContain('Nic nie zostało zapisane.')
        ->assertFailed();

    Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception->getMessage() === 'Zapis małżeństwa nie powiódł się.');
    expect(ownershipSnapshot())->toBe($before);
});

test('reports a plan that no longer fits the tree and writes nothing', function () {
    Storage::fake('local');
    $author = arrangeEmptyAuthorWithOverlappingOther();
    $before = ownershipSnapshot();

    $this->mock(PlanImport::class, function ($mock): void {
        $mock->shouldReceive('handle')->andThrow(new InvalidArgumentException('Decyzje wskazują osobę spoza drzewa konta.'));
    });

    $this->artisan('import:research', importArguments($author))
        ->expectsChoice(newPlacesQuestion(2), 'all', PLACES_MODE_OPTIONS, true)
        ->expectsOutputToContain('Decyzje wskazują osobę spoza drzewa konta.')
        ->doesntExpectOutputToContain('Podsumowanie importu')
        ->assertFailed();

    expect(ownershipSnapshot())->toBe($before);
});

test('stops when the database backup fails in production', function () {
    Storage::fake('local');
    $calls = fakeBackupRun(1);
    app()->detectEnvironment(fn (): string => 'production');
    $author = arrangeEmptyAuthorWithOverlappingOther();
    $before = ownershipSnapshot();

    $this->artisan('import:research', importArguments($author))
        ->expectsChoice(newPlacesQuestion(2), 'all', PLACES_MODE_OPTIONS, true)
        ->expectsConfirmation(saveQuestion(13, 0, 27), 'yes')
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
        ->expectsChoice(newPlacesQuestion(2), 'all', PLACES_MODE_OPTIONS, true)
        ->expectsConfirmation(saveQuestion(13, 0, 27), 'yes')
        ->assertSuccessful();

    expect($calls->getArrayCopy())->toBe([true])
        ->and($author->tree->people()->count())->toBe(13);
});

test('does not run a backup outside production', function () {
    Storage::fake('local');
    $calls = fakeBackupRun(0);
    $author = arrangeEmptyAuthorWithOverlappingOther();

    $this->artisan('import:research', importArguments($author))
        ->expectsChoice(newPlacesQuestion(2), 'all', PLACES_MODE_OPTIONS, true)
        ->expectsConfirmation(saveQuestion(13, 0, 27), 'yes')
        ->assertSuccessful();

    expect($calls->getArrayCopy())->toBe([])
        ->and($author->tree->people()->count())->toBe(13);
});

test('lists files without a block only with -v', function () {
    $author = arrangeEmptyAuthorWithOverlappingOther();
    $arguments = importArguments($author);

    $this->artisan('import:research', $arguments)
        ->doesntExpectOutputToContain('Pliki bez bloku:')
        ->expectsChoice(newPlacesQuestion(2), 'all', PLACES_MODE_OPTIONS, true)
        ->expectsConfirmation(saveQuestion(13, 0, 27), 'no')
        ->assertSuccessful();

    $this->artisan('import:research', [...$arguments, '-v' => true])
        ->expectsOutputToContain('Pliki bez bloku:')
        ->expectsOutputToContain('bez_bloku.md')
        ->expectsChoice(newPlacesQuestion(2), 'all', PLACES_MODE_OPTIONS, true)
        ->expectsConfirmation(saveQuestion(13, 0, 27), 'no')
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
        ->expectsChoice(newPlacesQuestion(2), 'all', PLACES_MODE_OPTIONS, true)
        ->expectsChoice(BIRTH_CONFLICT_QUESTION, 'repo', BIRTH_CONFLICT_OPTIONS, true)
        ->expectsConfirmation(saveQuestion(11, 2, 27, changes: 3), 'yes')
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
        ->expectsChoice(newPlacesQuestion(2), 'all', PLACES_MODE_OPTIONS, true)
        ->expectsQuestion(BIRTH_CONFLICT_QUESTION, 'app')
        ->expectsConfirmation(saveQuestion(11, 2, 27, changes: 2), 'yes')
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
        ->expectsChoice(newPlacesQuestion(2), 'all', PLACES_MODE_OPTIONS, true)
        ->expectsQuestion(BIRTH_CONFLICT_QUESTION, 'app')
        ->expectsConfirmation(saveQuestion(12, 1, 26, changes: 2), 'yes')
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
        ->expectsChoice(newPlacesQuestion(2), 'all', PLACES_MODE_OPTIONS, true)
        ->expectsQuestion(BIRTH_CONFLICT_QUESTION, 'repo')
        ->expectsConfirmation(saveQuestion(11, 1, 21, changes: 3), 'yes')
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
        ->expectsChoice(newPlacesQuestion(2), 'all', PLACES_MODE_OPTIONS, true)
        ->expectsQuestion(BIRTH_CONFLICT_QUESTION, 'app')
        ->expectsConfirmation(saveQuestion(11, 1, 21, changes: 2), 'no')
        ->assertSuccessful();
});

/**
 * Jan Nowak without dates, son of Piotr Nowak (male, birth 1770) and Maria Wrona (female, birth 1775), who married in 1795.
 *
 * @return list<Person> Jan, Piotr and Maria
 */
function arrangeSureThroughParentsTree(Tree $tree): array
{
    $jan = Person::factory()->for($tree)->create(['first_name' => 'Jan', 'last_name' => 'Nowak']);
    $piotr = Person::factory()->male()->for($tree)->create(['first_name' => 'Piotr', 'last_name' => 'Nowak']);
    $maria = Person::factory()->female()->for($tree)->create(['first_name' => 'Maria', 'last_name' => 'Wrona']);

    attachBirth($piotr, 1770);
    attachBirth($maria, 1775);
    attachMarriage($piotr, $maria, 1795);
    linkParent($piotr, $jan);
    linkParent($maria, $jan);

    return [$jan, $piotr, $maria];
}

test('lists a hand-entered person who is sure through both parents by name in the sure table and matches it on approving all', function () {
    Storage::fake('local');
    $other = User::factory()->create(['email' => 'inny@example.com']);
    $author = User::factory()->create(['email' => 'autor@example.com']);
    arrangeSureThroughParentsTree($other->tree);
    [$jan, $piotr, $maria] = arrangeSureThroughParentsTree($author->tree);
    $otherPeopleBefore = Person::where('tree_id', $other->tree->id)->orderBy('id')->get()->toArray();
    $otherRelationsBefore = ParentChildRelation::where('tree_id', $other->tree->id)->orderBy('id')->get()->toArray();

    $repo = commandTemporaryRepo([
        'O-0001' => "imiona: Jan\nnazwisko: Nowak\nrodzice:\n  ojciec: O-0002\n  matka: O-0003",
        'O-0002' => "imiona: Piotr\nnazwisko: Nowak\nplec: M\nurodzenie:\n  data: \"1770\"\nmalzenstwa:\n  - z: O-0003\n    data: \"1795\"",
        'O-0003' => "imiona: Maria\nnazwisko: Wrona\nplec: K\nurodzenie:\n  data: \"1775\"",
    ]);

    try {
        $this->artisan('import:research', ['email' => $author->email, 'path' => $repo])
            ->expectsOutputToContain('nowe: 0, pewne: 3, wątpliwe: 0')
            ->expectsOutputToContain('Pewne dopasowania:')
            ->expectsOutputToContain('ojciec, matka')
            ->expectsChoice('Pewne dopasowania (3): co zrobić?', 'all', ['all' => 'Zatwierdź wszystkie', 'each' => 'Przejrzyj pojedynczo'], true)
            ->expectsOutputToContain('Osoby: nowe 0, dopasowane 3, pominięte 0')
            ->expectsOutputToContain('Konflikty wartości do rozstrzygnięcia: 0')
            ->expectsConfirmation(saveQuestion(0, 3, 8), 'yes')
            ->expectsOutputToContain('Relacje rodzic–dziecko: 0 (istniejące oznaczone: 2)')
            ->assertSuccessful();
    } finally {
        File::deleteDirectory($repo);
    }

    expect($jan->fresh()->repo_id)->toBe('O-0001')
        ->and($piotr->fresh()->repo_id)->toBe('O-0002')
        ->and($maria->fresh()->repo_id)->toBe('O-0003')
        ->and($author->tree->people()->count())->toBe(3)
        ->and($author->tree->parentChildRelations()->orderBy('repo_id')->pluck('repo_id')->all())->toBe(['O-0002>O-0001', 'O-0003>O-0001']);

    expect(Person::where('tree_id', $other->tree->id)->orderBy('id')->get()->toArray())->toBe($otherPeopleBefore)
        ->and(ParentChildRelation::where('tree_id', $other->tree->id)->orderBy('id')->get()->toArray())->toBe($otherRelationsBefore)
        ->and(Person::where('tree_id', $other->tree->id)->whereNotNull('repo_id')->count())->toBe(0);
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
            ->expectsConfirmation(saveQuestion(1, 1, 4), 'yes')
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
        ->expectsChoice(newPlacesQuestion(2), 'all', PLACES_MODE_OPTIONS, true)
        ->expectsQuestion(BIRTH_CONFLICT_QUESTION, 'repo')
        ->expectsConfirmation(saveQuestion(11, 2, 27, changes: 3), 'yes')
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
        ->expectsChoice(newPlacesQuestion(2), 'all', PLACES_MODE_OPTIONS, true)
        ->expectsQuestion(BIRTH_CONFLICT_QUESTION, 'repo')
        ->expectsConfirmation(saveQuestion(11, 2, 27, changes: 3), 'no')
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

afterEach(function (): void {
    foreach (reimportRepos() as $repo) {
        File::deleteDirectory($repo);
    }
});

/**
 * The throwaway repos of this test, removed afterwards.
 *
 * @return list<string>
 */
function reimportRepos(?string $add = null): array
{
    static $repos = [];

    if ($add !== null) {
        $repos[] = $add;
    }

    return $repos;
}

/**
 * Jan Nowak (birth 1801, death 1870), Anna Wrona (birth 1805) and their son Leon Nowak (birth 1830).
 *
 * @return array<string, string>
 */
function reimportBlocks(): array
{
    return [
        'O-0001' => "imiona: Jan\nnazwisko: Nowak\nplec: M\nurodzenie:\n  data: \"1801\"\nzgon:\n  data: \"1870\"",
        'O-0002' => "imiona: Anna\nnazwisko: Wrona\nplec: K\nurodzenie:\n  data: \"1805\"",
        'O-0003' => "imiona: Leon\nnazwisko: Nowak\nplec: M\nurodzenie:\n  data: \"1830\"\nrodzice:\n  ojciec: O-0001\n  matka: O-0002",
    ];
}

function reimportRepo(array $blocks): string
{
    $repo = commandTemporaryRepo($blocks);
    reimportRepos($repo);

    return $repo;
}

/**
 * Import a repo into a tree the way the command does after the author approves everything.
 */
function importRepoInto(Tree $tree, string $repo): void
{
    $reading = app(ReadResearchRepo::class)->handle($repo);
    $classes = app(ClassifyRepoRecords::class)->handle($tree, $reading);
    $decisions = ImportDecisions::forFirstImport(IdentityDecisions::allNew($reading));
    $plan = app(PlanImport::class)->handle($tree, $reading, $classes, $decisions);

    app(ApplyImport::class)->handle($tree, $plan, new ChangeApprovals, new ConflictResolutions);
}

/**
 * The author and another account that both imported the same repo and changed the same values in the app afterwards.
 *
 * @return array{0: User, 1: User}
 */
function arrangeImportedAuthors(): array
{
    Storage::fake('local');
    $other = User::factory()->create(['email' => 'inny@example.com']);
    $author = User::factory()->create(['email' => 'autor@example.com']);
    $first = reimportRepo(reimportBlocks());

    foreach ([$other, $author] as $user) {
        importRepoInto($user->tree, $first);
    }

    return [$author, $other];
}

function setBirthYearInApp(Tree $tree, string $repoId, int $year): void
{
    $tree->events()->where('repo_id', $repoId)->firstOrFail()->update(['date_year' => $year]);
}

function birthYearOf(Tree $tree, string $repoId): ?int
{
    return $tree->events()->where('repo_id', $repoId)->firstOrFail()->date_year;
}

/**
 * Everything a re-import could touch in a tree.
 *
 * @return array<string, mixed>
 */
function treeState(Tree $tree): array
{
    return [
        'people' => $tree->people()->orderBy('id')->get()->toArray(),
        'events' => $tree->events()->orderBy('id')->get()->toArray(),
        'relations' => $tree->parentChildRelations()->orderBy('id')->get()->toArray(),
        'memory' => $tree->repoSnapshots()->orderBy('id')->get()->toArray(),
    ];
}

const CHANGES_ALL = ['all' => 'Zatwierdź wszystkie', 'each' => 'Przejrzyj pojedynczo'];

test('refuses an empty repo when the tree already has imported persons and writes nothing', function () {
    [$author, $other] = arrangeImportedAuthors();
    $before = ownershipSnapshot();
    $repo = sys_get_temp_dir().'/empty-repo-'.uniqid();
    mkdir($repo.'/drzewo', 0777, true);
    reimportRepos($repo);

    $this->artisan('import:research', ['email' => $author->email, 'path' => $repo])
        ->expectsOutputToContain('Repo nie zawiera żadnej poprawnej osoby')
        ->assertFailed();

    expect(ownershipSnapshot())->toBe($before);
});

test('has nothing to save and asks nothing when neither the repo nor the app changed', function () {
    [$author, $other] = arrangeImportedAuthors();
    setBirthYearInApp($author->tree, 'O-0002/urodzenie', 1806);
    setBirthYearInApp($other->tree, 'O-0002/urodzenie', 1806);
    $before = ownershipSnapshot();

    $this->artisan('import:research', ['email' => $author->email, 'path' => reimportRepo(reimportBlocks())])
        ->expectsOutputToContain('Stan względem ostatniego importu')
        ->expectsOutputToContain('Znane osoby: 3')
        ->expectsOutputToContain('Zachowane ręczne zmiany: 1')
        ->expectsOutputToContain('Nic do zapisania.')
        ->assertSuccessful();

    expect(ownershipSnapshot())->toBe($before);
});

test('takes a changed date from the repo after approving all and leaves the other account alone', function () {
    [$author, $other] = arrangeImportedAuthors();
    $otherBefore = treeState($other->tree);
    $blocks = reimportBlocks();
    $blocks['O-0001'] = str_replace('1801', '1802', $blocks['O-0001']);

    $this->artisan('import:research', ['email' => $author->email, 'path' => reimportRepo($blocks)])
        ->expectsOutputToContain('Zmiany z repo: 1')
        ->expectsChoice('Zmiany z repo (1): co zrobić?', 'all', CHANGES_ALL, true)
        ->expectsConfirmation(saveQuestion(0, 0, 1, changes: 1), 'yes')
        ->expectsOutputToContain('Zmiany z repo: wzięte 1, odrzucone 0')
        ->assertSuccessful();

    expect(birthYearOf($author->tree, 'O-0001/urodzenie'))->toBe(1802)
        ->and(birthYearOf($other->tree, 'O-0001/urodzenie'))->toBe(1801)
        ->and(treeState($other->tree))->toBe($otherBefore);
});

test('shows a changed list of surname variants in the table of changes and writes it only for the author', function () {
    [$author, $other] = arrangeImportedAuthors();
    $otherBefore = treeState($other->tree);
    $otherVariants = PersonSurnameVariant::where('tree_id', $other->tree->id)->count();
    $blocks = reimportBlocks();
    $blocks['O-0001'] .= "\nwarianty_nazwiska: [Nowakowski, Nowacki]";

    $this->artisan('import:research', ['email' => $author->email, 'path' => reimportRepo($blocks)])
        ->expectsOutputToContain('Warianty nazwiska')
        ->expectsTable(['Osoba', 'Pole', 'W aplikacji', 'Teraz w repo'], [['Jan Nowak (O-0001)', 'Warianty nazwiska', '—', 'Nowakowski, Nowacki']])
        ->expectsChoice('Zmiany z repo (1): co zrobić?', 'all', CHANGES_ALL, true)
        ->expectsConfirmation(saveQuestion(0, 0, 1, changes: 1), 'yes')
        ->assertSuccessful();

    expect($author->tree->people()->where('repo_id', 'O-0001')->firstOrFail()->surnameVariants()->pluck('name')->all())->toBe(['Nowakowski', 'Nowacki'])
        ->and(PersonSurnameVariant::where('tree_id', $other->tree->id)->count())->toBe($otherVariants)
        ->and(treeState($other->tree))->toBe($otherBefore);
});

test('rejects one change and takes the other when the author goes through them one by one', function () {
    [$author, $other] = arrangeImportedAuthors();
    $otherBefore = treeState($other->tree);
    $blocks = reimportBlocks();
    $blocks['O-0001'] = str_replace('1801', '1802', $blocks['O-0001']);
    $blocks['O-0002'] = str_replace('1805', '1806', $blocks['O-0002']);

    $this->artisan('import:research', ['email' => $author->email, 'path' => reimportRepo($blocks)])
        ->expectsChoice('Zmiany z repo (2): co zrobić?', 'each', CHANGES_ALL, true)
        ->expectsQuestion('Zmiana 1/2: Jan Nowak (O-0001) — Data urodzenia', 'app')
        ->expectsQuestion('Zmiana 2/2: Anna Wrona (O-0002) — Data urodzenia', 'repo')
        ->expectsConfirmation(saveQuestion(0, 0, 2, changes: 1), 'yes')
        ->expectsOutputToContain('Zmiany z repo: wzięte 1, odrzucone 1')
        ->assertSuccessful();

    expect(birthYearOf($author->tree, 'O-0001/urodzenie'))->toBe(1801)
        ->and(birthYearOf($author->tree, 'O-0002/urodzenie'))->toBe(1806)
        ->and(treeState($other->tree))->toBe($otherBefore);
});

test('asks about a date changed on both sides, shows the previous repo value and keeps the app value on app', function () {
    [$author, $other] = arrangeImportedAuthors();
    setBirthYearInApp($author->tree, 'O-0001/urodzenie', 1850);
    setBirthYearInApp($other->tree, 'O-0001/urodzenie', 1850);
    $otherBefore = treeState($other->tree);
    $blocks = reimportBlocks();
    $blocks['O-0001'] = str_replace('1801', '1802', $blocks['O-0001']);

    $this->artisan('import:research', ['email' => $author->email, 'path' => reimportRepo($blocks)])
        ->expectsOutputToContain('Konflikty wartości do rozstrzygnięcia: 1')
        ->expectsOutputToContain('poprzednio w repo: 1801')
        ->expectsQuestion('Konflikt 1/1: Jan Nowak (O-0001) — Data urodzenia', 'app')
        ->expectsConfirmation(saveQuestion(0, 0, 1), 'yes')
        ->assertSuccessful();

    expect(birthYearOf($author->tree, 'O-0001/urodzenie'))->toBe(1850)
        ->and(treeState($other->tree))->toBe($otherBefore);
});

/**
 * The repo without Leon (O-0003), who is in both trees with his birth and his two parent pairs.
 *
 * @return array<string, string>
 */
function reimportBlocksWithoutLeon(): array
{
    return array_slice(reimportBlocks(), 0, 2, true);
}

test('shows what removing a person takes and removes him after delete and yes', function () {
    [$author, $other] = arrangeImportedAuthors();
    $otherBefore = treeState($other->tree);

    $this->artisan('import:research', ['email' => $author->email, 'path' => reimportRepo(reimportBlocksWithoutLeon())])
        ->expectsOutputToContain('Usunięte w repo: 1')
        ->expectsOutputToContain('Skutki usunięcia')
        ->expectsOutputToContain('relacje: 2')
        ->expectsQuestion('Usunięte w repo 1/1: Leon Nowak (O-0003) — co zrobić?', 'delete')
        ->expectsOutputToContain('Usunięcia: 1, odłączenia: 0, przywrócenia: 0')
        ->expectsConfirmation(saveQuestion(0, 0, 4, removals: 1), 'yes')
        ->expectsOutputToContain('Usunięte: osoby 1')
        ->assertSuccessful();

    expect($author->tree->people()->where('repo_id', 'O-0003')->exists())->toBeFalse()
        ->and($author->tree->people()->count())->toBe(2)
        ->and($author->tree->repoSnapshots()->where('repo_id', 'like', 'O-0003%')->count())->toBe(0)
        ->and(treeState($other->tree))->toBe($otherBefore);
});

test('detaches a person the repo dropped and does not ask about him again', function () {
    [$author, $other] = arrangeImportedAuthors();
    $otherBefore = treeState($other->tree);
    $repo = reimportRepo(reimportBlocksWithoutLeon());

    $this->artisan('import:research', ['email' => $author->email, 'path' => $repo])
        ->expectsQuestion('Usunięte w repo 1/1: Leon Nowak (O-0003) — co zrobić?', 'detach')
        ->expectsConfirmation(saveQuestion(0, 0, 4), 'yes')
        ->expectsOutputToContain('odłączone: 1')
        ->assertSuccessful();

    $leon = $author->tree->people()->where('first_name', 'Leon')->firstOrFail();

    expect($leon->repo_id)->toBeNull()
        ->and($author->tree->parentChildRelations()->whereNotNull('repo_id')->count())->toBe(0);

    $this->artisan('import:research', ['email' => $author->email, 'path' => $repo])
        ->expectsOutputToContain('Nic do zapisania.')
        ->assertSuccessful();

    expect(treeState($other->tree))->toBe($otherBefore);
});

test('leaves a death deleted in the app after keep and then does not ask again', function () {
    [$author, $other] = arrangeImportedAuthors();

    foreach ([$author, $other] as $user) {
        $user->tree->events()->where('repo_id', 'O-0001/zgon')->firstOrFail()->delete();
    }

    $otherBefore = treeState($other->tree);
    $repo = reimportRepo(reimportBlocks());

    $this->artisan('import:research', ['email' => $author->email, 'path' => $repo])
        ->expectsOutputToContain('Usunięte w aplikacji: 1 (do decyzji)')
        ->expectsQuestion('Usunięte w aplikacji 1/1: Zgon: Jan Nowak (O-0001/zgon) — co zrobić?', 'keep')
        ->expectsConfirmation(saveQuestion(0, 0, 1), 'yes')
        ->expectsOutputToContain('zostawione jako usunięte: 1')
        ->assertSuccessful();

    expect($author->tree->events()->where('repo_id', 'O-0001/zgon')->exists())->toBeFalse();

    $this->artisan('import:research', ['email' => $author->email, 'path' => $repo])
        ->expectsOutputToContain('Nic do zapisania.')
        ->assertSuccessful();

    expect(treeState($other->tree))->toBe($otherBefore);
});

test('brings a death deleted in the app back after restore', function () {
    [$author, $other] = arrangeImportedAuthors();

    foreach ([$author, $other] as $user) {
        $user->tree->events()->where('repo_id', 'O-0001/zgon')->firstOrFail()->delete();
    }

    $otherBefore = treeState($other->tree);

    $this->artisan('import:research', ['email' => $author->email, 'path' => reimportRepo(reimportBlocks())])
        ->expectsQuestion('Usunięte w aplikacji 1/1: Zgon: Jan Nowak (O-0001/zgon) — co zrobić?', 'restore')
        ->expectsOutputToContain('Usunięcia: 0, odłączenia: 0, przywrócenia: 1')
        ->expectsConfirmation(saveQuestion(0, 0, 0), 'yes')
        ->expectsOutputToContain('przywrócone: 1')
        ->assertSuccessful();

    $death = $author->tree->events()->where('repo_id', 'O-0001/zgon')->firstOrFail();

    expect($death->date_year)->toBe(1870)
        ->and(treeState($other->tree))->toBe($otherBefore);
});

test('asks for consent to write the memory of a tree imported before it existed and then has nothing to save', function () {
    [$author, $other] = arrangeImportedAuthors();
    $author->tree->repoSnapshots()->delete();
    $other->tree->repoSnapshots()->delete();
    $otherBefore = treeState($other->tree);
    $repo = reimportRepo(reimportBlocks());

    $this->artisan('import:research', ['email' => $author->email, 'path' => $repo])
        ->expectsConfirmation(saveQuestion(0, 0, 9), 'yes')
        ->assertSuccessful();

    expect($author->tree->repoSnapshots()->count())->toBe(9)
        ->and(RepoSnapshot::where('tree_id', $other->tree->id)->count())->toBe(0);

    $this->artisan('import:research', ['email' => $author->email, 'path' => $repo])
        ->expectsOutputToContain('Nic do zapisania.')
        ->assertSuccessful();

    expect(treeState($other->tree))->toBe($otherBefore);
});

test('asks who a new repo person is when a namesake was entered by hand', function () {
    [$author, $other] = arrangeImportedAuthors();

    foreach ([$author, $other] as $user) {
        Person::factory()->for($user->tree)->create(['first_name' => 'Maria', 'last_name' => 'Kruk']);
    }

    $otherBefore = treeState($other->tree);
    $blocks = reimportBlocks();
    $blocks['O-0004'] = "imiona: Maria\nnazwisko: Kruk\nplec: K";
    $maria = $author->tree->people()->where('first_name', 'Maria')->firstOrFail();

    $this->artisan('import:research', ['email' => $author->email, 'path' => reimportRepo($blocks)])
        ->expectsOutputToContain('Nowe w repo: 1')
        ->expectsQuestion('Wątpliwe 1/1: Maria Kruk (O-0004) — kto to?', "person:{$maria->id}")
        ->expectsConfirmation(saveQuestion(0, 1, 1, changes: 1), 'yes')
        ->assertSuccessful();

    expect($maria->fresh()->repo_id)->toBe('O-0004')
        ->and(treeState($other->tree))->toBe($otherBefore);
});

test('lists an entry skipped without an id as not assessable and does not ask about removals', function () {
    [$author, $other] = arrangeImportedAuthors();
    $otherBefore = treeState($other->tree);
    $repo = reimportRepo(reimportBlocksWithoutLeon());
    file_put_contents("{$repo}/drzewo/zepsuty.md", "---\nwersja: 1\nimiona: Ktoś\n---\n");
    $before = ownershipSnapshot();

    $this->artisan('import:research', ['email' => $author->email, 'path' => $repo])
        ->expectsOutputToContain('Nie do oceny: 1')
        ->expectsOutputToContain('nie do oceny: popraw pominięte wpisy repo')
        ->expectsOutputToContain('Nic do zapisania.')
        ->assertSuccessful();

    expect(ownershipSnapshot())->toBe($before)
        ->and(treeState($other->tree))->toBe($otherBefore);
});

test('refuses a re-import with changes when run with --no-interaction and writes nothing', function () {
    [$author, $other] = arrangeImportedAuthors();
    $blocks = reimportBlocks();
    $blocks['O-0001'] = str_replace('1801', '1802', $blocks['O-0001']);
    $repo = reimportRepo($blocks);
    $before = ownershipSnapshot();

    $exitCode = Artisan::call('import:research', ['email' => $author->email, 'path' => $repo, '--no-interaction' => true]);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('Import wymaga odpowiedzi w terminalu; nic nie zostało zapisane.')
        ->and(ownershipSnapshot())->toBe($before);
});

test('writes nothing when the database backup fails after the consent to a re-import in production', function () {
    [$author, $other] = arrangeImportedAuthors();
    $calls = fakeBackupRun(1);
    app()->detectEnvironment(fn (): string => 'production');
    $blocks = reimportBlocks();
    $blocks['O-0001'] = str_replace('1801', '1802', $blocks['O-0001']);
    $repo = reimportRepo($blocks);
    $before = ownershipSnapshot();

    $this->artisan('import:research', ['email' => $author->email, 'path' => $repo])
        ->expectsChoice('Zmiany z repo (1): co zrobić?', 'all', CHANGES_ALL, true)
        ->expectsConfirmation(saveQuestion(0, 0, 1, changes: 1), 'yes')
        ->expectsOutputToContain('Kopia bazy nie powiodła się, nic nie zostało zapisane.')
        ->assertFailed();

    expect($calls->getArrayCopy())->toBe([true])
        ->and(ownershipSnapshot())->toBe($before);
});

/**
 * Jan Nowak (born in Bukinia, died in Zalesie) and Anna Wrona (born in Bukinia), in a throwaway repo.
 *
 * @return array<string, string>
 */
function placeBlocks(string $birthPlace = 'Bukinia'): array
{
    return [
        'O-0001' => "imiona: Jan\nnazwisko: Nowak\nplec: M\nurodzenie:\n  data: \"1801\"\n  miejsce: {$birthPlace}\nzgon:\n  data: \"1870\"\n  miejsce: Zalesie",
        'O-0002' => "imiona: Anna\nnazwisko: Wrona\nplec: K\nurodzenie:\n  data: \"1805\"\n  miejsce: {$birthPlace}",
    ];
}

/**
 * Two accounts with the same localities and variant; the author may get a different list of them.
 *
 * @param  list<string>  $localities  the names both accounts hold
 * @return array{0: User, 1: User}
 */
function arrangePlaceUsers(array $localities = []): array
{
    Storage::fake('local');
    $other = User::factory()->create(['email' => 'inny@example.com']);
    $author = User::factory()->create(['email' => 'autor@example.com']);

    foreach ([$other, $author] as $user) {
        foreach ($localities as $name) {
            Locality::factory()->create(['tree_id' => $user->tree->id, 'name' => $name]);
        }
    }

    return [$author, $other];
}

/**
 * What the other account holds that an import could touch.
 *
 * @return array<string, mixed>
 */
function placeState(User $user): array
{
    return [
        'localities' => $user->tree->localities()->orderBy('id')->pluck('name')->all(),
        'aliases' => $user->tree->localityAliases()->count(),
        'cemeteries' => $user->tree->cemeteries()->count(),
        'events' => $user->tree->events()->count(),
        'memory' => $user->tree->repoSnapshots()->count(),
    ];
}

test('adds the new localities after approving them all and writes the places of the events', function () {
    [$author, $other] = arrangePlaceUsers();
    $repo = reimportRepo(placeBlocks());
    $otherBefore = placeState($other);

    $this->artisan('import:research', ['email' => $author->email, 'path' => $repo])
        ->expectsOutputToContain('Nowe miejscowości:')
        ->expectsOutputToContain('Jan Nowak (O-0001), Anna Wrona (O-0002)')
        ->expectsChoice(newPlacesQuestion(2), 'all', PLACES_MODE_OPTIONS, true)
        ->expectsOutputToContain('nowe miejscowości: 2, nowe cmentarze: 0, zapamiętane warianty: 0')
        ->expectsConfirmation(saveQuestion(2, 0, 5), 'yes')
        ->expectsOutputToContain('Miejsca: nowe miejscowości 2, nowe cmentarze 0, zapamiętane warianty 0')
        ->assertSuccessful();

    $tree = $author->tree;
    $bukinia = $tree->localities()->where('name', 'Bukinia')->firstOrFail();
    $zalesie = $tree->localities()->where('name', 'Zalesie')->firstOrFail();

    expect($tree->events()->where('repo_id', 'O-0001/urodzenie')->firstOrFail()->locality_id)->toBe($bukinia->id)
        ->and($tree->events()->where('repo_id', 'O-0002/urodzenie')->firstOrFail()->locality_id)->toBe($bukinia->id)
        ->and($tree->events()->where('repo_id', 'O-0001/zgon')->firstOrFail()->locality_id)->toBe($zalesie->id)
        ->and(placeState($other))->toBe($otherBefore);

    $this->artisan('import:research', ['email' => $author->email, 'path' => $repo])
        ->expectsOutputToContain('Nic do zapisania.')
        ->assertSuccessful();
});

test('adds only the new localities the author approves one by one', function () {
    [$author, $other] = arrangePlaceUsers();
    $otherBefore = placeState($other);

    $this->artisan('import:research', ['email' => $author->email, 'path' => reimportRepo(placeBlocks())])
        ->expectsChoice(newPlacesQuestion(2), 'each', PLACES_MODE_OPTIONS, true)
        ->expectsChoice('Nowa miejscowość 1/2: Bukinia (2 zdarzenia)', 'none', ['new' => 'Dodaj do spisu', 'none' => 'Bez miejsca'], true)
        ->expectsChoice('Nowa miejscowość 2/2: Zalesie (1 zdarzenie)', 'new', ['new' => 'Dodaj do spisu', 'none' => 'Bez miejsca'], true)
        ->expectsConfirmation(saveQuestion(2, 0, 5), 'yes')
        ->assertSuccessful();

    $tree = $author->tree;

    expect($tree->localities()->pluck('name')->all())->toBe(['Zalesie'])
        ->and($tree->events()->where('repo_id', 'O-0001/urodzenie')->firstOrFail()->locality_id)->toBeNull()
        ->and($tree->events()->where('repo_id', 'O-0001/zgon')->firstOrFail()->locality_id)->toBe($tree->localities()->firstOrFail()->id)
        ->and(placeState($other))->toBe($otherBefore);
});

test('remembers a doubtful spelling as a variant of the chosen locality and then asks nothing', function () {
    [$author, $other] = arrangePlaceUsers(['Bukinia', 'Zalesie']);
    $bukinia = $author->tree->localities()->where('name', 'Bukinia')->firstOrFail();
    $repo = reimportRepo(placeBlocks('Bokinia'));
    $otherBefore = placeState($other);

    $this->artisan('import:research', ['email' => $author->email, 'path' => $repo])
        ->expectsOutputToContain('Niepewne 1/1: Bokinia — Jan Nowak (O-0001), Anna Wrona (O-0002)')
        ->expectsChoice('Niepewne 1/1: Bokinia (2 zdarzenia)', "locality:{$bukinia->id}", [
            "locality:{$bukinia->id}" => 'To Bukinia (zapamiętaj wariant)',
            'new' => 'Dodaj jako nową',
            'none' => 'Bez miejsca',
        ], true)
        ->expectsOutputToContain('zapamiętane warianty: 1')
        ->expectsConfirmation(saveQuestion(2, 0, 5), 'yes')
        ->assertSuccessful();

    $tree = $author->tree;

    expect($tree->localityAliases()->pluck('name')->all())->toBe(['Bokinia'])
        ->and($tree->localityAliases()->firstOrFail()->locality_id)->toBe($bukinia->id)
        ->and($tree->events()->where('repo_id', 'O-0002/urodzenie')->firstOrFail()->locality_id)->toBe($bukinia->id)
        ->and($tree->localities()->count())->toBe(2)
        ->and(placeState($other))->toBe($otherBefore);

    $this->artisan('import:research', ['email' => $author->email, 'path' => $repo])
        ->expectsOutputToContain('Nic do zapisania.')
        ->assertSuccessful();
});

test('offers no new locality for a name two localities share and remembers the chosen one, then asks nothing', function () {
    [$author, $other] = arrangePlaceUsers(['Wola', 'Wóla', 'Zalesie']);
    $wola = $author->tree->localities()->where('name', 'Wola')->firstOrFail();
    $accented = $author->tree->localities()->where('name', 'Wóla')->firstOrFail();
    $repo = reimportRepo(placeBlocks('Wola'));
    $otherBefore = placeState($other);

    $this->artisan('import:research', ['email' => $author->email, 'path' => $repo])
        ->expectsChoice('Niepewne 1/1: Wola (2 zdarzenia)', "locality:{$accented->id}", [
            "locality:{$wola->id}" => 'To Wola (zapamiętaj wariant)',
            "locality:{$accented->id}" => 'To Wóla (zapamiętaj wariant)',
            'none' => 'Bez miejsca',
        ], true)
        ->expectsConfirmation(saveQuestion(2, 0, 5), 'yes')
        ->assertSuccessful();

    $tree = $author->tree;

    expect($tree->localityAliases()->pluck('name', 'locality_id')->all())->toBe([$accented->id => 'Wola'])
        ->and($tree->events()->where('repo_id', 'O-0001/urodzenie')->firstOrFail()->locality_id)->toBe($accented->id)
        ->and(placeState($other))->toBe($otherBefore);

    $this->artisan('import:research', ['email' => $author->email, 'path' => $repo])
        ->expectsOutputToContain('Nic do zapisania.')
        ->assertSuccessful();
});

test('leaves a doubtful place out when the author keeps the default and the text stays in the note', function () {
    [$author, $other] = arrangePlaceUsers(['Bukinia', 'Zalesie']);
    $otherBefore = placeState($other);
    $bukinia = $author->tree->localities()->where('name', 'Bukinia')->firstOrFail();

    $this->artisan('import:research', ['email' => $author->email, 'path' => reimportRepo(placeBlocks('Bokinia'))])
        ->expectsChoice('Niepewne 1/1: Bokinia (2 zdarzenia)', 'none', [
            "locality:{$bukinia->id}" => 'To Bukinia (zapamiętaj wariant)',
            'new' => 'Dodaj jako nową',
            'none' => 'Bez miejsca',
        ], true)
        ->expectsConfirmation(saveQuestion(2, 0, 5), 'yes')
        ->assertSuccessful();

    $tree = $author->tree;

    expect($tree->localityAliases()->count())->toBe(0)
        ->and($tree->localities()->count())->toBe(2)
        ->and($tree->events()->where('repo_id', 'O-0001/urodzenie')->firstOrFail()->locality_id)->toBeNull()
        ->and($tree->people()->where('repo_id', 'O-0001')->firstOrFail()->note)->toContain('Bokinia')
        ->and(placeState($other))->toBe($otherBefore);
});

test('asks which cemetery a burial names when the locality has two and the block names none', function () {
    [$author, $other] = arrangePlaceUsers();

    foreach ([$other, $author] as $user) {
        $husynne = Locality::factory()->create(['tree_id' => $user->tree->id, 'name' => 'Husynne']);
        Cemetery::factory()->create(['tree_id' => $user->tree->id, 'locality_id' => $husynne->id, 'name' => 'Stary']);
        Cemetery::factory()->create(['tree_id' => $user->tree->id, 'locality_id' => $husynne->id, 'name' => 'Nowy']);
    }

    $repo = reimportRepo(['O-0001' => "imiona: Jan\nnazwisko: Nowak\nplec: M\nzgon:\n  data: \"1870\"\npochowek:\n  miejsce: Husynne"]);
    $stary = $author->tree->cemeteries()->where('name', 'Stary')->firstOrFail();
    $nowy = $author->tree->cemeteries()->where('name', 'Nowy')->firstOrFail();
    $otherBefore = placeState($other);

    $this->artisan('import:research', ['email' => $author->email, 'path' => $repo])
        ->expectsOutputToContain('Cmentarz 1/1: Husynne — Jan Nowak (O-0001)')
        ->expectsChoice('Cmentarz 1/1: Husynne (1 pochowanie)', "cemetery:{$stary->id}", [
            "cemetery:{$stary->id}" => 'Stary, Husynne',
            "cemetery:{$nowy->id}" => 'Nowy, Husynne',
            'new' => 'Nowy cmentarz',
            'none' => 'Bez miejsca',
        ], true)
        ->expectsConfirmation(saveQuestion(1, 0, 3), 'yes')
        ->assertSuccessful();

    $burial = $author->tree->events()->where('repo_id', 'O-0001/pochowek')->firstOrFail();

    expect($burial->cemetery_id)->toBe($stary->id)
        ->and($burial->locality_id)->toBe($stary->locality_id)
        ->and($author->tree->cemeteries()->count())->toBe(2)
        ->and(placeState($other))->toBe($otherBefore);
});

test('adds a new cemetery without asking when its locality is new and counts it', function () {
    [$author, $other] = arrangePlaceUsers();
    $repo = reimportRepo(['O-0001' => "imiona: Jan\nnazwisko: Nowak\nplec: M\nzgon:\n  data: \"1870\"\npochowek:\n  miejsce: Husynne"]);
    $otherBefore = placeState($other);

    $this->artisan('import:research', ['email' => $author->email, 'path' => $repo])
        ->expectsChoice(newPlacesQuestion(1), 'all', PLACES_MODE_OPTIONS, true)
        ->expectsOutputToContain('nowe miejscowości: 1, nowe cmentarze: 1, zapamiętane warianty: 0')
        ->expectsConfirmation(saveQuestion(1, 0, 3), 'yes')
        ->assertSuccessful();

    expect($author->tree->cemeteries()->count())->toBe(1)
        ->and($author->tree->events()->where('repo_id', 'O-0001/pochowek')->firstOrFail()->cemetery_id)->toBe($author->tree->cemeteries()->firstOrFail()->id)
        ->and(placeState($other))->toBe($otherBefore);
});

test('refuses a doubtful place when run with --no-interaction and writes nothing', function () {
    [$author] = arrangePlaceUsers(['Bukinia', 'Zalesie']);
    $before = ownershipSnapshot();

    $exitCode = Artisan::call('import:research', ['email' => $author->email, 'path' => reimportRepo(placeBlocks('Bokinia')), '--no-interaction' => true]);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('Import wymaga odpowiedzi w terminalu; nic nie zostało zapisane.')
        ->and(ownershipSnapshot())->toBe($before);
});

/**
 * A throwaway repo of persons and sources with real scans, written into `$repo` again when it is given so that a test
 * can change the repo between two runs.
 *
 * @param  array<string, string>  $blocks  repo id of a person => block body without id and version
 * @param  array<string, array{extra?: string, shade?: int, scan?: bool}>  $sources  repo id of a source => extra lines of its entry, the shade of its scan and whether it has a scan at all
 */
function cmdSrcRepo(array $blocks, array $sources, ?string $repo = null): string
{
    if ($repo === null) {
        $repo = sys_get_temp_dir().'/command-src-repo-'.uniqid();
        reimportRepos($repo);
    } else {
        File::deleteDirectory($repo);
    }

    mkdir($repo.'/drzewo', 0777, true);
    mkdir($repo.'/zrodla/skany', 0777, true);

    foreach ($blocks as $id => $body) {
        file_put_contents("{$repo}/drzewo/".strtolower($id).'.md', "---\nwersja: 1\nid: {$id}\n{$body}\n---\n");
    }

    $entries = '';

    foreach ($sources as $id => $spec) {
        $entries .= "  - id: {$id}\n";

        if ($spec['scan'] ?? true) {
            $image = imagecreatetruecolor(40, 30);
            imagefill($image, 0, 0, imagecolorallocate($image, $spec['shade'] ?? 20, 20, 20));
            imagejpeg($image, "{$repo}/zrodla/skany/{$id}.jpg");
            $entries .= "    plik: zrodla/skany/{$id}.jpg\n";
        }

        $entries .= "    rodzaj: akt\n".($spec['extra'] ?? '');
    }

    file_put_contents("{$repo}/zrodla/akty.md", "---\nwersja: 1\nskany:\n{$entries}---\n");

    return $repo;
}

/**
 * Jan Nowak born in 1837 and Anna Wrona born in 1840, each with a link to a source under an entry number.
 *
 * @return array<string, string>
 */
function cmdSrcBlocks(string $janEntry = '15/1837'): array
{
    return [
        'O-0001' => "imiona: Jan\nnazwisko: Nowak\nurodzenie:\n  data: \"1837\"\n  zrodla:\n    - id: ZR-0001\n      wpis: \"{$janEntry}\"",
        'O-0002' => "imiona: Anna\nnazwisko: Wrona\nurodzenie:\n  data: \"1840\"\n  zrodla:\n    - id: ZR-0003\n      wpis: \"7/1840\"",
    ];
}

const CMD_SRC_OFFICE = "    typ: urodzenie\n    nazwa: Akt urodzenia 15/1837\n    rok: 1837\n    parafia:\n      miejscowosc: Lipnica\n      rodzaj: usc\n";

/**
 * Two accounts with the locality Lipnica, the same repo imported by both of them without any question: the sources
 * name a civil registry office that is new in both trees.
 *
 * @return array{0: User, 1: User, 2: string}
 */
function cmdSrcImported(): array
{
    Storage::fake('local');
    $other = User::factory()->create(['email' => 'inny@example.com']);
    $author = User::factory()->create(['email' => 'autor@example.com']);
    $repo = cmdSrcRepo(cmdSrcBlocks(), [
        'ZR-0001' => ['extra' => CMD_SRC_OFFICE],
        'ZR-0003' => ['extra' => "    nazwa: Akt urodzenia 7/1840\n"],
    ]);

    foreach ([$other, $author] as $user) {
        Locality::factory()->create(['tree_id' => $user->tree->id, 'name' => 'Lipnica']);
        importRepoInto($user->tree, $repo);
    }

    return [$author, $other, $repo];
}

/**
 * Everything of a tree a source import could touch.
 *
 * @return array<string, mixed>
 */
function cmdSrcState(Tree $tree): array
{
    return [
        'sources' => $tree->sources()->orderBy('id')->get()->toArray(),
        'parishes' => $tree->parishes()->orderBy('id')->get()->toArray(),
        'links' => DB::table('event_source')->whereIn('source_id', $tree->sources()->select('id'))->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all(),
        'state' => treeState($tree),
    ];
}

test('imports the sources of a repo after the questions about parishes and manual scans, and leaves the other account alone', function () {
    Storage::fake('local');
    $other = User::factory()->create(['email' => 'inny@example.com']);
    $author = User::factory()->create(['email' => 'autor@example.com']);
    $repo = cmdSrcRepo(cmdSrcBlocks(), [
        'ZR-0001' => ['extra' => "    typ: urodzenie\n    nazwa: Akt urodzenia 15/1837\n    rok: 1837\n    parafia:\n      miejscowosc: Lipnica\n      wyznanie: rzymskokatolickie\n      wezwanie: św. Jana\n"],
        'ZR-0003' => ['extra' => CMD_SRC_OFFICE],
        'ZR-0009' => ['scan' => false, 'extra' => "    nazwa: Bez skanu\n"],
    ]);

    foreach ([$other, $author] as $user) {
        $lipnica = Locality::factory()->create(['tree_id' => $user->tree->id, 'name' => 'Lipnica']);
        Parish::factory()->seatedIn($lipnica)->withReligion(Religion::RomanCatholic)->dedicatedTo('św. Pawła')->create();
        Parish::factory()->seatedIn($lipnica)->withReligion(Religion::RomanCatholic)->dedicatedTo('św. Piotra')->create();
        $jan = Person::factory()->for($user->tree)->create(['first_name' => 'Jan', 'last_name' => 'Nowak']);
        attachBirth($jan, 1837);
        $user->tree->events()->firstOrFail()->sources()->attach(Source::factory()->create(['tree_id' => $user->tree->id, 'record_type' => SourceRecordType::Birth, 'year' => 1837]), ['entry_number' => '15/1837']);
    }

    $otherBefore = cmdSrcState($other->tree);
    $jan = $author->tree->people()->firstOrFail();
    $manual = $author->tree->sources()->firstOrFail();
    $parishOptions = [
        ...$author->tree->parishes()->orderBy('id')->get()->mapWithKeys(fn (Parish $parish): array => ["parish:{$parish->id}" => $parish->label()])->all(),
        PlaceDecisions::NEW => 'Dodaj nową',
        PlaceDecisions::NONE => 'Bez parafii',
    ];

    $this->artisan('import:research', ['email' => $author->email, 'path' => $repo])
        ->expectsOutputToContain('Pliki źródeł przejrzane: 1 (z blokiem: 1, bez bloku: 0)')
        ->expectsOutputToContain('Skany w blokach: 3, pominięte: 1')
        ->expectsOutputToContain('Pominięte skany:')
        ->expectsOutputToContain('brak skanu')
        ->expectsQuestion('Wątpliwe 1/1: Jan Nowak (O-0001) — kto to?', "person:{$jan->id}")
        ->expectsOutputToContain('Nowe parafie i urzędy:')
        ->expectsChoice('Nowe parafie i urzędy (1): co zrobić?', 'all', PLACES_MODE_OPTIONS, true)
        ->expectsChoice('Parafia 1/1: Lipnica św. Jana (rzym.-kat.) (1 źródło)', PlaceDecisions::NONE, $parishOptions, true)
        ->expectsChoice('Źródło 1/1: Akt urodzenia 15/1837 (ZR-0001) — to ten sam skan?', 'new', ['new' => 'Dodaj jako nowe', "source:{$manual->id}" => "Połącz z {$manual->original_name}"], true)
        ->expectsOutputToContain('Źródła: 2 nowych, 0 połączone z ręcznymi, 0 nowy plik skanu; powiązania z faktami: 1; nowe parafie: 1')
        ->expectsConfirmation(saveQuestion(1, 1, 6, changes: 1, sources: '2, powiązania z faktami 1, nowe parafie 1'), 'yes')
        ->expectsOutputToContain('Źródła: nowe 2, połączone z ręcznymi 0, zmienione 0 (podmienione pliki: 0), usunięte 0, odłączone od repo 0')
        ->expectsOutputToContain('nowe parafie: 1')
        ->assertSuccessful();

    $tree = $author->tree;
    $first = $tree->sources()->where('repo_id', 'ZR-0001')->firstOrFail();
    $third = $tree->sources()->where('repo_id', 'ZR-0003')->firstOrFail();

    expect($tree->sources()->count())->toBe(3)
        ->and($tree->sources()->where('repo_id', 'ZR-0009')->exists())->toBeFalse()
        ->and($first->parish_id)->toBeNull()
        ->and($third->parish->kind)->toBe(ParishKind::CivilRegistry)
        ->and($tree->parishes()->count())->toBe(3)
        ->and(Storage::disk('local')->get($first->path))->toBe(file_get_contents("{$repo}/zrodla/skany/ZR-0001.jpg"))
        ->and(Storage::disk('local')->exists($third->path))->toBeTrue()
        ->and($manual->fresh()->repo_id)->toBeNull()
        ->and($tree->events()->where('repo_id', 'O-0002/urodzenie')->firstOrFail()->sources()->firstOrFail()->id)->toBe($third->id)
        ->and(cmdSrcState($other->tree))->toBe($otherBefore);

    $this->artisan('import:research', ['email' => $author->email, 'path' => $repo])
        ->expectsOutputToContain('Nic do zapisania.')
        ->assertSuccessful();
});

/**
 * The sources of {@see cmdSrcImported()} as the repo has them now.
 *
 * @return array<string, array{extra?: string, shade?: int, scan?: bool}>
 */
function cmdSrcSources(int $firstShade = 20): array
{
    return [
        'ZR-0001' => ['extra' => CMD_SRC_OFFICE, 'shade' => $firstShade],
        'ZR-0003' => ['extra' => "    nazwa: Akt urodzenia 7/1840\n"],
    ];
}

test('takes a changed entry number of the repo as a change to approve', function () {
    [$author, $other, $repo] = cmdSrcImported();
    $otherBefore = cmdSrcState($other->tree);
    cmdSrcRepo(cmdSrcBlocks('16/1837'), cmdSrcSources(), $repo);

    $this->artisan('import:research', ['email' => $author->email, 'path' => $repo])
        ->expectsOutputToContain('Zmiany z repo:')
        ->expectsChoice('Zmiany z repo (1): co zrobić?', 'all', CHANGES_ALL, true)
        ->expectsConfirmation(saveQuestion(0, 0, 1, changes: 1), 'yes')
        ->assertSuccessful();

    $link = $author->tree->events()->where('repo_id', 'O-0001/urodzenie')->firstOrFail()->sources()->firstOrFail();

    expect($link->pivot->entry_number)->toBe('16/1837')
        ->and(cmdSrcState($other->tree))->toBe($otherBefore);

    $this->artisan('import:research', ['email' => $author->email, 'path' => $repo])
        ->expectsOutputToContain('Nic do zapisania.')
        ->assertSuccessful();
});

test('keeps the old scan when the author rejects the new file of a source one by one', function () {
    [$author, $other, $repo] = cmdSrcImported();
    $otherBefore = cmdSrcState($other->tree);
    $source = $author->tree->sources()->where('repo_id', 'ZR-0001')->firstOrFail();
    $oldScan = Storage::disk('local')->get($source->path);
    cmdSrcRepo(cmdSrcBlocks(), cmdSrcSources(200), $repo);

    $this->artisan('import:research', ['email' => $author->email, 'path' => $repo])
        ->expectsOutputToContain('Zmiany z repo:')
        ->expectsChoice('Zmiany z repo (1): co zrobić?', 'each', CHANGES_ALL, true)
        ->expectsQuestion('Zmiana 1/1: Akt urodzenia 15/1837 (ZR-0001) — Plik skanu', 'app')
        ->expectsConfirmation(saveQuestion(0, 0, 1), 'yes')
        ->assertSuccessful();

    $source->refresh();

    expect(Storage::disk('local')->get($source->path))->toBe($oldScan)
        ->and(cmdSrcState($other->tree))->toBe($otherBefore);
});

test('removes a source the repo dropped with its scan after delete and shows what it takes', function () {
    [$author, $other, $repo] = cmdSrcImported();
    $otherBefore = cmdSrcState($other->tree);
    $source = $author->tree->sources()->where('repo_id', 'ZR-0003')->firstOrFail();
    $blocks = cmdSrcBlocks();
    $blocks['O-0002'] = "imiona: Anna\nnazwisko: Wrona\nurodzenie:\n  data: \"1840\"";
    cmdSrcRepo($blocks, array_slice(cmdSrcSources(), 0, 1, true), $repo);

    $this->artisan('import:research', ['email' => $author->email, 'path' => $repo])
        ->expectsOutputToContain('Usunięte w repo: 1')
        ->expectsOutputToContain('skan do usunięcia')
        ->expectsQuestion('Usunięte w repo 1/1: Źródło: Akt urodzenia 7/1840 (ZR-0003) — co zrobić?', 'delete')
        ->expectsConfirmation(saveQuestion(0, 0, 2, removals: 1), 'yes')
        ->expectsOutputToContain('usunięte 1, odłączone od repo 0')
        ->assertSuccessful();

    expect($author->tree->sources()->where('repo_id', 'ZR-0003')->exists())->toBeFalse()
        ->and(Storage::disk('local')->exists($source->path))->toBeFalse()
        ->and($author->tree->sources()->where('repo_id', 'ZR-0001')->exists())->toBeTrue()
        ->and(cmdSrcState($other->tree))->toBe($otherBefore);
});

test('leaves a source deleted in the app deleted and then does not ask again', function () {
    [$author, $other, $repo] = cmdSrcImported();

    foreach ([$author, $other] as $user) {
        $user->tree->sources()->where('repo_id', 'ZR-0003')->firstOrFail()->delete();
    }

    $otherBefore = cmdSrcState($other->tree);

    $this->artisan('import:research', ['email' => $author->email, 'path' => $repo])
        ->expectsOutputToContain('Usunięte w aplikacji: 1 (do decyzji)')
        ->expectsQuestion('Usunięte w aplikacji 1/1: Źródło: Akt urodzenia 7/1840 (ZR-0003) — co zrobić?', 'keep')
        ->expectsConfirmation(saveQuestion(0, 0, 2), 'yes')
        ->expectsOutputToContain('zostawione jako usunięte: 1')
        ->assertSuccessful();

    expect($author->tree->sources()->where('repo_id', 'ZR-0003')->exists())->toBeFalse();

    $this->artisan('import:research', ['email' => $author->email, 'path' => $repo])
        ->expectsOutputToContain('Nic do zapisania.')
        ->assertSuccessful();

    expect(cmdSrcState($other->tree))->toBe($otherBefore);
});

test('writes a source the repo gained when nothing else changed instead of saying there is nothing to write', function () {
    [$author, $other, $repo] = cmdSrcImported();
    $otherBefore = cmdSrcState($other->tree);
    cmdSrcRepo(cmdSrcBlocks(), [...cmdSrcSources(), 'ZR-0004' => ['extra' => "    nazwa: Nowy akt\n"]], $repo);

    $this->artisan('import:research', ['email' => $author->email, 'path' => $repo])
        ->doesntExpectOutputToContain('Nic do zapisania.')
        ->expectsConfirmation(saveQuestion(0, 0, 1, sources: '1, powiązania z faktami 0, nowe parafie 0'), 'yes')
        ->assertSuccessful();

    expect($author->tree->sources()->where('repo_id', 'ZR-0004')->exists())->toBeTrue()
        ->and(cmdSrcState($other->tree))->toBe($otherBefore);
});

test('refuses a new parish when run with --no-interaction and writes nothing', function () {
    Storage::fake('local');
    $other = User::factory()->create(['email' => 'inny@example.com']);
    $author = User::factory()->create(['email' => 'autor@example.com']);
    $repo = cmdSrcRepo(cmdSrcBlocks(), cmdSrcSources());

    foreach ([$other, $author] as $user) {
        Locality::factory()->create(['tree_id' => $user->tree->id, 'name' => 'Lipnica']);
    }

    $before = ownershipSnapshot();

    $exitCode = Artisan::call('import:research', ['email' => $author->email, 'path' => $repo, '--no-interaction' => true]);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('Import wymaga odpowiedzi w terminalu; nic nie zostało zapisane.')
        ->and(ownershipSnapshot())->toBe($before);
});
