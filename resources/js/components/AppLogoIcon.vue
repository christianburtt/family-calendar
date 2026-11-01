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
    <!--
        The mark is transparent ink rather than a single currentColor glyph, so it
        reads on both light and dark backgrounds without a variant. Callers only
        pick a size, e.g. class="size-5".
    -->
    <img
        src="/logo.png"
        alt=""
        :class="className"
        v-bind="$attrs"
        draggable="false"
    />
</template>
