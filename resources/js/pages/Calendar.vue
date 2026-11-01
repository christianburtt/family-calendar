<script setup lang="ts">
import { Head, Link, router, usePage, usePoll } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, LogIn, Plus, Users } from '@lucide/vue';
import { useStorage } from '@vueuse/core';
import { computed, ref } from 'vue';
import EventDialog from '@/components/calendar/EventDialog.vue';
import JoinCalendarDialog from '@/components/calendar/JoinCalendarDialog.vue';
import MonthView from '@/components/calendar/MonthView.vue';
import WeekView from '@/components/calendar/WeekView.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectSeparator,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    addDays,
    addMonths,
    defaultStartFor,
    formatMonthTitle,
    formatWeekTitle,
    nowIn,
    parseWallClock,
    toDateKey,
    toDateTimeKey,
    toSpan,
} from '@/lib/calendar';
import { cn } from '@/lib/utils';
import { codeFromInput } from '@/lib/joinCode';
import { calendar as calendarRoute } from '@/routes';import {
    create as createCalendar,
    members as calendarMembers,
    select as selectCalendar,
} from '@/routes/calendars';
import { show as showJoin } from '@/routes/join';
import { leave as leaveCalendar } from '@/routes/memberships';
import type {
    CalendarContext,
    CalendarEvent,
    CalendarSummary,
    CalendarView,
    FamilyMember,
} from '@/types';

type Props = {
    view: CalendarView;
    /** The date the view is anchored on, "Y-m-d". */
    date: string;
    today: string;
    rangeStart: string;
    /** Exclusive. */
    rangeEnd: string;
    /** The calendar on screen, or null when the viewer is not on one yet. */
    calendar: CalendarContext | null;
    /** Every calendar the viewer can switch to. */
    calendars: CalendarSummary[];
    /** Calendars the viewer has asked to join and is waiting on. */
    pending: CalendarSummary[];
    events: CalendarEvent[];
    members: FamilyMember[];
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Calendar',
                href: calendarRoute(),
            },
        ],
    },
});

// Keep a fridge-mounted tablet up to date with everyone else's changes...
usePoll(60_000, {
    only: ['events', 'members', 'calendar', 'calendars', 'pending'],
});

const page = usePage();
const currentUser = computed(() => page.props.auth.user);

const hiddenMemberIds = useStorage<number[]>(
    'family-calendar:hidden-members',
    [],
);

const membersById = computed(
    () => new Map(props.members.map((member) => [member.id, member])),
);

const spans = computed(() =>
    props.events
        .filter((event) => !hiddenMemberIds.value.includes(event.user_id))
        .map(toSpan),
);

const anchor = computed(() => parseWallClock(props.date));

const title = computed(() =>
    props.view === 'month'
        ? formatMonthTitle(anchor.value)
        : formatWeekTitle(parseWallClock(props.rangeStart)),
);

const previousDate = computed(() =>
    toDateKey(
        props.view === 'month'
            ? addMonths(anchor.value, -1)
            : addDays(anchor.value, -7),
    ),
);

const nextDate = computed(() =>
    toDateKey(
        props.view === 'month'
            ? addMonths(anchor.value, 1)
            : addDays(anchor.value, 7),
    ),
);

const views: { value: CalendarView; label: string }[] = [
    { value: 'month', label: 'Month' },
    { value: 'week', label: 'Week' },
];

const calendarLink = (view: CalendarView, date: string) =>
    calendarRoute({ query: { view, date } });

function showWeek(day: Date): void {
    router.visit(calendarLink('week', toDateKey(day)));
}

function toggleMember(memberId: number): void {
    hiddenMemberIds.value = hiddenMemberIds.value.includes(memberId)
        ? hiddenMemberIds.value.filter((id) => id !== memberId)
        : [...hiddenMemberIds.value, memberId];
}

const dialogOpen = ref(false);
const selectedEvent = ref<CalendarEvent | null>(null);
const newEventStart = ref(toDateTimeKey(parseWallClock(props.today)));

function openEvent(event: CalendarEvent): void {
    selectedEvent.value = event;
    dialogOpen.value = true;
}

function createAt(start: Date): void {
    selectedEvent.value = null;
    newEventStart.value = toDateTimeKey(start);
    dialogOpen.value = true;
}

function createOnDay(day: Date): void {
    createAt(
        defaultStartFor(toDateKey(day), nowIn(currentUser.value.timezone)),
    );
}

