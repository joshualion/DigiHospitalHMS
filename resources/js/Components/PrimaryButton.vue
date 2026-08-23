<script setup>
defineProps({
    disabled: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <button
        :disabled="disabled"
        class="public-focus inline-flex items-center justify-center rounded-md px-4 py-2 text-sm font-semibold shadow-sm transition disabled:cursor-not-allowed disabled:opacity-60"
        style="background: var(--public-accent); color: var(--public-accent-foreground);"
    >
        <slot />
    </button>
</template>
