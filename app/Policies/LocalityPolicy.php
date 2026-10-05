<?php

namespace App\Policies;

use App\Models\Locality;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class LocalityPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Locality $locality): Response
    {
        return $locality->tree_id === $user->tree->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Locality $locality): Response
    {
        return $locality->tree_id === $user->tree->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
