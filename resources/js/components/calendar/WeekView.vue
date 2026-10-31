<script setup lang="ts">
import { useIntervalFn, useMediaQuery } from '@vueuse/core';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import DayAgenda from '@/components/calendar/DayAgenda.vue';
import {
    addDays,
    addMinutes,
    eachDay,
    formatTime,
    formatTimeRange,
    formatWeekday,
    layoutDay,
    minutesIntoDay,
    nowIn,
    parseWallClock,
    spansOnDay,
    toDateKey,
} from '@/lib/calendar';
import type { EventSpan, PositionedSegment } from '@/lib/calendar';
import { eventColorStyle } from '@/lib/colors';
import type { CalendarEvent, FamilyMember } from '@/types';

type Props = {
    rangeStart: string;
    spans: EventSpan[];
    membersById: Map<number, FamilyMember>;
    timezone: string;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    select: [event: CalendarEvent];
    create: [start: Date];
    createOnDay: [day: Date];
}>();

const HOUR_HEIGHT = 48;
const MINIMUM_EVENT_MINUTES = 30;

const isDesktop = useMediaQuery('(min-width: 768px)');

const now = ref(nowIn(props.timezone));
useIntervalFn(() => (now.value = nowIn(props.timezone)), 60_000);

const days = computed(() => {
    const start = parseWallClock(props.rangeStart);

    return eachDay(start, addDays(start, 7));
});

/** Events covering a whole day go in the "All day" strip instead of the time grid. */
const coversDay = (span: EventSpan, day: Date): boolean =>
    span.start <= day && span.end >= addDays(day, 1);

const allDaySpans = computed(
    () =>
        new Map(
            days.value.map((day) => [
                toDateKey(day),
                spansOnDay(props.spans, day).filter((span) =>
                    coversDay(span, day),
                ),
            ]),
        ),
);

const hasAllDaySpans = computed(() =>
    [...allDaySpans.value.values()].some((spans) => spans.length > 0),
);

const layouts = computed(
    () =>
        new Map(
            days.value.map((day) => [
                toDateKey(day),
                layoutDay(
                    props.spans.filter((span) => !coversDay(span, day)),
                    day,
                    MINIMUM_EVENT_MINUTES,
                ),
            ]),
        ),
);

const hourLabels = Array.from({ length: 23 }, (_, index) =>
    formatTime(new Date(Date.UTC(2000, 0, 1, index + 1))),
);

const isToday = (day: Date): boolean => toDateKey(day) === toDateKey(now.value);

const nowTop = computed(() => (minutesIntoDay(now.value) / 60) * HOUR_HEIGHT);

function segmentStyle(segment: PositionedSegment): Record<string, string> {
    const minutes = Math.max(
        segment.endMinute - segment.startMinute,
        MINIMUM_EVENT_MINUTES,
    );

    return {
        ...eventColorStyle(
            props.membersById.get(segment.span.event.user_id)?.color ??
                '#737373',
        ),
        top: `${(segment.startMinute / 60) * HOUR_HEIGHT}px`,
        height: `${(minutes / 60) * HOUR_HEIGHT - 2}px`,
        left: `calc(${(segment.column / segment.columns) * 100}% + 2px)`,
        width: `calc(${100 / segment.columns}% - 4px)`,
    };
}

function onSlotClick(event: MouseEvent, day: Date): void {
    const column = event.currentTarget as HTMLElement;
    const offset = event.clientY - column.getBoundingClientRect().top;
    const minutes = Math.floor(((offset / HOUR_HEIGHT) * 60) / 30) * 30;

    emit('create', addMinutes(day, Math.min(Math.max(minutes, 0), 23 * 60)));
}

const scroller = ref<HTMLElement | null>(null);

/** Start scrolled to the morning, or to just before "now" when this week is today. */
function scrollToWorkingHours(): void {
    if (!scroller.value) {
        return;
    }

    const showsToday = days.value.some(isToday);
    const hour = showsToday ? Math.max(now.value.getUTCHours() - 2, 0) : 7;

    scroller.value.scrollTop = hour * HOUR_HEIGHT;
}

onMounted(scrollToWorkingHours);

watch(isDesktop, async (desktop) => {
    if (desktop) {
        await nextTick();
        scrollToWorkingHours();
    }
});
</script>

