<?php

namespace App\Support\Import;

use App\Enums\DateQualifier;
use App\Models\Event;

/**
 * The years a date may stand for. An exact or approximate date is one year, a range spans its years,
 * "before" has no lower bound and "after" no upper bound.
 */
final readonly class YearSpan
{
    /**
     * Two single years that far apart (or fewer) still agree.
     */
    private const TOLERANCE = 2;

    private function __construct(
        public ?int $from,
        public ?int $to,
        public bool $single,
    ) {}

    public static function fromDate(?ResearchDate $date): ?self
    {
        return $date === null ? null : self::fromColumns($date->qualifier, $date->year, $date->untilYear);
    }

    public static function fromEvent(?Event $event): ?self
    {
        return $event === null ? null : self::fromColumns($event->date_qualifier, $event->date_year, $event->date_until_year);
    }

    public static function fromColumns(?DateQualifier $qualifier, ?int $year, ?int $untilYear): ?self
    {
        if ($year === null) {
            return null;
        }

        return match ($qualifier) {
            DateQualifier::Before => new self(null, $year, false),
            DateQualifier::After => new self($year, null, false),
            DateQualifier::Range => $untilYear === null ? new self($year, $year, true) : new self($year, $untilYear, false),
            default => new self($year, $year, true),
        };
    }

    public function isBounded(): bool
    {
        return $this->from !== null && $this->to !== null;
    }

    /**
     * Both spans are bounded and no further apart than a typo in a register: two years when one of them is a single year,
     * overlapping when both are ranges.
     */
    public function agreesWith(self $other): bool
    {
        return $this->isBounded() && $other->isBounded() && $this->gap($other) <= $this->tolerance($other);
    }

    /**
     * The spans are too far apart for one date to be the other. An open span ("before", "after") contradicts
     * a span it does not touch, but never agrees with one.
     */
    public function contradicts(self $other): bool
    {
        if (! $this->isBounded() || ! $other->isBounded()) {
            return $this->gap($other) > 0;
        }

        return $this->gap($other) > $this->tolerance($other);
    }

    /**
     * The distance in years between the spans, 0 when they overlap.
     */
    private function gap(self $other): int
    {
        if ($this->to !== null && $other->from !== null && $this->to < $other->from) {
            return $other->from - $this->to;
        }

        if ($other->to !== null && $this->from !== null && $other->to < $this->from) {
            return $this->from - $other->to;
        }

        return 0;
    }

    private function tolerance(self $other): int
    {
        return $this->single || $other->single ? self::TOLERANCE : 0;
    }
}
