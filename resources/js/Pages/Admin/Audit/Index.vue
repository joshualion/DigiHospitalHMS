<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';

const props = defineProps({
    events: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const search = useForm({ action: props.filters.action || '', subject: props.filters.subject || '' });

function filter() {
    router.get('/admin/audit-logs', search.data(), { preserveState: true, replace: true });
}
</script>

<template>
    <Head title="Audit Logs" />
    <AppLayout title="Audit Logs">
        <section class="rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="grid gap-3 border-b border-slate-200 p-4 md:grid-cols-2 dark:border-slate-800">
                <input v-model="search.action" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900" placeholder="Filter by action" @change="filter">
                <input v-model="search.subject" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900" placeholder="Filter by subject" @change="filter">
            </div>
            <table class="min-w-full text-sm">
                <tbody>
                    <tr v-for="event in events.data" :key="event.id" class="border-b border-slate-100 dark:border-slate-800">
                        <td class="p-4"><strong>{{ event.action }}</strong><br><span class="text-slate-500">{{ event.subject_type }} #{{ event.subject_id }}</span></td>
                        <td class="p-4">{{ event.actor?.full_name || event.actor?.email || 'System' }}</td>
                        <td class="p-4">{{ event.occurred_at }}</td>
                    </tr>
                    <tr v-if="events.data.length === 0"><td class="p-4 text-slate-500" colspan="3">No audit events found.</td></tr>
                </tbody>
            </table>
        </section>
    </AppLayout>
</template>
