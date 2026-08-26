<script setup>
import { usePage } from '@inertiajs/vue3';
import { CheckCircle2, CircleAlert, Info, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

const page = usePage();
const toasts = ref([]);
let nextId = 1;

const icons = {
    success: CheckCircle2,
    error: CircleAlert,
    info: Info,
};

const errorsSignature = computed(() => JSON.stringify(page.props.errors || {}));

function remove(id) {
    toasts.value = toasts.value.filter((toast) => toast.id !== id);
}

function pushToast(type, message) {
    if (!message) return;

    const id = nextId++;
    toasts.value.push({ id, type, message });
    window.setTimeout(() => remove(id), type === 'error' ? 6500 : 4200);
}

watch(
    () => page.props.flash?.success,
    (message) => pushToast('success', message),
    { immediate: true },
);

watch(
    () => page.props.flash?.status,
    (message) => pushToast('info', message),
    { immediate: true },
);

watch(errorsSignature, (signature) => {
    const errors = JSON.parse(signature || '{}');
    const first = Object.values(errors)[0];
    if (first) pushToast('error', Array.isArray(first) ? first[0] : first);
});
</script>

<template>
    <Teleport to="body">
        <div class="admin-theme pointer-events-none fixed right-4 top-4 z-[100] grid w-[calc(100vw-2rem)] max-w-sm gap-3 sm:right-6 sm:top-6" aria-live="polite" aria-atomic="true">
            <div
                v-for="toast in toasts"
                :key="toast.id"
                class="pointer-events-auto grid grid-cols-[auto_1fr_auto] items-start gap-3 rounded-md border p-4 shadow-xl"
                :style="toast.type === 'error'
                    ? 'background: color-mix(in srgb, var(--public-danger) 12%, var(--admin-surface)); border-color: color-mix(in srgb, var(--public-danger) 44%, var(--admin-border)); color: var(--admin-text);'
                    : toast.type === 'success'
                        ? 'background: color-mix(in srgb, var(--public-success) 12%, var(--admin-surface)); border-color: color-mix(in srgb, var(--public-success) 44%, var(--admin-border)); color: var(--admin-text);'
                        : 'background: var(--admin-surface); border-color: var(--admin-border); color: var(--admin-text);'"
                role="status"
            >
                <component :is="icons[toast.type] || Info" class="mt-0.5 h-5 w-5 shrink-0" :style="toast.type === 'error' ? 'color: var(--public-danger);' : toast.type === 'success' ? 'color: var(--public-success);' : 'color: var(--public-info);'" />
                <p class="text-sm font-semibold leading-5">{{ toast.message }}</p>
                <button class="rounded-md p-1" type="button" aria-label="Dismiss notification" @click="remove(toast.id)">
                    <X class="h-4 w-4" />
                </button>
            </div>
        </div>
    </Teleport>
</template>
