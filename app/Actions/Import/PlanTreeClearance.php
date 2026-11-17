<?php

namespace App\Actions\Import;

use App\Enums\RepoKeyKind;
use App\Models\RepoSnapshot;
use App\Models\Tree;
use App\Support\Import\RepoKeys;
use App\Support\Import\TreeClearance;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PlanTreeClearance
{
    /**
     * Count what `ClearTree` removes from the tree, without writing anything. Every count goes through the
     * tree: rows that hang off people or events are found through a subquery on the tree's own records.
     */
    public function handle(Tree $tree): TreeClearance
    {
        $people = $tree->people()->select('id');
        $events = $tree->events()->select('id');

        return new TreeClearance(
            persons: $tree->people()->count(),
            events: $tree->events()->count(),
            relations: $tree->parentChildRelations()->count(),
            residences: DB::table('residences')->whereIn('person_id', $people)->count(),
            photos: $tree->people()->whereNotNull('photo_path')->count(),
            sourceLinks: DB::table('event_source')->whereIn('event_id', $events)->count(),
            buriedLinks: DB::table('buried_people')->whereIn('person_id', $people)->count(),
            snapshots: $this->clearableSnapshotIds($tree)->count(),
        );
    }

    /**
     * The ids of the tree's repo snapshots of people and facts; those of sources are kept.
     *
     * @return Collection<int, int>
     */
    public function clearableSnapshotIds(Tree $tree): Collection
    {
        return $tree->repoSnapshots()
            ->get(['id', 'repo_id'])
            ->filter(fn (RepoSnapshot $snapshot): bool => RepoKeys::kindOf($snapshot->repo_id) !== RepoKeyKind::Source)
            ->pluck('id');
    }
}
