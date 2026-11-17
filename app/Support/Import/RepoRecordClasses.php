<?php

namespace App\Support\Import;

use App\Models\Person;
use App\Models\Source;

/**
 * Every key of the repo, the tree and the memory of the last import sorted into the one group it belongs to.
 *
 * A source key is never a member of a person: it is decided on its own. A key of a person that is removed in the repo, deleted in the app or kept deleted is not listed apart: it is a member
 * of the person's entry. A key that names a person whose repo entry was skipped is in no group.
 */
final readonly class RepoRecordClasses
{
    /**
     * @param  array<string, Person>  $knownPersons  repo id => the tree person that holds it
     * @param  list<string>  $newPersonIds  persons the repo has and the tree never held
     * @param  list<RepoRecord>  $deletedInApp  the repo has them, the app deleted them and the author still has to decide
     * @param  list<string>  $keptDeleted  keys the author already chose to leave deleted, with the repo unchanged since
     * @param  list<RepoRecord>  $removedInRepo  records of the tree whose key the repo no longer has
     * @param  list<string>  $goneBoth  keys with a memory that neither the repo nor the tree has any more
     * @param  list<RepoRecord>  $unassessable  records that could be removed in the repo, held back because an entry was skipped without a readable id (a source: an entry or a file of zrodla/)
     * @param  array<string, Source>  $knownSources  repo id => the tree source that holds it
     * @param  list<string>  $newSourceIds  sources the repo has and the tree never held
     */
    public function __construct(
        public array $knownPersons = [],
        public array $newPersonIds = [],
        public array $deletedInApp = [],
        public array $keptDeleted = [],
        public array $removedInRepo = [],
        public array $goneBoth = [],
        public array $unassessable = [],
        public array $knownSources = [],
        public array $newSourceIds = [],
    ) {}
}
