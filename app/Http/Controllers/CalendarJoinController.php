<?php

namespace App\Http\Controllers;

use App\Models\Calendar;
use App\Models\CalendarUser;
use App\Support\JoinCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CalendarJoinController extends Controller
{
    /**
     * Show the confirmation screen for a join code.
     *
     * Joining is deliberately a two step flow: link previews and mail scanners
     * follow GET requests, and nobody should be added by accident.
     */
    public function show(Request $request, string $code): Response
    {
        $calendar = $this->findCalendar($code);

        return Inertia::render('join/Show', [
            'code' => JoinCode::normalise($code),
            'calendar' => $calendar === null ? null : [
                'id' => $calendar->id,
                'name' => $calendar->name,
            ],
            'status' => $calendar === null
                ? null
                : $calendar->membershipFor($request->user())?->status,
        ]);
    }

    /**
     * Ask to join the calendar behind a join code.
     */
    public function store(Request $request, string $code): RedirectResponse
    {
        $calendar = $this->findCalendar($code);

        if ($calendar === null) {
            return back()->withErrors([
                'code' => __('That code did not work. Check it and try again.'),
            ]);
        }

        $user = $request->user();
        $membership = $calendar->membershipFor($user);

        if ($membership?->isActive()) {
            $user->switchTo($calendar);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('You are already on this calendar.')]);

            return to_route('calendar');
        }

        if ($membership?->isPending()) {
            return to_route('calendar');
        }

        $calendar->members()->attach($user, [
            'role' => CalendarUser::ROLE_MEMBER,
            'status' => CalendarUser::STATUS_PENDING,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Request sent. Someone on the calendar needs to approve you.')]);

        return to_route('calendar');
    }

    /**
     * Look up a calendar by a code a person typed or pasted.
     */
    private function findCalendar(string $code): ?Calendar
    {
        $code = JoinCode::normalise($code);

        if ($code === '') {
            return null;
        }

        return Calendar::query()->where('join_code', $code)->first();
    }
}
