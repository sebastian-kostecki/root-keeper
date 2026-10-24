<?php

use App\Actions\Import\MatchResearchPersons;
use App\Actions\Import\ReadResearchRepo;
use App\Enums\EventType;
use App\Enums\Gender;
use App\Enums\MatchElement;
use App\Enums\MatchVerdict;
use App\Models\Locality;
use App\Models\Person;
use App\Models\Tree;
use App\Models\User;
use App\Support\Import\PersonMatch;
use App\Support\Import\PersonMatches;
use Illuminate\Support\Facades\File;

/**
 * Builds the same hand-entered persons in the tree of the author and in the tree of another account, and returns the author's.
 *
 * @param  Closure(Tree): void  $arrange
 */
function authorWithTwin(Closure $arrange): Tree
{
    $author = User::factory()->create();
    $other = User::factory()->create();

    $arrange($other->tree);
    $arrange($author->tree);

    return $author->tree;
}

/**
 * Matches a throwaway repo built from person blocks, e.g. ['O-0001' => "imiona: Jan\nnazwisko: Nowak"], against the tree.
 * Fails when a candidate comes from another tree.
 *
 * @param  array<string, string>  $blocks  repo id => block body without id and version
 * @param  list<string>|null  $repoIds  the repo persons to classify, `null` for all of them
 */
function matchTemporaryRepo(Tree $tree, array $blocks, ?array $repoIds = null): PersonMatches
{
    $repo = sys_get_temp_dir().'/match-repo-'.uniqid();
    mkdir($repo.'/drzewo', 0777, true);

    foreach ($blocks as $id => $body) {
        file_put_contents("{$repo}/drzewo/".strtolower($id).'.md', "---\nwersja: 1\nid: {$id}\n{$body}\n---\n");
    }

    try {
        $reading = app(ReadResearchRepo::class)->handle($repo);
    } finally {
        File::deleteDirectory($repo);
    }

    $matches = app(MatchResearchPersons::class)->handle($tree, $reading, $repoIds);

    foreach ($matches->matches as $match) {
        foreach ($match->candidates as $candidate) {
            expect($candidate->person->tree_id)->toBe($tree->id);
        }
    }

    return $matches;
}

function treePerson(Tree $tree, string $first, string $last, ?Gender $gender = null): Person
{
    return Person::factory()->for($tree)->create(['first_name' => $first, 'last_name' => $last, 'gender' => $gender]);
}

/**
 * @param  list<Person>  $parents
 */
function withParents(Person $child, array $parents): void
{
    foreach ($parents as $parent) {
        linkParent($parent, $child);
    }
}

/**
 * The repo persons that a person block names as parents: O-0002 is the father and O-0003 the mother.
 *
 * @param  array<string, string>  $extra  further blocks, keyed by repo id
 * @return array<string, string>
 */
function repoWithParents(string $childBody, array $extra = []): array
{
    return [
        'O-0001' => $childBody."\nrodzice:\n  ojciec: O-0002\n  matka: O-0003",
        'O-0002' => "imiona: Piotr\nnazwisko: Nowak\nplec: M",
        'O-0003' => "imiona: Maria\nnazwisko: Wrona\nplec: K",
        ...$extra,
    ];
}

/**
 * Jan Nowak with a father Piotr Nowak and a mother Maria Wrona, as entered by hand.
 */
function arrangeJanWithParents(Tree $tree): Person
{
    $jan = treePerson($tree, 'Jan', 'Nowak');
    withParents($jan, [treePerson($tree, 'Piotr', 'Nowak', Gender::Male), treePerson($tree, 'Maria', 'Wrona', Gender::Female)]);

    return $jan;
}

function setBirthPlace(Person $person, string $name): void
{
    $locality = Locality::factory()->for($person->tree)->create(['name' => $name]);

    $person->events()->where('type', EventType::Birth)->firstOrFail()->update(['locality_id' => $locality->id]);
}

function matchFor(PersonMatches $matches, string $repoId): PersonMatch
{
    return $matches->matches[$repoId];
}

test('classifies a person without a namesake in the tree as new', function () {
    $other = User::factory()->create();
    arrangeJanWithParents($other->tree);
    $author = User::factory()->create();
    treePerson($author->tree, 'Piotr', 'Wrona');

    $matches = matchTemporaryRepo($author->tree, ['O-0001' => "imiona: Jan\nnazwisko: Nowak"]);

    expect(matchFor($matches, 'O-0001')->verdict)->toBe(MatchVerdict::New)
        ->and(matchFor($matches, 'O-0001')->candidates)->toBe([]);
});

