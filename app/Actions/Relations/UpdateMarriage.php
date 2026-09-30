<?php

namespace App\Actions\Relations;

use App\Actions\Persons\MapsEventInput;
use App\Models\Event;

class UpdateMarriage
{
    use MapsEventInput;

    /**
     * Update a marriage event's date and place in place, leaving its
     * participants and linked sources untouched.
     *
     * @param  array{year?: int|string|null, month?: int|string|null, day?: int|string|null, is_approximate?: bool|int|string|null, place?: string|null}  $date
     */
    public function handle(Event $marriage, array $date): Event
    {
        $marriage->update($this->eventAttributesFrom($date));

        return $marriage;
    }
}
