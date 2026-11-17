<?php

namespace App\Support\Import;

/**
 * The note block of a person whose text depends on the author's answers after planning: an event place the plan
 * changes or puts in conflict leaves the note only when the event ends up with that place. The plan shows the block as
 * the default answers give it (a change taken, a conflict settled for the app); the write renders it again with the
 * answers given.
 */
final readonly class PlannedNote
{
    /**
     * @param  array<string, true>  $skipped  repo ids of persons left out
     * @param  array<string, true>  $placed  repo keys of the events that have the repo place after the import with the default answers
     * @param  array<string, array{0: string, 1: bool}>  $waiting  repo key of an event => the key of the place question it waits for and whether it is a conflict (otherwise a change)
     * @param  array<string, true>  $outside  repo keys of the burials the tree does not hold after the import
     */
    public function __construct(
        public ResearchPerson $person,
        public ResearchRepoReading $reading,
        public array $skipped,
        public array $placed,
        public array $waiting,
        public array $outside = [],
    ) {}

    /**
     * The block with the default answers, as the plan shows it.
     */
    public function planned(): ?string
    {
        return RepoValues::note($this->person, $this->reading, $this->skipped, $this->placed, $this->outside);
    }

    /**
     * The block with the author's answers: a rejected place change keeps the repo text, a conflict settled for the repo
     * drops it.
     */
    public function answered(ChangeApprovals $approvals, ConflictResolutions $resolutions): ?string
    {
        $placed = $this->placed;

        foreach ($this->waiting as $eventKey => [$questionKey, $conflict]) {
            if ($conflict ? $resolutions->takesRepo($questionKey) : ! $approvals->rejects($questionKey)) {
                $placed[$eventKey] = true;
            } else {
                unset($placed[$eventKey]);
            }
        }

        return RepoValues::note($this->person, $this->reading, $this->skipped, $placed, $this->outside);
    }
}
