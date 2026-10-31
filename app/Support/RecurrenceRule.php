<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Generator;
use InvalidArgumentException;

/**
 * A small, RFC 5545 compatible subset of recurrence rules.
 *
 * Supported parts: FREQ (DAILY, WEEKLY, MONTHLY, YEARLY), INTERVAL, BYDAY
 * (weekly), COUNT and UNTIL. Occurrences are expanded in the event owner's
 * timezone, so a 4 PM practice stays at 4 PM across daylight saving changes.
 */
final class RecurrenceRule
{
    /**
     * @var list<string>
     */
    public const array WEEKDAYS = ['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'];

    /**
     * @var list<string>
     */
    public const array FREQUENCIES = ['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'];

    /**
     * Weekday offsets from Sunday, matching the calendar's Sunday-first weeks.
     *
     * @var array<string, int>
     */
    private const array WEEKDAY_OFFSETS = [
        'SU' => 0,
        'MO' => 1,
        'TU' => 2,
        'WE' => 3,
        'TH' => 4,
        'FR' => 5,
        'SA' => 6,
    ];

    /**
     * A hard stop so a malformed rule can never expand forever.
     */
    private const int MAX_ITERATIONS = 10_000;

    /**
     * @param  list<string>  $days
     */
    private function __construct(
        public readonly string $frequency,
        public readonly int $interval = 1,
        public readonly array $days = [],
        public readonly ?CarbonImmutable $until = null,
        public readonly ?int $count = null,
    ) {}

    /**
     * Build a rule from the structured values submitted by the event form.
     *
     * @param  list<string>  $days
     */
    public static function make(
        string $frequency,
        int $interval = 1,
        array $days = [],
        ?CarbonInterface $until = null,
        ?int $count = null,
    ): self {
        $frequency = strtoupper($frequency);

        if (! in_array($frequency, self::FREQUENCIES, true)) {
            throw new InvalidArgumentException("Unsupported recurrence frequency [{$frequency}].");
        }

        $days = array_values(array_unique(array_map('strtoupper', $days)));

        foreach ($days as $day) {
            if (! in_array($day, self::WEEKDAYS, true)) {
                throw new InvalidArgumentException("Unsupported weekday [{$day}].");
            }
        }

        return new self(
            $frequency,
            max(1, $interval),
            $days,
            $until === null ? null : CarbonImmutable::instance($until)->utc(),
            $count,
        );
    }

