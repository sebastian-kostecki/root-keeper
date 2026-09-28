<?php

namespace App\Actions\Trees;

use App\Models\Person;
use App\Models\Tree;

class ListPersonCandidates
{
    /**
     * Every person of the tree, in the standard person order, for the tree's
     * parent and child dialogs; the client narrows them by the rules of the
     * slot or pair.
     *
     * @return list<array{id: int, display_name: string, birth_date: string|null, gender: 'male'|'female'|null}>
     */
    public function handle(Tree $tree): array
    {
        $people = $tree->people()
            ->with('events')
            ->orderByRaw('last_name is null')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->get();

        return array_values($people->map(fn (Person $person): array => [
            'id' => $person->id,
            'display_name' => $person->displayName(),
            'birth_date' => $person->birth()?->date->format(),
            'gender' => $person->gender?->value,
        ])->all());
    }
}
