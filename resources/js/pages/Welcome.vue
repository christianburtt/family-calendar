<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { eventColorStyle } from '@/lib/colors';
import { calendar, login, register } from '@/routes';

/** A static peek at the whiteboard, purely decorative. */
const preview = [
    { day: 'Mon', events: [{ color: '#2563eb', title: 'Swim' }] },
    {
        day: 'Tue',
        events: [
            { color: '#db2777', title: 'Piano' },
            { color: '#16a34a', title: 'Dentist' },
        ],
    },
    { day: 'Wed', events: [{ color: '#ea580c', title: 'Soccer' }] },
    {
        day: 'Thu',
        events: [
            { color: '#2563eb', title: 'Work trip' },
            { color: '#db2777', title: 'Book club' },
        ],
    },
    { day: 'Fri', events: [{ color: '#16a34a', title: 'Date night' }] },
    { day: 'Sat', events: [{ color: '#ea580c', title: 'Playdate' }] },
    { day: 'Sun', events: [] },
];
</script>

<template>
    <Head title="Welcome" />

    <div class="flex min-h-svh flex-col bg-background text-foreground">
        <header class="flex items-center justify-between px-6 py-4">
            <div class="flex items-center gap-2 font-semibold">
                <AppLogoIcon class="size-6" />
                {{ $page.props.name }}
            </div>
            <nav class="flex items-center gap-2">
                <Button v-if="$page.props.auth.user" as-child>
                    <Link :href="calendar()">Open calendar</Link>
                </Button>
                <template v-else>
                    <Button variant="ghost" as-child>
                        <Link :href="login()">Log in</Link>
                    </Button>
                    <Button as-child>
                        <Link :href="register()">Register</Link>
                    </Button>
                </template>
            </nav>
        </header>

        <main
            class="mx-auto flex w-full max-w-3xl flex-1 flex-col items-center justify-center gap-10 px-6 py-12 text-center"
        >
            <div class="flex flex-col items-center gap-4">
                <h1 class="text-4xl font-semibold tracking-tight sm:text-5xl">
                    Our week, at a glance.
                </h1>
                <p class="max-w-xl text-lg text-muted-foreground">
                    One shared calendar for the whole family, like the
                    whiteboard on the fridge. Everyone gets a color and adds
                    their own plans.
                </p>
            </div>

            <div
                class="grid w-full grid-cols-7 gap-px overflow-hidden rounded-xl border bg-border text-left shadow-sm"
                aria-hidden="true"
            >
                <div
                    v-for="column in preview"
                    :key="column.day"
                    class="flex min-h-32 flex-col gap-1 bg-background p-1.5 sm:p-2"
                >
                    <span class="text-xs font-medium text-muted-foreground">
                        {{ column.day }}
                    </span>
                    <span
                        v-for="event in column.events"
                        :key="event.title"
                        class="truncate rounded-sm border-l-[3px] px-1 py-0.5 text-[10px] sm:px-1.5 sm:text-xs"
                        :style="eventColorStyle(event.color)"
                    >
                        {{ event.title }}
                    </span>
                </div>
            </div>
        </main>
    </div>
</template>