    /**
     * Parse a stored RRULE string, returning null when there is no recurrence.
     */
    public static function fromRRule(?string $rule): ?self
    {
        if ($rule === null || trim($rule) === '') {
            return null;
        }

        $parts = [];

        foreach (explode(';', $rule) as $segment) {
            if (! str_contains($segment, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $segment, 2);
            $parts[strtoupper(trim($key))] = trim($value);
        }

        $days = isset($parts['BYDAY'])
            ? explode(',', strtoupper($parts['BYDAY']))
            : [];

        // Drop ordinal prefixes such as "2TU" that we do not expand.
        $days = array_values(array_filter(array_map(
            fn (string $day): string => (string) preg_replace('/^[+-]?\d+/', '', $day),
            $days,
        )));

        return self::make(
            $parts['FREQ'] ?? '',
            (int) ($parts['INTERVAL'] ?? 1),
            $days,
            isset($parts['UNTIL']) ? self::parseUntil($parts['UNTIL']) : null,
            isset($parts['COUNT']) ? (int) $parts['COUNT'] : null,
        );
    }

    /**
     * Serialize the rule back to its RRULE string.
     */
    public function toString(): string
    {
        $parts = ['FREQ='.$this->frequency];

        if ($this->interval > 1) {
            $parts[] = 'INTERVAL='.$this->interval;
        }

        if ($this->days !== []) {
            $parts[] = 'BYDAY='.implode(',', $this->orderedDays());
        }

        if ($this->count !== null) {
            $parts[] = 'COUNT='.$this->count;
        }

        if ($this->until !== null) {
            $parts[] = 'UNTIL='.$this->until->utc()->format('Ymd\THis\Z');
        }

        return implode(';', $parts);
    }

    /**
     * The structured shape the event form uses to edit the rule.
     *
     * @return array{frequency: string, interval: int, days: list<string>, ends: string, until: string|null, count: int|null}
     */
    public function toArray(string $timezone): array
    {
        return [
            'frequency' => strtolower($this->frequency),
            'interval' => $this->interval,
            'days' => $this->orderedDays(),
            'ends' => $this->count !== null ? 'after' : ($this->until !== null ? 'on' : 'never'),
            'until' => $this->until?->setTimezone($timezone)->toDateString(),
            'count' => $this->count,
        ];
    }

    /**
     * Expand the rule into occurrences that overlap the given range.
     *
     * @return list<array{starts_at: CarbonImmutable, ends_at: CarbonImmutable}>
     */
    public function occurrencesBetween(
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        CarbonInterface $rangeStart,
        CarbonInterface $rangeEnd,
        string $timezone,
    ): array {
        $localStart = CarbonImmutable::instance($startsAt)->setTimezone($timezone);
        $durationSeconds = (int) CarbonImmutable::instance($startsAt)
            ->diffInSeconds(CarbonImmutable::instance($endsAt), true);

        $localRangeStart = CarbonImmutable::instance($rangeStart)->setTimezone($timezone);
        $localRangeEnd = CarbonImmutable::instance($rangeEnd)->setTimezone($timezone);

        // An occurrence can begin before the range and still overlap it.
        $searchFrom = $localRangeStart->subSeconds($durationSeconds);

        $occurrences = [];

        foreach ($this->candidateStarts($localStart, $searchFrom, $localRangeEnd) as $candidate) {
            $occurrenceStart = $candidate->utc();
            $occurrenceEnd = $occurrenceStart->addSeconds($durationSeconds);

            if ($occurrenceEnd->lessThanOrEqualTo($localRangeStart->utc())) {
                continue;
            }

            $occurrences[] = [
                'starts_at' => $occurrenceStart,
                'ends_at' => $occurrenceEnd,
            ];
        }

        return $occurrences;
    }

    /**
     * Yield the rule's start times in the owner's timezone, in order.
     *
     * @return Generator<int, CarbonImmutable>
     */
    private function candidateStarts(
        CarbonImmutable $localStart,
        CarbonImmutable $searchFrom,
        CarbonImmutable $stopAt,
    ): Generator {
        // COUNT counts from the first occurrence, so only skip ahead when it is absent.
        $skipAhead = $this->count === null;
        $emitted = 0;

        foreach ($this->dateCandidates($localStart, $searchFrom, $skipAhead) as $candidate) {
            if ($candidate->lessThan($localStart)) {
                continue;
            }

            if ($this->until !== null && $candidate->greaterThan($this->until)) {
                return;
            }

            if ($candidate->greaterThanOrEqualTo($stopAt)) {
                return;
            }

            $emitted++;

            yield $candidate;

            if ($this->count !== null && $emitted >= $this->count) {
                return;
            }
        }
    }

    /**
     * @return Generator<int, CarbonImmutable>
     */
    private function dateCandidates(
        CarbonImmutable $localStart,
        CarbonImmutable $searchFrom,
        bool $skipAhead,
    ): Generator {
        return match ($this->frequency) {
            'DAILY' => $this->dailyCandidates($localStart, $searchFrom, $skipAhead),
            'WEEKLY' => $this->weeklyCandidates($localStart, $searchFrom, $skipAhead),
            'MONTHLY' => $this->monthlyCandidates($localStart, $searchFrom, $skipAhead),
            'YEARLY' => $this->yearlyCandidates($localStart, $searchFrom, $skipAhead),
        };
    }

    /**
     * @return Generator<int, CarbonImmutable>
     */
    private function dailyCandidates(
        CarbonImmutable $localStart,
        CarbonImmutable $searchFrom,
        bool $skipAhead,
    ): Generator {
        $offset = $skipAhead
            ? $this->skipOffset((int) $localStart->startOfDay()->diffInDays($searchFrom->startOfDay()))
            : 0;

        for ($index = $offset; $index < $offset + self::MAX_ITERATIONS; $index++) {
            $candidate = $localStart->addDays($index * $this->interval);

            if ($this->days !== [] && ! in_array($this->weekdayCode($candidate), $this->days, true)) {
                continue;
            }

            yield $candidate;
        }
    }

    /**
     * @return Generator<int, CarbonImmutable>
     */
    private function weeklyCandidates(
        CarbonImmutable $localStart,
        CarbonImmutable $searchFrom,
        bool $skipAhead,
    ): Generator {
        $baseWeek = $localStart->startOfWeek(CarbonInterface::SUNDAY);

        $offset = $skipAhead
            ? $this->skipOffset((int) $baseWeek->diffInWeeks($searchFrom->startOfWeek(CarbonInterface::SUNDAY)))
            : 0;

        $days = $this->weekdaysFor($localStart);

        for ($week = $offset; $week < $offset + self::MAX_ITERATIONS; $week++) {
            $weekStart = $baseWeek->addWeeks($week * $this->interval);

            foreach ($days as $day) {
                yield $weekStart
                    ->addDays(self::WEEKDAY_OFFSETS[$day])
                    ->setTime($localStart->hour, $localStart->minute, $localStart->second);
            }
        }
    }

    /**
     * @return Generator<int, CarbonImmutable>
     */
    private function monthlyCandidates(
        CarbonImmutable $localStart,
        CarbonImmutable $searchFrom,
        bool $skipAhead,
    ): Generator {
        $offset = $skipAhead
            ? $this->skipOffset((int) $localStart->startOfMonth()->diffInMonths($searchFrom->startOfMonth()))
            : 0;

        for ($index = $offset; $index < $offset + self::MAX_ITERATIONS; $index++) {
            $candidate = $localStart->addMonths($index * $this->interval);

            // RFC 5545 skips months that do not contain the series' day, e.g. the 31st.
            if ($candidate->day !== $localStart->day) {
                continue;
            }

            yield $candidate;
        }
    }

    /**
     * @return Generator<int, CarbonImmutable>
     */
    private function yearlyCandidates(
        CarbonImmutable $localStart,
        CarbonImmutable $searchFrom,
        bool $skipAhead,
    ): Generator {
        $offset = $skipAhead
            ? $this->skipOffset($localStart->year - $searchFrom->year)
            : 0;

        for ($index = $offset; $index < $offset + self::MAX_ITERATIONS; $index++) {
            $candidate = $localStart->addYears($index * $this->interval);

            // A February 29th series skips years that are not leap years.
            if ($candidate->month !== $localStart->month || $candidate->day !== $localStart->day) {
                continue;
            }

            yield $candidate;
        }
    }

    /**
     * How many intervals can be skipped before the search range.
     *
     * One interval is kept as a safety margin so an occurrence that starts
     * just before the range but overlaps it is never skipped.
     */
    private function skipOffset(int $difference): int
    {
        return max(0, intdiv(max(0, $difference), $this->interval) - 1);
    }

    /**
     * The rule's weekdays, or the series start's weekday when none were chosen.
     *
     * @return list<string>
     */
    private function weekdaysFor(CarbonImmutable $localStart): array
    {
        $days = $this->days !== [] ? $this->days : [$this->weekdayCode($localStart)];

        $days = array_values(array_unique($days));

        usort(
            $days,
            fn (string $a, string $b): int => self::WEEKDAY_OFFSETS[$a] <=> self::WEEKDAY_OFFSETS[$b],
        );

        return $days;
    }

    /**
     * @return list<string>
     */
    private function orderedDays(): array
    {
        $days = array_values(array_unique($this->days));

        usort(
            $days,
            fn (string $a, string $b): int => array_search($a, self::WEEKDAYS, true) <=> array_search($b, self::WEEKDAYS, true),
        );

        return $days;
    }

    private function weekdayCode(CarbonImmutable $date): string
    {
        return self::WEEKDAYS[($date->dayOfWeek + 6) % 7];
    }

    private static function parseUntil(string $value): CarbonImmutable
    {
        $value = strtoupper($value);

        foreach (['Ymd\THis\Z', 'Ymd\THis', 'Ymd'] as $format) {
            $date = CarbonImmutable::createFromFormat('!'.$format, $value, 'UTC');

            if ($date !== false && $date->format($format) === $value) {
                return $date;
            }
        }

        throw new InvalidArgumentException("Invalid recurrence UNTIL [{$value}].");
    }
}
