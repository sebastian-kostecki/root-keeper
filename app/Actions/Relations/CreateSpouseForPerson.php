<?php

namespace App\Actions\Relations;

use App\Actions\Persons\CreatePerson;
use App\Models\Person;
use App\Models\Tree;
use Illuminate\Support\Facades\DB;

class CreateSpouseForPerson
{
    public function __construct(
        private CreatePerson $createPerson,
        private CreateMarriage $createMarriage,
    ) {}

    /**
     * Create a new person and marry them to the given person, with no date and
     * no place; a rule failure on the marriage rolls the new person back.
     *
     * @param  array<string, mixed>  $data  Validated person input, as accepted by CreatePerson.
     */
    public function handle(Tree $tree, Person $person, array $data): Person
    {
        return DB::transaction(function () use ($tree, $person, $data): Person {
            $spouse = $this->createPerson->handle($tree, $data);

            $this->createMarriage->handle($tree, $person, $spouse, []);

            return $spouse;
        });
    }
}
