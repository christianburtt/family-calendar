<?php

namespace App\Http\Controllers;

use App\Models\Calendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class CalendarJoinCodeController extends Controller
{
    /**
     * Replace a calendar's join code, invalidating the old one immediately.
     */
    public function update(Calendar $calendar): RedirectResponse
    {
        Gate::authorize('manage', $calendar);

        $calendar->rotateJoinCode();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('New join code generated. The old one no longer works.')]);

        return back();
    }
}
