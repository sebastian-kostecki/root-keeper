<?php

namespace App\Policies;

use App\Models\Source;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SourcePolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Source $source): Response
    {
        return $source->tree_id === $user->tree->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Source $source): Response
    {
        return $source->tree_id === $user->tree->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Source $source): Response
    {
        return $source->tree_id === $user->tree->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
