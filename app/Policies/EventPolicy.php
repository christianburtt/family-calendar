<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    /**
     * Determine whether the user can update the event.
     *
     * Everyone sees the shared calendar, but only the owner may change their events.
     */
    public function update(User $user, Event $event): bool
    {
        return $event->user()->is($user);
    }

    /**
     * Determine whether the user can delete the event.
     */
    public function delete(User $user, Event $event): bool
    {
        return $event->user()->is($user);
    }
}
