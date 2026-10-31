<?php

use App\Support\RecurrenceRule;
use Carbon\CarbonImmutable;

function expand(RecurrenceRule $rule, string $startsAt, string $endsAt, string $rangeStart, string $rangeEnd, string $timezone = 'UTC'): array
{
    return array_map(
        fn (array $occurrence): string => $occurrence['starts_at']->utc()->toDateTimeString(),
        $rule->occurrencesBetween(
            CarbonImmutable::parse($startsAt, 'UTC'),
            CarbonImmutable::parse($endsAt, 'UTC'),
            CarbonImmutable::parse($rangeStart, $timezone),
            CarbonImmutable::parse($rangeEnd, $timezone),
            $timezone,
        ),
    );
}

test('a weekly rule expands on each chosen weekday', function () {
    $rule = RecurrenceRule::make('WEEKLY', 1, ['TU', 'TH']);

    expect(expand(
        $rule,
        '2026-11-03 21:00:00',
        '2026-11-03 22:00:00',
        '2026-11-01',
        '2026-12-01',
        'America/New_York',
    ))->toBe([
        '2026-11-03 21:00:00',
        '2026-11-05 21:00:00',
        '2026-11-10 21:00:00',
        '2026-11-12 21:00:00',
        '2026-11-17 21:00:00',
        '2026-11-19 21:00:00',
        '2026-11-24 21:00:00',
        '2026-11-26 21:00:00',
    ]);
});

test('the interval spaces occurrences out', function () {
    $rule = RecurrenceRule::make('WEEKLY', 2, ['TU', 'TH']);

    expect(expand(
        $rule,
        '2026-11-03 21:00:00',
        '2026-11-03 22:00:00',
        '2026-11-01',
        '2026-12-01',
        'America/New_York',
    ))->toBe([
        '2026-11-03 21:00:00',
        '2026-11-05 21:00:00',
        '2026-11-17 21:00:00',
        '2026-11-19 21:00:00',
    ]);
});

test('count limits the series', function () {
    $rule = RecurrenceRule::make('WEEKLY', 1, ['TU', 'TH'], count: 3);

    expect(expand(
        $rule,
        '2026-11-03 21:00:00',
        '2026-11-03 22:00:00',
        '2026-11-01',
        '2026-12-01',
        'America/New_York',
    ))->toBe([
        '2026-11-03 21:00:00',
        '2026-11-05 21:00:00',
        '2026-11-10 21:00:00',
    ]);
});

test('until limits the series', function () {
    $rule = RecurrenceRule::make(
        'WEEKLY',
        1,
        ['TU', 'TH'],
        CarbonImmutable::parse('2026-11-10 23:59:59', 'America/New_York')->utc(),
    );

    expect(expand(
        $rule,
        '2026-11-03 21:00:00',
        '2026-11-03 22:00:00',
        '2026-11-01',
        '2026-12-01',
        'America/New_York',
    ))->toBe([
        '2026-11-03 21:00:00',
        '2026-11-05 21:00:00',
        '2026-11-10 21:00:00',
    ]);
});

test('a monthly rule skips months without its day', function () {
    $rule = RecurrenceRule::make('MONTHLY');

    expect(expand(
        $rule,
        '2026-01-31 10:00:00',
        '2026-01-31 11:00:00',
        '2026-01-01',
        '2026-05-01',
    ))->toBe([
        '2026-01-31 10:00:00',
        '2026-03-31 10:00:00',
    ]);
});

test('a yearly rule skips non-leap years', function () {
    $rule = RecurrenceRule::make('YEARLY');

    expect(expand(
        $rule,
        '2024-02-29 10:00:00',
        '2024-02-29 11:00:00',
        '2024-01-01',
        '2029-01-01',
    ))->toBe([
        '2024-02-29 10:00:00',
        '2028-02-29 10:00:00',
    ]);
});

test('a daily rule respects its interval', function () {
    $rule = RecurrenceRule::make('DAILY', 3);

    expect(expand(
        $rule,
        '2026-11-01 09:00:00',
        '2026-11-01 10:00:00',
        '2026-11-01',
        '2026-11-08',
    ))->toBe([
        '2026-11-01 09:00:00',
        '2026-11-04 09:00:00',
        '2026-11-07 09:00:00',
    ]);
});

test('occurrences keep their wall-clock time across daylight saving', function () {
    $rule = RecurrenceRule::make('WEEKLY', 1, ['SU']);

    expect(expand(
        $rule,
        '2026-10-25 13:00:00',
        '2026-10-25 14:00:00',
        '2026-10-20',
        '2026-11-15',
        'America/New_York',
    ))->toBe([
        '2026-10-25 13:00:00',
        '2026-11-01 14:00:00',
        '2026-11-08 14:00:00',
    ]);
});

test('an occurrence that started before the range still overlaps it', function () {
    $rule = RecurrenceRule::make('WEEKLY');

    expect(expand(
        $rule,
        '2026-11-01 22:00:00',
        '2026-11-03 02:00:00',
        '2026-11-03',
        '2026-11-04',
    ))->toBe([
        '2026-11-01 22:00:00',
    ]);
});

test('a rule round-trips through its RRULE string', function () {
    $rule = RecurrenceRule::make(
        'WEEKLY',
        2,
        ['TH', 'TU'],
        CarbonImmutable::parse('2026-12-31 23:59:59', 'UTC'),
    );

    $string = $rule->toString();

    expect($string)->toBe('FREQ=WEEKLY;INTERVAL=2;BYDAY=TU,TH;UNTIL=20261231T235959Z');

    $parsed = RecurrenceRule::fromRRule($string);

    expect($parsed)->not->toBeNull()
        ->and($parsed->toString())->toBe($string)
        ->and($parsed->toArray('UTC'))->toBe([
            'frequency' => 'weekly',
            'interval' => 2,
            'days' => ['TU', 'TH'],
            'ends' => 'on',
            'until' => '2026-12-31',
            'count' => null,
        ]);
});

test('an empty rule is not recurring', function () {
    expect(RecurrenceRule::fromRRule(null))->toBeNull()
        ->and(RecurrenceRule::fromRRule(''))->toBeNull();
});
