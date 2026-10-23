<?php

namespace App\Actions\Relations;

use App\Actions\Persons\MapsEventInput;
use App\Enums\DateQualifier;
use App\Models\Event;

class UpdateMarriage
{
    use MapsEventInput;

    /**
     * Replace a marriage event's date, locality and wedding form in place,
     * leaving its participants and linked sources untouched. A missing key
     * clears its value: without `form` the wedding form becomes unknown.
     *
     * @param  array{year?: int|string|null, month?: int|string|null, day?: int|string|null, qualifier?: DateQualifier|string|null, until_year?: int|string|null, locality_id?: int|string|null, form?: string|null}  $input
     */
    public function handle(Event $marriage, array $input): Event
    {
        $marriage->update($this->marriageAttributesFrom($input));

        return $marriage;
    }
}
