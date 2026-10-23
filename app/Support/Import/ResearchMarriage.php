<?php

namespace App\Support\Import;

final readonly class ResearchMarriage
{
    /**
     * @param  array{0: string, 1: string}  $personIds  sorted pair of repo ids
     * @param  list<string>  $files  files that record this marriage
     */
    public function __construct(
        public array $personIds,
        public ResearchEvent $event,
        public array $files,
    ) {}

    /**
     * @return array{0: string, 1: string}
     */
    public static function pair(string $first, string $second): array
    {
        return $first <= $second ? [$first, $second] : [$second, $first];
    }
}
