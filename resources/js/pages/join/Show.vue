<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { CalendarDays, Clock, TriangleAlert } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { calendar as calendarRoute } from '@/routes';
import { store as storeJoin } from '@/routes/join';
import type { CalendarSummary } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Calendar',
                href: calendarRoute(),
            },
            {
                title: 'Join a calendar',
                href: calendarRoute(),
            },
        ],
    },
});

const props = defineProps<{
    code: string;
    calendar: CalendarSummary | null;
    /** The viewer's existing membership of this calendar, if they have one. */
    status: 'active' | 'pending' | null;
}>();

const form = useForm({
    code: props.code,
});

function join(): void {
    form.submit(storeJoin(props.code));
}
</script>

<template>
    <Head title="Join a calendar" />

    <div class="mx-auto flex w-full max-w-md flex-col gap-6 p-4">
        <div
            v-if="calendar === null"
            class="space-y-4 rounded-lg border p-6 text-center"
            data-test="join-code-unknown"
        >
            <TriangleAlert class="mx-auto size-6 text-muted-foreground" />
            <div class="space-y-1">
                <h1 class="text-lg font-semibold tracking-tight">
                    That code did not work
                </h1>
                <p class="text-sm text-muted-foreground">
                    Check the code with whoever sent it to you, then try again
                    from your calendar.
                </p>
            </div>
            <Button as-child>
                <Link :href="calendarRoute()">Go to my calendar</Link>
            </Button>
        </div>

        <div
            v-else-if="status === 'active'"
            class="space-y-4 rounded-lg border p-6 text-center"
            data-test="join-already-member"
        >
            <CalendarDays class="mx-auto size-6 text-muted-foreground" />
            <div class="space-y-1">
                <h1 class="text-lg font-semibold tracking-tight">
                    You are already on {{ calendar.name }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    Open it to see what everyone has planned.
                </p>
            </div>
            <Button as-child>
                <Link :href="calendarRoute()">Open {{ calendar.name }}</Link>
            </Button>
        </div>

        <div
            v-else-if="status === 'pending'"
            class="space-y-4 rounded-lg border p-6 text-center"
            data-test="join-pending"
        >
            <Clock class="mx-auto size-6 text-muted-foreground" />
            <div class="space-y-1">
                <h1 class="text-lg font-semibold tracking-tight">
                    Waiting for approval
                </h1>
                <p class="text-sm text-muted-foreground">
                    Someone on {{ calendar.name }} still needs to approve you.
                </p>
            </div>
            <Button variant="outline" as-child>
                <Link :href="calendarRoute()">Go to my calendar</Link>
            </Button>
        </div>

        <div
            v-else
            class="space-y-4 rounded-lg border p-6"
            data-test="join-confirm"
        >
            <div class="space-y-1">
                <h1 class="text-lg font-semibold tracking-tight">
                    Join {{ calendar.name }}?
                </h1>
                <p class="text-sm text-muted-foreground">
                    You will be able to see everyone's events. Someone on the
                    calendar has to approve you first.
                </p>
            </div>

            <InputError :message="form.errors.code" />

            <div class="flex items-center gap-2">
                <Button :disabled="form.processing" @click="join">
                    Ask to join
                </Button>
                <Button variant="ghost" as-child>
                    <Link :href="calendarRoute()">Not now</Link>
                </Button>
            </div>
        </div>
    </div>
</template>
