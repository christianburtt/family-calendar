<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { computed } from 'vue';

type Props = {
    id?: string;
    name?: string;
    tabindex?: number;
};

withDefaults(defineProps<Props>(), {
    id: undefined,
    name: 'timezone',
    tabindex: undefined,
});

const timezone = defineModel<string>({ required: true });

const deviceTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;

const timezones = computed(() =>
    [
        ...new Set([
            ...Intl.supportedValuesOf('timeZone'),
            'UTC',
            deviceTimezone,
            timezone.value,
        ]),
    ]
        .filter(Boolean)
        .sort(),
);

const label = (value: string): string => value.replaceAll('_', ' ');
</script>

<template>
    <div class="grid gap-2">
        <div class="relative">
            <select
                :id="id"
                v-model="timezone"
                :name="name"
                :tabindex="tabindex"
                class="h-9 w-full min-w-0 appearance-none rounded-md border border-input bg-transparent py-1 pr-9 pl-3 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm dark:bg-input/30 dark:scheme-dark"
            >
                <option v-for="value in timezones" :key="value" :value="value">
                    {{ label(value) }}
                </option>
            </select>
            <ChevronDown
                class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 opacity-50"
            />
        </div>

        <button
            v-if="deviceTimezone && timezone !== deviceTimezone"
            type="button"
            :tabindex="tabindex"
            class="w-fit cursor-pointer text-left text-xs text-muted-foreground underline underline-offset-4 hover:text-foreground"
            @click="timezone = deviceTimezone"
        >
            Use this device's timezone ({{ label(deviceTimezone) }})
        </button>
    </div>
</template>
