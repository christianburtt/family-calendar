<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { calendar } from '@/routes';
import {
    create as createCalendar,
    store as storeCalendar,
} from '@/routes/calendars';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Calendar',
                href: calendar(),
            },
            {
                title: 'New calendar',
                href: createCalendar(),
            },
        ],
    },
});

const form = useForm({
    name: '',
});

function submit(): void {
    form.submit(storeCalendar());
}
</script>

<template>
    <Head title="New calendar" />

    <div class="mx-auto flex w-full max-w-md flex-col gap-6 p-4">
        <div class="space-y-1">
            <h1 class="text-xl font-semibold tracking-tight">New calendar</h1>
            <p class="text-sm text-muted-foreground">
                Give your calendar a name. You can share it with other people
                once it exists.
            </p>
        </div>

        <form class="space-y-4" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="name">Calendar name</Label>
                <Input
                    id="name"
                    v-model="form.name"
                    name="name"
                    placeholder="Burtt Family"
                    required
                    autofocus
                    autocomplete="off"
                />
                <InputError :message="form.errors.name" />
            </div>

            <div class="flex items-center gap-2">
                <Button type="submit" :disabled="form.processing">
                    Create calendar
                </Button>
                <Button variant="ghost" as-child>
                    <Link :href="calendar()">
                        <ArrowLeft />
                        Cancel
                    </Link>
                </Button>
            </div>
        </form>
    </div>
</template>
