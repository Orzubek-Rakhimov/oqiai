<?php

namespace App\Policies;

use App\Models\Child;
use App\Models\Reading;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Only the active Child reaches their own Readings. Another Child's Reading,
 * even a sibling's, is reported as not found, so its existence is never revealed.
 */
class ReadingPolicy
{
    /**
     * Determine whether the active Child can see the Reading's screen or result.
     */
    public function view(User $parent, Reading $reading, Child $activeChild): Response
    {
        return $this->readBy($parent, $reading, $activeChild);
    }

    /**
     * Determine whether the active Child can go on with the Reading: read a
     * sentence, retry a word or close Syllable help.
     */
    public function update(User $parent, Reading $reading, Child $activeChild): Response
    {
        return $this->readBy($parent, $reading, $activeChild);
    }

    /**
     * Allow only the active Child of the Parent whose Reading it is.
     */
    private function readBy(User $parent, Reading $reading, Child $activeChild): Response
    {
        return $reading->child_id === $activeChild->id && $activeChild->user_id === $parent->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
