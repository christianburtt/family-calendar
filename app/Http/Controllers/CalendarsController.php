<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCalendarRequest;
use App\Http\Requests\UpdateCalendarRequest;
use App\Models\Calendar;
use App\Models\CalendarUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CalendarsController extends Controller
{
    /**
     * Show the form for creating a calendar.
     */
    public function create(): Response
    {
        return Inertia::render('calendars/Create');
    }

    /**
     * Create a calendar and make the current user its owner.
     */
    public function store(StoreCalendarRequest $request): RedirectResponse
    {
        $user = $request->user();
        $name = $request->validated('name');

        $calendar = DB::transaction(function () use ($user, $name): Calendar {
            $calendar = Calendar::create([
                'owner_id' => $user->id,
                'name' => $name,
                'slug' => Calendar::unusedSlug($name),
                'join_code' => Calendar::unusedJoinCode(),
            ]);

            $calendar->members()->attach($user, [
                'role' => CalendarUser::ROLE_OWNER,
                'status' => CalendarUser::STATUS_ACTIVE,
            ]);

            return $calendar;
        });

        $user->switchTo($calendar);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Calendar created.')]);

        return to_route('calendar');
    }

    /**
     * Rename a calendar.
     */
    public function update(UpdateCalendarRequest $request, Calendar $calendar): RedirectResponse
    {
        $calendar->update(['name' => $request->validated('name')]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Calendar updated.')]);

        return back();
    }

    /**
     * Make one of the user's calendars the one they are looking at.
     */
    public function select(Request $request, Calendar $calendar): RedirectResponse
    {
        Gate::authorize('view', $calendar);

        $request->user()->switchTo($calendar);

        return to_route('calendar');
    }
}
