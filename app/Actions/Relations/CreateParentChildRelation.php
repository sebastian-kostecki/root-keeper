<?php

namespace App\Actions\Relations;

use App\Models\ParentChildRelation;
use App\Models\Person;
use App\Models\Tree;
use App\Support\ParentChildGraph;
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

            Person::whereKey($first->id)->lockForUpdate()->first();
            Person::whereKey($second->id)->lockForUpdate()->first();

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

            return $tree->parentChildRelations()->create([
                'parent_id' => $parent->id,
                'child_id' => $child->id,
            ]);
        });
    }
}
