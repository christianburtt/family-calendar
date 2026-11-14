<?php

namespace App\Medical\Policies;

use App\Medical\Models\Dosar;
use App\Models\Calendar;
use App\Models\User;

/**
 * Who may open and change a person's medical folder.
 *
 * Read is household-wide: every approved member of the calendar sees every
 * folder, because a folder belongs to the person it describes rather than to
 * the account that created it. Writes follow the same rule; deleting the folder
 * is left to the household's owner.
 */
class DosarPolicy
{
    /**
     * Determine whether the user can open the folder and read what is in it.
     */
    public function view(User $user, Dosar $dosar): bool
    {
        return $this->belongsToMember($user, $dosar);
    }

    /**
     * Determine whether the user can add documents and edit the folder.
     */
    public function update(User $user, Dosar $dosar): bool
    {
        return $this->belongsToMember($user, $dosar);
    }

    /**
     * Determine whether the user can delete the folder.
     *
     * Removing a folder removes everything in it, so this is narrower than
     * editing: the household's owner, not just any member.
     */
    public function delete(User $user, Dosar $dosar): bool
    {
        return $this->belongsToMember($user, $dosar)
            && $dosar->calendar?->isManagedBy($user) === true;
    }

    /**
     * Whether the folder's household has the module and the user is on it.
     */
    private function belongsToMember(User $user, Dosar $dosar): bool
    {
        return $this->calendarAllows($user, $dosar->calendar);
    }

    /**
     * Whether the user is an approved member of a household with medical on.
     */
    private function calendarAllows(User $user, ?Calendar $calendar): bool
    {
        return $calendar !== null
            && $calendar->hasModule(Calendar::MODULE_MEDICAL)
            && $calendar->hasActiveMember($user);
    }
}