test('finds candidates by first name and surname or its variant', function (string $treeFirst, string $treeLast, string $repoBody, int $candidates) {
    $tree = authorWithTwin(fn (Tree $tree) => treePerson($tree, $treeFirst, $treeLast));

    $matches = matchTemporaryRepo($tree, ['O-0001' => $repoBody]);

    expect(matchFor($matches, 'O-0001')->candidates)->toHaveCount($candidates)
        ->and(matchFor($matches, 'O-0001')->verdict)->toBe($candidates === 0 ? MatchVerdict::New : MatchVerdict::Doubtful);
})->with([
    'more given names in the tree' => ['Jan Józef', 'Nowak', "imiona: Jan\nnazwisko: Nowak", 1],
    'more given names in the repo' => ['Jan', 'Nowak', "imiona: Jan Józef\nnazwisko: Nowak", 1],
    'case and diacritics' => ['Łukasz', 'ŻÓŁĆ', "imiona: lukasz\nnazwisko: zolc", 1],
    'surname variant' => ['jan', 'nowakowski', "imiona: Jan\nnazwisko: Nowak\nwarianty_nazwiska: [Nowakowski]", 1],
    'surname is not a variant' => ['Jan', 'Nowakowski', "imiona: Jan\nnazwisko: Nowak", 0],
    'second given name is not the first' => ['Józef', 'Nowak', "imiona: Jan\nnazwisko: Nowak", 0],
    'another surname' => ['Jan', 'Wrona', "imiona: Jan\nnazwisko: Nowak", 0],
]);

test('matches a missing name only with a missing name', function () {
    $tree = authorWithTwin(function (Tree $tree) {
        treePerson($tree, 'Jan', 'Nowak');
        Person::factory()->for($tree)->create(['first_name' => 'Anna', 'last_name' => null]);
    });

    $matches = matchTemporaryRepo($tree, [
        'O-0001' => "imiona: Jan\n",
        'O-0002' => "imiona: Anna\n",
        'O-0003' => "nazwisko: Nowak\n",
    ]);

    expect(matchFor($matches, 'O-0001')->candidates)->toBe([])
        ->and(matchFor($matches, 'O-0002')->candidates)->toHaveCount(1)
        ->and(matchFor($matches, 'O-0003')->candidates)->toBe([]);
});

test('is sure when father and mother agree by name even without a year', function () {
    $tree = authorWithTwin(fn (Tree $tree) => arrangeJanWithParents($tree));

    $match = matchFor(matchTemporaryRepo($tree, repoWithParents("imiona: Jan\nnazwisko: Nowak")), 'O-0001');

    expect($match->verdict)->toBe(MatchVerdict::Sure)
        ->and($match->candidates)->toHaveCount(1)
        ->and($match->candidates[0]->agreeing)->toBe([MatchElement::Father, MatchElement::Mother])
        ->and($match->candidates[0]->contradictions)->toBe([]);
});

test('is sure when birth and death years agree within two years', function () {
    $tree = authorWithTwin(function (Tree $tree) {
        $jan = treePerson($tree, 'Jan', 'Nowak');
        attachBirth($jan, 1803);
        attachDeath($jan, 1870, approximate: true);
    });

    $match = matchFor(matchTemporaryRepo($tree, ['O-0001' => "imiona: Jan\nnazwisko: Nowak\nurodzenie:\n  data: \"1801\"\nzgon:\n  data: 1871"]), 'O-0001');

    expect($match->verdict)->toBe(MatchVerdict::Sure)
        ->and($match->candidates[0]->agreeing)->toBe([MatchElement::BirthYear, MatchElement::DeathYear]);
});

test('is sure when birth place and spouse agree', function () {
    $tree = authorWithTwin(function (Tree $tree) {
        $jan = treePerson($tree, 'Jan', 'Nowak');
        attachBirth($jan);
        setBirthPlace($jan, 'Łąki');
        attachMarriage($jan, treePerson($tree, 'Anna', 'Wrona'), 1825);
    });

    $match = matchFor(matchTemporaryRepo($tree, [
        'O-0001' => "imiona: Jan\nnazwisko: Nowak\nurodzenie:\n  miejsce: laki\nmalzenstwa:\n  - z: O-0002",
        'O-0002' => "imiona: Anna\nnazwisko: Wrona\nmalzenstwa:\n  - z: O-0001",
    ]), 'O-0001');

    expect($match->verdict)->toBe(MatchVerdict::Sure)
        ->and($match->candidates[0]->agreeing)->toBe([MatchElement::BirthPlace, MatchElement::Spouse]);
});

