<?php

use App\Enums\EventType;
use App\Enums\ParticipantRole;
use App\Models\Event;
use App\Models\Locality;
use App\Models\Person;
use App\Models\Residence;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Attach an event of the given type to a person, with no date unless given.
 *
 * @param  array<string, mixed>  $attributes
 */
function attachTimelineEvent(Person $person, EventType $type, array $attributes = []): Event
{
    $event = Event::factory()->for($person->tree)->create([
        'type' => $type,
        'date_year' => null,
        'date_month' => null,
        'date_day' => null,
        'date_is_approximate' => false,
        ...$attributes,
    ]);

    $event->participants()->attach($person, ['role' => ParticipantRole::Subject]);

    return $event;
}

/**
 * The timeline rows of the preview, reduced to label, date, place and relative name.
 *
 * @return list<array{0: string, 1: string|null, 2: string|null, 3: string|null}>
 */
function timelineRows(TestCase $test, User $user, Person $person): array
{
    $timeline = $test->actingAs($user)
        ->get(personPreviewUrl($person))
        ->assertOk()
        ->viewData('page')['props']['personPreview']['person']['timeline'];

    return array_map(
        fn (array $row): array => [$row['label'], $row['date'], $row['place'], $row['relative']['display_name'] ?? null],
        $timeline,
    );
}

/**
 * Build the scene of the range test: Adam Nowak with a birth, two marriages, a death,
 * a burial, a son (birth and death), a daughter (birth), and a spouse who died.
 *
 * @return array{person: Person, ewa: Person, maria: Person, jan: Person, zofia: Person}
 */
function timelineRangeScene(User $user): array
{
    $person = Person::factory()->for($user->tree)->male()->create(['first_name' => 'Adam', 'last_name' => 'Nowak']);
    $ewa = Person::factory()->for($user->tree)->female()->create(['first_name' => 'Ewa', 'last_name' => 'Zielińska']);
    $maria = Person::factory()->for($user->tree)->female()->create(['first_name' => 'Maria', 'last_name' => 'Lis']);
    $jan = Person::factory()->for($user->tree)->male()->create(['first_name' => 'Jan', 'last_name' => 'Nowak']);
    $zofia = Person::factory()->for($user->tree)->female()->create(['first_name' => 'Zofia', 'last_name' => 'Nowak']);
    $parent = Person::factory()->for($user->tree)->create(['first_name' => 'Piotr', 'last_name' => 'Nowak']);

    attachTimelineEvent($person, EventType::Birth, ['date_year' => 1900]);
    attachMarriage($person, $ewa, 1920);
    attachMarriage($person, $maria, 1930);
    attachTimelineEvent($person, EventType::Death, ['date_year' => 1960]);
    attachTimelineEvent($person, EventType::Burial, ['locality_id' => Locality::factory()->create(['tree_id' => $user->tree->id, 'name' => 'Zakopane'])->id]);
    attachTimelineEvent($jan, EventType::Birth, ['date_year' => 1925]);
    attachTimelineEvent($jan, EventType::Death, ['date_year' => 1990]);
    attachTimelineEvent($zofia, EventType::Birth, ['date_year' => 1928]);
    attachTimelineEvent($ewa, EventType::Birth, ['date_year' => 1800]);
    attachTimelineEvent($ewa, EventType::Death, ['date_year' => 1950]);
    attachTimelineEvent($parent, EventType::Birth, ['date_year' => 1870]);
    attachTimelineEvent($parent, EventType::Death, ['date_year' => 1940]);

    linkParent($person, $jan);
    linkParent($person, $zofia);
    linkParent($parent, $person);
    Residence::factory()->for($person)->years(1910, 1915)->create();

    return ['person' => $person, 'ewa' => $ewa, 'maria' => $maria, 'jan' => $jan, 'zofia' => $zofia];
}

test('the timeline holds own events, children births and deaths and each spouse death, and nothing else', function () {
    $user = User::factory()->create();
    $scene = timelineRangeScene($user);

    expect(timelineRows($this, $user, $scene['person']))->toBe([
        ['Urodzenie', '1900', null, null],
        ['Małżeństwo', '1920', null, 'Ewa Zielińska'],
        ['Urodzenie syna', '1925', null, 'Jan Nowak'],
        ['Urodzenie córki', '1928', null, 'Zofia Nowak'],
        ['Małżeństwo', '1930', null, 'Maria Lis'],
        ['Zgon żony', '1950', null, 'Ewa Zielińska'],
        ['Zgon', '1960', null, null],
        ['Pochowanie', null, 'Zakopane', null],
        ['Zgon syna', '1990', null, 'Jan Nowak'],
    ]);
});

test('a marriage with a spouse who died appears once and the spouse death appears once', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $spouse = Person::factory()->for($user->tree)->female()->create(['first_name' => 'Ewa', 'last_name' => 'Zielińska']);
    attachMarriage($person, $spouse, 1920);
    attachMarriage($person, $spouse, 1921);
    attachTimelineEvent($spouse, EventType::Death, ['date_year' => 1950]);

    expect(timelineRows($this, $user, $person))->toBe([
        ['Małżeństwo', '1920', null, 'Ewa Zielińska'],
        ['Małżeństwo', '1921', null, 'Ewa Zielińska'],
        ['Zgon żony', '1950', null, 'Ewa Zielińska'],
    ]);
});

