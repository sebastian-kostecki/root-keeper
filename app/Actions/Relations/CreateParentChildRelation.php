<?php

namespace App\Actions\Relations;

use App\Enums\Gender;
use App\Models\ParentChildRelation;
use App\Models\Person;
use App\Models\Tree;
use App\Support\ParentChildGraph;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateParentChildRelation
{
    /**
     * Link a parent to a child within the given tree, after checking every
     * structural rule that would otherwise corrupt the tree.
     */
    public function handle(Tree $tree, Person $parent, Person $child): ParentChildRelation
    {
        return DB::transaction(function () use ($tree, $parent, $child): ParentChildRelation {
            [$first, $second] = $parent->id <= $child->id ? [$parent, $child] : [$child, $parent];

            Person::whereKey($first->id)->lockForUpdate()->firstOrFail();
            Person::whereKey($second->id)->lockForUpdate()->firstOrFail();

            $graph = ParentChildGraph::forTree($tree);

            if ($graph->hasRelation($parent->id, $child->id)) {
                throw ValidationException::withMessages(['relation' => 'Ta relacja już istnieje.']);
            }

            if (count($graph->parentsOf($child->id)) >= 2) {
                throw ValidationException::withMessages(['relation' => 'Ta osoba ma już dwoje rodziców.']);
            }

            if (in_array($parent->id, $graph->descendantsOf($child->id), true)) {
                throw ValidationException::withMessages(['relation' => 'Ta relacja utworzyłaby cykl w drzewie.']);
            }

            $this->guardSameSexParent($tree, $parent, $graph->parentsOf($child->id));

            try {
                return $tree->parentChildRelations()->create([
                    'parent_id' => $parent->id,
                    'child_id' => $child->id,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['relation' => 'Ta relacja już istnieje.']);
            }
        });
    }

    /**
     * A child cannot get a second parent of the same known sex; an unknown
     * sex on either side never conflicts. Genders are read fresh from the
     * database, the graph holds ids only.
     *
     * @param  list<int>  $currentParentIds
     */
    private function guardSameSexParent(Tree $tree, Person $parent, array $currentParentIds): void
    {
        $gender = $tree->people()->whereKey($parent->id)->firstOrFail()->gender;

        if ($gender === null || $currentParentIds === []) {
            return;
        }

        $hasSameSexParent = $tree->people()
            ->whereIn('id', $currentParentIds)
            ->where('gender', $gender)
            ->exists();

        if ($hasSameSexParent) {
            throw ValidationException::withMessages([
                'relation' => $gender === Gender::Male ? 'Ta osoba ma już ojca.' : 'Ta osoba ma już matkę.',
            ]);
        }
    }
}