/** "New event" lands on today when it's on screen, otherwise on the day being viewed. */
function createFromToolbar(): void {
    const showsToday =
        props.today >= props.rangeStart && props.today < props.rangeEnd;

    createOnDay(parseWallClock(showsToday ? props.today : props.date));
}

const joinCode = ref('');
const joinDialogOpen = ref(false);

/** A sentinel value so "New calendar" can live in the calendar switcher. */
const newCalendarValue = '__new';

/** Hand a typed code to the confirmation screen rather than joining straight away. */
function openJoinCode(): void {
    const code = joinCode.value.trim();

    if (code === '') {
        return;
    }

    router.visit(showJoin(codeFromInput(code)));
}

function switchCalendar(id: number): void {
    router.put(selectCalendar(id).url, {}, { preserveScroll: true });
}

function switchCalendarFrom(value: unknown): void {
    if (value === newCalendarValue) {
        router.visit(createCalendar());

        return;
    }

    switchCalendar(Number(value));
}

function withdrawRequest(id: number): void {
    router.delete(leaveCalendar(id).url, { preserveScroll: true });
}
</script>

<template>
    <Head title="Calendar" />

    <div
        v-if="calendar === null"
        class="flex h-full flex-1 items-center justify-center p-4"
    >
        <div class="w-full max-w-sm space-y-6">
            <div class="space-y-1 text-center">
                <h1 class="text-xl font-semibold tracking-tight">
                    No calendar yet
                </h1>
                <p class="text-sm text-muted-foreground">
                    Join a family calendar with its join code, or start a new
                    one.
                </p>
            </div>

            <div
                v-if="pending.length > 0"
                class="space-y-2 rounded-lg border p-4"
                data-test="pending-calendars"
            >
                <p class="text-sm font-medium">Waiting for approval</p>
                <ul class="space-y-1">
                    <li
                        v-for="waiting in pending"
                        :key="waiting.id"
                        class="flex items-center justify-between gap-2 text-sm text-muted-foreground"
                    >
                        {{ waiting.name }}
                        <button
                            type="button"
                            class="cursor-pointer text-xs underline underline-offset-2 hover:text-foreground"
                            @click="withdrawRequest(waiting.id)"
                        >
                            Withdraw
                        </button>
                    </li>
                </ul>
                <p class="text-xs text-muted-foreground">
                    Someone on the calendar needs to approve you before you can
                    see it.
                </p>
            </div>

            <form class="space-y-2" @submit.prevent="openJoinCode">
                <label for="join-code" class="text-sm font-medium">
                    Join code
                </label>
                <div class="flex gap-2">
                    <Input
                        id="join-code"
                        v-model="joinCode"
                        placeholder="brave-otter-teapot"
                        autocomplete="off"
                        autocapitalize="off"
                        spellcheck="false"
                        class="font-mono"
                    />
                    <Button type="submit">Join</Button>
                </div>
                <p class="text-xs text-muted-foreground">
                    Three words, separated by hyphens. Ask whoever runs the
                    calendar for it.
                </p>
            </form>

            <div
                class="flex items-center gap-3 text-xs text-muted-foreground"
                aria-hidden="true"
            >
                <span class="h-px flex-1 bg-border" />
                or
                <span class="h-px flex-1 bg-border" />
            </div>

            <Button variant="outline" class="w-full" as-child>
                <Link :href="createCalendar()">Create a calendar</Link>
            </Button>
        </div>
    </div>

    <div v-else class="flex h-full flex-1 flex-col gap-4 p-4">
        <div
            v-if="pending.length > 0"
            class="flex flex-wrap items-center gap-2 rounded-lg border bg-muted/30 px-4 py-2 text-sm"
            data-test="pending-calendars-banner"
        >
            <span class="text-muted-foreground">Waiting for approval:</span>
            <span
                v-for="waiting in pending"
                :key="waiting.id"
                class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5"
            >
                {{ waiting.name }}
                <button
                    type="button"
                    class="cursor-pointer text-xs text-muted-foreground underline underline-offset-2 hover:text-foreground"
                    :aria-label="`Withdraw request to join ${waiting.name}`"
                    @click="withdrawRequest(waiting.id)"
                >
                    Withdraw
                </button>
            </span>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <div class="flex items-center">
                    <Button variant="ghost" size="icon" as-child>
                        <Link
                            :href="calendarLink(view, previousDate)"
                            preserve-scroll
                            :aria-label="`Previous ${view}`"
                        >
                            <ChevronLeft />
                        </Link>
                    </Button>
                    <Button variant="ghost" size="icon" as-child>
                        <Link
                            :href="calendarLink(view, nextDate)"
                            preserve-scroll
                            :aria-label="`Next ${view}`"
                        >
                            <ChevronRight />
                        </Link>
                    </Button>
                </div>
                <h1 class="text-xl font-semibold tracking-tight">
                    {{ title }}
                </h1>
            </div>

            <div class="flex items-center gap-2">
                <Select
                    :model-value="String(calendar.id)"
                    @update:model-value="switchCalendarFrom"
                >
                    <SelectTrigger
                        class="h-9 w-[10.5rem] pl-3"
                        aria-label="Calendar"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in calendars"
                            :key="option.id"
                            :value="String(option.id)"
                        >
                            {{ option.name }}
                        </SelectItem>
                        <SelectSeparator />
                        <SelectItem :value="newCalendarValue">
                            <Plus />
                            New calendar
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Button variant="ghost" size="icon" as-child>
                    <Link
                        :href="calendarMembers(calendar.id)"
                        :aria-label="`People on ${calendar.name}`"
                    >
                        <Users />
                    </Link>
                </Button>

                <Button
                    variant="ghost"
                    size="icon"
                    data-test="join-another-calendar"
                    aria-label="Join another calendar"
                    @click="joinDialogOpen = true"
                >
                    <LogIn />
                </Button>

                <Button variant="outline" size="sm" as-child>
                    <Link :href="calendarLink(view, today)" preserve-scroll>
                        Today
                    </Link>
                </Button>

                <div
                    class="flex items-center rounded-md border p-0.5"
                    role="group"
                    aria-label="Calendar view"
                >
                    <Link
                        v-for="option in views"
                        :key="option.value"
                        :href="calendarLink(option.value, date)"
                        preserve-scroll
                        :aria-current="
                            option.value === view ? 'page' : undefined
                        "
                        :class="
                            cn(
                                'rounded-sm px-3 py-1 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground',
                                option.value === view &&
                                    'bg-secondary text-foreground shadow-xs',
                            )
                        "
                    >
                        {{ option.label }}
                    </Link>
                </div>

                <Button
                    size="sm"
                    data-test="new-event-button"
                    @click="createFromToolbar"
                >
                    <Plus />
                    <span class="hidden sm:inline">New event</span>
                </Button>
            </div>
        </div>

        <div
            class="flex flex-wrap items-center gap-2"
            role="group"
            aria-label="Show or hide family members"
        >
            <button
                v-for="member in members"
                :key="member.id"
                type="button"
                class="inline-flex cursor-pointer items-center gap-2 rounded-full border px-3 py-1 text-sm transition hover:bg-muted/50"
                :class="{
                    'text-muted-foreground opacity-60':
                        hiddenMemberIds.includes(member.id),
                }"
                :aria-pressed="!hiddenMemberIds.includes(member.id)"
                :title="
                    hiddenMemberIds.includes(member.id)
                        ? `Show ${member.name}'s events`
                        : `Hide ${member.name}'s events`
                "
                @click="toggleMember(member.id)"
            >
                <span
                    class="size-2.5 rounded-full transition"
                    :style="
                        hiddenMemberIds.includes(member.id)
                            ? { boxShadow: `inset 0 0 0 1.5px ${member.color}` }
                            : { backgroundColor: member.color }
                    "
                />
                <span
                    :class="{
                        'line-through': hiddenMemberIds.includes(member.id),
                    }"
                >
                    {{ member.name }}
                </span>
                <span
                    v-if="member.id === currentUser.id"
                    class="text-xs text-muted-foreground"
                >
                    (you)
                </span>
            </button>
        </div>

        <MonthView
            v-if="view === 'month'"
            :date="date"
            :today="today"
            :range-start="rangeStart"
            :range-end="rangeEnd"
            :spans="spans"
            :members-by-id="membersById"
            @select="openEvent"
            @create="createOnDay"
            @show-week="showWeek"
        />

        <WeekView
            v-else
            :range-start="rangeStart"
            :spans="spans"
            :members-by-id="membersById"
            :timezone="currentUser.timezone"
            @select="openEvent"
            @create="createAt"
            @create-on-day="createOnDay"
        />

        <EventDialog
            v-model:open="dialogOpen"
            :event="selectedEvent"
            :default-start="newEventStart"
            :members-by-id="membersById"
        />

        <JoinCalendarDialog v-model:open="joinDialogOpen" />
    </div>
</template>
