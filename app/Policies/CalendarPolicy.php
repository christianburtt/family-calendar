<?php

namespace App\Policies;

use App\Models\Calendar;
use App\Models\User;

class CalendarPolicy
{
    /**
     * Determine whether the user can look at the calendar.
     *
     * Only approved members may see a calendar's events and members.
     */
    public function view(User $user, Calendar $calendar): bool
    {
        return $calendar->hasActiveMember($user);
    }

    /**
     * Determine whether the user can rename the calendar, approve requests,
     * remove members or rotate the join code.
     */
    public function manage(User $user, Calendar $calendar): bool
    {
        return $calendar->isManagedBy($user);
    }
}
