<script setup>
import { usePublicTheme } from '@/Composables/usePublicTheme';
import { Check, Monitor, Moon, Palette, Sun } from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';

const props = defineProps({ defaults: { type: Object, default: () => ({}) } });
const open = ref(false);
const root = ref(null);
const theme = usePublicTheme(props.defaults);
const appearanceOptions = [
    { value: 'light', label: 'Light', icon: Sun },
    { value: 'dark', label: 'Dark', icon: Moon },
    { value: 'system', label: 'System', icon: Monitor },
];
const accentLabels = {
    calm: 'Calm',
    healing: 'Healing',
    alert: 'Alert',
    blood: 'Blood',
    seagrass: 'Seagrass',
};
const currentLabel = computed(() => `${appearanceOptions.find((item) => item.value === theme.appearance.value)?.label || 'System'}, ${accentLabels[theme.accent.value]}`);

function close() {
    open.value = false;
}

function toggle() {
    open.value = !open.value;
}

function onPointerDown(event) {
    if (open.value && root.value && !root.value.contains(event.target)) {
        close();
    }
}

function onKeydown(event) {
    if (event.key === 'Escape') close();
}

if (typeof window !== 'undefined') {
    window.addEventListener('pointerdown', onPointerDown);
    window.addEventListener('keydown', onKeydown);
}

onBeforeUnmount(() => {
    window.removeEventListener('pointerdown', onPointerDown);
    window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div v-if="theme.switcherVisible.value" ref="root" class="relative">
        <button type="button" class="public-focus grid h-11 w-11 place-items-center rounded-full border text-sm font-bold transition" style="border-color: var(--public-border); background: var(--public-surface); color: var(--public-text);" :aria-expanded="open" aria-label="Open theme settings" @click="toggle">
            <Palette class="h-4 w-4" aria-hidden="true" />
            <span class="sr-only">{{ currentLabel }}</span>
        </button>
        <div v-if="open" class="absolute right-0 z-50 mt-3 w-72 rounded-2xl border p-4 shadow-2xl" style="background: var(--public-surface-elevated); border-color: var(--public-border); color: var(--public-text);">
            <p class="text-sm font-black">Appearance</p>
            <div class="mt-3 grid grid-cols-3 gap-2">
                <button v-for="option in appearanceOptions" :key="option.value" type="button" class="public-focus rounded-xl border px-2 py-3 text-xs font-bold" :style="theme.appearance.value === option.value ? 'border-color: var(--public-accent); background: var(--public-accent-soft); color: var(--public-text);' : 'border-color: var(--public-border);'" @click="theme.setAppearance(option.value)">
                    <component :is="option.icon" class="mx-auto mb-1 h-4 w-4" aria-hidden="true" />
                    {{ option.label }}
                </button>
            </div>
            <p class="mt-5 text-sm font-black">Accent</p>
            <div class="mt-3 grid gap-2">
                <button v-for="value in theme.allowedAccents.value" :key="value" type="button" class="public-focus flex items-center justify-between rounded-xl border px-3 py-2 text-sm font-bold" :style="theme.accent.value === value ? 'border-color: var(--public-accent); background: var(--public-accent-soft); color: var(--public-text);' : 'border-color: var(--public-border);'" @click="theme.setAccent(value)">
                    <span>{{ accentLabels[value] }}</span>
                    <Check v-if="theme.accent.value === value" class="h-4 w-4" aria-hidden="true" />
                </button>
            </div>
        </div>
    </div>
</template>
