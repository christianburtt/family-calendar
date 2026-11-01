<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Check, Copy, RefreshCw, UserMinus, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { calendar as calendarRoute } from '@/routes';
import { update as updateCalendar } from '@/routes/calendars';
import { update as rotateJoinCode } from '@/routes/calendars/join-code';
import {
    approve,
    destroy as removeMembership,
    leave,
} from '@/routes/memberships';
import type { CalendarMemberRecord } from '@/types';

type Props = {
    calendar: {
        id: number;
        name: string;
        join_code: string;
        join_url: string;
        can_manage: boolean;
        is_owner: boolean;
    };
    members: CalendarMemberRecord[];
    pending: CalendarMemberRecord[];
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Calendar',
                href: calendarRoute(),
            },
            {
                title: 'People',
                href: calendarRoute(),
            },
        ],
    },
});

const page = usePage();
const currentUserId = computed(() => page.props.auth.user.id);

const form = useForm({
    name: props.calendar.name,
});

function rename(): void {
    form.submit(updateCalendar(props.calendar.id), { preserveScroll: true });
}

function rotate(): void {
    const confirmed = window.confirm(
        'Generate a new join code? The current one will stop working.',
    );

    if (!confirmed) {
        return;
    }

    router.put(
        rotateJoinCode(props.calendar.id).url,
        {},
        { preserveScroll: true },
    );
}

function approveMember(member: CalendarMemberRecord): void {
    router.patch(
        approve({
            calendar: props.calendar.id,
            membership: member.id,
        }).url,
        {},
        { preserveScroll: true },
    );
}

function removeMember(member: CalendarMemberRecord): void {
    router.delete(
        removeMembership({
            calendar: props.calendar.id,
            membership: member.id,
        }).url,
        { preserveScroll: true },
    );
}

function leaveCalendar(): void {
    const confirmed = window.confirm(`Leave ${props.calendar.name}?`);

    if (!confirmed) {
        return;
    }

    router.delete(leave(props.calendar.id).url);
}

const copied = ref<string | null>(null);

async function copy(value: string, key: string): Promise<void> {
    await navigator.clipboard.writeText(value);

    copied.value = key;

    window.setTimeout(() => {
        if (copied.value === key) {
            copied.value = null;
        }
    }, 2000);
}
</script>

<template>
    <Head :title="`People on ${calendar.name}`" />

    <div class="mx-auto flex w-full max-w-2xl flex-col gap-8 p-4">
        <div class="space-y-1">
            <h1 class="text-xl font-semibold tracking-tight">
                {{ calendar.name }}
            </h1>
            <p class="text-sm text-muted-foreground">
                Share the join code with family, then approve them here.
            </p>
        </div>

        <section class="space-y-4 rounded-lg border p-4">
            <div class="space-y-1">
                <h2 class="text-sm font-medium">Join code</h2>
                <p class="text-xs text-muted-foreground">
                    Three words, separated by hyphens. Anyone with this code can
                    ask to join, but nobody gets in until you approve them.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <code
                    class="rounded-md bg-muted px-3 py-1.5 font-mono text-sm select-all"
                    data-test="join-code"
                >
                    {{ calendar.join_code }}
                </code>
                <Button
                    variant="outline"
                    size="sm"
                    @click="copy(calendar.join_code, 'code')"
                >
                    <Check v-if="copied === 'code'" />
                    <Copy v-else />
                    {{ copied === 'code' ? 'Copied' : 'Copy code' }}
                </Button>
                <Button
                    v-if="calendar.can_manage"
                    variant="ghost"
                    size="sm"
                    @click="rotate"
                >
                    <RefreshCw />
                    New code
                </Button>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Input
                    :model-value="calendar.join_url"
                    readonly
                    class="min-w-0 flex-1 font-mono text-xs"
                    data-test="join-url"
                />
                <Button
                    variant="outline"
                    size="sm"
                    @click="copy(calendar.join_url, 'url')"
                >
                    <Check v-if="copied === 'url'" />
                    <Copy v-else />
                    {{ copied === 'url' ? 'Copied' : 'Copy link' }}
                </Button>
            </div>
        </section>

        <section
            v-if="calendar.can_manage && pending.length > 0"
            class="space-y-3 rounded-lg border p-4"
            data-test="pending-requests"
        >
            <h2 class="text-sm font-medium">Waiting for approval</h2>

            <ul class="space-y-2">
                <li
                    v-for="member in pending"
                    :key="member.id"
                    class="flex items-center justify-between gap-3"
                >
                    <span class="flex items-center gap-2 text-sm">
                        <span
                            class="size-2.5 rounded-full"
                            :style="{ backgroundColor: member.color }"
                        />
                        {{ member.name }}
                    </span>
                    <span class="flex items-center gap-1">
                        <Button size="sm" @click="approveMember(member)">
                            <Check />
                            Approve
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            @click="removeMember(member)"
                        >
                            <X />
                            Decline
                        </Button>
                    </span>
                </li>
            </ul>
        </section>

        <section class="space-y-3 rounded-lg border p-4">
            <h2 class="text-sm font-medium">
                On this calendar ({{ members.length }})
            </h2>

            <ul class="space-y-2">
                <li
                    v-for="member in members"
                    :key="member.id"
                    class="flex items-center justify-between gap-3"
                >
                    <span class="flex items-center gap-2 text-sm">
                        <span
                            class="size-2.5 rounded-full"
                            :style="{ backgroundColor: member.color }"
                        />
                        {{ member.name }}
                        <span
                            v-if="member.user_id === currentUserId"
                            class="text-xs text-muted-foreground"
                        >
                            (you)
                        </span>
                        <span
                            v-if="member.role === 'owner'"
                            class="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground"
                        >
                            Owner
                        </span>
                    </span>

                    <Button
                        v-if="
                            calendar.can_manage &&
                            member.role !== 'owner' &&
                            member.user_id !== currentUserId
                        "
                        variant="ghost"
                        size="sm"
                        @click="removeMember(member)"
                    >
                        <UserMinus />
                        Remove
                    </Button>
                </li>
            </ul>
        </section>

        <section
            v-if="calendar.can_manage"
            class="space-y-3 rounded-lg border p-4"
        >
            <h2 class="text-sm font-medium">Calendar name</h2>

            <form class="flex flex-wrap items-end gap-2" @submit.prevent="rename">
                <div class="grid min-w-0 flex-1 gap-2">
                    <Label for="calendar-name" class="sr-only">
                        Calendar name
                    </Label>
                    <Input
                        id="calendar-name"
                        v-model="form.name"
                        name="name"
                        required
                        autocomplete="off"
                    />
                    <InputError :message="form.errors.name" />
                </div>
                <Button type="submit" :disabled="form.processing">
                    Save
                </Button>
            </form>
        </section>

        <section
            v-if="!calendar.is_owner"
            class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-destructive/30 p-4"
        >
            <div class="space-y-1">
                <h2 class="text-sm font-medium">Leave this calendar</h2>
                <p class="text-xs text-muted-foreground">
                    Your events stay on the calendar after you leave.
                </p>
            </div>
            <Button variant="outline" @click="leaveCalendar">
                <UserMinus />
                Leave
            </Button>
        </section>

        <div>
            <Button variant="ghost" as-child>
                <Link :href="calendarRoute()">Back to calendar</Link>
            </Button>
        </div>
    </div>
</template>
