<?php

namespace App\Medical\Http\Middleware;

use App\Models\Calendar;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the medical routes behind the household's module switch.
 *
 * The household is whatever calendar the viewer is currently looking at, so a
 * member of two households only reaches the records of the one on screen. The
 * resolved calendar is put on the request so controllers do not look it up
 * again and cannot drift from the one that was checked.
 */
class EnsureMedicalModuleIsEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $calendar = $request->user()?->currentCalendar();

        abort_if($calendar === null, 403, __('Join a calendar before opening medical records.'));

        abort_unless(
            $calendar->hasModule(Calendar::MODULE_MEDICAL),
            403,
            __('Medical records are not enabled for this household.'),
        );

        $request->attributes->set('medical_calendar', $calendar);

        return $next($request);
    }
}
