<?php

namespace App\Policies;

use App\Models\ParentChildRelation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ParentChildRelationPolicy
{
    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ParentChildRelation $parentChildRelation): Response
    {
        return $parentChildRelation->tree_id === $user->tree->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
