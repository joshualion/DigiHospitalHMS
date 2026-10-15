<script setup>
import { usePublicTheme } from '@/Composables/usePublicTheme';
import { Moon, Sun } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps({
    defaults: { type: Object, default: () => ({}) },
});

const theme = usePublicTheme({ ...props.defaults, allowAccentSwitching: false });
const isDark = computed(() => theme.resolvedAppearance.value === 'dark');

function toggleAppearance() {
    theme.setAppearance(isDark.value ? 'light' : 'dark');
}
</script>

<template>
    <button
        type="button"
        class="public-focus grid h-11 w-11 place-items-center rounded-full border transition"
        style="border-color: var(--public-border); background: var(--public-surface); color: var(--public-text);"
        :aria-label="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
        :title="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
        :aria-pressed="isDark"
        @click="toggleAppearance"
    >
        <Sun v-if="isDark" class="h-5 w-5" aria-hidden="true" />
        <Moon v-else class="h-5 w-5" aria-hidden="true" />
    </button>
</template>
