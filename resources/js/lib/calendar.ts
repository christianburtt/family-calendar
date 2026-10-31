import type { CalendarEvent } from '@/types';

/*
 * The server sends dates and times as wall-clock strings that are already in
 * the viewer's timezone ("2026-11-03" or "2026-11-03T16:00"). They are held in
 * Date objects using only the UTC fields, so the browser's own timezone can
 * never shift them.
 */

export const MINUTES_PER_DAY = 24 * 60;

export type EventSpan = {
    event: CalendarEvent;
    start: Date;
    end: Date;
};

export type PositionedSegment = {
    span: EventSpan;
    startMinute: number;
    endMinute: number;
    column: number;
    columns: number;
};

const pad = (value: number): string => String(value).padStart(2, '0');

export function parseWallClock(value: string): Date {
    const [datePart, timePart = '00:00'] = value.split('T');
    const [year, month, day] = datePart.split('-').map(Number);
    const [hours, minutes] = timePart.split(':').map(Number);

    return new Date(Date.UTC(year, month - 1, day, hours, minutes));
}

export function toDateKey(date: Date): string {
    return `${date.getUTCFullYear()}-${pad(date.getUTCMonth() + 1)}-${pad(date.getUTCDate())}`;
}

export function toDateTimeKey(date: Date): string {
    return `${toDateKey(date)}T${pad(date.getUTCHours())}:${pad(date.getUTCMinutes())}`;
}

export function addDays(date: Date, days: number): Date {
    return new Date(
        Date.UTC(
            date.getUTCFullYear(),
            date.getUTCMonth(),
            date.getUTCDate() + days,
            date.getUTCHours(),
            date.getUTCMinutes(),
        ),
    );
}

export function addMinutes(date: Date, minutes: number): Date {
    return new Date(date.getTime() + minutes * 60_000);
}

/**
 * The first day of the month that is `months` away from the given date.
 */
export function addMonths(date: Date, months: number): Date {
    return new Date(
        Date.UTC(date.getUTCFullYear(), date.getUTCMonth() + months, 1),
    );
}

export function minutesBetween(start: Date, end: Date): number {
    return Math.round((end.getTime() - start.getTime()) / 60_000);
}

export function minutesIntoDay(date: Date): number {
    return date.getUTCHours() * 60 + date.getUTCMinutes();
}

export function eachDay(start: Date, endExclusive: Date): Date[] {
    const days: Date[] = [];

    for (let day = start; day < endExclusive; day = addDays(day, 1)) {
        days.push(day);
    }

    return days;
}

/**
 * The current wall-clock time in the given IANA timezone.
 */
export function nowIn(timezone: string): Date {
    try {
        const parts = Object.fromEntries(
            new Intl.DateTimeFormat('en-US', {
                timeZone: timezone,
                year: 'numeric',
                month: 'numeric',
                day: 'numeric',
                hour: 'numeric',
                minute: 'numeric',
                hourCycle: 'h23',
            })
                .formatToParts(new Date())
                .map((part) => [part.type, part.value]),
        );

        return new Date(
            Date.UTC(
                Number(parts.year),
                Number(parts.month) - 1,
                Number(parts.day),
                Number(parts.hour),
                Number(parts.minute),
            ),
        );
    } catch {
        const now = new Date();

        return new Date(
            Date.UTC(
                now.getFullYear(),
                now.getMonth(),
                now.getDate(),
                now.getHours(),
                now.getMinutes(),
            ),
        );
    }
}

/**
 * A sensible start time for a new event on the given day: the next full hour
 * when the day is today, otherwise 9 AM.
 */
export function defaultStartFor(dateKey: string, now: Date): Date {
    const day = parseWallClock(dateKey);

    if (toDateKey(now) !== dateKey) {
        return addMinutes(day, 9 * 60);
    }

    return addMinutes(day, Math.min(now.getUTCHours() + 1, 23) * 60);
}

export function toSpan(event: CalendarEvent): EventSpan {
    return {
        event,
        start: parseWallClock(event.starts_at),
        end: parseWallClock(event.ends_at),
    };
}

export function spansOnDay(spans: EventSpan[], day: Date): EventSpan[] {
    const nextDay = addDays(day, 1);

    return spans.filter((span) => span.start < nextDay && span.end > day);
}

