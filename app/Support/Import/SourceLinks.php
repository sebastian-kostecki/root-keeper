<?php

namespace App\Support\Import;

use App\Actions\Import\PlanImport;

/**
 * The links of one event to sources of the repo, as one value of the event: which sources confirm it and under which
 * entry number. Compared as its canonical text, `ZR-0012=15/1837;ZR-0013=` (sorted by id, an empty entry after the `=`),
 * which is also what the memory of an import keeps in the field `zrodla`.
 */
final readonly class SourceLinks
{
    /**
     * @param  list<ResearchSourceLink>  $links
     */
    public function __construct(public array $links = []) {}

    /**
     * The canonical text of the links, `null` for none.
     */
    public function text(): ?string
    {
        if ($this->links === []) {
            return null;
        }

        $parts = [];

        foreach ($this->links as $link) {
            $parts[$link->id] = $link->id.'='.(PlanImport::cleanText($link->entry) ?? '');
        }

        ksort($parts);

        return implode(';', $parts);
    }

    /**
     * The links as shown to the author, e.g. "ZR-0012 (wpis 15/1837), ZR-0013", `null` for none.
     */
    public function label(): ?string
    {
        if ($this->links === []) {
            return null;
        }

        $parts = [];

        foreach ($this->links as $link) {
            $entry = PlanImport::cleanText($link->entry);
            $parts[$link->id] = $entry === null ? $link->id : "{$link->id} (wpis {$entry})";
        }

        ksort($parts);

        return implode(', ', $parts);
    }

    /**
     * The repo ids the canonical text of an earlier import names.
     *
     * @return list<string>
     */
    public static function idsIn(?string $text): array
    {
        preg_match_all('/(?:^|;)(ZR-\d{4,})=/', (string) $text, $matches);

        return array_values(array_unique($matches[1]));
    }
}
