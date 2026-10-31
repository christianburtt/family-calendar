<?php

namespace App\Http\Controllers;

use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    /**
     * Show the shared family calendar for a month or a week, in the viewer's timezone.
     */
    public function index(Request $request): Response
    {
        $timezone = $request->user()->timezone;
        $view = $request->query('view') === 'week' ? 'week' : 'month';
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $date = $this->parseDate($request->query('date'), $timezone) ?? $today;

        if ($view === 'week') {
            $rangeStart = $date->startOfWeek(CarbonInterface::SUNDAY);
            $rangeEnd = $rangeStart->addWeek();
        } else {
            $rangeStart = $date->startOfMonth()->startOfWeek(CarbonInterface::SUNDAY);
            $rangeEnd = $date->endOfMonth()->startOfWeek(CarbonInterface::SUNDAY)->addWeek();
        }

        $events = Event::query()
            ->overlapping($rangeStart, $rangeEnd)
            ->orderBy('starts_at')
            ->get();

        return Inertia::render('Calendar', [
            'view' => $view,
            'date' => $date->toDateString(),
            'today' => $today->toDateString(),
            'rangeStart' => $rangeStart->toDateString(),
            'rangeEnd' => $rangeEnd->toDateString(),
            'events' => EventResource::collection($events)->resolve($request),
            'members' => User::query()->orderBy('name')->get(['id', 'name', 'color']),
        ]);
    }

    /**
     * Parse a "Y-m-d" date in the given timezone, ignoring anything malformed.
     */
    private function parseDate(mixed $value, string $timezone): ?CarbonImmutable
    {
        if (! is_string($value)) {
            return null;
        }

        return rescue(
            fn () => CarbonImmutable::createFromFormat('!Y-m-d', $value, $timezone),
            report: false,
        ) ?: null;
    }
}