<template>
    <div>
        <div v-if="isDesktop" class="overflow-hidden rounded-xl border">
            <div
                class="grid grid-cols-[3.5rem_repeat(7,minmax(0,1fr))] border-b bg-muted/40"
            >
                <div />
                <div
                    v-for="day in days"
                    :key="toDateKey(day)"
                    class="flex items-center justify-center gap-1.5 border-l py-2 text-sm"
                >
                    <span class="text-muted-foreground">
                        {{ formatWeekday(day, 'short') }}
                    </span>
                    <span
                        class="flex size-7 items-center justify-center rounded-full font-medium"
                        :class="{
                            'bg-primary text-primary-foreground': isToday(day),
                        }"
                    >
                        {{ day.getUTCDate() }}
                    </span>
                </div>
            </div>

            <div
                v-if="hasAllDaySpans"
                class="grid grid-cols-[3.5rem_repeat(7,minmax(0,1fr))] border-b"
            >
                <span
                    class="self-center pr-2 text-right text-[11px] text-muted-foreground"
                >
                    All day
                </span>
                <div
                    v-for="day in days"
                    :key="toDateKey(day)"
                    class="flex min-w-0 flex-col gap-0.5 border-l p-1"
                >
                    <button
                        v-for="span in allDaySpans.get(toDateKey(day))"
                        :key="span.event.id"
                        type="button"
                        class="cursor-pointer truncate rounded-sm border-l-[3px] px-1.5 py-0.5 text-left text-xs text-foreground transition hover:brightness-95 dark:hover:brightness-125"
                        :style="
                            eventColorStyle(
                                membersById.get(span.event.user_id)?.color ??
                                    '#737373',
                            )
                        "
                        :title="span.event.title"
                        @click="emit('select', span.event)"
                    >
                        {{ span.event.title }}
                    </button>
                </div>
            </div>

            <div
                ref="scroller"
                class="h-[calc(100svh-17rem)] min-h-[26rem] overflow-y-auto"
            >
                <div
                    class="grid grid-cols-[3.5rem_repeat(7,minmax(0,1fr))]"
                    :style="{ height: `${24 * HOUR_HEIGHT}px` }"
                >
                    <div class="relative">
                        <span
                            v-for="(label, index) in hourLabels"
                            :key="label"
                            class="absolute right-2 -translate-y-1/2 text-[11px] whitespace-nowrap text-muted-foreground"
                            :style="{ top: `${(index + 1) * HOUR_HEIGHT}px` }"
                        >
                            {{ label }}
                        </span>
                    </div>

                    <div
                        v-for="day in days"
                        :key="toDateKey(day)"
                        class="relative cursor-pointer border-l"
                        :class="{ 'bg-primary/[0.03]': isToday(day) }"
                        :style="{
                            backgroundImage: `repeating-linear-gradient(to bottom, transparent 0, transparent ${HOUR_HEIGHT - 1}px, var(--border) ${HOUR_HEIGHT - 1}px, var(--border) ${HOUR_HEIGHT}px)`,
                        }"
                        @click="onSlotClick($event, day)"
                    >
                        <button
                            v-for="segment in layouts.get(toDateKey(day))"
                            :key="segment.span.event.id"
                            type="button"
                            class="absolute flex cursor-pointer flex-col overflow-hidden rounded-md border-l-[3px] px-1.5 py-1 text-left text-xs leading-tight text-foreground transition hover:z-10 hover:brightness-95 dark:hover:brightness-125"
                            :style="segmentStyle(segment)"
                            :title="segment.span.event.title"
                            @click.stop="emit('select', segment.span.event)"
                        >
                            <span class="truncate font-semibold">
                                {{ segment.span.event.title }}
                            </span>
                            <span
                                v-if="
                                    segment.endMinute - segment.startMinute >=
                                    45
                                "
                                class="truncate text-muted-foreground tabular-nums"
                            >
                                {{
                                    formatTimeRange(
                                        segment.span.start,
                                        segment.span.end,
                                    )
                                }}
                            </span>
                        </button>

                        <div
                            v-if="isToday(day)"
                            class="pointer-events-none absolute inset-x-0 z-20 border-t-2 border-red-500"
                            :style="{ top: `${nowTop}px` }"
                        >
                            <span
                                class="absolute -top-[5px] -left-[5px] size-2 rounded-full bg-red-500"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="flex flex-col gap-3">
            <DayAgenda
                v-for="day in days"
                :key="toDateKey(day)"
                :day="day"
                :spans="spans"
                :members-by-id="membersById"
                :is-today="isToday(day)"
                @select="emit('select', $event)"
                @create="emit('createOnDay', $event)"
            />
        </div>
    </div>
</template>
