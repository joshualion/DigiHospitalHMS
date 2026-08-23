<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';

defineProps({
    visits: { type: Object, required: true },
    encounters: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="Clinical Worklist" />
    <AppLayout title="Clinical Worklist">
        <div class="grid gap-6 xl:grid-cols-[1fr_360px]">
            <section class="rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 p-4 dark:border-slate-800">
                    <h2 class="text-lg font-black">Checked-in Patients</h2>
                </div>
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    <article v-for="visit in visits.data" :key="visit.id" class="grid gap-3 p-4 md:grid-cols-[1fr_160px_180px]">
                        <div>
                            <p class="font-bold">{{ visit.patient?.full_name }} · {{ visit.patient?.hospital_number }}</p>
                            <p class="text-sm text-slate-500">{{ visit.facility?.name }} · {{ visit.department?.name || 'No department' }} · Queue #{{ visit.queue_entry?.queue_number || '-' }}</p>
                        </div>
                        <span class="h-fit w-fit rounded-full bg-slate-100 px-2 py-1 text-xs font-bold text-slate-700">{{ visit.status }}</span>
                        <div class="flex flex-wrap gap-2">
                            <Link v-if="visit.encounter" class="rounded-md border px-3 py-2 text-sm font-bold" :href="`/admin/encounters/${visit.encounter.id}`">Open</Link>
                            <button v-else class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="router.post(`/admin/visits/${visit.id}/encounter`)">Start</button>
                        </div>
                    </article>
                    <p v-if="visits.data.length === 0" class="p-4 text-sm text-slate-500">No checked-in patients are waiting for clinical review.</p>
                </div>
            </section>

            <aside class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="font-bold">Active Encounters</h2>
                <div class="mt-3 divide-y divide-slate-100 text-sm dark:divide-slate-800">
                    <Link v-for="encounter in encounters" :key="encounter.id" class="block py-3" :href="`/admin/encounters/${encounter.id}`">
                        <p class="font-semibold">{{ encounter.patient?.full_name }}</p>
                        <p class="text-slate-500">{{ encounter.status }} · {{ encounter.clinician?.user?.full_name || 'Clinician' }}</p>
                    </Link>
                    <p v-if="encounters.length === 0" class="py-3 text-slate-500">No active encounters.</p>
                </div>
            </aside>
        </div>
    </AppLayout>
</template>
