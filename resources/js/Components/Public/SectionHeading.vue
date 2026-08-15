<script setup>
defineProps({ kicker: String, title: String, description: String, align: { type: String, default: 'center' } });
</script>
<template>
    <div :class="align === 'center' ? 'mx-auto text-center' : ''" class="max-w-3xl">
        <p v-if="kicker" class="public-kicker">{{ kicker }}</p>
        <h2 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl lg:text-5xl" style="color: var(--public-text);">{{ title }}</h2>
        <p v-if="description" class="mx-auto mt-4 max-w-2xl text-base leading-8" style="color: var(--public-text-secondary);">{{ description }}</p>
    </div>
</template>