<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import {
    destroy,
    store,
    update,
} from '@/actions/App/Http/Controllers/EventController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import {
    addMinutes,
    formatEventRange,
    minutesBetween,
    parseWallClock,
    toDateTimeKey,
} from '@/lib/calendar';
import {
    describeRecurrence,
    repeatUnit,
    WEEKDAY_OPTIONS,
    weekdayOf,
} from '@/lib/recurrence';
import type {
    CalendarEvent,
    FamilyMember,
    RecurrenceEnd,
    RecurrenceFrequency,
    WeekdayCode,
} from '@/types';

type Props = {
    /** The event to show, or null to create a new one. */
    event: CalendarEvent | null;
    /** Where a new event starts, e.g. "2026-11-03T16:00". */
    defaultStart: string;
    membersById: Map<number, FamilyMember>;
};

const props = defineProps<Props>();

const open = defineModel<boolean>('open', { required: true });

const page = usePage();
const currentUser = computed(() => page.props.auth.user);

const owner = computed(() =>
    props.membersById.get(props.event?.user_id ?? currentUser.value.id),
);
const canEdit = computed(
    () => props.event === null || props.event.user_id === currentUser.value.id,
);

const selectClass =
    'h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

const form = useForm({
    title: '',
    notes: '',
    starts_at: '',
    ends_at: '',
    repeats: 'never' as RecurrenceFrequency | 'never',
    repeat_interval: 1,
    repeat_on: [] as WeekdayCode[],
    repeat_ends: 'never' as RecurrenceEnd,
    repeat_until: '',
    repeat_count: 1,
});

const confirmingDelete = ref(false);
const deleting = ref(false);

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    const start = parseWallClock(props.defaultStart);
    const recurrence = props.event?.recurrence ?? null;

    form.defaults(
        props.event
            ? {
                  title: props.event.title,
                  notes: props.event.notes ?? '',
                  starts_at: props.event.series_starts_at,
                  ends_at: props.event.series_ends_at,
                  repeats: recurrence?.frequency ?? 'never',
                  repeat_interval: recurrence?.interval ?? 1,
                  repeat_on: recurrence?.days ?? [],
                  repeat_ends: recurrence?.ends ?? 'never',
                  repeat_until: recurrence?.until ?? '',
                  repeat_count: recurrence?.count ?? 1,
              }
            : {
                  title: '',
                  notes: '',
                  starts_at: toDateTimeKey(start),
                  ends_at: toDateTimeKey(addMinutes(start, 60)),
                  repeats: 'never',
                  repeat_interval: 1,
                  repeat_on: [weekdayOf(toDateTimeKey(start))],
                  repeat_ends: 'never',
                  repeat_until: '',
                  repeat_count: 1,
              },
    );
    form.reset();
    form.clearErrors();
    confirmingDelete.value = false;
});

/** Default a weekly series to the day it starts on. */
watch(
    () => form.repeats,
    (repeats) => {
        if (repeats === 'weekly' && form.repeat_on.length === 0) {
            form.repeat_on = [weekdayOf(form.starts_at)];
        }
    },
);

/**
 * Moving the start keeps the event's length, the way paper calendars "slide" a block.
 */
function updateStart(value: string | number): void {
    const startsAt = String(value);

    if (startsAt === '' || form.starts_at === '' || form.ends_at === '') {
        form.starts_at = startsAt;

        return;
    }

    const duration = minutesBetween(
        parseWallClock(form.starts_at),
        parseWallClock(form.ends_at),
    );

    form.starts_at = startsAt;
    form.ends_at = toDateTimeKey(
        addMinutes(parseWallClock(startsAt), duration > 0 ? duration : 60),
    );
}

function toggleWeekday(code: WeekdayCode): void {
    form.repeat_on = form.repeat_on.includes(code)
        ? form.repeat_on.filter((day) => day !== code)
        : [...form.repeat_on, code];
}

function submit(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    };

    if (props.event) {
        form.submit(update(props.event.id), options);
    } else {
        form.submit(store(), options);
    }
}