test('a birth without a year and without a locality is omitted for the person and for a child', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $child = Person::factory()->for($user->tree)->create(['first_name' => 'Jan', 'last_name' => 'Nowak']);
    attachTimelineEvent($person, EventType::Birth);
    attachTimelineEvent($child, EventType::Birth);
    linkParent($person, $child);

    expect(timelineRows($this, $user, $person))->toBe([]);
});

test('a birth with only a place stays and sorts after the dated rows', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $child = Person::factory()->for($user->tree)->create(['first_name' => 'Jan', 'last_name' => 'Nowak']);
    $locality = Locality::factory()->create(['tree_id' => $user->tree->id, 'name' => 'Kraków']);
    attachTimelineEvent($person, EventType::Birth, ['date_year' => 1900]);
    attachTimelineEvent($person, EventType::Birth, ['locality_id' => $locality->id]);
    attachTimelineEvent($child, EventType::Birth, ['locality_id' => $locality->id]);
    linkParent($person, $child);

    expect(timelineRows($this, $user, $person))->toBe([
        ['Urodzenie', '1900', null, null],
        ['Urodzenie dziecka', null, 'Kraków', 'Jan Nowak'],
    ]);
});

test('a death without a date and place is shown with a null date at the end', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $spouse = Person::factory()->for($user->tree)->female()->create(['first_name' => 'Ewa', 'last_name' => 'Zielińska']);
    attachTimelineEvent($person, EventType::Birth, ['date_year' => 1900]);
    attachMarriage($person, $spouse, 1920);
    attachTimelineEvent($spouse, EventType::Death);

    expect(timelineRows($this, $user, $person))->toBe([
        ['Urodzenie', '1900', null, null],
        ['Małżeństwo', '1920', null, 'Ewa Zielińska'],
        ['Zgon żony', null, null, 'Ewa Zielińska'],
    ]);
});

test('a marriage in 1880 sorts before the birth of a son in 1880', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $spouse = Person::factory()->for($user->tree)->create();
    $son = Person::factory()->for($user->tree)->male()->create(['first_name' => 'Jan', 'last_name' => 'Nowak']);
    attachMarriage($person, $spouse, 1880);
    attachTimelineEvent($son, EventType::Birth, ['date_year' => 1880]);
    linkParent($person, $son);

    expect(timelineRows($this, $user, $person))->toBe([
        ['Małżeństwo', '1880', null, $spouse->displayName()],
        ['Urodzenie syna', '1880', null, 'Jan Nowak'],
    ]);
});

test('a marriage in 1880 sorts before the birth of a son on 05.03.1880', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $spouse = Person::factory()->for($user->tree)->create();
    $son = Person::factory()->for($user->tree)->male()->create(['first_name' => 'Jan', 'last_name' => 'Nowak']);
    attachMarriage($person, $spouse, 1880);
    attachTimelineEvent($son, EventType::Birth, ['date_year' => 1880, 'date_month' => 3, 'date_day' => 5]);
    linkParent($person, $son);

    expect(timelineRows($this, $user, $person))->toBe([
        ['Małżeństwo', '1880', null, $spouse->displayName()],
        ['Urodzenie syna', '05.03.1880', null, 'Jan Nowak'],
    ]);
});

test('an approximate year sorts like its plain year', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $spouse = Person::factory()->for($user->tree)->create();
    $son = Person::factory()->for($user->tree)->male()->create(['first_name' => 'Jan', 'last_name' => 'Nowak']);
    attachMarriage($person, $spouse, 1849);
    attachTimelineEvent($son, EventType::Birth, ['date_year' => 1850, 'date_is_approximate' => true]);
    linkParent($person, $son);

    expect(timelineRows($this, $user, $person))->toBe([
        ['Małżeństwo', '1849', null, $spouse->displayName()],
        ['Urodzenie syna', 'ok. 1850', null, 'Jan Nowak'],
    ]);
});

test('rows without a year sort last in rank order', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $spouse = Person::factory()->for($user->tree)->female()->create(['first_name' => 'Ewa', 'last_name' => 'Zielińska']);
    $locality = Locality::factory()->create(['tree_id' => $user->tree->id, 'name' => 'Kraków']);
    attachTimelineEvent($person, EventType::Birth, ['locality_id' => $locality->id]);
    attachTimelineEvent($spouse, EventType::Death);
    attachTimelineEvent($person, EventType::Death);
    attachTimelineEvent($person, EventType::Burial, ['locality_id' => $locality->id]);
    attachMarriage($person, $spouse, 1920);

    expect(timelineRows($this, $user, $person))->toBe([
        ['Małżeństwo', '1920', null, 'Ewa Zielińska'],
        ['Urodzenie', null, 'Kraków', null],
        ['Zgon żony', null, null, 'Ewa Zielińska'],
        ['Zgon', null, null, null],
        ['Pochowanie', null, 'Kraków', null],
    ]);
});

