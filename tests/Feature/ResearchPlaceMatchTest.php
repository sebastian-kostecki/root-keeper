<?php

use App\Actions\Import\MatchResearchPlaces;
use App\Actions\Import\ReadResearchRepo;
use App\Enums\CemeteryMatchKind;
use App\Enums\PlaceMatchKind;
use App\Models\Cemetery;
use App\Models\Locality;
use App\Models\LocalityAlias;
use App\Models\Tree;
use App\Models\User;
use App\Support\Import\IdentityDecisions;
use App\Support\Import\ImportDecisions;
use App\Support\Import\PlaceDecisions;
use App\Support\Import\PlaceMatches;
use App\Support\Import\RepoRecordClasses;
use Illuminate\Support\Facades\File;

/**
 * Reads a throwaway repo built from person blocks, e.g. ['O-0001' => "imiona: Jan\nnazwisko: Nowak\nurodzenie:\n  miejsce: Zalesie"],
 * and matches its places against the tree. Fails when a locality or cemetery of another tree shows up.
 *
 * @param  array<string, string>  $blocks  repo id => block body without id and version
 * @param  list<string>  $skipped  repo ids the author left out
 */
function matchTemporaryPlaces(Tree $tree, array $blocks, array $skipped = []): PlaceMatches
{
    $repo = sys_get_temp_dir().'/place-repo-'.uniqid();
    mkdir($repo.'/drzewo', 0777, true);

    foreach ($blocks as $id => $body) {
        file_put_contents("{$repo}/drzewo/".strtolower($id).'.md', "---\nwersja: 1\nid: {$id}\n{$body}\n---\n");
    }

    try {
        $reading = app(ReadResearchRepo::class)->handle($repo);
    } finally {
        File::deleteDirectory($repo);
    }

    $new = array_values(array_diff(array_map(strval(...), array_keys($reading->persons)), $skipped));
    $decisions = new ImportDecisions(new IdentityDecisions(skipped: $skipped, new: $new));

    $matches = app(MatchResearchPlaces::class)->handle($tree, $reading, new RepoRecordClasses(newPersonIds: $new), $decisions);

    foreach ($matches->localities as $verdict) {
        foreach ([$verdict->locality, ...$verdict->candidates] as $locality) {
            expect($locality === null || $locality->tree_id === $tree->id)->toBeTrue();
        }
    }

    return $matches;
}

/**
 * A person block whose birth names the given place.
 */
function bornIn(string $place, string $given = 'Jan'): string
{
    return "imiona: {$given}\nnazwisko: Nowak\nurodzenie:\n  miejsce: {$place}";
}

/**
 * A person block whose burial names the given place and, optionally, the cemetery.
 */
function buriedIn(string $place, ?string $cemetery = null): string
{
    return "imiona: Jan\nnazwisko: Nowak\npochowek:\n  miejsce: {$place}".($cemetery === null ? '' : "\n  cmentarz: {$cemetery}");
}

function localityOf(Tree $tree, string $name): Locality
{
    return Locality::factory()->create(['tree_id' => $tree->id, 'name' => $name]);
}

test('a name equal to a locality ignoring capitals and polish characters is sure', function () {
    $tree = User::factory()->create()->tree;
    $locality = localityOf($tree, 'Kołemczyce');

    $verdict = matchTemporaryPlaces($tree, ['O-0001' => bornIn('Kolemczyce')])->localities['kolemczyce'];

    expect($verdict->kind)->toBe(PlaceMatchKind::Sure)
        ->and($verdict->locality?->id)->toBe($locality->id);
});

test('a remembered variant makes a name sure', function () {
    $tree = User::factory()->create()->tree;
    $locality = localityOf($tree, 'Bukinia');
    LocalityAlias::factory()->create(['tree_id' => $tree->id, 'locality_id' => $locality->id, 'name' => 'Bokinia']);

    $verdict = matchTemporaryPlaces($tree, ['O-0001' => bornIn('Bokinia')])->localities['bokinia'];

    expect($verdict->kind)->toBe(PlaceMatchKind::Sure)
        ->and($verdict->locality?->id)->toBe($locality->id);
});

test('a similar spelling is doubtful with the closest candidates first', function () {
    $tree = User::factory()->create()->tree;
    $far = localityOf($tree, 'Bokinka');
    $near = localityOf($tree, 'Bukinia');
    localityOf($tree, 'Zalesie');

    $verdict = matchTemporaryPlaces($tree, ['O-0001' => bornIn('Bokinia')])->localities['bokinia'];

    expect($verdict->kind)->toBe(PlaceMatchKind::Doubtful)
        ->and(array_map(fn (Locality $locality): int => $locality->id, $verdict->candidates))->toBe([$far->id, $near->id]);
});

test('the same words in another order are doubtful', function () {
    $tree = User::factory()->create()->tree;
    $locality = localityOf($tree, 'Stary Majdan');

    $verdict = matchTemporaryPlaces($tree, ['O-0001' => bornIn('Majdan Stary')])->localities['majdan stary'];

    expect($verdict->kind)->toBe(PlaceMatchKind::Doubtful)
        ->and($verdict->candidates[0]->id)->toBe($locality->id);
});

