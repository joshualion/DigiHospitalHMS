<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';

defineProps({
    sequences: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="Numbering" />
    <AppLayout title="Numbering Sequences">
        <div v-if="sequences.length" class="grid gap-4 xl:grid-cols-2">
            <div v-for="sequence in sequences" :key="sequence.id" class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold">{{ sequence.label }}</h2>
                        <p class="text-sm text-slate-500">{{ sequence.key }} - Preview {{ sequence.preview }}</p>
                        <p class="text-sm text-slate-500">Issued: {{ sequence.issued_count }}</p>
                    </div>
                    <span class="rounded bg-slate-100 px-2 py-1 text-xs dark:bg-slate-800">{{ sequence.status }}</span>
                </div>
                <div class="mt-4 text-sm text-slate-600 dark:text-slate-300">Configuration is protected after numbers have been issued.</div>
            </div>
        </div>
        <div v-else class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900">
            No numbering sequences have been configured yet.
        </div>
    </AppLayout>
</template>
