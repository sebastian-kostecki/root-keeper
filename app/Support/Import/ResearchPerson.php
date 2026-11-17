<?php

namespace App\Support\Import;

use App\Enums\Gender;

final readonly class ResearchPerson
{
    /**
     * @param  list<int>  $ahnentafel
     * @param  list<string>  $surnameVariants
     * @param  list<ResearchMarriage>  $marriages  entries written in this person's own file
     */
    public function __construct(
        public string $id,
        public string $file,
        public array $ahnentafel,
        public ?string $givenNames,
        public ?string $surname,
        public array $surnameVariants,
        public ?Gender $gender,
        public ?string $fatherId,
        public ?string $motherId,
        public ?ResearchEvent $birth,
        public ?ResearchEvent $death,
        public ?ResearchEvent $burial,
        public array $marriages,
    ) {}
}
