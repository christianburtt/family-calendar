<script setup lang="ts">
import { Plus } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { formatDayHeading, formatSpanOnDay, spansOnDay } from '@/lib/calendar';
import type { EventSpan } from '@/lib/calendar';
import type { CalendarEvent, FamilyMember } from '@/types';

type Props = {
    day: Date;
    spans: EventSpan[];
    membersById: Map<number, FamilyMember>;
    isToday?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    isToday: false,
});

const emit = defineEmits<{
    select: [event: CalendarEvent];
    create: [day: Date];
}>();

const daySpans = computed(() => spansOnDay(props.spans, props.day));
</script>

<template>
    <section class="overflow-hidden rounded-xl border">
        <header
            class="flex items-center justify-between gap-2 border-b bg-muted/40 py-1.5 pr-1.5 pl-4"
        >
            <h3 class="flex items-center gap-2 text-sm font-semibold">
                {{ formatDayHeading(day) }}
                <span
                    v-if="isToday"
                    class="rounded-full bg-primary px-2 py-0.5 text-[11px] font-medium text-primary-foreground"
                >
                    Today
                </span>
            </h3>
            <Button
                variant="ghost"
                size="icon-sm"
                :aria-label="`Add an event on ${formatDayHeading(day)}`"
                @click="emit('create', day)"
            >
                <Plus />
            </Button>
        </header>

        <ul v-if="daySpans.length > 0" class="divide-y">
            <li v-for="span in daySpans" :key="span.event.occurrence_id">
                <button
                    type="button"
                    class="flex w-full cursor-pointer items-stretch gap-3 px-4 py-2.5 text-left transition-colors hover:bg-muted/50"
                    @click="emit('select', span.event)"
                >
                    <span
                        class="w-1 shrink-0 rounded-full"
                        :style="{
                            backgroundColor: membersById.get(span.event.user_id)
                                ?.color,
                        }"
                    />
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-medium">
                            {{ span.event.title }}
                        </span>
                        <span
                            class="block truncate text-xs text-muted-foreground"
                        >
                            {{ formatSpanOnDay(span, day) }} ·
                            {{ membersById.get(span.event.user_id)?.name }}
                        </span>
                    </span>
                </button>
            </li>
        </ul>
        <p v-else class="px-4 py-3 text-sm text-muted-foreground">
            Nothing planned.
        </p>
    </section>
</template>
