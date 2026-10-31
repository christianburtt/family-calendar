<script setup lang="ts">
import { Check } from '@lucide/vue';
import { computed } from 'vue';
import { familyColors } from '@/lib/colors';

type Props = {
    id?: string;
    name?: string;
    takenColors?: string[];
    tabindex?: number;
};

const props = withDefaults(defineProps<Props>(), {
    id: undefined,
    name: 'color',
    takenColors: () => [],
    tabindex: undefined,
});

const color = defineModel<string>({ required: true });

const taken = computed(
    () => new Set(props.takenColors.map((value) => value.toLowerCase())),
);

const isSelected = (value: string): boolean =>
    color.value.toLowerCase() === value;

const isCustom = computed(
    () => !familyColors.some((swatch) => isSelected(swatch.value)),
);
</script>

<template>
    <div class="grid gap-2">
        <input type="hidden" :name="name" :value="color" />

        <div
            :id="id"
            role="radiogroup"
            aria-label="Calendar color"
            class="flex flex-wrap items-center gap-2"
        >
            <button
                v-for="swatch in familyColors"
                :key="swatch.value"
                type="button"
                role="radio"
                :aria-checked="isSelected(swatch.value)"
                :aria-label="swatch.name"
                :tabindex="tabindex"
                :title="
                    taken.has(swatch.value)
                        ? `${swatch.name} (already used in your family)`
                        : swatch.name
                "
                class="flex size-8 cursor-pointer items-center justify-center rounded-full ring-offset-2 ring-offset-background transition outline-none hover:scale-110 focus-visible:ring-2 focus-visible:ring-ring"
                :class="{
                    'ring-2 ring-foreground': isSelected(swatch.value),
                    'opacity-35':
                        taken.has(swatch.value) && !isSelected(swatch.value),
                }"
                :style="{ backgroundColor: swatch.value }"
                @click="color = swatch.value"
            >
                <Check
                    v-if="isSelected(swatch.value)"
                    class="size-4 text-white"
                    :stroke-width="3"
                />
            </button>

            <label
                title="Pick a custom color"
                class="relative flex size-8 cursor-pointer items-center justify-center rounded-full ring-offset-2 ring-offset-background transition hover:scale-110 has-focus-visible:ring-2 has-focus-visible:ring-ring"
                :class="{ 'ring-2 ring-foreground': isCustom }"
                :style="
                    isCustom
                        ? { backgroundColor: color }
                        : {
                              background:
                                  'conic-gradient(#dc2626, #d97706, #16a34a, #0284c7, #7c3aed, #db2777, #dc2626)',
                          }
                "
            >
                <input
                    v-model="color"
                    type="color"
                    class="sr-only"
                    :tabindex="tabindex"
                    aria-label="Custom color"
                />
                <Check
                    v-if="isCustom"
                    class="size-4 text-white"
                    :stroke-width="3"
                />
            </label>
        </div>

        <p v-if="taken.size > 0" class="text-xs text-muted-foreground">
            Faded colors are already used by someone in your family.
        </p>
    </div>
</template>
