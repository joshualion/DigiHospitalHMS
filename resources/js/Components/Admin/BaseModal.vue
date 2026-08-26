<script setup>
import { X } from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: '' },
    description: { type: String, default: '' },
    size: { type: String, default: 'lg' },
    closeOnBackdrop: { type: Boolean, default: true },
    closeOnEscape: { type: Boolean, default: true },
    busy: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);
const panel = ref(null);
const previousActive = ref(null);
let priorOverflow = '';

const sizeClass = computed(() => ({
    sm: 'sm:max-w-md',
    md: 'sm:max-w-xl',
    lg: 'sm:max-w-2xl',
    xl: 'sm:max-w-4xl',
    full: 'sm:max-w-6xl',
}[props.size] || 'sm:max-w-2xl'));

function focusableElements() {
    return Array.from(panel.value?.querySelectorAll('a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])') || []);
}

function requestClose() {
    if (!props.busy) emit('close');
}

function onKeydown(event) {
    if (!props.show) return;
    if (event.key === 'Escape' && props.closeOnEscape && !props.busy) {
        event.preventDefault();
        requestClose();
        return;
    }
    if (event.key !== 'Tab') return;

    const items = focusableElements();
    if (items.length === 0) {
        event.preventDefault();
        panel.value?.focus();
        return;
    }

    const first = items[0];
    const last = items[items.length - 1];
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}

watch(() => props.show, async (visible) => {
    if (visible) {
        previousActive.value = document.activeElement;
        priorOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        await nextTick();
        focusableElements()[0]?.focus() || panel.value?.focus();
    } else {
        document.body.style.overflow = priorOverflow;
        previousActive.value?.focus?.();
    }
});

onMounted(() => document.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = priorOverflow;
});
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="admin-theme admin-modal-root fixed inset-0 z-[80] overflow-hidden" role="presentation">
            <div class="absolute inset-0 backdrop-blur-sm" style="background: color-mix(in srgb, #020817 66%, transparent);" @click="closeOnBackdrop && requestClose()"></div>
            <div class="relative flex min-h-dvh items-end justify-center p-0 sm:items-center sm:p-4">
                <section
                    ref="panel"
                    class="admin-modal-panel relative flex max-h-dvh w-full flex-col rounded-t-lg border shadow-2xl outline-none sm:max-h-[88vh] sm:rounded-lg"
                    :class="sizeClass"
                    style="background: var(--admin-surface); border-color: var(--admin-border); color: var(--admin-text);"
                    role="dialog"
                    aria-modal="true"
                    tabindex="-1"
                    @click.stop
                >
                    <header class="flex shrink-0 items-start justify-between gap-4 border-b px-4 py-4 sm:px-5" style="background: var(--admin-surface); border-color: var(--admin-border);">
                        <div class="min-w-0">
                            <h2 class="text-lg font-black">{{ title }}</h2>
                            <p v-if="description" class="mt-1 text-sm" style="color: var(--admin-text-muted);">{{ description }}</p>
                        </div>
                        <button class="grid h-10 w-10 shrink-0 place-items-center rounded-md border disabled:opacity-60" style="background: var(--public-input); border-color: var(--admin-border); color: var(--admin-text);" type="button" aria-label="Close dialog" :disabled="busy" @click="requestClose">
                            <X class="h-4 w-4" />
                        </button>
                    </header>
                    <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-5" style="background: var(--admin-surface);">
                        <slot />
                    </div>
                    <footer v-if="$slots.footer" class="shrink-0 border-t px-4 py-4 sm:px-5" style="background: var(--admin-surface); border-color: var(--admin-border);">
                        <slot name="footer" />
                    </footer>
                </section>
            </div>
        </div>
    </Teleport>
</template>
