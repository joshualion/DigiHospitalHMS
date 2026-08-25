<script setup>
defineProps({
    align: { type: String, default: 'start' },
});
</script>

<template>
    <div class="flex min-w-0 flex-wrap gap-2" :class="align === 'end' ? 'justify-end' : align === 'between' ? 'justify-between' : 'justify-start'">
        <slot />
    </div>
</template>
