<?php

namespace Database\Seeders;

use App\Actions\Relations\CreateMarriage;
use App\Actions\Relations\CreateParentChildRelation;
use App\Actions\Sources\GenerateSourceThumbnail;
use App\Actions\Trees\SetTreeRootPerson;
use App\Enums\EventType;
use App\Enums\ParticipantRole;
use App\Enums\Religion;
use App\Enums\SourceRecordType;
use App\Models\Event;
use App\Models\Locality;
use App\Models\Parish;
use App\Models\Person;
use App\Models\Residence;
use App\Models\Source;
use App\Models\Tree;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Kitchen-sink tree for the family-sheet and person-modal visual gates. Run explicitly:
 * `sail artisan db:seed --class=TreeViewDemoSeeder`. Not part of DatabaseSeeder.
 *
 * Person-modal states (appended after the tree-sheet persons, so their creation order and id offsets
 * stay intact): the proband Jan carries a birth source and a marriage source (Anna, 2001); Leon Kowalski
 * has an approximate death and no source; Nina Rzadka is sparse (name and gender only, one relative:
 * Leon); Wiktor Skanowski has a death source (unchecking "Osoba nie żyje" must fail).
 *
 * Source states (appended last): one unlinked gravestone and one unlinked other source (short delete
 * dialog, kind badges), and one akt linked to two events, Jan's birth (also confirmed by another akt)
 * and Kacper's birth (only this akt), so the linked delete dialog shows both impact suffixes.
 * Then the description states: a fully described akt (opisany-akt-lipnica-1878.jpg), a gravestone with
 * only name and rok (nagrobek-opisany.jpg) and an akt whose name and pochodzenie truncate on a card
 * (akt-z-dlugim-opisem.jpg). The undescribed sources above are the empty state.
 *
 * Confirmation states (appended after the source states): Genowefa's death is confirmed by a gravestone
 * only (nagrobek-genowefy.jpg); Wiktor's death by his akt plus a gravestone (nagrobek-wiktora.jpg); Jan and
 * Anna's marriage by its akt, then an other source (zaproszenie-slubne.jpg), then a gravestone
 * (nagrobek-jana-i-anny.jpg), linked out of kind order; Helena Kowalska's death by an other source only
 * (nekrolog-heleny.jpg). Leon's death stays without a source.
 *
 * Matching states (appended last, after the confirmation states): three akty metrykalne for the source picker
 * sections. An akt zgonu 1932 uploaded one year back (akt-zgonu-lipnica-1932.jpg), the only match for Leon's
 * death: last in the newest-first list, first once matching akts lead. An odpis aktu urodzenia without a rok
 * (odpis-aktu-urodzenia-jana.jpg), linked to Jan's birth as its third source: a match that is already linked.
 * An akt typed "inny", 1890 (ksiega-ludnosci-lipnica.jpg): typed, but it matches no event. No akt is typed
 * marriage, so the marriage picker is the state where nothing matches.
 *
 * Image states (sources list gallery): eight sources carry a drawn image (paper tone with ruled lines, stone
 * tone for gravestones) and a real thumbnail from GenerateSourceThumbnail: portrait akt-urodzenia-jan.jpg,
 * opisany-akt-lipnica-1878.jpg and akt-zgonu-lipnica-1932.jpg; landscape akt-slubu-jan-anna.jpg,
 * akt-z-dlugim-opisem.jpg and zaproszenie-slubne.jpg; portrait stone nagrobek-opisany.jpg; very tall stone
 * nagrobek-genowefy.jpg. Every other source keeps the fallback (no file, no thumbnail), so each kind has both.
 */
class TreeViewDemoSeeder extends Seeder
{
    private const DEMO_EMAIL = 'tree-demo@example.com';

    private const EMPTY_EMAIL = 'tree-demo-empty@example.com';

    private Tree $tree;