test('a name that only contains another one is new', function () {
    $tree = User::factory()->create()->tree;
    localityOf($tree, 'Siedliszcze');

    $verdict = matchTemporaryPlaces($tree, ['O-0001' => bornIn('Siedliszcze Bramowe')])->localities['siedliszcze bramowe'];

    expect($verdict->kind)->toBe(PlaceMatchKind::New)
        ->and($verdict->candidates)->toBe([]);
});

test('two localities with the same key are doubtful until a variant picks one', function () {
    $tree = User::factory()->create()->tree;
    $first = localityOf($tree, 'Wola');
    $second = localityOf($tree, 'Wóla');

    $verdict = matchTemporaryPlaces($tree, ['O-0001' => bornIn('Wola')])->localities['wola'];

    expect($verdict->kind)->toBe(PlaceMatchKind::Doubtful)
        ->and(array_map(fn (Locality $locality): int => $locality->id, $verdict->candidates))->toEqualCanonicalizing([$first->id, $second->id]);

    LocalityAlias::factory()->create(['tree_id' => $tree->id, 'locality_id' => $second->id, 'name' => 'Wola']);

    $verdict = matchTemporaryPlaces($tree, ['O-0001' => bornIn('Wola')])->localities['wola'];

    expect($verdict->kind)->toBe(PlaceMatchKind::Sure)
        ->and($verdict->locality?->id)->toBe($second->id);
});

test('one name from several events and spellings is one question with a polish spelling for the new locality', function () {
    $tree = User::factory()->create()->tree;

    $matches = matchTemporaryPlaces($tree, [
        'O-0001' => bornIn('Kolemczyce'),
        'O-0002' => bornIn('Kołemczyce', 'Adam')."\nzgon:\n  miejsce: Kolemczyce",
        'O-0003' => bornIn('Kolemczyce', 'Piotr'),
    ]);

    expect($matches->localities)->toHaveCount(1);

    $verdict = $matches->localities['kolemczyce'];

    expect($verdict->kind)->toBe(PlaceMatchKind::New)
        ->and($verdict->name)->toBe('Kołemczyce')
        ->and($verdict->eventCount())->toBe(4)
        ->and($verdict->repoIds())->toBe(['O-0001', 'O-0002', 'O-0003'])
        ->and($verdict->uses[0]->eventKey)->toBe('O-0001/urodzenie');
});

test('the most frequent spelling wins when none has polish characters', function () {
    $tree = User::factory()->create()->tree;

    $matches = matchTemporaryPlaces($tree, [
        'O-0001' => bornIn('rostoka'),
        'O-0002' => bornIn('Rostoka', 'Adam'),
        'O-0003' => bornIn('Rostoka', 'Piotr'),
    ]);

    expect($matches->localities['rostoka']->name)->toBe('Rostoka');
});

test('a place the memory already holds with its id is not asked about again', function (array $values, bool $asked) {
    $tree = User::factory()->create()->tree;
    $tree->repoSnapshots()->create(['repo_id' => 'O-0001/urodzenie', 'values' => $values]);

    $matches = matchTemporaryPlaces($tree, ['O-0001' => bornIn('Zalesie')]);

    expect(isset($matches->localities['zalesie']))->toBe($asked);
})->with([
    'the same text and id' => [['data' => null, 'miejsce' => 'Zalesie', 'miejsce_id' => 'L:1'], false],
    'the same text without a recognized place' => [['data' => null, 'miejsce' => 'Zalesie', 'miejsce_id' => null], false],
    'a memory from before places' => [['data' => null], true],
    'a memory without the id' => [['data' => null, 'miejsce' => 'Zalesie'], true],
    'another text' => [['data' => null, 'miejsce' => 'Wola', 'miejsce_id' => 'L:1'], true],
]);

test('persons skipped at the identity step and events left deleted are not asked about', function () {
    $tree = User::factory()->create()->tree;
    $tree->repoSnapshots()->create(['repo_id' => 'O-0003/urodzenie', 'values' => ['data' => null], 'deletion_kept' => true]);

    $matches = matchTemporaryPlaces($tree, [
        'O-0001' => bornIn('Zalesie'),
        'O-0002' => bornIn('Wola', 'Adam'),
        'O-0003' => bornIn('Bukinia', 'Piotr'),
    ], skipped: ['O-0002']);

    expect(array_keys($matches->localities))->toContain('zalesie', 'bukinia')
        ->and($matches->localities)->not->toHaveKey('wola');
});

