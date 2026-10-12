<?php

namespace App\Actions\Events;

use App\Models\Event;
use App\Models\Source;

class UpdateEventSourceEntryNumber
{
    /**
     * Set or clear the entry number of an existing event-source link.
     *
     * @return bool false when the pair is not linked
     */
    public function handle(Event $event, Source $source, ?string $entryNumber): bool
    {
        return $event->sources()->updateExistingPivot($source->id, ['entry_number' => $entryNumber]) > 0;
    }
}
