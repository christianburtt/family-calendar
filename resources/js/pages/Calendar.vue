<script setup lang="ts">
import { Head, Link, router, usePage, usePoll } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Plus } from '@lucide/vue';
import { useStorage } from '@vueuse/core';
import { computed, ref } from 'vue';
import EventDialog from '@/components/calendar/EventDialog.vue';
import MonthView from '@/components/calendar/MonthView.vue';
import WeekView from '@/components/calendar/WeekView.vue';
import { Button } from '@/components/ui/button';
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
import { calendar } from '@/routes';
import type { CalendarEvent, CalendarView, FamilyMember } from '@/types';

type Props = {
    view: CalendarView;
    /** The date the view is anchored on, "Y-m-d". */
    date: string;
    today: string;
    rangeStart: string;
    /** Exclusive. */
    rangeEnd: string;
    events: CalendarEvent[];
    members: FamilyMember[];
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Calendar',
                href: calendar(),
            },
        ],
    },
});

// Keep a fridge-mounted tablet up to date with everyone else's changes...
usePoll(60_000, { only: ['events', 'members'] });

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
    calendar({ query: { view, date } });

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
</script>

<template>
    <Head title="Calendar" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
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
    </div>
</template>
