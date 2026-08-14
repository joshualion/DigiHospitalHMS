<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';

defineProps({
    pages: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <Head title="CMS Pages" />
    <AppLayout title="CMS Pages">
        <div class="rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 p-5 dark:border-slate-800">
                <h2 class="text-lg font-semibold">CMS deferred</h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Existing CMS records are preserved. Editing is disabled during Phase 0.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead>
                        <tr class="text-left text-slate-500">
                            <th class="px-5 py-3">Title</th>
                            <th class="px-5 py-3">Slug</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="page in pages.data" :key="page.id">
                            <td class="px-5 py-3">{{ page.title }}</td>
                            <td class="px-5 py-3">{{ page.slug }}</td>
                            <td class="px-5 py-3">{{ page.is_active ? 'Active' : 'Inactive' }}</td>
                            <td class="px-5 py-3 text-right">
                                <Link :href="`/admin/pages/${page.id}/edit`" class="text-red-800 dark:text-red-300">View</Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