test('a cemetery is sure when the name equals exactly one cemetery of the locality or the only one is meant', function () {
    $tree = User::factory()->create()->tree;
    $husynne = localityOf($tree, 'Husynne');
    $named = Cemetery::factory()->in($husynne)->create(['name' => 'Cmentarz parafialny']);
    Cemetery::factory()->in($husynne)->create(['name' => 'Cmentarz wojenny']);
    $wola = localityOf($tree, 'Wola');
    $only = Cemetery::factory()->in($wola)->unnamed()->create();

    $places = matchTemporaryPlaces($tree, [
        'O-0001' => buriedIn('Husynne', 'cmentarz Parafialny'),
        'O-0002' => buriedIn('Wola'),
    ]);
    $matches = app(MatchResearchPlaces::class)->cemeteries($tree, $places, new PlaceDecisions)->matches;

    expect($matches['husynne|cmentarz parafialny']->kind)->toBe(CemeteryMatchKind::Sure)
        ->and($matches['husynne|cmentarz parafialny']->cemetery?->id)->toBe($named->id)
        ->and($matches['wola|']->kind)->toBe(CemeteryMatchKind::Sure)
        ->and($matches['wola|']->cemetery?->id)->toBe($only->id);
});

test('a cemetery is a choice when several fit or the name matches none', function () {
    $tree = User::factory()->create()->tree;
    $husynne = localityOf($tree, 'Husynne');
    Cemetery::factory()->in($husynne)->create(['name' => 'Cmentarz parafialny']);
    Cemetery::factory()->in($husynne)->create(['name' => 'Cmentarz wojenny']);

    $places = matchTemporaryPlaces($tree, [
        'O-0001' => buriedIn('Husynne'),
        'O-0002' => buriedIn('Husynne', 'Cmentarz żydowski'),
    ]);
    $matches = app(MatchResearchPlaces::class)->cemeteries($tree, $places, new PlaceDecisions)->matches;

    expect($matches['husynne|']->kind)->toBe(CemeteryMatchKind::Choice)
        ->and($matches['husynne|']->options)->toHaveCount(2)
        ->and($matches['husynne|cmentarz zydowski']->kind)->toBe(CemeteryMatchKind::Choice);
});

test('a cemetery is new in a locality without cemeteries or in a new locality and none when the locality is left out', function () {
    $tree = User::factory()->create()->tree;
    localityOf($tree, 'Wola');

    $places = matchTemporaryPlaces($tree, [
        'O-0001' => buriedIn('Wola'),
        'O-0002' => buriedIn('Bokinia'),
        'O-0003' => buriedIn('Zalesie'),
    ]);
    $decisions = new PlaceDecisions(localities: ['bokinia' => PlaceDecisions::NEW, 'zalesie' => PlaceDecisions::NONE]);
    $matches = app(MatchResearchPlaces::class)->cemeteries($tree, $places, $decisions)->matches;

    expect($matches['wola|']->kind)->toBe(CemeteryMatchKind::New)
        ->and($matches['wola|']->localityId)->not->toBeNull()
        ->and($matches['bokinia|']->kind)->toBe(CemeteryMatchKind::New)
        ->and($matches['bokinia|']->localityId)->toBeNull()
        ->and($matches['zalesie|']->kind)->toBe(CemeteryMatchKind::None);
});

test('a doubtful locality settled by the author decides its cemetery', function () {
    $tree = User::factory()->create()->tree;
    $locality = localityOf($tree, 'Bukinia');
    $cemetery = Cemetery::factory()->in($locality)->unnamed()->create();

    $places = matchTemporaryPlaces($tree, ['O-0001' => buriedIn('Bokinia')]);

    expect(app(MatchResearchPlaces::class)->cemeteries($tree, $places, new PlaceDecisions)->matches)->toBe([]);

    $decisions = new PlaceDecisions(localities: ['bokinia' => $locality->id], aliases: ['bokinia' => true]);
    $verdict = app(MatchResearchPlaces::class)->cemeteries($tree, $places, $decisions)->matches['bokinia|'];

    expect($verdict->kind)->toBe(CemeteryMatchKind::Sure)
        ->and($verdict->cemetery?->id)->toBe($cemetery->id);
});

test('the places of another account never take part', function () {
    $other = User::factory()->create()->tree;
    $author = User::factory()->create()->tree;
    $arrange = function (Tree $tree): void {
        $zalesie = localityOf($tree, 'Zalesie');
        $bukinia = localityOf($tree, 'Bukinia');
        LocalityAlias::factory()->create(['tree_id' => $tree->id, 'locality_id' => $bukinia->id, 'name' => 'Bokinia']);
        Cemetery::factory()->in($zalesie)->unnamed()->create();
    };
    $arrange($other);

    $blocks = ['O-0001' => bornIn('Bokinia'), 'O-0002' => buriedIn('Zalesie')];
    $places = matchTemporaryPlaces($author, $blocks);

    expect($places->localities['bokinia']->kind)->toBe(PlaceMatchKind::New)
        ->and($places->localities['zalesie']->kind)->toBe(PlaceMatchKind::New);

    $arrange($author);

    $places = matchTemporaryPlaces($author, $blocks);
    $cemeteries = app(MatchResearchPlaces::class)->cemeteries($author, $places, new PlaceDecisions)->matches;

    expect($places->localities['bokinia']->locality?->tree_id)->toBe($author->id)
        ->and($cemeteries['zalesie|']->cemetery?->tree_id)->toBe($author->id)
        ->and($cemeteries['zalesie|']->cemetery?->id)->not->toBe($other->cemeteries()->first()->id);
});
