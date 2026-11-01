<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class EventController extends Controller
{
    /**
     * Add an event to the calendar the current user is looking at.
     */
    public function store(StoreEventRequest $request): RedirectResponse
    {
        $calendar = $request->user()->currentCalendar();

        abort_if($calendar === null, 403, __('Join a calendar before adding events.'));

        $request->user()->events()->create([
            ...$request->eventAttributes(),
            'calendar_id' => $calendar->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Event added.')]);

        return back();
    }

    /**
     * Update one of the current user's events.
     */
    public function update(UpdateEventRequest $request, Event $event): RedirectResponse
    {
        $event->update($request->eventAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Event updated.')]);

        return back();
    }

    /**
     * Remove one of the current user's events.
     */
    public function destroy(Event $event): RedirectResponse
    {
        Gate::authorize('delete', $event);

        $event->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Event deleted.')]);

        return back();
    }
}
