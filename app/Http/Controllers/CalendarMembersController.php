<?php

namespace App\Http\Controllers;

use App\Models\Calendar;
use App\Models\CalendarUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CalendarMembersController extends Controller
{
    /**
     * Show the people on a calendar, its pending requests and its join code.
     */
    public function index(Request $request, Calendar $calendar): Response
    {
        Gate::authorize('view', $calendar);

        $user = $request->user();

        return Inertia::render('calendars/Members', [
            'calendar' => [
                'id' => $calendar->id,
                'name' => $calendar->name,
                'join_code' => $calendar->join_code,
                'join_url' => route('join.show', $calendar->join_code),
                'can_manage' => $calendar->isManagedBy($user),
                'is_owner' => $calendar->owner_id === $user->id,
            ],
            'members' => $this->memberships($calendar, CalendarUser::STATUS_ACTIVE),
            'pending' => $this->memberships($calendar, CalendarUser::STATUS_PENDING),
        ]);
    }

    /**
     * Approve someone who asked to join.
     */
    public function approve(Request $request, Calendar $calendar, CalendarUser $membership): RedirectResponse
    {
        Gate::authorize('manage', $calendar);

        $membership->update(['status' => CalendarUser::STATUS_ACTIVE]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name joined the calendar.', ['name' => $membership->user->name])]);

        return back();
    }

    /**
     * Decline a request, or remove someone who has already joined.
     *
     * Events they added stay on the calendar.
     */
    public function destroy(Request $request, Calendar $calendar, CalendarUser $membership): RedirectResponse
    {
        Gate::authorize('manage', $calendar);

        abort_if(
            $membership->user_id === $calendar->owner_id,
            403,
            __('The owner cannot be removed from the calendar.'),
        );

        $membership->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name is no longer on the calendar.', ['name' => $membership->user->name])]);

        return back();
    }

    /**
     * Leave a calendar the current user does not own.
     *
     * Works for a pending request too, so nobody is stuck waiting on a calendar
     * they asked to join by mistake.
     */
    public function leave(Request $request, Calendar $calendar): RedirectResponse
    {
        $user = $request->user();

        abort_if($calendar->membershipFor($user) === null, 403);
        abort_if(
            $calendar->owner_id === $user->id,
            403,
            __('You own this calendar, so you cannot leave it.'),
        );

        $calendar->members()->detach($user);

        if ($user->current_calendar_id === $calendar->id) {
            $user->forceFill(['current_calendar_id' => null])->save();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('You left the calendar.')]);

        return to_route('calendar');
    }

    /**
     * List the people on a calendar with the given membership status.
     *
     * @return array<int, array{id: int, user_id: int, name: string, color: string, role: string}>
     */
    private function memberships(Calendar $calendar, string $status): array
    {
        return $calendar->memberships()
            ->with('user')
            ->where('status', $status)
            ->get()
            ->sortBy(fn (CalendarUser $membership): string => $membership->user->name)
            ->map(fn (CalendarUser $membership): array => [
                'id' => $membership->id,
                'user_id' => $membership->user_id,
                'name' => $membership->user->name,
                'color' => $membership->user->color,
                'role' => $membership->role,
            ])
            ->values()
            ->all();
    }
}
