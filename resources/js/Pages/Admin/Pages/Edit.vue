<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';

defineProps({
    page: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <Head :title="`CMS: ${page.title}`" />
    <AppLayout title="CMS Page">
        <div class="rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-lg font-semibold">{{ page.title }}</h2>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Editing is deferred during Phase 0. Existing page, section, and block data is preserved.</p>
        </div>
    </AppLayout>
</template>
