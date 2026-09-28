<?php

namespace App\Actions\Relations;

use App\Actions\Persons\CreatePerson;
use App\Models\Person;
use App\Models\Tree;
use Illuminate\Support\Facades\DB;

class CreateParentForPerson
{
    public function __construct(
        private CreatePerson $createPerson,
        private CreateParentChildRelation $createParentChildRelation,
    ) {}

    /**
     * Create a new person and link them as a parent of the given child; a
     * rule failure on the relation rolls the new person back.
     *
     * @param  array<string, mixed>  $data  Validated person input, as accepted by CreatePerson.
     */
    public function handle(Tree $tree, Person $child, array $data): Person
    {
        return DB::transaction(function () use ($tree, $child, $data): Person {
            $parent = $this->createPerson->handle($tree, $data);

            $this->createParentChildRelation->handle($tree, $parent, $child);

            return $parent;
        });
    }
}
