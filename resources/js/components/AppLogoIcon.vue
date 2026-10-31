<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        :class="className"
        v-bind="$attrs"
    >
        <path
            fill="currentColor"
            fill-rule="evenodd"
            clip-rule="evenodd"
            d="M7 1a1 1 0 0 1 1 1v1h8V2a1 1 0 1 1 2 0v1h1a3 3 0 0 1 3 3v13a3 3 0 0 1-3 3H5a3 3 0 0 1-3-3V6a3 3 0 0 1 3-3h1V2a1 1 0 0 1 1-1Zm-3 9v9a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-9H4Z"
        />
        <rect x="6" y="12" width="3" height="3" rx="0.75" fill="currentColor" />
        <rect
            x="10.5"
            y="12"
            width="3"
            height="3"
            rx="0.75"
            fill="currentColor"
        />
        <rect
            x="15"
            y="12"
            width="3"
            height="3"
            rx="0.75"
            fill="currentColor"
        />
        <rect
            x="6"
            y="16"
            width="3"
            height="2.5"
            rx="0.75"
            fill="currentColor"
        />
        <rect
            x="10.5"
            y="16"
            width="3"
            height="2.5"
            rx="0.75"
            fill="currentColor"
        />
    </svg>
</template>
