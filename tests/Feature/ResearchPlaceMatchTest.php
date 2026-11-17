<?php

use App\Actions\Import\MatchResearchPlaces;
use App\Actions\Import\ReadResearchRepo;
use App\Enums\CemeteryMatchKind;
use App\Enums\ParishKind;
use App\Enums\ParishMatchKind;
use App\Enums\PlaceMatchKind;
use App\Enums\Religion;
use App\Models\Cemetery;
use App\Models\Locality;
use App\Models\LocalityAlias;
use App\Models\Parish;
use App\Models\Tree;
use App\Models\User;
use App\Support\Import\CemeteryVerdict;
use App\Support\Import\IdentityDecisions;
use App\Support\Import\ImportDecisions;
use App\Support\Import\ParishMatches;
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
 * The tree of a new author, created after another account whose gazetteer has the same names the tests use, a variant
 * and cemeteries, so that a match that leaves its tree would show up.
 */
function placeTwinTree(): Tree
{
    $other = User::factory()->create()->tree;

    foreach (['Kolemczyce', 'Bukinia', 'Bokinka', 'Zalesie', 'Stary Majdan', 'Siedliszcze', 'Wola', 'Rostoka', 'Husynne'] as $name) {
        Locality::factory()->create(['tree_id' => $other->id, 'name' => $name]);
    }

    $localities = $other->localities()->get()->keyBy('name');
    LocalityAlias::factory()->create(['tree_id' => $other->id, 'locality_id' => $localities['Bukinia']->id, 'name' => 'Bokinia']);
    Cemetery::factory()->in($localities['Zalesie'])->unnamed()->create();
    Cemetery::factory()->in($localities['Zalesie'])->create(['name' => 'Cmentarz parafialny']);
    Cemetery::factory()->in($localities['Husynne'])->create(['name' => 'Cmentarz wojenny']);
    Cemetery::factory()->in($localities['Wola'])->unnamed()->create();

    return User::factory()->create()->tree;
}

/**
 * Settles the cemeteries of the burials against the tree. Fails when a cemetery of another tree shows up.
 *
 * @return array<string, CemeteryVerdict>
 */
