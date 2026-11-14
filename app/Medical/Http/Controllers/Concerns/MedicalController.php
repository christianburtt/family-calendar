<?php

namespace App\Medical\Http\Controllers\Concerns;

use App\Http\Controllers\Controller;
use App\Models\Calendar;
use Illuminate\Http\Request;

/**
 * Shared plumbing for the medical controllers.
 */
abstract class MedicalController extends Controller
{
    /**
     * The calendar the middleware checked, or a 403 when it is missing.
     *
     * The medical routes sit behind EnsureMedicalModuleIsEnabled, which resolves
     * the household once. Reading it back here keeps every controller on the
     * same calendar that was authorised.
     */
    protected function calendar(Request $request): Calendar
    {
        $calendar = $request->attributes->get('medical_calendar');

        abort_unless($calendar instanceof Calendar, 403, __('Medical records are not enabled for this household.'));

        return $calendar;
    }
}
