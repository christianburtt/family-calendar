export type CalendarView = 'month' | 'week';

export type RecurrenceFrequency = 'daily' | 'weekly' | 'monthly' | 'yearly';

export type RecurrenceEnd = 'never' | 'on' | 'after';

export type WeekdayCode = 'MO' | 'TU' | 'WE' | 'TH' | 'FR' | 'SA' | 'SU';

export type Recurrence = {
    frequency: RecurrenceFrequency;
    interval: number;
    days: WeekdayCode[];
    ends: RecurrenceEnd;
    /** "Y-m-d" in the viewer's timezone. */
    until: string | null;
    count: number | null;
};

export type CalendarEvent = {
    id: number;
    /** Unique per expanded occurrence, so a series can render many chips. */
    occurrence_id: string;
    user_id: number;
    title: string;
    notes: string | null;
    /** Wall-clock time in the viewer's timezone, e.g. "2026-11-03T16:00". */
    starts_at: string;
    /** Wall-clock time in the viewer's timezone, e.g. "2026-11-03T17:00". */
    ends_at: string;
    /** The first occurrence's wall-clock start, used when editing the series. */
    series_starts_at: string;
    /** The first occurrence's wall-clock end, used when editing the series. */
    series_ends_at: string;
    /** The stored RFC 5545 rule, or null for a one-off event. */
    recurrence_rule: string | null;
    recurrence: Recurrence | null;
};

export type FamilyMember = {
    id: number;
    name: string;
    color: string;
};
