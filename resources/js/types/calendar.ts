export type CalendarView = 'month' | 'week';

export type CalendarEvent = {
    id: number;
    user_id: number;
    title: string;
    notes: string | null;
    /** Wall-clock time in the viewer's timezone, e.g. "2026-11-03T16:00". */
    starts_at: string;
    /** Wall-clock time in the viewer's timezone, e.g. "2026-11-03T17:00". */
    ends_at: string;
};

export type FamilyMember = {
    id: number;
    name: string;
    color: string;
};