/**
 * Position a day's events on a time grid, placing overlapping events side by side.
 */
export function layoutDay(
    spans: EventSpan[],
    day: Date,
    minimumMinutes = 30,
): PositionedSegment[] {
    const nextDay = addDays(day, 1);

    const segments: PositionedSegment[] = spansOnDay(spans, day)
        .map((span) => ({
            span,
            startMinute: span.start <= day ? 0 : minutesIntoDay(span.start),
            endMinute:
                span.end >= nextDay
                    ? MINUTES_PER_DAY
                    : minutesIntoDay(span.end),
            column: 0,
            columns: 1,
        }))
        .sort(
            (a, b) =>
                a.startMinute - b.startMinute || b.endMinute - a.endMinute,
        );

    let cluster: PositionedSegment[] = [];
    let columnEnds: number[] = [];
    let clusterEnd = -1;

    const closeCluster = (): void => {
        cluster.forEach((segment) => (segment.columns = columnEnds.length));
        cluster = [];
        columnEnds = [];
    };

    for (const segment of segments) {
        const visualEnd = Math.max(
            segment.endMinute,
            segment.startMinute + minimumMinutes,
        );

        if (segment.startMinute >= clusterEnd) {
            closeCluster();
        }

        let column = columnEnds.findIndex((end) => end <= segment.startMinute);

        if (column === -1) {
            column = columnEnds.length;
            columnEnds.push(visualEnd);
        } else {
            columnEnds[column] = visualEnd;
        }

        segment.column = column;
        cluster.push(segment);
        clusterEnd = Math.max(clusterEnd, visualEnd);
    }

    closeCluster();

    return segments;
}

function format(date: Date, options: Intl.DateTimeFormatOptions): string {
    return new Intl.DateTimeFormat(undefined, {
        ...options,
        timeZone: 'UTC',
    }).format(date);
}

function formatRange(
    start: Date,
    end: Date,
    options: Intl.DateTimeFormatOptions,
): string {
    return new Intl.DateTimeFormat(undefined, {
        ...options,
        timeZone: 'UTC',
    }).formatRange(start, end);
}

const timeOptions = (date: Date): Intl.DateTimeFormatOptions =>
    date.getUTCMinutes() === 0
        ? { hour: 'numeric' }
        : { hour: 'numeric', minute: '2-digit' };

/** "4 PM" or "4:30 PM". */
export function formatTime(date: Date): string {
    return format(date, timeOptions(date));
}

/** "November 2026". */
export function formatMonthTitle(date: Date): string {
    return format(date, { month: 'long', year: 'numeric' });
}

/** "Nov 1 – 7, 2026". */
export function formatWeekTitle(weekStart: Date): string {
    return formatRange(weekStart, addDays(weekStart, 6), {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

export function formatWeekday(
    date: Date,
    style: 'narrow' | 'short' | 'long',
): string {
    return format(date, { weekday: style });
}

/** "Tuesday, November 3". */
export function formatDayHeading(date: Date): string {
    return format(date, { weekday: 'long', month: 'long', day: 'numeric' });
}

/** "4 – 5 PM", "4:30 – 5:15 PM". */
export function formatTimeRange(start: Date, end: Date): string {
    const onTheHour = start.getUTCMinutes() === 0 && end.getUTCMinutes() === 0;

    return formatRange(
        start,
        end,
        onTheHour
            ? { hour: 'numeric' }
            : { hour: 'numeric', minute: '2-digit' },
    );
}

/** "Tue, Nov 3, 4:00 – 5:00 PM", or both full dates for multi-day events. */
export function formatEventRange(start: Date, end: Date): string {
    return formatRange(start, end, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

/**
 * How an event reads within a single day, e.g. "4 – 5 PM", "From 10 PM", "All day".
 */
export function formatSpanOnDay(span: EventSpan, day: Date): string {
    const nextDay = addDays(day, 1);
    const startsBefore = span.start < day;

    if (startsBefore && span.end >= nextDay) {
        return 'All day';
    }

    if (startsBefore) {
        return `Until ${formatTime(span.end)}`;
    }

    if (span.end > nextDay) {
        return `From ${formatTime(span.start)}`;
    }

    return formatTimeRange(span.start, span.end);
}
