<?php

namespace App\Policies;

use App\Models\Child;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * A Parent reaches only their own Children. Another Parent's Child is
 * reported as not found, so its existence is never revealed.
 */
class ChildPolicy
{
    /**
     * Determine whether the Parent can edit the Child.
     */
    public function update(User $parent, Child $child): Response
    {
        return $this->ownedBy($parent, $child);
    }

    /**
     * Determine whether the Parent can delete the Child.
     */
    public function delete(User $parent, Child $child): Response
    {
        return $this->ownedBy($parent, $child);
    }

    /**
     * Determine whether the Parent can choose the Child on the picker.
     */
    public function choose(User $parent, Child $child): Response
    {
        return $this->ownedBy($parent, $child);
    }

    /**
     * Allow only the Parent who owns the Child.
     */
    private function ownedBy(User $parent, Child $child): Response
    {
        return $child->user_id === $parent->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
