<?php

namespace App\Medical\Http\Controllers;

use App\Medical\Http\Controllers\Concerns\MedicalController;
use App\Models\Calendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Switches a household's medical module on or off.
 *
 * This lives on the calendar rather than in the medical module because it is
 * the owner's decision about the household, made in the same place they decide
 * who is in it.
 */
class MedicalModuleController extends MedicalController
{
    /**
     * Turn the module on or off for a calendar.
     */
    public function update(Request $request, Calendar $calendar): RedirectResponse
    {
        Gate::authorize('manage', $calendar);

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $calendar->setModule(Calendar::MODULE_MEDICAL, (bool) $validated['enabled']);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $validated['enabled']
                ? __('Medical records are now available to everyone on :name.', ['name' => $calendar->name])
                : __('Medical records are hidden from everyone on :name.', ['name' => $calendar->name]),
        ]);

        return back();
    }
}
