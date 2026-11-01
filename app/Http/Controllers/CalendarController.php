<?php

namespace App\Http\Controllers;

use App\Http\Resources\EventResource;
use App\Models\Calendar;
use App\Models\Event;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    /**
     * Show a calendar for a month or a week, in the viewer's timezone.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $timezone = $user->timezone;
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

        $calendar = $user->currentCalendar();

        return Inertia::render('Calendar', [
            'view' => $view,
            'date' => $date->toDateString(),
            'today' => $today->toDateString(),
            'rangeStart' => $rangeStart->toDateString(),
            'rangeEnd' => $rangeEnd->toDateString(),
            'calendar' => $calendar === null ? null : [
                'id' => $calendar->id,
                'name' => $calendar->name,
                'can_manage' => $calendar->isManagedBy($user),
            ],
            'calendars' => $this->calendarSummaries($user->activeCalendars()->get()),
            'pending' => $this->calendarSummaries($user->pendingCalendars()->get()),
            'events' => $calendar === null ? [] : $this->eventsFor($request, $calendar, $rangeStart, $rangeEnd),
            'members' => $calendar === null
                ? []
                : $calendar->activeMembers()
                    ->orderBy('users.name')
                    ->get(['users.id', 'users.name', 'users.color'])
                    ->map(fn (User $member): array => [
                        'id' => $member->id,
                        'name' => $member->name,
                        'color' => $member->color,
                    ])
                    ->all(),
        ]);
    }

    /**
     * Expand the calendar's events into the occurrences that overlap the range.
     *
     * @return array<int, array<string, mixed>>
     */
    private function eventsFor(
        Request $request,
        Calendar $calendar,
        CarbonImmutable $rangeStart,
        CarbonImmutable $rangeEnd,
    ): array {
        $rangeStartUtc = $rangeStart->utc();
        $rangeEndUtc = $rangeEnd->utc();

        $events = Event::query()
            ->whereBelongsTo($calendar)
            ->with('user')
            ->where('starts_at', '<', $rangeEndUtc)
            ->where(function (Builder $query) use ($rangeStartUtc): void {
                // Recurring series can reach the range from any start date.
                $query->whereNotNull('recurrence_rule')
                    ->orWhere('ends_at', '>', $rangeStartUtc);
            })
            ->get();

        return $events
            ->flatMap(fn (Event $event): array => array_map(
                fn (array $occurrence): array => [$event, $occurrence['starts_at'], $occurrence['ends_at']],
                $event->occurrencesBetween($rangeStartUtc, $rangeEndUtc),
            ))
            ->sortBy(fn (array $occurrence): int => $occurrence[1]->getTimestamp())
            ->values()
            ->map(fn (array $occurrence): array => (new EventResource(...$occurrence))->resolve($request))
            ->all();
    }

    /**
     * Reduce calendars to the shape the frontend switcher needs.
     *
     * @param  Collection<int, Calendar>  $calendars
     * @return array<int, array{id: int, name: string}>
     */
    private function calendarSummaries(Collection $calendars): array
    {
        return $calendars
            ->map(fn (Calendar $calendar): array => [
                'id' => $calendar->id,
                'name' => $calendar->name,
            ])
            ->values()
            ->all();
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
