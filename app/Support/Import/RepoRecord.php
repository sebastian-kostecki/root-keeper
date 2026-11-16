<?php

namespace App\Support\Import;

use App\Enums\RepoKeyKind;
use App\Models\RepoSnapshot;
use Illuminate\Database\Eloquent\Model;

/**
 * One key of the repo as it stands against the tree and the memory of the last import.
 */
final readonly class RepoRecord
{
    /**
     * @param  Model|null  $record  the record of the tree with this `repo_id` (a person, an event, a parent-child pair or a source), `null` when it was deleted in the app
     * @param  list<string>  $personIds  the repo ids of the persons the key names
     * @param  RemovalImpact|null  $impact  what a removal takes with it; set for a record removed in the repo, except a birth
     * @param  list<RepoRecord>  $members  the other keys of the same person that are decided together with it
     */
    public function __construct(
        public string $key,
        public RepoKeyKind $kind,
        public ?Model $record,
        public ?RepoSnapshot $snapshot,
        public array $personIds,
        public ?RemovalImpact $impact = null,
        public array $members = [],
    ) {}

    /**
     * A birth is never removed by an import, only detached from the repo, because the app gives every person one.
     */
    public function detachOnly(): bool
    {
        return $this->kind === RepoKeyKind::Birth;
    }
}