test('the burial follows the dated death of the person and the child death comes after it', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $son = Person::factory()->for($user->tree)->male()->create(['first_name' => 'Jan', 'last_name' => 'Nowak']);
    attachTimelineEvent($person, EventType::Death, ['date_year' => 1900]);
    attachTimelineEvent($person, EventType::Burial);
    attachTimelineEvent($son, EventType::Death, ['date_year' => 1950]);
    linkParent($person, $son);

    expect(timelineRows($this, $user, $person))->toBe([
        ['Zgon', '1900', null, null],
        ['Pochowanie', null, null, null],
        ['Zgon syna', '1950', null, 'Jan Nowak'],
    ]);
});

test('the child and spouse labels follow the gender of the relative and fall back to the neutral form', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user->tree)->create();
    $unknownChild = Person::factory()->for($user->tree)->create(['first_name' => 'Ola', 'last_name' => 'Nowak']);
    $husband = Person::factory()->for($user->tree)->male()->create(['first_name' => 'Karol', 'last_name' => 'Lis']);
    $unknownSpouse = Person::factory()->for($user->tree)->create(['first_name' => 'Anna', 'last_name' => 'Kos']);
    attachTimelineEvent($unknownChild, EventType::Birth, ['date_year' => 1930]);
    attachTimelineEvent($unknownChild, EventType::Death, ['date_year' => 1980]);
    attachMarriage($person, $husband, 1920);
    attachMarriage($person, $unknownSpouse, 1921);
    attachTimelineEvent($husband, EventType::Death, ['date_year' => 1940]);
    attachTimelineEvent($unknownSpouse, EventType::Death, ['date_year' => 1945]);
    linkParent($person, $unknownChild);

    expect(timelineRows($this, $user, $person))->toBe([
        ['Małżeństwo', '1920', null, 'Karol Lis'],
        ['Małżeństwo', '1921', null, 'Anna Kos'],
        ['Urodzenie dziecka', '1930', null, 'Ola Nowak'],
        ['Zgon męża', '1940', null, 'Karol Lis'],
        ['Zgon małżonka', '1945', null, 'Anna Kos'],
        ['Zgon dziecka', '1980', null, 'Ola Nowak'],
    ]);
});

test('relative ids and names are given for child and spouse rows, and null for own events outside marriages', function () {
    $user = User::factory()->create();
    $scene = timelineRangeScene($user);

    $timeline = $this->actingAs($user)
        ->get(personPreviewUrl($scene['person']))
        ->viewData('page')['props']['personPreview']['person']['timeline'];

    expect($timeline[0]['relative'])->toBeNull()
        ->and($timeline[1]['relative'])->toMatchArray(['id' => $scene['ewa']->id, 'display_name' => 'Ewa Zielińska'])
        ->and($timeline[2]['relative'])->toMatchArray(['id' => $scene['jan']->id, 'display_name' => 'Jan Nowak'])
        ->and($timeline[5]['relative'])->toMatchArray(['id' => $scene['ewa']->id, 'display_name' => 'Ewa Zielińska'])
        ->and($timeline[6]['relative'])->toBeNull()
        ->and($timeline[7]['relative'])->toBeNull();
});

test('the number of queries to load the preview does not grow with more children or spouses', function () {
    $user = User::factory()->create();
    $locality = Locality::factory()->create(['tree_id' => $user->tree->id, 'name' => 'Kraków']);
    $person = Person::factory()->for($user->tree)->create();

    $addChild = function (int $year) use ($user, $person, $locality): void {
        $child = Person::factory()->for($user->tree)->create();
        attachTimelineEvent($child, EventType::Birth, ['date_year' => $year, 'locality_id' => $locality->id]);
        attachTimelineEvent($child, EventType::Death, ['date_year' => $year + 60, 'locality_id' => $locality->id]);
        linkParent($person, $child);
    };

    $addSpouse = function (int $year) use ($user, $person, $locality): void {
        $spouse = Person::factory()->for($user->tree)->create();
        attachMarriage($person, $spouse, $year, locality: $locality);
        attachTimelineEvent($spouse, EventType::Death, ['date_year' => $year + 30, 'locality_id' => $locality->id]);
    };

    $countQueries = function () use ($user, $person): int {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($user)->get(personPreviewUrl($person))->assertOk();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    $addChild(1900);
    $addSpouse(1890);
    $oneChildOneSpouse = $countQueries();

    $addChild(1910);
    $addChild(1920);
    $addSpouse(1891);
    $threeChildrenTwoSpouses = $countQueries();

    expect($threeChildrenTwoSpouses)->toBe($oneChildOneSpouse);
});

test('the persons index carries no timeline', function () {
    $user = User::factory()->create();
    Person::factory()->for($user->tree)->create();

    $response = $this->actingAs($user)->get(route('persons.index'));

    $response->assertOk();
    expect($response->viewData('page')['props']['persons'][0])->not->toHaveKey('timeline');
});
