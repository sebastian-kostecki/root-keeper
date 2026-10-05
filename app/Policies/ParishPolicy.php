<?php

namespace App\Policies;

use App\Models\Parish;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ParishPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Parish $parish): Response
    {
        return $parish->tree_id === $user->tree->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Parish $parish): Response
    {
        return $parish->tree_id === $user->tree->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
