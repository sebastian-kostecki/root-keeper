<?php

namespace App\Actions\Trees;

use App\Actions\Persons\CreatePerson;
use App\Models\Person;
use App\Models\Tree;
use Illuminate\Support\Facades\DB;

class CreateProband
{
    public function __construct(
        private CreatePerson $createPerson,
        private SetTreeRootPerson $setTreeRootPerson,
    ) {}

    /**
     * Create a person in the given tree and make them its proband, in one transaction.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Tree $tree, array $data): Person
    {
        return DB::transaction(function () use ($tree, $data): Person {
            $person = $this->createPerson->handle($tree, $data);

            $this->setTreeRootPerson->handle($tree, $person);

            return $person;
        });
    }
}
