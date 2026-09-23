<?php

namespace App\Support;

final readonly class PartialDate
{
    public function __construct(
        public ?int $year,
        public ?int $month,
        public ?int $day,
        public bool $isApproximate,
    ) {}

    public function isEmpty(): bool
    {
        return $this->year === null && $this->month === null && $this->day === null;
    }

    public function format(): ?string
    {
        if ($this->year === null) {
            return null;
        }

        if ($this->month !== null && $this->day !== null) {
            $formatted = sprintf('%02d.%02d.%04d', $this->day, $this->month, $this->year);
        } elseif ($this->month !== null) {
            $formatted = sprintf('%02d.%04d', $this->month, $this->year);
        } else {
            $formatted = sprintf('%04d', $this->year);
        }

        return $this->isApproximate ? "ok. {$formatted}" : $formatted;
    }
}
