<?php

namespace App\Actions\Import;

use App\Models\Tree;
use App\Support\Import\TreeClearance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ClearTree
{
    public function __construct(private readonly PlanTreeClearance $plan) {}

    /**
     * Delete every fact, every person and the import memory of people and facts of the tree in one transaction,
     * and return the counts taken in that same transaction before the deletion. Facts go first because events
     * have no foreign key to people. Sources, their scans, the import memory of sources and places stay.
     *
     * The photo directory of the tree is removed after the outermost transaction commits, so a caller that
     * rolls back keeps the files; a directory that cannot be deleted is reported, not thrown, because the
     * rows are already gone.
     */
    public function handle(Tree $tree): TreeClearance
    {
        return DB::transaction(function () use ($tree): TreeClearance {
            $clearance = $this->plan->handle($tree);
            $snapshotIds = $this->plan->clearableSnapshotIds($tree);

            $tree->events()->delete();
            $tree->people()->delete();
            $tree->repoSnapshots()->whereIn('id', $snapshotIds)->delete();

            DB::afterCommit(function () use ($tree): void {
                if (! Storage::disk('local')->deleteDirectory("persons/{$tree->id}")) {
                    report(new RuntimeException("Unable to delete the person photo directory [persons/{$tree->id}]."));
                }
            });

            return $clearance;
        });
    }
}
