<?php

namespace Database\Seeders;

use App\Actions\Relations\CreateMarriage;
use App\Actions\Relations\CreateParentChildRelation;
use App\Actions\Trees\SetTreeRootPerson;
use App\Enums\EventType;
use App\Enums\ParticipantRole;
use App\Models\Event;
use App\Models\Person;
use App\Models\Scan;
use App\Models\Tree;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Kitchen-sink tree for the family-sheet and person-modal visual gates. Run explicitly:
 * `sail artisan db:seed --class=TreeViewDemoSeeder`. Not part of DatabaseSeeder.
 *
 * Person-modal states (appended after the tree-sheet persons, so their creation order and id offsets
 * stay intact): the proband Jan carries a birth scan and a marriage scan (Anna, 2001); Leon Kowalski
 * has an approximate death and no scan; Nina Rzadka is sparse (name and gender only, one relative:
 * Leon); Wiktor Skanowski has a death scan (unchecking "Osoba nie żyje" must fail).
 */
class TreeViewDemoSeeder extends Seeder
{
    private const DEMO_EMAIL = 'tree-demo@example.com';

    private const EMPTY_EMAIL = 'tree-demo-empty@example.com';

    private Tree $tree;

    public function run(): void
    {
        DB::transaction(function (): void {
            User::query()->whereIn('email', [self::DEMO_EMAIL, self::EMPTY_EMAIL])->delete();

            User::factory()->create([
                'name' => 'Demo drzewa (pusty)',
                'email' => self::EMPTY_EMAIL,
            ]);

            $user = User::factory()->create([
                'name' => 'Demo drzewa',
                'email' => self::DEMO_EMAIL,
            ]);

            $this->tree = $user->tree;
            $this->seedTree();
        });
    }

    private function seedTree(): void
    {
        // Proband with all four grandparents (Dziadkowie row: 4 of 8 cards).
        $jan = $this->person('Jan', 'Kowalski', 'male', 1975);
        $adam = $this->person('Adam', 'Kowalski', 'male', 1948);
        $maria = $this->person('Maria', 'Kowalska', 'female', 1950);
        $jozef = $this->person('Józef', 'Kowalski', 'male', 1921, deceased: true);
        $helena = $this->person('Helena', 'Kowalska', 'female', 1924, deceased: 1999);
        $stanislaw = $this->person('Stanisław', 'Nowak', 'male', 1925, deceased: 1988);
        $zofia = $this->person('Zofia', 'Nowak', 'female', 1927, deceased: 2004);
        $this->parents($adam, [$jozef, $helena]);
        $this->parents($maria, [$stanislaw, $zofia]);
        $this->parents($jan, [$adam, $maria]);

        // First (dated) spouse with all four grandparents (the other 4 of 8 cards).
        $anna = $this->person('Anna', 'Wiśniewska', 'female', 1977);
        $piotr = $this->person('Piotr', 'Wiśniewski', 'male', 1951);
        $krystyna = $this->person('Krystyna', 'Wiśniewska', 'female', 1953);
        $tadeusz = $this->person('Tadeusz', 'Wiśniewski', 'male', 1922, deceased: 1990);
        $irena = $this->person('Irena', 'Wiśniewska', 'female', 1925, deceased: 2010);
        $wladyslaw = $this->person('Władysław', 'Zając', 'male', 1926, deceased: 1995);
        $janina = $this->person('Janina', 'Zając', 'female', 1929, deceased: 2007);
        $this->parents($piotr, [$tadeusz, $irena]);
        $this->parents($krystyna, [$wladyslaw, $janina]);
        $this->parents($anna, [$piotr, $krystyna]);

        // Second spouse, undated marriage; approximate birth year, one known (female) parent.
        $ewa = $this->person('Ewa', 'Zielińska', 'female', 1980, approximate: true);
        $barbara = $this->person('Barbara', 'Zielińska', 'female', 1955);
        $this->parents($ewa, [$barbara]);

        // Co-parent without a marriage; no known parents.
        $marta = $this->person('Marta', 'Lewandowska', 'female', 1982);

        $janAnnaMarriage = $this->marry($jan, $anna, ['year' => 2001, 'month' => 6, 'day' => 15, 'place' => 'Kraków']);
        $this->marry($jan, $ewa, []);

        // Children: one per union, one with a very long name, one with only the proband as parent.
        $kacper = $this->person('Kacper', 'Kowalski', 'male', 2003); // no unions at all
        $zuzanna = $this->person('Zuzanna Anastazja Wielisława', 'Kowalska-Nowakowska-Przybyszewska', 'female', 2006);
        $michal = $this->person('Michał', 'Kowalski', 'male', 2012);
        $oliwia = $this->person('Oliwia', 'Kowalska', 'female', 2009);
        $tomasz = $this->person('Tomasz', 'Kowalski', 'male', 2015);
        $this->parents($kacper, [$jan, $anna]);
        $this->parents($zuzanna, [$jan, $anna]);
        $this->parents($michal, [$jan, $ewa]);
        $this->parents($oliwia, [$jan, $marta]);
        $this->parents($tomasz, [$jan]);

        // Parent of unknown gender without a partner -> "Nieznany rodzic".
        $sasza = $this->person('Sasza', 'Mazur', null, 1990);
        $kuba = $this->person('Kuba', 'Mazur', null, 2016);
        $this->parents($kuba, [$sasza]);

        // Unrelated married couple with 5 children -> children wrap into a second row.
        $nowickiFather = $this->person('Robert', 'Nowicki', 'male', 1970);
        $nowickiMother = $this->person('Agnieszka', 'Nowicka', 'female', 1972);
        $this->marry($nowickiFather, $nowickiMother, ['year' => 1996]);

        foreach ([
            ['Filip', 'male', 1998],
            ['Julia', 'female', 2000],
            ['Antoni', 'male', 2003],
            ['Maja', 'female', 2006],
            ['Franciszek', 'male', 2010],
        ] as [$firstName, $gender, $birthYear]) {
            $this->parents(
                $this->person($firstName, 'Nowicki', $gender, $birthYear),
                [$nowickiFather, $nowickiMother],
            );
        }

        // Married couple with 3 children -> add-child placeholder on its own row.
        $koniecznyFather = $this->person('Marek', 'Konieczny', 'male', 1975);
        $koniecznyMother = $this->person('Ilona', 'Konieczna', 'female', 1977);
        $this->marry($koniecznyFather, $koniecznyMother, ['year' => 2000]);

        foreach ([
            ['Hubert', 'male', 2002],
            ['Lena', 'female', 2005],
            ['Igor', 'male', 2009],
        ] as [$firstName, $gender, $birthYear]) {
            $this->parents(
                $this->person($firstName, 'Konieczny', $gender, $birthYear),
                [$koniecznyFather, $koniecznyMother],
            );
        }

        // Person-modal states, appended last: scans on the proband's birth and marriage, an approximate
        // death without a scan, a sparse person, and a death with a scan.
        $this->attachScan($this->eventOf($jan, EventType::Birth), 'akt-urodzenia-jan.jpg');
        $this->attachScan($janAnnaMarriage, 'akt-slubu-jan-anna.jpg');

        $leon = $this->person('Leon', 'Kowalski', 'male', 1940, deceased: 2015);
        $this->eventOf($leon, EventType::Death)->update(['date_is_approximate' => true]);

        $nina = $this->sparsePerson('Nina', 'Rzadka', 'female');
        $this->parents($nina, [$leon]);

        $wiktor = $this->person('Wiktor', 'Skanowski', 'male', 1935, deceased: 2001);
        $this->attachScan($this->eventOf($wiktor, EventType::Death), 'akt-zgonu-wiktor.jpg');

        (new SetTreeRootPerson)->handle($this->tree, $jan);
    }