test('is doubtful with a single agreeing element', function () {
    $tree = authorWithTwin(function (Tree $tree) {
        attachBirth(treePerson($tree, 'Jan', 'Nowak'), 1801);
    });

    $match = matchFor(matchTemporaryRepo($tree, ['O-0001' => "imiona: Jan\nnazwisko: Nowak\nurodzenie:\n  data: \"1801\""]), 'O-0001');

    expect($match->verdict)->toBe(MatchVerdict::Doubtful)
        ->and($match->candidates[0]->agreeing)->toBe([MatchElement::BirthYear])
        ->and($match->candidates[0]->contradictions)->toBe([]);
});

test('is doubtful with two candidates even when one agrees on three elements', function () {
    $tree = authorWithTwin(function (Tree $tree) {
        attachBirth(arrangeJanWithParents($tree), 1801);
        treePerson($tree, 'Jan', 'Nowak');
    });

    $match = matchFor(matchTemporaryRepo($tree, repoWithParents("imiona: Jan\nnazwisko: Nowak\nurodzenie:\n  data: \"1801\"")), 'O-0001');

    expect($match->verdict)->toBe(MatchVerdict::Doubtful)
        ->and($match->candidates)->toHaveCount(2)
        ->and(count($match->candidates[0]->agreeing))->toBe(3)
        ->and($match->candidates[1]->agreeing)->toBe([]);
});

test('is doubtful when two agreeing elements come with a contradicting year', function () {
    $tree = authorWithTwin(function (Tree $tree) {
        attachBirth(arrangeJanWithParents($tree), 1830);
    });

    $match = matchFor(matchTemporaryRepo($tree, repoWithParents("imiona: Jan\nnazwisko: Nowak\nurodzenie:\n  data: \"1801\"")), 'O-0001');

    expect($match->verdict)->toBe(MatchVerdict::Doubtful)
        ->and($match->candidates[0]->agreeing)->toBe([MatchElement::Father, MatchElement::Mother])
        ->and($match->candidates[0]->contradictions)->toBe(['rok urodzenia: repo 1801, drzewo 1830']);
});

test('contradicts on another known father by first name', function () {
    $tree = authorWithTwin(function (Tree $tree) {
        $jan = treePerson($tree, 'Jan', 'Nowak');
        withParents($jan, [treePerson($tree, 'Józef', 'Nowak', Gender::Male)]);
    });

    $candidate = matchFor(matchTemporaryRepo($tree, repoWithParents("imiona: Jan\nnazwisko: Nowak")), 'O-0001')->candidates[0];

    expect($candidate->agreeing)->toBe([])
        ->and($candidate->contradictions)->toBe(['ojciec: repo Piotr Nowak, drzewo Józef Nowak']);
});

test('does not count a parent or a spouse without a first name as agreeing', function () {
    $tree = authorWithTwin(function (Tree $tree) {
        $jan = treePerson($tree, 'Jan', 'Nowak');
        withParents($jan, [
            Person::factory()->for($tree)->create(['first_name' => null, 'last_name' => 'Nowak', 'gender' => Gender::Male]),
            Person::factory()->for($tree)->create(['first_name' => null, 'last_name' => 'Wrona', 'gender' => Gender::Female]),
        ]);
        attachMarriage($jan, Person::factory()->for($tree)->create(['first_name' => null, 'last_name' => 'Lis']), 1825);
    });

    $match = matchFor(matchTemporaryRepo($tree, [
        'O-0001' => "imiona: Jan\nnazwisko: Nowak\nrodzice:\n  ojciec: O-0002\n  matka: O-0003\nmalzenstwa:\n  - z: O-0004",
        'O-0002' => "nazwisko: Nowak\nplec: M",
        'O-0003' => "nazwisko: Wrona\nplec: K",
        'O-0004' => "nazwisko: Lis\nmalzenstwa:\n  - z: O-0001",
    ]), 'O-0001');

    expect($match->candidates)->toHaveCount(1)
        ->and($match->candidates[0]->agreeing)->toBe([])
        ->and($match->candidates[0]->contradictions)->toBe([])
        ->and($match->verdict)->toBe(MatchVerdict::Doubtful);
});

