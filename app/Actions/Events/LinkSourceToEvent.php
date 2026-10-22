<?php

namespace App\Actions\Events;

use App\Enums\SourceKind;
use App\Models\Event;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class LinkSourceToEvent
{
    /**
     * Link a source to an event with an optional entry number. A gravestone never takes an entry number.
     * An already linked pair is left untouched, entry number included; the savepoint keeps a lost race on the unique index from aborting the outer transaction.
     */
    public function handle(Event $event, int $sourceId, ?string $entryNumber): void
    {
        if ($event->sources()->whereKey($sourceId)->exists()) {
            return;
        }

        $isGravestone = $event->tree->sources()->whereKey($sourceId)->value('kind') === SourceKind::Gravestone;

        try {
            DB::transaction(fn () => $event->sources()->attach($sourceId, ['entry_number' => $isGravestone ? null : $entryNumber]));
        } catch (UniqueConstraintViolationException) {
            // A concurrent request already created the link; linking is idempotent.
        }
    }
}