function matchTemporaryCemeteries(Tree $tree, PlaceMatches $places, PlaceDecisions $decisions): array
{
    $matches = app(MatchResearchPlaces::class)->cemeteries($tree, $places, $decisions)->matches;

    foreach ($matches as $verdict) {
        foreach ([$verdict->cemetery, ...$verdict->options] as $cemetery) {
            expect($cemetery === null || $cemetery->tree_id === $tree->id)->toBeTrue();
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
    $tree = placeTwinTree();
    $locality = localityOf($tree, 'Kołemczyce');

    $verdict = matchTemporaryPlaces($tree, ['O-0001' => bornIn('Kolemczyce')])->localities['kolemczyce'];

    expect($verdict->kind)->toBe(PlaceMatchKind::Sure)
        ->and($verdict->locality?->id)->toBe($locality->id);
});

test('a remembered variant makes a name sure', function () {
    $tree = placeTwinTree();
    $locality = localityOf($tree, 'Bukinia');
    LocalityAlias::factory()->create(['tree_id' => $tree->id, 'locality_id' => $locality->id, 'name' => 'Bokinia']);

    $verdict = matchTemporaryPlaces($tree, ['O-0001' => bornIn('Bokinia')])->localities['bokinia'];

    expect($verdict->kind)->toBe(PlaceMatchKind::Sure)
        ->and($verdict->locality?->id)->toBe($locality->id);
});

test('a similar spelling is doubtful with the closest candidates first', function () {
    $tree = placeTwinTree();
    $far = localityOf($tree, 'Bokinka');
    $near = localityOf($tree, 'Bukinia');
    localityOf($tree, 'Zalesie');

    $verdict = matchTemporaryPlaces($tree, ['O-0001' => bornIn('Bokinia')])->localities['bokinia'];

    expect($verdict->kind)->toBe(PlaceMatchKind::Doubtful)
        ->and(array_map(fn (Locality $locality): int => $locality->id, $verdict->candidates))->toBe([$far->id, $near->id]);
});

test('the same words in another order are doubtful', function () {
    $tree = placeTwinTree();
    $locality = localityOf($tree, 'Stary Majdan');

    $verdict = matchTemporaryPlaces($tree, ['O-0001' => bornIn('Majdan Stary')])->localities['majdan stary'];

    expect($verdict->kind)->toBe(PlaceMatchKind::Doubtful)
        ->and($verdict->candidates[0]->id)->toBe($locality->id);
});

test('a name that only contains another one is new', function () {
    $tree = placeTwinTree();
    localityOf($tree, 'Siedliszcze');

    $verdict = matchTemporaryPlaces($tree, ['O-0001' => bornIn('Siedliszcze Bramowe')])->localities['siedliszcze bramowe'];

    expect($verdict->kind)->toBe(PlaceMatchKind::New)
        ->and($verdict->candidates)->toBe([]);
});

test('two localities with the same key are doubtful until a variant picks one', function () {
    $tree = placeTwinTree();
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
    $tree = placeTwinTree();

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
    $tree = placeTwinTree();

    $matches = matchTemporaryPlaces($tree, [
        'O-0001' => bornIn('rostoka'),
        'O-0002' => bornIn('Rostoka', 'Adam'),
        'O-0003' => bornIn('Rostoka', 'Piotr'),
    ]);

    expect($matches->localities['rostoka']->name)->toBe('Rostoka');
});

test('a place the memory already holds with its id is not asked about again', function (array $values, bool $asked) {
    $tree = placeTwinTree();
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
    $tree = placeTwinTree();
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
    $tree = placeTwinTree();
    $husynne = localityOf($tree, 'Husynne');
    $named = Cemetery::factory()->in($husynne)->create(['name' => 'Cmentarz parafialny']);
    Cemetery::factory()->in($husynne)->create(['name' => 'Cmentarz wojenny']);
    $wola = localityOf($tree, 'Wola');
    $only = Cemetery::factory()->in($wola)->unnamed()->create();

    $places = matchTemporaryPlaces($tree, [
        'O-0001' => buriedIn('Husynne', 'cmentarz Parafialny'),
        'O-0002' => buriedIn('Wola'),
    ]);
    $matches = matchTemporaryCemeteries($tree, $places, new PlaceDecisions);

    expect($matches['husynne|cmentarz parafialny']->kind)->toBe(CemeteryMatchKind::Sure)
        ->and($matches['husynne|cmentarz parafialny']->cemetery?->id)->toBe($named->id)
        ->and($matches['wola|']->kind)->toBe(CemeteryMatchKind::Sure)
        ->and($matches['wola|']->cemetery?->id)->toBe($only->id);
});

test('a cemetery is a choice when several fit or the name matches none', function () {
    $tree = placeTwinTree();
    $husynne = localityOf($tree, 'Husynne');
    Cemetery::factory()->in($husynne)->create(['name' => 'Cmentarz parafialny']);
    Cemetery::factory()->in($husynne)->create(['name' => 'Cmentarz wojenny']);

    $places = matchTemporaryPlaces($tree, [
        'O-0001' => buriedIn('Husynne'),
        'O-0002' => buriedIn('Husynne', 'Cmentarz żydowski'),
    ]);
    $matches = matchTemporaryCemeteries($tree, $places, new PlaceDecisions);

    expect($matches['husynne|']->kind)->toBe(CemeteryMatchKind::Choice)
        ->and($matches['husynne|']->options)->toHaveCount(2)
        ->and($matches['husynne|cmentarz zydowski']->kind)->toBe(CemeteryMatchKind::Choice);
});

test('a cemetery is new in a locality without cemeteries or in a new locality and none when the locality is left out', function () {
    $tree = placeTwinTree();
    localityOf($tree, 'Wola');

    $places = matchTemporaryPlaces($tree, [
        'O-0001' => buriedIn('Wola'),
        'O-0002' => buriedIn('Bokinia'),
        'O-0003' => buriedIn('Zalesie'),
    ]);
    $decisions = new PlaceDecisions(localities: ['bokinia' => PlaceDecisions::NEW, 'zalesie' => PlaceDecisions::NONE]);
    $matches = matchTemporaryCemeteries($tree, $places, $decisions);

    expect($matches['wola|']->kind)->toBe(CemeteryMatchKind::New)
        ->and($matches['wola|']->localityId)->not->toBeNull()
        ->and($matches['bokinia|']->kind)->toBe(CemeteryMatchKind::New)
        ->and($matches['bokinia|']->localityId)->toBeNull()
        ->and($matches['zalesie|']->kind)->toBe(CemeteryMatchKind::None);
});

test('a doubtful locality settled by the author decides its cemetery', function () {
    $tree = placeTwinTree();
    $locality = localityOf($tree, 'Bukinia');
    $cemetery = Cemetery::factory()->in($locality)->unnamed()->create();

    $places = matchTemporaryPlaces($tree, ['O-0001' => buriedIn('Bokinia')]);

    expect(matchTemporaryCemeteries($tree, $places, new PlaceDecisions))->toBe([]);

    $decisions = new PlaceDecisions(localities: ['bokinia' => $locality->id], aliases: ['bokinia' => true]);
    $verdict = matchTemporaryCemeteries($tree, $places, $decisions)['bokinia|'];

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

/**
 * Reads a throwaway repo whose sources name the given parishes and settles them against the tree. Fails when a
 * locality or parish of another tree shows up.
 *
 * @param  array<string, array<string, string>>  $parishes  repo id of a source => fields of its `parafia` block (`miejscowosc`, `wyznanie`, `rodzaj`, `wezwanie`)
 * @return array{0: PlaceMatches, 1: ParishMatches}
 */
function matchTemporaryParishes(Tree $tree, array $parishes, ?PlaceDecisions $decisions = null): array
{
    $repo = sys_get_temp_dir().'/parish-repo-'.uniqid();
    mkdir($repo.'/drzewo', 0777, true);
    mkdir($repo.'/zrodla/skany', 0777, true);

    $entries = '';

    foreach (array_values($parishes) as $index => $fields) {
        $id = array_keys($parishes)[$index];
        $image = imagecreatetruecolor(8, 8);
        imagefill($image, 0, 0, imagecolorallocate($image, 20 + $index, 20, 20));
        imagejpeg($image, "{$repo}/zrodla/skany/{$id}.jpg");

        $entries .= "  - id: {$id}\n    plik: zrodla/skany/{$id}.jpg\n    rodzaj: akt\n    parafia:\n";

        foreach ($fields as $field => $value) {
            $entries .= "      {$field}: \"{$value}\"\n";
        }
    }

    file_put_contents("{$repo}/zrodla/akty.md", "---\nwersja: 1\nskany:\n{$entries}---\n");

    try {
        $reading = app(ReadResearchRepo::class)->handle($repo);
    } finally {
        File::deleteDirectory($repo);
    }

    expect($reading->skippedSources)->toBe([])->and($reading->sources)->toHaveCount(count($parishes));

    $decisions ??= new PlaceDecisions;
    $places = app(MatchResearchPlaces::class)->handle($tree, $reading, new RepoRecordClasses, new ImportDecisions(new IdentityDecisions, places: $decisions));
    $matches = app(MatchResearchPlaces::class)->parishes($tree, $places, $decisions);

    foreach ($places->localities as $verdict) {
        foreach ([$verdict->locality, ...$verdict->candidates] as $locality) {
            expect($locality === null || $locality->tree_id === $tree->id)->toBeTrue();
        }
    }

    foreach ($matches->matches as $verdict) {
        foreach ([$verdict->parish, ...$verdict->options] as $parish) {
            expect($parish === null || $parish->tree_id === $tree->id)->toBeTrue();
        }
    }

    return [$places, $matches];
}

/**
 * The tree of a new author, created after another account that has the seat Lipnica with parishes of every religion
 * and dedication the parish tests use, so that a match that leaves its tree would show up.
 */
function parishTwinTree(): Tree
{
    $other = User::factory()->create()->tree;
    $seat = Locality::factory()->create(['tree_id' => $other->id, 'name' => 'Lipnica']);

    foreach ([Religion::RomanCatholic, Religion::GreekCatholic, null] as $religion) {
        Parish::factory()->seatedIn($seat)->create(['religion' => $religion, 'dedication' => 'św. Jana']);
        Parish::factory()->seatedIn($seat)->create(['religion' => $religion, 'dedication' => null]);
    }

    return User::factory()->create()->tree;
}

function parishAt(Tree $tree, Locality $seat, ?Religion $religion, ?string $dedication = null, ParishKind $kind = ParishKind::Parish): Parish
{
    return Parish::factory()->seatedIn($seat)->create(['tree_id' => $tree->id, 'religion' => $religion, 'dedication' => $dedication, 'kind' => $kind]);
}

test('a parish is sure when one candidate of the seat has the same kind and religion and the block gives no dedication', function () {
    $tree = parishTwinTree();
    $seat = localityOf($tree, 'Lipnica');
    $parish = parishAt($tree, $seat, Religion::RomanCatholic, 'św. Jana');
    parishAt($tree, $seat, Religion::GreekCatholic);
    parishAt($tree, $seat, Religion::RomanCatholic, null, ParishKind::CivilRegistry);

    [, $matches] = matchTemporaryParishes($tree, ['ZR-0001' => ['miejscowosc' => 'lipnica', 'wyznanie' => 'rzymskokatolickie']]);
    $verdict = $matches->matches['lipnica|roman_catholic|parish|'];

    expect($verdict->kind)->toBe(ParishMatchKind::Sure)
        ->and($verdict->parish?->id)->toBe($parish->id)
        ->and($verdict->localityId)->toBe($seat->id);
});

test('a parish is sure by its dedication among several candidates, ignoring capitals and polish characters', function () {
    $tree = parishTwinTree();
    $seat = localityOf($tree, 'Lipnica');
    parishAt($tree, $seat, Religion::RomanCatholic, 'św. Jana');
    $match = parishAt($tree, $seat, Religion::RomanCatholic, 'Św. Michała');

    [, $matches] = matchTemporaryParishes($tree, ['ZR-0001' => ['miejscowosc' => 'Lipnica', 'wyznanie' => 'rzymskokatolickie', 'wezwanie' => 'sw. michala']]);
    $verdict = $matches->matches['lipnica|roman_catholic|parish|sw. michala'];

    expect($verdict->kind)->toBe(ParishMatchKind::Sure)
        ->and($verdict->parish?->id)->toBe($match->id);
});

test('a parish is a choice when several candidates fit and the block gives no dedication or none matches', function () {
    $tree = parishTwinTree();
    $seat = localityOf($tree, 'Lipnica');
    $first = parishAt($tree, $seat, Religion::RomanCatholic, 'św. Jana');
    $second = parishAt($tree, $seat, Religion::RomanCatholic, 'św. Michała');

    [, $matches] = matchTemporaryParishes($tree, [
        'ZR-0001' => ['miejscowosc' => 'Lipnica', 'wyznanie' => 'rzymskokatolickie'],
        'ZR-0002' => ['miejscowosc' => 'Lipnica', 'wyznanie' => 'rzymskokatolickie', 'wezwanie' => 'św. Piotra'],
    ]);

    expect($matches->matches['lipnica|roman_catholic|parish|']->kind)->toBe(ParishMatchKind::Choice)
        ->and(array_map(fn (Parish $parish): int => $parish->id, $matches->matches['lipnica|roman_catholic|parish|']->options))->toBe([$first->id, $second->id])
        ->and($matches->matches['lipnica|roman_catholic|parish|sw. piotra']->kind)->toBe(ParishMatchKind::Choice);
});

test('a parish is new when no parish of the seat has the kind and religion', function () {
    $tree = parishTwinTree();
    $seat = localityOf($tree, 'Lipnica');
    parishAt($tree, $seat, Religion::GreekCatholic, 'św. Jana');
    parishAt($tree, $seat, Religion::RomanCatholic, null, ParishKind::CivilRegistry);
    localityOf($tree, 'Wola');

    [, $matches] = matchTemporaryParishes($tree, [
        'ZR-0001' => ['miejscowosc' => 'Lipnica', 'wyznanie' => 'rzymskokatolickie'],
        'ZR-0002' => ['miejscowosc' => 'Wola', 'wyznanie' => 'rzymskokatolickie'],
    ]);

    expect($matches->matches['lipnica|roman_catholic|parish|']->kind)->toBe(ParishMatchKind::New)
        ->and($matches->matches['lipnica|roman_catholic|parish|']->localityId)->toBe($seat->id)
        ->and($matches->matches['wola|roman_catholic|parish|']->kind)->toBe(ParishMatchKind::New);
});

test('a parish without a religion in the tree is an unsure candidate', function () {
    $tree = parishTwinTree();
    $seat = localityOf($tree, 'Lipnica');
    $unknown = parishAt($tree, $seat, null, 'św. Jana');

    [, $matches] = matchTemporaryParishes($tree, ['ZR-0001' => ['miejscowosc' => 'Lipnica', 'wyznanie' => 'rzymskokatolickie', 'wezwanie' => 'św. Jana']]);
    $verdict = $matches->matches['lipnica|roman_catholic|parish|sw. jana'];

    expect($verdict->kind)->toBe(ParishMatchKind::Choice)
        ->and($verdict->parish)->toBeNull()
        ->and(array_map(fn (Parish $parish): int => $parish->id, $verdict->options))->toBe([$unknown->id]);
});

test('a block without a religion offers the parishes of every religion and is never sure', function () {
    $tree = parishTwinTree();
    $seat = localityOf($tree, 'Lipnica');
    $roman = parishAt($tree, $seat, Religion::RomanCatholic);

    [, $matches] = matchTemporaryParishes($tree, ['ZR-0001' => ['miejscowosc' => 'Lipnica']]);
    $verdict = $matches->matches['lipnica||parish|'];

    expect($verdict->kind)->toBe(ParishMatchKind::Choice)
        ->and(array_map(fn (Parish $parish): int => $parish->id, $verdict->options))->toBe([$roman->id]);

    [, $matches] = matchTemporaryParishes(parishTwinTree(), ['ZR-0001' => ['miejscowosc' => 'Lipnica']]);

    expect(isset($matches->matches['lipnica||parish|']))->toBeFalse();
});

test('a block without a religion makes a new parish only when the seat has no candidate', function () {
    $tree = parishTwinTree();
    $seat = localityOf($tree, 'Lipnica');
    parishAt($tree, $seat, Religion::RomanCatholic, null, ParishKind::CivilRegistry);

    [, $matches] = matchTemporaryParishes($tree, ['ZR-0001' => ['miejscowosc' => 'Lipnica']], new PlaceDecisions);

    expect($matches->matches['lipnica||parish|']->kind)->toBe(ParishMatchKind::New);
});

test('a doubtful seat leaves its parish out until it is decided and the decision settles the parish', function () {
    $tree = parishTwinTree();
    $seat = localityOf($tree, 'Bukinia');
    $parish = parishAt($tree, $seat, Religion::RomanCatholic);
    $blocks = ['ZR-0001' => ['miejscowosc' => 'Bokinia', 'wyznanie' => 'rzymskokatolickie']];

    [$places, $matches] = matchTemporaryParishes($tree, $blocks);

    expect($places->localities['bokinia']->kind)->toBe(PlaceMatchKind::Doubtful)
        ->and($places->localities['bokinia']->repoIds())->toBe(['ZR-0001'])
        ->and($places->parishRequests)->toHaveCount(1)
        ->and($matches->matches)->toBe([]);

    [, $matches] = matchTemporaryParishes($tree, $blocks, new PlaceDecisions(localities: ['bokinia' => $seat->id], aliases: ['bokinia' => true]));

    expect($matches->matches['bokinia|roman_catholic|parish|']->kind)->toBe(ParishMatchKind::Sure)
        ->and($matches->matches['bokinia|roman_catholic|parish|']->parish?->id)->toBe($parish->id);
});

test('a source with no place gets no parish and a new seat gets a new parish', function () {
    $tree = parishTwinTree();
    $blocks = [
        'ZR-0001' => ['miejscowosc' => 'Bokinia', 'wyznanie' => 'rzymskokatolickie'],
        'ZR-0002' => ['miejscowosc' => 'Kolemczyce', 'wezwanie' => 'św. Jana'],
    ];

    [, $matches] = matchTemporaryParishes($tree, $blocks, new PlaceDecisions(localities: ['bokinia' => PlaceDecisions::NONE, 'kolemczyce' => PlaceDecisions::NEW]));

    expect($matches->matches['bokinia|roman_catholic|parish|']->kind)->toBe(ParishMatchKind::None)
        ->and($matches->matches['kolemczyce||parish|sw. jana']->kind)->toBe(ParishMatchKind::New)
        ->and($matches->matches['kolemczyce||parish|sw. jana']->localityId)->toBeNull();
});

test('a parish the memory holds with its text and id is not asked about, a changed text is', function (array $values, bool $asked) {
    $tree = parishTwinTree();
    localityOf($tree, 'Lipnica');
    $tree->repoSnapshots()->create(['repo_id' => 'ZR-0001', 'values' => $values]);

    [$places, $matches] = matchTemporaryParishes($tree, ['ZR-0001' => ['miejscowosc' => 'Lipnica', 'wyznanie' => 'rzymskokatolickie', 'wezwanie' => 'św. Jana']]);

    expect($places->parishRequests !== [])->toBe($asked)
        ->and(isset($places->localities['lipnica']))->toBe($asked)
        ->and($matches->matches !== [])->toBe($asked);
})->with([
    'the same text and id' => [['parafia' => 'Lipnica|roman_catholic|parish|św. Jana', 'parafia_id' => 'P:1'], false],
    'the same text without a recognized parish' => [['parafia' => 'Lipnica|roman_catholic|parish|św. Jana', 'parafia_id' => null], false],
    'a memory from before parishes' => [['nazwa' => 'Akt'], true],
    'a memory without the id' => [['parafia' => 'Lipnica|roman_catholic|parish|św. Jana'], true],
    'another text' => [['parafia' => 'Lipnica|roman_catholic|parish|św. Michała', 'parafia_id' => 'P:1'], true],
]);

test('one parish named by several sources is one question that counts them all', function () {
    $tree = parishTwinTree();
    $seat = localityOf($tree, 'Lipnica');
    parishAt($tree, $seat, Religion::RomanCatholic, 'św. Jana');
    parishAt($tree, $seat, Religion::RomanCatholic, 'św. Michała');

    [$places, $matches] = matchTemporaryParishes($tree, [
        'ZR-0001' => ['miejscowosc' => 'Lipnica', 'wyznanie' => 'rzymskokatolickie'],
        'ZR-0002' => ['miejscowosc' => 'lipnica', 'wyznanie' => 'rzymskokatolickie'],
        'ZR-0003' => ['miejscowosc' => 'Lipnica', 'wyznanie' => 'rzymskokatolickie'],
    ]);

    expect($places->parishRequests)->toHaveCount(1)
        ->and($matches->matches)->toHaveCount(1)
        ->and($matches->matches['lipnica|roman_catholic|parish|']->sourceCount())->toBe(3)
        ->and($places->localities['lipnica']->repoIds())->toEqualCanonicalizing(['ZR-0001', 'ZR-0002', 'ZR-0003']);
});

test('the parishes of another account never take part', function () {
    $other = User::factory()->create()->tree;
    $author = User::factory()->create()->tree;
    $blocks = ['ZR-0001' => ['miejscowosc' => 'Lipnica', 'wyznanie' => 'rzymskokatolickie', 'wezwanie' => 'św. Jana']];
    $arrange = fn (Tree $tree): Parish => parishAt($tree, localityOf($tree, 'Lipnica'), Religion::RomanCatholic, 'św. Jana');
    $arrange($other);

    [$places, $matches] = matchTemporaryParishes($author, $blocks);

    expect($places->localities['lipnica']->kind)->toBe(PlaceMatchKind::New)
        ->and($matches->matches)->toBe([]);

    $mine = $arrange($author);

    [, $matches] = matchTemporaryParishes($author, $blocks);

    expect($matches->matches['lipnica|roman_catholic|parish|sw. jana']->parish?->id)->toBe($mine->id);
});
