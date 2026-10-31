<script setup lang="ts">
import { useMediaQuery } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import DayAgenda from '@/components/calendar/DayAgenda.vue';
import {
    eachDay,
    formatDayHeading,
    formatTime,
    formatWeekday,
    parseWallClock,
    spansOnDay,
    toDateKey,
} from '@/lib/calendar';
import type { EventSpan } from '@/lib/calendar';
import { eventColorStyle } from '@/lib/colors';
import type { CalendarEvent, FamilyMember } from '@/types';

type Props = {
    date: string;
    today: string;
    rangeStart: string;
    rangeEnd: string;
    spans: EventSpan[];
    membersById: Map<number, FamilyMember>;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    select: [event: CalendarEvent];
    create: [day: Date];
    showWeek: [day: Date];
}>();

const MAX_VISIBLE_EVENTS = 3;

const isDesktop = useMediaQuery('(min-width: 768px)');

const days = computed(() =>
    eachDay(parseWallClock(props.rangeStart), parseWallClock(props.rangeEnd)),
);
const weekdays = computed(() => days.value.slice(0, 7));
const month = computed(() => parseWallClock(props.date).getUTCMonth());

const spansByDay = computed(
    () =>
        new Map(
            days.value.map((day) => [
                toDateKey(day),
                spansOnDay(props.spans, day),
            ]),
        ),
);

const initialSelectedDay = (): string =>
    props.today >= props.rangeStart &&
    props.today < props.rangeEnd &&
    parseWallClock(props.today).getUTCMonth() === month.value
        ? props.today
        : props.date.slice(0, 8) + '01';

/** The day whose agenda is listed under the grid on small screens. */
const selectedDay = ref(initialSelectedDay());

watch(
    () => props.date,
    () => (selectedDay.value = initialSelectedDay()),
);

function onDayClick(day: Date): void {
    if (isDesktop.value) {
        emit('create', day);
    } else {
        selectedDay.value = toDateKey(day);
    }
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="overflow-hidden rounded-xl border">
            <div
                class="grid grid-cols-7 border-b bg-muted/40 text-xs font-medium text-muted-foreground"
            >
                <div
                    v-for="day in weekdays"
                    :key="day.getUTCDay()"
                    class="px-2 py-2 text-center md:text-left"
                >
                    <span class="md:hidden">
                        {{ formatWeekday(day, 'narrow') }}
                    </span>
                    <span class="hidden md:inline">
                        {{ formatWeekday(day, 'short') }}
                    </span>
                </div>
            </div>

            <div
                class="grid auto-rows-[minmax(3.75rem,auto)] grid-cols-7 gap-px bg-border md:auto-rows-[minmax(7.5rem,auto)]"
            >
                <div
                    v-for="day in days"
                    :key="toDateKey(day)"
                    class="relative flex flex-col p-1 md:p-1.5"
                    :class="[
                        day.getUTCMonth() === month
                            ? 'bg-background'
                            : 'bg-muted text-muted-foreground',
                        !isDesktop && selectedDay === toDateKey(day)
                            ? 'ring-2 ring-primary ring-inset'
                            : '',
                    ]"
                >
                    <button
                        type="button"
                        class="absolute inset-0 cursor-pointer transition-colors outline-none hover:bg-muted/40 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset"
                        :aria-label="
                            isDesktop
                                ? `Add an event on ${formatDayHeading(day)}`
                                : `Show ${formatDayHeading(day)}`
                        "
                        @click="onDayClick(day)"
                    />

                    <span
                        class="pointer-events-none relative mx-auto flex size-6 items-center justify-center rounded-full text-xs font-medium md:mx-0 md:size-7 md:text-sm"
                        :class="{
                            'bg-primary text-primary-foreground':
                                toDateKey(day) === today,
                        }"
                    >
                        {{ day.getUTCDate() }}
                    </span>

                    <!-- Desktop: event chips -->
                    <div
                        v-if="isDesktop"
                        class="pointer-events-none relative mt-1 flex min-w-0 flex-col gap-0.5"
                    >
                        <button
                            v-for="span in spansByDay
                                .get(toDateKey(day))
                                ?.slice(0, MAX_VISIBLE_EVENTS)"
                            :key="span.event.id"
                            type="button"
                            class="pointer-events-auto cursor-pointer truncate rounded-sm border-l-[3px] px-1.5 py-0.5 text-left text-xs text-foreground transition hover:brightness-95 dark:hover:brightness-125"
                            :style="
                                eventColorStyle(
                                    membersById.get(span.event.user_id)
                                        ?.color ?? '#737373',
                                )
                            "
                            :title="span.event.title"
                            @click="emit('select', span.event)"
                        >
                            <span
                                v-if="toDateKey(span.start) === toDateKey(day)"
                                class="font-semibold tabular-nums"
                            >
                                {{ formatTime(span.start) }}
                            </span>
                            {{ span.event.title }}
                        </button>

                        <button
                            v-if="
                                (spansByDay.get(toDateKey(day))?.length ?? 0) >
                                MAX_VISIBLE_EVENTS
                            "
                            type="button"
                            class="pointer-events-auto cursor-pointer px-1.5 text-left text-xs font-medium text-muted-foreground hover:text-foreground"
                            @click="emit('showWeek', day)"
                        >
                            +{{
                                (spansByDay.get(toDateKey(day))?.length ?? 0) -
                                MAX_VISIBLE_EVENTS
                            }}
                            more
                        </button>
                    </div>

                    <!-- Mobile: a dot per event -->
                    <div
                        v-else
                        class="pointer-events-none relative mt-1 flex flex-wrap justify-center gap-0.5"
                    >
                        <span
                            v-for="span in spansByDay
                                .get(toDateKey(day))
                                ?.slice(0, 6)"
                            :key="span.event.id"
                            class="size-1.5 rounded-full"
                            :style="{
                                backgroundColor: membersById.get(
                                    span.event.user_id,
                                )?.color,
                            }"
                        />
                    </div>
                </div>
            </div>
        </div>

        <DayAgenda
            v-if="!isDesktop"
            :day="parseWallClock(selectedDay)"
            :spans="spans"
            :members-by-id="membersById"
            :is-today="selectedDay === today"
            @select="emit('select', $event)"
            @create="emit('create', $event)"
        />
    </div>
</template>
