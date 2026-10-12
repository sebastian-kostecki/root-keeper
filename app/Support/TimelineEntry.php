<?php

namespace App\Support;

use App\Enums\EventType;
use App\Models\Event;
use App\Models\Person;

/**
 * One row of a person's timeline: an event, the role it plays for the viewed
 * person and the relative it concerns (a child, a spouse or the other spouse).
 */
final readonly class TimelineEntry
{
    /**
     * @param  'self'|'child'|'spouse'  $role
     */
    public function __construct(
        public Event $event,
        public string $role,
        public ?Person $relative,
    ) {}

    /**
     * The tie-breaker for entries sharing the same date: own birth 0, marriage 1,
     * child birth 2, child death 3, spouse death 4, own death 5, burial 6.
     */
    public function rank(): int
    {
        return match ($this->event->type) {
            EventType::Birth => $this->role === 'child' ? 2 : 0,
            EventType::Marriage => 1,
            EventType::Death => match ($this->role) {
                'child' => 3,
                'spouse' => 4,
                'self' => 5,
            },
            EventType::Burial => 6,
        };
    }
}
