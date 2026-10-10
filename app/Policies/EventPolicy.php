<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EventPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Event $event): Response
    {
        return $event->tree_id === $user->tree->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Event $event): Response
    {
        return $event->tree_id === $user->tree->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
