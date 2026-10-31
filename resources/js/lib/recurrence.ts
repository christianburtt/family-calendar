import type { Recurrence, RecurrenceFrequency, WeekdayCode } from '@/types';

export const WEEKDAY_OPTIONS: {
    code: WeekdayCode;
    short: string;
    long: string;
}[] = [
    { code: 'MO', short: 'M', long: 'Monday' },
    { code: 'TU', short: 'T', long: 'Tuesday' },
    { code: 'WE', short: 'W', long: 'Wednesday' },
    { code: 'TH', short: 'T', long: 'Thursday' },
    { code: 'FR', short: 'F', long: 'Friday' },
    { code: 'SA', short: 'S', long: 'Saturday' },
    { code: 'SU', short: 'S', long: 'Sunday' },
];

const WEEKDAY_BY_JS_DAY: WeekdayCode[] = [
    'SU',
    'MO',
    'TU',
    'WE',
    'TH',
    'FR',
    'SA',
];

const UNITS: Record<RecurrenceFrequency, { one: string; many: string }> = {
    daily: { one: 'day', many: 'days' },
    weekly: { one: 'week', many: 'weeks' },
    monthly: { one: 'month', many: 'months' },
    yearly: { one: 'year', many: 'years' },
};

/**
 * The weekday of a wall-clock key such as "2026-11-03T16:00".
 */
export function weekdayOf(dateTimeKey: string): WeekdayCode {
    const [year, month, day] = dateTimeKey.split('T')[0].split('-').map(Number);

    return WEEKDAY_BY_JS_DAY[
        new Date(Date.UTC(year, month - 1, day)).getUTCDay()
    ];
}

/**
 * "day" or "days" for the chosen interval.
 */
export function repeatUnit(
    frequency: RecurrenceFrequency | 'never',
    interval: number,
): string {
    if (frequency === 'never') {
        return '';
    }

    const unit = UNITS[frequency];

    return Number(interval) === 1 ? unit.one : unit.many;
}

function joinWords(values: string[]): string {
    if (values.length <= 1) {
        return values[0] ?? '';
    }

    return `${values.slice(0, -1).join(', ')} and ${values.at(-1)}`;
}

function formatUntilDate(value: string): string {
    return new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(`${value}T00:00:00Z`));
}

/**
 * A human summary of a rule, e.g. "Every 2 weeks on Tuesday and Thursday".
 */
export function describeRecurrence(recurrence: Recurrence): string {
    let description = '';

    switch (recurrence.frequency) {
        case 'daily':
            description =
                recurrence.interval === 1
                    ? 'Every day'
                    : `Every ${recurrence.interval} days`;
            break;
        case 'weekly': {
            const base =
                recurrence.interval === 1
                    ? 'Every week'
                    : `Every ${recurrence.interval} weeks`;
            const days = WEEKDAY_OPTIONS.filter((day) =>
                recurrence.days.includes(day.code),
            ).map((day) => day.long);

            description =
                days.length > 0 ? `${base} on ${joinWords(days)}` : base;
            break;
        }
        case 'monthly':
            description =
                recurrence.interval === 1
                    ? 'Every month'
                    : `Every ${recurrence.interval} months`;
            break;
        case 'yearly':
            description =
                recurrence.interval === 1
                    ? 'Every year'
                    : `Every ${recurrence.interval} years`;
            break;
    }

    if (recurrence.ends === 'on' && recurrence.until) {
        description += ` until ${formatUntilDate(recurrence.until)}`;
    } else if (recurrence.ends === 'after' && recurrence.count) {
        description += `, ${recurrence.count} ${recurrence.count === 1 ? 'time' : 'times'}`;
    }

    return description;
}
