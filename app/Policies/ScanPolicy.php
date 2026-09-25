<?php

namespace App\Policies;

use App\Models\Scan;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ScanPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Scan $scan): Response
    {
        return $scan->tree_id === $user->tree->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Scan $scan): Response
    {
        return $scan->tree_id === $user->tree->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