test('does not contradict on the same father with another surname', function () {
    $tree = authorWithTwin(function (Tree $tree) {
        $jan = treePerson($tree, 'Jan', 'Nowak');
        withParents($jan, [treePerson($tree, 'Piotr', 'Kowalski', Gender::Male)]);
    });

    $candidate = matchFor(matchTemporaryRepo($tree, repoWithParents("imiona: Jan\nnazwisko: Nowak")), 'O-0001')->candidates[0];

    expect($candidate->agreeing)->not->toContain(MatchElement::Father)
        ->and($candidate->contradictions)->toBe([]);
});

test('agrees on a mother of unknown gender and never contradicts through her', function () {
    $tree = authorWithTwin(function (Tree $tree) {
        withParents(treePerson($tree, 'Jan', 'Nowak'), [treePerson($tree, 'Maria', 'Wrona')]);
        withParents(treePerson($tree, 'Jan', 'Nowak'), [treePerson($tree, 'Zofia', 'Wrona')]);
    });

    $candidates = matchFor(matchTemporaryRepo($tree, repoWithParents("imiona: Jan\nnazwisko: Nowak")), 'O-0001')->candidates;

    expect($candidates)->toHaveCount(2)
        ->and($candidates[0]->agreeing)->toBe([MatchElement::Mother])
        ->and($candidates[0]->contradictions)->toBe([])
        ->and($candidates[1]->agreeing)->toBe([])
        ->and($candidates[1]->contradictions)->toBe([]);
});

test('compares the birth place ignoring case and Polish diacritics and never contradicts on it', function (string $treePlace, string $repoPlace, bool $agrees) {
    $tree = authorWithTwin(function (Tree $tree) use ($treePlace) {
        $jan = treePerson($tree, 'Jan', 'Nowak');
        attachBirth($jan);
        setBirthPlace($jan, $treePlace);
    });

    $candidate = matchFor(matchTemporaryRepo($tree, ['O-0001' => "imiona: Jan\nnazwisko: Nowak\nurodzenie:\n  miejsce: {$repoPlace}"]), 'O-0001')->candidates[0];

    expect($candidate->agreeing)->toBe($agrees ? [MatchElement::BirthPlace] : [])
        ->and($candidate->contradictions)->toBe([]);
})->with([
    'diacritics and case' => ['Łąki Górne', 'laki gorne', true],
    'same place' => ['Zalesie', 'Zalesie', true],
    'another place' => ['Zalesie', 'Wola', false],
]);

test('contradicts on a different known gender', function () {
    $tree = authorWithTwin(function (Tree $tree) {
        treePerson($tree, 'Jan', 'Nowak', Gender::Female);
        treePerson($tree, 'Janina', 'Wrona');
    });

    $matches = matchTemporaryRepo($tree, [
        'O-0001' => "imiona: Jan\nnazwisko: Nowak\nplec: M",
        'O-0002' => "imiona: Janina\nnazwisko: Wrona\nplec: K",
    ]);

    expect(matchFor($matches, 'O-0001')->candidates[0]->contradictions)->toBe(['płeć: repo Mężczyzna, drzewo Kobieta'])
        ->and(matchFor($matches, 'O-0001')->verdict)->toBe(MatchVerdict::Doubtful)
        ->and(matchFor($matches, 'O-0002')->candidates[0]->contradictions)->toBe([]);
});

test('makes both persons doubtful when two repo persons are sure about the same tree person', function () {
    $tree = authorWithTwin(fn (Tree $tree) => arrangeJanWithParents($tree));

    $matches = matchTemporaryRepo($tree, repoWithParents("imiona: Jan\nnazwisko: Nowak", [
        'O-0004' => "imiona: Jan\nnazwisko: Nowak\nrodzice:\n  ojciec: O-0002\n  matka: O-0003",
    ]));

    expect(matchFor($matches, 'O-0001')->verdict)->toBe(MatchVerdict::Doubtful)
        ->and(matchFor($matches, 'O-0004')->verdict)->toBe(MatchVerdict::Doubtful)
        ->and(matchFor($matches, 'O-0001')->candidates[0]->person->id)->toBe(matchFor($matches, 'O-0004')->candidates[0]->person->id);
});

