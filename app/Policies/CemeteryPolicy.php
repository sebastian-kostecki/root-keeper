<?php

namespace App\Policies;

use App\Models\Cemetery;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CemeteryPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Cemetery $cemetery): Response
    {
        return $cemetery->tree_id === $user->tree->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Cemetery $cemetery): Response
    {
        return $cemetery->tree_id === $user->tree->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Cemetery $cemetery): Response
    {
        return $cemetery->tree_id === $user->tree->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
