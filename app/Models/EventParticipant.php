<?php

namespace App\Models;

use App\Enums\ParticipantRole;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $event_id
 * @property int $person_id
 * @property ParticipantRole $role
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class EventParticipant extends Pivot
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => ParticipantRole::class,
        ];
    }
}