test('describes the comparison in the block grammar', function () {
    $tree = authorWithTwin(function (Tree $tree) {
        $jan = arrangeJanWithParents($tree);
        attachBirth($jan, 1801, 5);
        setBirthPlace($jan, 'Zalesie');
        attachDeath($jan, 1870, approximate: true);
    });

    $candidate = matchFor(matchTemporaryRepo($tree, repoWithParents("imiona: Jan Józef\nnazwisko: Nowak\nplec: M\nurodzenie:\n  data: \"1801-05-12\"\n  miejsce: Zalesie\nzgon:\n  data: ok. 1870")), 'O-0001')->candidates[0];

    expect($candidate->rows)->toBe([
        ['Imiona', 'Jan Józef', 'Jan'],
        ['Nazwisko', 'Nowak', 'Nowak'],
        ['Płeć', 'Mężczyzna', '—'],
        ['Urodzenie', '1801-05-12', '1801-05'],
        ['Miejsce urodzenia', 'Zalesie', 'Zalesie'],
        ['Zgon', 'ok. 1870', 'ok. 1870'],
        ['Ojciec', 'Piotr Nowak', 'Piotr Nowak'],
        ['Matka', 'Maria Wrona', 'Maria Wrona'],
        ['Małżonkowie', '—', '—'],
        ['Id', 'O-0001', '#'.$candidate->person->id],
    ]);
});

test('never takes a person that came from an import as a candidate, but takes one without a repo id', function () {
    $author = User::factory()->create();
    $other = User::factory()->create();
    $imported = Person::factory()->for($author->tree)->create(['first_name' => 'Jan', 'last_name' => 'Nowak', 'repo_id' => 'O-0001']);
    $theirs = treePerson($other->tree, 'Jan', 'Nowak');

    $withoutHandEntered = matchTemporaryRepo($author->tree, ['O-0001' => "imiona: Jan\nnazwisko: Nowak"]);
    $handEntered = treePerson($author->tree, 'Jan', 'Nowak');
    $withHandEntered = matchTemporaryRepo($author->tree, ['O-0001' => "imiona: Jan\nnazwisko: Nowak"]);

    expect(matchFor($withoutHandEntered, 'O-0001')->verdict)->toBe(MatchVerdict::New)
        ->and(matchFor($withoutHandEntered, 'O-0001')->candidates)->toBe([])
        ->and(matchFor($withHandEntered, 'O-0001')->candidates)->toHaveCount(1)
        ->and(matchFor($withHandEntered, 'O-0001')->candidates[0]->person->id)->toBe($handEntered->id)
        ->and($imported->fresh()->repo_id)->toBe('O-0001')
        ->and($theirs->fresh()->repo_id)->toBeNull();
});

test('is not affected by a person from an import in another account', function () {
    $author = User::factory()->create();
    $other = User::factory()->create();
    treePerson($author->tree, 'Jan', 'Nowak');
    Person::factory()->for($other->tree)->create(['first_name' => 'Jan', 'last_name' => 'Nowak', 'repo_id' => 'O-0001']);

    $matches = matchTemporaryRepo($author->tree, ['O-0001' => "imiona: Jan\nnazwisko: Nowak"]);

    expect(matchFor($matches, 'O-0001')->candidates)->toHaveCount(1);
});

test('classifies only the given repo persons', function () {
    $tree = authorWithTwin(fn (Tree $tree) => arrangeJanWithParents($tree));
    $blocks = repoWithParents("imiona: Jan\nnazwisko: Nowak");

    $all = matchTemporaryRepo($tree, $blocks);
    $limited = matchTemporaryRepo($tree, $blocks, ['O-0001', 'O-0003']);
    $none = matchTemporaryRepo($tree, $blocks, []);

    expect(array_keys($all->matches))->toBe(['O-0001', 'O-0002', 'O-0003'])
        ->and(array_keys($limited->matches))->toBe(['O-0001', 'O-0003'])
        ->and(matchFor($limited, 'O-0001')->verdict)->toBe(matchFor($all, 'O-0001')->verdict)
        ->and($none->matches)->toBe([]);
});

test('classifies every person as new in an empty tree', function () {
    $author = User::factory()->create();
    $other = User::factory()->create();
    arrangeJanWithParents($other->tree);

    $matches = matchTemporaryRepo($author->tree, repoWithParents("imiona: Jan\nnazwisko: Nowak"));

    expect(array_keys($matches->matches))->toBe(['O-0001', 'O-0002', 'O-0003'])
        ->and(collect($matches->matches)->every(fn (PersonMatch $match): bool => $match->verdict === MatchVerdict::New && $match->candidates === []))->toBeTrue();
});
