<?php

namespace App\Support;

use App\Enums\DateQualifier;

final readonly class PartialDate
{
    public function __construct(
        public ?int $year,
        public ?int $month,
        public ?int $day,
        public DateQualifier $qualifier = DateQualifier::Exact,
        public ?int $untilYear = null,
    ) {}

    /**
     * Before, after and range dates are too loose to compute an age from.
     */
    public function isFuzzy(): bool
    {
        return in_array($this->qualifier, [DateQualifier::Before, DateQualifier::After, DateQualifier::Range], true);
    }

    public function format(): ?string
    {
        if ($this->year === null) {
            return null;
        }

        if ($this->qualifier === DateQualifier::Range && $this->untilYear !== null) {
            return sprintf('%04d–%04d', $this->year, $this->untilYear);
        }

        return $this->withQualifier($this->formatWithoutQualifier());
    }

    public function formatYear(): ?string
    {
        if ($this->year === null) {
            return null;
        }

        if ($this->qualifier === DateQualifier::Range && $this->untilYear !== null) {
            return sprintf('%04d–%04d', $this->year, $this->untilYear);
        }

        return $this->withQualifier(sprintf('%04d', $this->year));
    }

    /**
     * The date at its own precision without the qualifier word, for places where the qualifier is shown separately.
     */
    public function formatWithoutQualifier(): ?string
    {
        if ($this->year === null) {
            return null;
        }

        if ($this->month !== null && $this->day !== null) {
            return sprintf('%02d.%02d.%04d', $this->day, $this->month, $this->year);
        }

        if ($this->month !== null) {
            return sprintf('%02d.%04d', $this->month, $this->year);
        }

        return sprintf('%04d', $this->year);
    }

    private function withQualifier(?string $formatted): ?string
    {
        return match ($this->qualifier) {
            DateQualifier::Approximate => "ok. {$formatted}",
            DateQualifier::Before => "przed {$formatted}",
            DateQualifier::After => "po {$formatted}",
            DateQualifier::Exact, DateQualifier::Range => $formatted,
        };
    }
}