function deleteEvent(): void {
    if (!props.event) {
        return;
    }

    if (!confirmingDelete.value) {
        confirmingDelete.value = true;

        return;
    }

    router.delete(destroy.url(props.event.id), {
        preserveScroll: true,
        onStart: () => (deleting.value = true),
        onFinish: () => (deleting.value = false),
        onSuccess: () => (open.value = false),
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            class="max-h-[calc(100svh-2rem)] overflow-y-auto sm:max-w-md"
        >
            <form v-if="canEdit" class="grid gap-5" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>
                        {{ event ? 'Edit event' : 'New event' }}
                    </DialogTitle>
                    <DialogDescription class="flex items-center gap-2">
                        <span
                            class="size-2.5 rounded-full"
                            :style="{ backgroundColor: owner?.color }"
                        />
                        On your calendar
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="event-title">Title</Label>
                    <Input
                        id="event-title"
                        v-model="form.title"
                        required
                        maxlength="255"
                        placeholder="Soccer practice"
                    />
                    <InputError :message="form.errors.title" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="event-starts-at">Starts</Label>
                        <Input
                            id="event-starts-at"
                            type="datetime-local"
                            required
                            :model-value="form.starts_at"
                            @update:model-value="updateStart"
                        />
                        <InputError :message="form.errors.starts_at" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="event-ends-at">Ends</Label>
                        <Input
                            id="event-ends-at"
                            v-model="form.ends_at"
                            type="datetime-local"
                            required
                            :min="form.starts_at"
                        />
                        <InputError :message="form.errors.ends_at" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="event-repeats">Repeats</Label>
                    <select
                        id="event-repeats"
                        v-model="form.repeats"
                        :class="[selectClass, 'w-full']"
                    >
                        <option value="never">Does not repeat</option>
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                        <option value="yearly">Yearly</option>
                    </select>
                    <InputError :message="form.errors.repeats" />
                </div>

                <div
                    v-if="form.repeats !== 'never'"
                    class="grid gap-4 rounded-md border bg-muted/20 p-3"
                >
                    <div class="grid gap-2">
                        <div class="flex flex-wrap items-center gap-2 text-sm">
                            <span class="text-muted-foreground">Every</span>
                            <Input
                                v-model.number="form.repeat_interval"
                                type="number"
                                min="1"
                                max="99"
                                class="w-20"
                            />
                            <span>
                                {{
                                    repeatUnit(
                                        form.repeats,
                                        form.repeat_interval,
                                    )
                                }}
                            </span>
                        </div>
                        <InputError :message="form.errors.repeat_interval" />
                    </div>

                    <div v-if="form.repeats === 'weekly'" class="grid gap-2">
                        <Label>On</Label>
                        <div
                            class="flex flex-wrap gap-1"
                            role="group"
                            aria-label="Days to repeat on"
                        >
                            <button
                                v-for="day in WEEKDAY_OPTIONS"
                                :key="day.code"
                                type="button"
                                class="size-8 cursor-pointer rounded-full border text-xs font-medium transition"
                                :class="
                                    form.repeat_on.includes(day.code)
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'hover:bg-muted'
                                "
                                :aria-pressed="
                                    form.repeat_on.includes(day.code)
                                "
                                :title="day.long"
                                @click="toggleWeekday(day.code)"
                            >
                                {{ day.short }}
                            </button>
                        </div>
                        <InputError :message="form.errors.repeat_on" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="event-repeat-ends">Ends</Label>
                        <div class="flex flex-wrap items-center gap-2">
                            <select
                                id="event-repeat-ends"
                                v-model="form.repeat_ends"
                                :class="[selectClass, 'w-auto']"
                            >
                                <option value="never">Never</option>
                                <option value="on">On a date</option>
                                <option value="after">After</option>
                            </select>
                            <Input
                                v-if="form.repeat_ends === 'on'"
                                v-model="form.repeat_until"
                                type="date"
                                class="w-44"
                            />
                            <template v-else-if="form.repeat_ends === 'after'">
                                <Input
                                    v-model.number="form.repeat_count"
                                    type="number"
                                    min="1"
                                    max="999"
                                    class="w-20"
                                />
                                <span class="text-sm text-muted-foreground">
                                    occurrences
                                </span>
                            </template>
                        </div>
                        <InputError :message="form.errors.repeat_ends" />
                        <InputError :message="form.errors.repeat_until" />
                        <InputError :message="form.errors.repeat_count" />
                    </div>

                    <p
                        v-if="event?.recurrence"
                        class="text-xs text-muted-foreground"
                    >
                        Changes apply to every occurrence.
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="event-notes">
                        Notes
                        <span class="font-normal text-muted-foreground">
                            (optional)
                        </span>
                    </Label>
                    <Textarea
                        id="event-notes"
                        v-model="form.notes"
                        rows="3"
                        maxlength="5000"
                        placeholder="Bring shin guards"
                    />
                    <InputError :message="form.errors.notes" />
                </div>

                <DialogFooter class="gap-2 sm:justify-between">
                    <Button
                        v-if="event"
                        type="button"
                        variant="ghost"
                        class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                        :disabled="deleting"
                        @click="deleteEvent"
                    >
                        <Spinner v-if="deleting" />
                        <Trash2 v-else />
                        {{
                            confirmingDelete ? 'Tap again to delete' : 'Delete'
                        }}
                    </Button>
                    <span v-else />

                    <div class="flex flex-col-reverse gap-2 sm:flex-row">
                        <DialogClose as-child>
                            <Button type="button" variant="secondary">
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            :disabled="form.processing"
                            data-test="save-event-button"
                        >
                            <Spinner v-if="form.processing" />
                            {{ event ? 'Save changes' : 'Add event' }}
                        </Button>
                    </div>
                </DialogFooter>
            </form>

            <div v-else-if="event" class="grid gap-4">
                <DialogHeader>
                    <DialogTitle class="pr-6">{{ event.title }}</DialogTitle>
                    <DialogDescription>
                        {{
                            formatEventRange(
                                parseWallClock(event.starts_at),
                                parseWallClock(event.ends_at),
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>

                <p
                    v-if="event.recurrence"
                    class="rounded-md bg-muted/50 px-3 py-2 text-sm"
                >
                    {{ describeRecurrence(event.recurrence) }}
                </p>

                <div class="flex items-center gap-2 text-sm">
                    <span
                        class="size-2.5 rounded-full"
                        :style="{ backgroundColor: owner?.color }"
                    />
                    On {{ owner?.name }}'s calendar
                </div>

                <p
                    v-if="event.notes"
                    class="rounded-md bg-muted/50 px-3 py-2 text-sm whitespace-pre-line"
                >
                    {{ event.notes }}
                </p>

                <p class="text-xs text-muted-foreground">
                    Only {{ owner?.name }} can change this event.
                </p>
            </div>
        </DialogContent>
    </Dialog>
</template>
