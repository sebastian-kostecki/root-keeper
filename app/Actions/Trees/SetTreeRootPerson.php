<?php

namespace App\Actions\Trees;

use App\Models\Person;
use App\Models\Tree;

class SetTreeRootPerson
{
    /**
     * Point the tree at the given person as its proband.
     */
    public function handle(Tree $tree, Person $person): void
    {
        $tree->update(['root_person_id' => $person->id]);
    }
}
