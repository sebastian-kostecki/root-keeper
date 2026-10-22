<?php

namespace App\Actions\Relations;

use App\Actions\Persons\MapsEventInput;
use App\Enums\EventType;
use App\Enums\ParticipantRole;
use App\Models\Event;
use App\Models\Person;
use App\Models\Tree;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateMarriage
{
    use MapsEventInput;

    /**
     * Create a marriage as one event with both people attached as spouses,
     * rejecting a duplicate pair atomically. The date, locality and wedding
     * form come from the input; a missing key leaves its value unknown.
     *
     * @param  array{year?: int|string|null, month?: int|string|null, day?: int|string|null, is_approximate?: bool|int|string|null, locality_id?: int|string|null, form?: string|null}  $input
     */
    public function handle(Tree $tree, Person $person, Person $spouse, array $input): Event
    {
        return DB::transaction(function () use ($tree, $person, $spouse, $input): Event {
            [$first, $second] = $person->id <= $spouse->id ? [$person, $spouse] : [$spouse, $person];

            Person::whereKey($first->id)->lockForUpdate()->firstOrFail();
            Person::whereKey($second->id)->lockForUpdate()->firstOrFail();

            $alreadyMarried = Event::query()
                ->where('tree_id', $tree->id)
                ->where('type', EventType::Marriage)
                ->whereHas('participants', fn ($query) => $query->whereKey($person->id))
                ->whereHas('participants', fn ($query) => $query->whereKey($spouse->id))
                ->exists();

            if ($alreadyMarried) {
                throw ValidationException::withMessages(['spouse_id' => 'Ta para ma już małżeństwo.']);
            }

            $event = $tree->events()->create([
                'type' => EventType::Marriage,
                ...$this->marriageAttributesFrom($input),
            ]);

            $event->participants()->attach([
                $person->id => ['role' => ParticipantRole::Spouse],
                $spouse->id => ['role' => ParticipantRole::Spouse],
            ]);

            return $event;
        });
    }
}
