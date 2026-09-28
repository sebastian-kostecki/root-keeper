<?php

namespace App\Actions\Relations;

use App\Actions\Persons\CreatePerson;
use App\Models\Person;
use App\Models\Tree;
use Illuminate\Support\Facades\DB;

class CreateChildForParents
{
    public function __construct(
        private CreatePerson $createPerson,
        private AttachChildToParents $attachChildToParents,
    ) {}

    /**
     * Create a new person and link them as the child of every given parent; a
     * rule failure on any link rolls the new person back.
     *
     * @param  list<Person>  $parents
     * @param  array<string, mixed>  $data  Validated person input, as accepted by CreatePerson.
     */
    public function handle(Tree $tree, array $parents, array $data): Person
    {
        return DB::transaction(function () use ($tree, $parents, $data): Person {
            $child = $this->createPerson->handle($tree, $data);

            $this->attachChildToParents->handle($tree, $child, $parents);

            return $child;
        });
    }
}