    /**
     * A person with only a name and a gender: no birth, no death, no dates.
     */
    private function sparsePerson(string $firstName, string $lastName, ?string $gender): Person
    {
        return Person::factory()->for($this->tree)->create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'gender' => $gender,
            'residence' => null,
        ]);
    }

    private function eventOf(Person $person, EventType $type): Event
    {
        return Event::query()
            ->where('type', $type)
            ->whereHas('participants', fn ($query) => $query->whereKey($person->id))
            ->firstOrFail();
    }

    private function attachScan(Event $event, string $originalName): void
    {
        $scan = Scan::factory()->for($this->tree)->create(['original_name' => $originalName]);
        $event->scans()->attach($scan);
    }

    /**
     * @param  bool|int  $deceased  true = death event without a year, int = death year
     */
    private function person(
        string $firstName,
        string $lastName,
        ?string $gender,
        ?int $birthYear,
        bool $approximate = false,
        bool|int $deceased = false,
    ): Person {
        $person = Person::factory()->for($this->tree)->create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'gender' => $gender,
            'residence' => null,
        ]);

        $birth = Event::factory()->for($this->tree)->birth()->withoutDate()->create([
            'date_year' => $birthYear,
            'date_is_approximate' => $approximate,
            'place' => null,
        ]);
        $birth->participants()->attach($person, ['role' => ParticipantRole::Subject]);

        if ($deceased !== false) {
            $death = Event::factory()->for($this->tree)->death()->withoutDate()->create([
                'date_year' => is_int($deceased) ? $deceased : null,
                'place' => null,
            ]);
            $death->participants()->attach($person, ['role' => ParticipantRole::Subject]);
        }

        return $person;
    }

    /**
     * @param  list<Person>  $parents
     */
    private function parents(Person $child, array $parents): void
    {
        foreach ($parents as $parent) {
            (new CreateParentChildRelation)->handle($this->tree, $parent, $child);
        }
    }

    /**
     * @param  array{year?: int, month?: int, day?: int, place?: string}  $date
     */
    private function marry(Person $person, Person $spouse, array $date): Event
    {
        return (new CreateMarriage)->handle($this->tree, $person, $spouse, $date);
    }
}
