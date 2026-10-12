<?php

namespace Database\Seeders;

use App\Actions\Relations\CreateMarriage;
use App\Actions\Relations\CreateParentChildRelation;
use App\Actions\Trees\SetTreeRootPerson;
use App\Enums\ParticipantRole;
use App\Models\Event;
use App\Models\Person;
use App\Models\Tree;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Six-generation tree for checking the readability of the ancestors view. Run explicitly:
 * `sail artisan db:seed --class=AncestorsDemoSeeder`. Not part of DatabaseSeeder.
 *
 * The proband Jan has ancestors up to the sixth generation, mostly complete. Paths from the proband
 * ('f' father, 'm' mother) name the ancestors: the missing ones leave an unknown placeholder above their
 * child (ffm, mmf, fffm, mmmmf), and mmm has no gender, so mm shows an unknown second parent. The parents
 * are first cousins: their common grandfather is both fff and mff, so he appears twice in the chart. The
 * surnames are long on purpose.
 */
class AncestorsDemoSeeder extends Seeder
{
    private const DEMO_EMAIL = 'ancestors-demo@example.com';

    /** Generations in the chart, the proband included. */
    private const GENERATIONS = 6;

    /** @var list<string> */
    private const MISSING_PATHS = ['ffm', 'mmf', 'fffm', 'mmmmf'];

    /** @var array<string, string> Path of a repeated ancestor mapped to the path of the same person. */
    private const SHARED_PATHS = ['mff' => 'fff'];

    /** @var list<string> */
    private const NO_GENDER_PATHS = ['mmm'];

    /** @var list<string> */
    private const MALE_FIRST_NAMES = ['Józef', 'Stanisław', 'Władysław', 'Tadeusz', 'Franciszek', 'Antoni', 'Wojciech', 'Bolesław'];

    /** @var list<string> */
    private const FEMALE_FIRST_NAMES = ['Helena', 'Zofia', 'Janina', 'Irena', 'Marianna', 'Agnieszka', 'Kunegunda', 'Jadwiga'];

    /** @var list<string> */
    private const SURNAMES = [
        'Kowalski',
        'Przybyszewska-Kaczmarkiewicz',
        'Wszołek-Sobieszczańska',
        'Nowak',
        'Zielińska',
        'Dąbrowski-Szczepańczyk',
        'Ciszewski',
        'Górecka-Majewska',
    ];

    private Tree $tree;

    /**
     * @var array<string, Person>
     */
    private array $ancestors = [];

    public function run(): void
    {
        DB::transaction(function (): void {
            User::query()->where('email', self::DEMO_EMAIL)->delete();

            $user = User::factory()->create([
                'name' => 'Demo przodków',
                'email' => self::DEMO_EMAIL,
            ]);

            $this->tree = $user->tree;
            $this->seedTree();
        });
    }

    private function seedTree(): void
    {
        $jan = $this->person('Jan', 'Dąbrowski-Szczepańczyk', 'male', 1975);
        $this->addParents($jan, '');

        $father = $this->ancestors['f'];
        $mother = $this->ancestors['m'];
        (new CreateMarriage)->handle($this->tree, $father, $mother, ['year' => 1972]);

        (new SetTreeRootPerson)->handle($this->tree, $jan);
    }

    /**
     * Adds the parents of the person at the given path, recursively, up to the last generation.
     */
    private function addParents(Person $child, string $path): void
    {
        if (strlen($path) >= self::GENERATIONS - 1) {
            return;
        }

        foreach (['f', 'm'] as $side) {
            $parentPath = $path.$side;

            if (in_array($parentPath, self::MISSING_PATHS, true)) {
                continue;
            }

            if (isset(self::SHARED_PATHS[$parentPath])) {
                (new CreateParentChildRelation)->handle($this->tree, $this->ancestors[self::SHARED_PATHS[$parentPath]], $child);

                continue;
            }

            $parent = $this->ancestor($parentPath);
            $this->ancestors[$parentPath] = $parent;

            (new CreateParentChildRelation)->handle($this->tree, $parent, $child);
            $this->addParents($parent, $parentPath);
        }
    }

    /**
     * An ancestor at the given path: the gender follows the last step (f = male, m = female), the names
     * are fixed per path, and every ancestor is deceased, born 27 years per generation before the proband.
     */
    private function ancestor(string $path): Person
    {
        $gender = match (true) {
            in_array($path, self::NO_GENDER_PATHS, true) => null,
            str_ends_with($path, 'f') => 'male',
            default => 'female',
        };
        $firstNames = $gender === 'female' ? self::FEMALE_FIRST_NAMES : self::MALE_FIRST_NAMES;
        $birthYear = 1975 - 27 * strlen($path);

        return $this->person(
            $firstNames[crc32($path) % count($firstNames)],
            self::SURNAMES[crc32($path) % count(self::SURNAMES)],
            $gender,
            $birthYear,
            deceased: $birthYear + 70,
        );
    }

    private function person(
        string $firstName,
        string $lastName,
        ?string $gender,
        int $birthYear,
        bool|int $deceased = false,
    ): Person {
        $person = Person::factory()->for($this->tree)->create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'gender' => $gender,
        ]);

        $birth = Event::factory()->for($this->tree)->birth()->withoutDate()->create([
            'date_year' => $birthYear,
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
}
