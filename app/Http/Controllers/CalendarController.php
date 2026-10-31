<?php

namespace App\Http\Controllers;

use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
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

        $rangeStartUtc = $rangeStart->utc();
        $rangeEndUtc = $rangeEnd->utc();

        $events = Event::query()
            ->with('user')
            ->where('starts_at', '<', $rangeEndUtc)
            ->where(function (Builder $query) use ($rangeStartUtc): void {
                // Recurring series can reach the range from any start date.
                $query->whereNotNull('recurrence_rule')
                    ->orWhere('ends_at', '>', $rangeStartUtc);
            })
            ->get();

        $occurrences = $events
            ->flatMap(fn (Event $event): array => array_map(
                fn (array $occurrence): array => [$event, $occurrence['starts_at'], $occurrence['ends_at']],
                $event->occurrencesBetween($rangeStartUtc, $rangeEndUtc),
            ))
            ->sortBy(fn (array $occurrence): int => $occurrence[1]->getTimestamp())
            ->values();

        return Inertia::render('Calendar', [
            'view' => $view,
            'date' => $date->toDateString(),
            'today' => $today->toDateString(),
            'rangeStart' => $rangeStart->toDateString(),
            'rangeEnd' => $rangeEnd->toDateString(),
            'events' => $occurrences
                ->map(fn (array $occurrence): array => (new EventResource(...$occurrence))->resolve($request))
                ->all(),
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
