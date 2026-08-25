<script setup>
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
});
</script>

<template>
    <header class="mb-5 flex min-w-0 flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <h1 class="text-2xl font-black tracking-normal">{{ title }}</h1>
            <p v-if="description" class="mt-1 max-w-3xl text-sm" style="color: var(--admin-text-muted);">{{ description }}</p>
        </div>
        <div v-if="$slots.actions" class="shrink-0">
            <slot name="actions" />
        </div>
    </header>
</template>
