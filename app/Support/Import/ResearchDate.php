<?php

namespace App\Support\Import;

use App\Enums\DateQualifier;

final readonly class ResearchDate
{
    private const FIRST_YEAR = 1000;

    public function __construct(
        public DateQualifier $qualifier,
        public int $year,
        public ?int $month = null,
        public ?int $day = null,
        public ?int $untilYear = null,
    ) {}

    /**
     * Parse "RRRR[-MM[-DD]]", "ok. …", "przed …", "po …" or "między RRRR a RRRR".
     */
    public static function parse(string $value): ?self
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));

        if (preg_match('/^między (\d{4}) a (\d{4})$/u', $value, $range) === 1) {
            $from = (int) $range[1];
            $until = (int) $range[2];

            if (! self::isYearInRange($from) || ! self::isYearInRange($until) || $from >= $until) {
                return null;
            }

            return new self(DateQualifier::Range, $from, untilYear: $until);
        }

        $qualifier = DateQualifier::Exact;

        foreach (['ok. ' => DateQualifier::Approximate, 'przed ' => DateQualifier::Before, 'po ' => DateQualifier::After] as $prefix => $prefixed) {
            if (str_starts_with($value, $prefix)) {
                $qualifier = $prefixed;
                $value = substr($value, strlen($prefix));

                break;
            }
        }

        if (preg_match('/^(\d{4})(?:-(\d{2})(?:-(\d{2}))?)?$/', $value, $parts) !== 1) {
            return null;
        }

        $year = (int) $parts[1];
        $month = isset($parts[2]) ? (int) $parts[2] : null;
        $day = isset($parts[3]) ? (int) $parts[3] : null;

        if (! self::isYearInRange($year)) {
            return null;
        }

        if ($month !== null && ($month < 1 || $month > 12)) {
            return null;
        }

        if ($day !== null && ! checkdate((int) $month, $day, $year)) {
            return null;
        }

        return new self($qualifier, $year, $month, $day);
    }

    /**
     * The date in the block grammar, the inverse of parse().
     */
    public function label(): string
    {
        $text = sprintf('%04d', $this->year);

        if ($this->month !== null) {
            $text .= sprintf('-%02d', $this->month);

            if ($this->day !== null) {
                $text .= sprintf('-%02d', $this->day);
            }
        }

        return match ($this->qualifier) {
            DateQualifier::Exact => $text,
            DateQualifier::Approximate => 'ok. '.$text,
            DateQualifier::Before => 'przed '.$text,
            DateQualifier::After => 'po '.$text,
            DateQualifier::Range => sprintf('między %04d a %04d', $this->year, (int) $this->untilYear),
        };
    }

    private static function isYearInRange(int $year): bool
    {
        return $year >= self::FIRST_YEAR && $year <= (int) date('Y');
    }
}
