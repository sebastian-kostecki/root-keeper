<?php

namespace App\Actions\Relations;

use App\Models\Person;
use App\Models\Tree;
use App\Support\ParentChildGraph;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttachChildToParents
{
    public function __construct(private CreateParentChildRelation $createParentChildRelation) {}

    /**
     * Make an existing person the child of every given parent, linking only
     * the parents they do not have yet. A rule failure on any link rolls all
     * of them back.
     *
     * @param  list<Person>  $parents
     */
    public function handle(Tree $tree, Person $child, array $parents): void
    {
        DB::transaction(function () use ($tree, $child, $parents): void {
            $graph = ParentChildGraph::forTree($tree);

            $missing = array_values(array_filter(
                $parents,
                fn (Person $parent): bool => ! $graph->hasRelation($parent->id, $child->id),
            ));

            if ($missing === []) {
                throw ValidationException::withMessages(['relation' => 'Ta relacja już istnieje.']);
            }

            foreach ($missing as $parent) {
                $this->createParentChildRelation->handle($tree, $parent, $child);
            }
        });
    }
}
