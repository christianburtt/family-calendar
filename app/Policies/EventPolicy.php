<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    /**
     * Determine whether the user can update the event.
     *
     * Everyone on a calendar sees its events, but only the person who added an
     * event may change it, and only while they are still on that calendar.
     */
    public function update(User $user, Event $event): bool
    {
        return $this->ownsOnCalendar($user, $event);
    }

    /**
     * Determine whether the user can delete the event.
     */
    public function delete(User $user, Event $event): bool
    {
        return $this->ownsOnCalendar($user, $event);
    }

    /**
     * Whether the user added the event and can still see its calendar.
     */
    private function ownsOnCalendar(User $user, Event $event): bool
    {
        return $event->user()->is($user)
            && $event->calendar !== null
            && $event->calendar->hasActiveMember($user);
    }
}
