<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PersonPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Person $person): Response
    {
        return $person->tree_id === $user->tree->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