    public function run(): void
    {
        $this->deleteDemoSourceFiles();

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

    /**
     * Remove the stored files of both demo trees; deleting the users with a plain query leaves them on disk.
     */
    private function deleteDemoSourceFiles(): void
    {
        $treeIds = Tree::query()
            ->whereIn('user_id', User::query()->whereIn('email', [self::DEMO_EMAIL, self::EMPTY_EMAIL])->select('id'))
            ->pluck('id');

        foreach ($treeIds as $treeId) {
            Storage::disk('local')->deleteDirectory("sources/{$treeId}");
        }
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

        $janAnnaMarriage = $this->marry($jan, $anna, ['year' => 2001, 'month' => 6, 'day' => 15, 'locality_id' => $this->localityId('Kraków')]);
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

        // Person-modal states, appended last: sources on the proband's birth and marriage, an approximate
        // death without a source, a sparse person, and a death with a source.
        $this->attachSource($this->eventOf($jan, EventType::Birth), 'akt-urodzenia-jan.jpg');
        $this->attachSource($janAnnaMarriage, 'akt-slubu-jan-anna.jpg');

        $leon = $this->person('Leon', 'Kowalski', 'male', 1940, deceased: 2015);
        $this->eventOf($leon, EventType::Death)->update(['date_is_approximate' => true]);

        $nina = $this->sparsePerson('Nina', 'Rzadka', 'female');
        $this->parents($nina, [$leon]);

        $wiktor = $this->person('Wiktor', 'Skanowski', 'male', 1935, deceased: 2001);
        $this->attachSource($this->eventOf($wiktor, EventType::Death), 'akt-zgonu-wiktor.jpg');

        $this->fullDataPerson();
        $this->nameOnlyPerson();
        $this->sourceStates($jan, $kacper);
        $this->confirmationStates($wiktor, $helena, $janAnnaMarriage);
        $this->matchingStates($jan);
        $this->imageStates();
        $this->largeFamilies();

        (new SetTreeRootPerson)->handle($this->tree, $jan);
    }

    /**
     * Tree-fits-screen gate: married couples with 8 children (children row as wide as the grandparent row),
     * 10 children (wider row), 4 and 6 children (first sizes with the add-child slot under the row) and 0 children
     * (married, empty children row). Appended
     * after every other person, in this order, so the id offsets from the proband stay intact.
     */
    private function largeFamilies(): void
    {
        $families = [
            ['Dariusz', 'Ósmy', 'Beata', 'Ósma', 8],
            ['Zbigniew', 'Dziesiąty', 'Halina', 'Dziesiąta', 10],
            ['Czesław', 'Czwarty', 'Irena', 'Czwarta', 4],
            ['Szymon', 'Szósty', 'Teresa', 'Szósta', 6],
            ['Leszek', 'Zerowy', 'Olga', 'Zerowa', 0],
        ];
        $childNames = [
            ['Adam', 'male'], ['Basia', 'female'], ['Cezary', 'male'], ['Dorota', 'female'], ['Emil', 'male'],
            ['Franciszka', 'female'], ['Grzegorz', 'male'], ['Hanna', 'female'], ['Ignacy', 'male'], ['Judyta', 'female'],
        ];

        foreach ($families as [$fatherName, $fatherSurname, $motherName, $motherSurname, $count]) {
            $father = $this->person($fatherName, $fatherSurname, 'male', 1965);
            $mother = $this->person($motherName, $motherSurname, 'female', 1967);
            $this->marry($father, $mother, ['year' => 1990]);

            foreach (array_slice($childNames, 0, $count) as $index => [$firstName, $gender]) {
                $this->parents(
                    $this->person($firstName, $fatherSurname, $gender, 1991 + $index * 2),
                    [$father, $mother],
                );
            }
        }
    }

    /**
     * Persons-list state: day-precise birth with place and a linked source, death with place, residence, religion.
     */
    private function fullDataPerson(): void
    {
        $person = Person::factory()->for($this->tree)->create([
            'first_name' => 'Genowefa',
            'last_name' => 'Pełnodanowska',
            'gender' => 'female',
            'religion' => Religion::RomanCatholic,
        ]);

        Residence::factory()->for($person)->create([
            'tree_id' => $this->tree->id,
            'locality_id' => $this->localityId('Nowy Targ'),
            'year_from' => 1920,
            'year_to' => 1935,
        ]);
        Residence::factory()->for($person)->create([
            'tree_id' => $this->tree->id,
            'locality_id' => $this->localityId('Zakopane'),
        ]);

        $birth = Event::factory()->for($this->tree)->birth()->create([
            'date_year' => 1932,
            'date_month' => 3,
            'date_day' => 14,
            'date_is_approximate' => false,
            'locality_id' => $this->localityId('Nowy Targ'),
        ]);
        $birth->participants()->attach($person, ['role' => ParticipantRole::Subject]);
        $this->attachSource($birth, 'akt-urodzenia-genowefa.jpg');

        $death = Event::factory()->for($this->tree)->death()->create([
            'date_year' => 2011,
            'date_month' => 11,
            'date_day' => 2,
            'date_is_approximate' => false,
            'locality_id' => $this->localityId('Kraków'),
        ]);
        $death->participants()->attach($person, ['role' => ParticipantRole::Subject]);

        $burial = Event::factory()->for($this->tree)->burial()->create([
            'locality_id' => $this->localityId('Nowy Targ'),
        ]);
        $burial->participants()->attach($person, ['role' => ParticipantRole::Subject]);
    }

    /**
     * Persons-list state: no surname, no dates, no events.
     */
    private function nameOnlyPerson(): void
    {
        Person::factory()->for($this->tree)->create([
            'first_name' => 'Bezimienna',
            'last_name' => null,
            'gender' => null,
            'religion' => null,
        ]);
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
        ]);
    }

    private function eventOf(Person $person, EventType $type): Event
    {
        return Event::query()
            ->where('type', $type)
            ->whereHas('participants', fn ($query) => $query->whereKey($person->id))
            ->firstOrFail();
    }

    /**
     * Source-library states: unlinked gravestone and other sources, and one akt confirming two events.
     */
    private function sourceStates(Person $jan, Person $kacper): void
    {
        $parish = Parish::factory()
            ->for($this->tree)
            ->withReligion(Religion::RomanCatholic)
            ->create([
                'name' => 'Parafia rzymskokatolicka w Tarnowie',
                'locality_id' => Locality::factory()->for($this->tree)->create(['name' => 'Tarnów'])->id,
            ]);

        Source::factory()->for($this->tree)->gravestone()->create(['original_name' => 'nagrobek-kowalskich.jpg']);
        Source::factory()->for($this->tree)->other()->create(['original_name' => 'list-rodzinny.jpg']);

        $shared = Source::factory()->for($this->tree)->create(['original_name' => 'akt-wspolny-jan-kacper.jpg']);
        $this->eventOf($jan, EventType::Birth)->sources()->attach($shared);
        $this->eventOf($kacper, EventType::Birth)->sources()->attach($shared);

        Source::factory()->for($this->tree)->described()->create([
            'original_name' => 'opisany-akt-lipnica-1878.jpg',
            'archive' => 'Archiwum Akt Dawnych w Warszawie',
            'parish_id' => $parish->id,
            'note' => "Wpis po prawej stronie rozkładówki, zapisany po łacinie.\n\nNazwisko matki odczytane z trudem, do sprawdzenia w księdze zgonów.",
            'url' => 'https://www.szukajwarchiwach.gov.pl/',
        ]);

        Source::factory()->for($this->tree)->gravestone()->create([
            'original_name' => 'nagrobek-opisany.jpg',
            'name' => 'Nagrobek rodziny Kowalskich, cmentarz w Lipnicy',
            'year' => 1932,
        ]);

        Source::factory()->for($this->tree)->create([
            'original_name' => 'akt-z-dlugim-opisem.jpg',
            'name' => 'Akt urodzenia z parafii Świętego Jana Chrzciciela w Lipnicy Murowanej, księga urodzeń z lat 1875-1885',
            'archive' => 'Archiwum Diecezjalne w Tarnowie, zespół akt parafialnych, sygnatura 1234/56/78',
            'parish_id' => $parish->id,
        ]);
    }

    /**
     * Facts confirmed by a non-akt source, and by several kinds linked out of kind order.
     */
    private function confirmationStates(Person $wiktor, Person $helena, Event $janAnnaMarriage): void
    {
        $genowefa = Person::query()->where('tree_id', $this->tree->id)->where('first_name', 'Genowefa')->firstOrFail();

        $genowefaGravestone = Source::factory()->for($this->tree)->gravestone()->create([
            'original_name' => 'nagrobek-genowefy.jpg',
            'name' => 'Nagrobek Genowefy Pełnodanowskiej, Zakopane',
        ]);
        $this->eventOf($genowefa, EventType::Death)->sources()->attach($genowefaGravestone);

        $wiktorGravestone = Source::factory()->for($this->tree)->gravestone()->create(['original_name' => 'nagrobek-wiktora.jpg']);
        $this->eventOf($wiktor, EventType::Death)->sources()->attach($wiktorGravestone);

        $invitation = Source::factory()->for($this->tree)->other()->create(['original_name' => 'zaproszenie-slubne.jpg']);
        $couplesGravestone = Source::factory()->for($this->tree)->gravestone()->create(['original_name' => 'nagrobek-jana-i-anny.jpg']);
        $janAnnaMarriage->sources()->attach($invitation);
        $janAnnaMarriage->sources()->attach($couplesGravestone);

        $obituary = Source::factory()->for($this->tree)->other()->create(['original_name' => 'nekrolog-heleny.jpg']);
        $this->eventOf($helena, EventType::Death)->sources()->attach($obituary);
    }

    /**
     * Source-picker states: a matching akt far down the list, a matching akt already linked, a typed akt that matches nothing.
     */
    private function matchingStates(Person $jan): void
    {
        $oneYearAgo = now()->subYear();

        Source::factory()->for($this->tree)->create([
            'original_name' => 'akt-zgonu-lipnica-1932.jpg',
            'name' => 'Zgony Lipnica 1932 nr 14',
            'record_type' => SourceRecordType::Death,
            'year' => 1932,
            'created_at' => $oneYearAgo,
            'updated_at' => $oneYearAgo,
        ]);

        $birthCopy = Source::factory()->for($this->tree)->create([
            'original_name' => 'odpis-aktu-urodzenia-jana.jpg',
            'name' => 'Odpis aktu urodzenia Jana Kowalskiego',
            'record_type' => SourceRecordType::Birth,
        ]);
        $this->eventOf($jan, EventType::Birth)->sources()->attach($birthCopy);

        Source::factory()->for($this->tree)->create([
            'original_name' => 'ksiega-ludnosci-lipnica.jpg',
            'name' => 'Księga ludności Lipnicy',
            'record_type' => SourceRecordType::Other,
            'year' => 1890,
        ]);
    }

    /**
     * Gallery states: draw an image and a real thumbnail for eight existing sources, found by original name.
     */
    private function imageStates(): void
    {
        $images = [
            'akt-urodzenia-jan.jpg' => [600, 850, false],
            'opisany-akt-lipnica-1878.jpg' => [640, 900, false],
            'akt-zgonu-lipnica-1932.jpg' => [620, 880, false],
            'akt-slubu-jan-anna.jpg' => [900, 620, false],
            'akt-z-dlugim-opisem.jpg' => [880, 600, false],
            'zaproszenie-slubne.jpg' => [920, 640, false],
            'nagrobek-opisany.jpg' => [640, 880, true],
            'nagrobek-genowefy.jpg' => [420, 1400, true],
        ];

        $disk = Storage::disk('local');
        $generateThumbnail = new GenerateSourceThumbnail;

        foreach ($images as $originalName => [$width, $height, $isStone]) {
            $source = Source::query()
                ->where('tree_id', $this->tree->id)
                ->where('original_name', $originalName)
                ->firstOrFail();

            $uuid = (string) Str::uuid();
            $path = "sources/{$this->tree->id}/{$uuid}.jpg";
            $thumbnailPath = "sources/{$this->tree->id}/thumbnails/{$uuid}.jpg";
            $content = $this->drawImage($width, $height, $isStone);

            $disk->put($path, $content);
            $thumbnailContent = $generateThumbnail->handle($disk->path($path), 'image/jpeg');

            if ($thumbnailContent !== null) {
                $disk->put($thumbnailPath, $thumbnailContent);
            }

            $source->update([
                'path' => $path,
                'thumbnail_path' => $thumbnailContent !== null ? $thumbnailPath : null,
                'width' => $width,
                'height' => $height,
                'size' => strlen($content),
            ]);
        }
    }

    /**
     * Draw a paper (or stone) toned JPEG with ruled lines.
     */
    private function drawImage(int $width, int $height, bool $isStone): string
    {
        $width = max(1, $width);
        $height = max(1, $height);
        $image = imagecreatetruecolor($width, $height);

        if ($image === false) {
            throw new RuntimeException('GD could not create the demo image.');
        }

        [$background, $line] = $isStone
            ? [[142, 145, 148], [104, 107, 110]]
            : [[238, 228, 204], [176, 160, 128]];

        $backgroundColor = imagecolorallocate($image, ...$background);
        $lineColor = imagecolorallocate($image, ...$line);

        if ($backgroundColor === false || $lineColor === false) {
            throw new RuntimeException('GD could not allocate the demo image colours.');
        }

        imagefilledrectangle($image, 0, 0, $width, $height, $backgroundColor);

        for ($y = (int) ($height * 0.08); $y < $height * 0.94; $y += max(18, (int) ($height / 30))) {
            imageline($image, (int) ($width * 0.08), $y, (int) ($width * 0.92), $y, $lineColor);
        }

        imagerectangle($image, 6, 6, $width - 7, $height - 7, $lineColor);

        ob_start();
        imagejpeg($image, null, 85);

        return (string) ob_get_clean();
    }

    private function attachSource(Event $event, string $originalName): void
    {
        $source = Source::factory()->for($this->tree)->create(['original_name' => $originalName]);
        $event->sources()->attach($source);
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
        ]);

        $birth = Event::factory()->for($this->tree)->birth()->withoutDate()->create([
            'date_year' => $birthYear,
            'date_is_approximate' => $approximate,
        ]);
        $birth->participants()->attach($person, ['role' => ParticipantRole::Subject]);

        if ($deceased !== false) {
            $death = Event::factory()->for($this->tree)->death()->withoutDate()->create([
                'date_year' => is_int($deceased) ? $deceased : null,
            ]);
            $death->participants()->attach($person, ['role' => ParticipantRole::Subject]);
        }

        return $person;
    }

    private function localityId(string $name): int
    {
        return Locality::query()->firstOrCreate(['tree_id' => $this->tree->id, 'name' => $name])->id;
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
     * @param  array{year?: int, month?: int, day?: int, locality_id?: int}  $date
     */
    private function marry(Person $person, Person $spouse, array $date): Event
    {
        return (new CreateMarriage)->handle($this->tree, $person, $spouse, $date);
    }
}
