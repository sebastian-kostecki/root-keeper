<?php

namespace App\Support\Import;

/**
 * What disappears from the tree when an import removes a record that the repo no longer has.
 *
 * Counted the way `DeletePerson` and the plain `->delete()` of the other records remove them: for a person every
 * marriage and every event with that person as the only participant goes with it.
 */
final readonly class RemovalImpact
{
    /**
     * @param  bool  $photo  the person has a photo file
     * @param  int  $sourceLinks  links between the removed events and sources; for a removed source its links to events
     * @param  bool  $burial  a burial event is removed
     * @param  int  $otherEvents  events of the person with no other participant that are removed, the burial excluded
     * @param  int  $residences  residences of the person
     * @param  int  $relations  parent-child pairs of the person, also the ones added by hand
     * @param  int  $marriages  marriages of the person, also the ones added by hand
     * @param  int  $gravestones  gravestones the person is listed on
     * @param  bool  $proband  the person is the proband of the tree
     * @param  bool  $scan  the removed source takes its scan file with it
     */
    public function __construct(
        public bool $photo = false,
        public int $sourceLinks = 0,
        public bool $burial = false,
        public int $otherEvents = 0,
        public int $residences = 0,
        public int $relations = 0,
        public int $marriages = 0,
        public int $gravestones = 0,
        public bool $proband = false,
        public bool $scan = false,
    ) {}

    public function isEmpty(): bool
    {
        return ! $this->photo
            && ! $this->burial
            && ! $this->proband
            && ! $this->scan
            && $this->sourceLinks === 0
            && $this->otherEvents === 0
            && $this->residences === 0
            && $this->relations === 0
            && $this->marriages === 0
            && $this->gravestones === 0;
    }
}
