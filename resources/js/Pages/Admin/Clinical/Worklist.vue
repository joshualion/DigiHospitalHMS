<script setup>
import ActionToolbar from '@/Components/Admin/ActionToolbar.vue';
import ConfirmDialog from '@/Components/Admin/ConfirmDialog.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    visits: { type: Object, required: true },
    encounters: { type: Array, default: () => [] },
});

const startForm = useForm({});
const startTarget = ref(null);

function startEncounter() {
    startForm.post(`/admin/visits/${startTarget.value.id}/encounter`, { preserveScroll: true, onSuccess: () => { startTarget.value = null; } });
}
</script>

<template>
    <Head title="Clinical Worklist" />
    <AppLayout title="Clinical Worklist">
        <PageHeader title="Clinical Worklist" description="Checked-in outpatients and active encounters." />

        <div class="grid gap-6">
            <section class="min-w-0 rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 p-4 dark:border-slate-800">
                    <h2 class="text-lg font-black">Checked-in Patients</h2>
                </div>
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    <article v-for="visit in visits.data" :key="visit.id" class="grid min-w-0 gap-3 p-4 md:grid-cols-[1fr_160px_180px]">
                        <div class="min-w-0">
                            <p class="break-words font-bold">{{ visit.patient?.full_name }} - {{ visit.patient?.hospital_number }}</p>
                            <p class="break-words text-sm text-slate-500">{{ visit.facility?.name }} - {{ visit.department?.name || 'No department' }} - Queue #{{ visit.queue_entry?.queue_number || '-' }}</p>
                        </div>
                        <span class="h-fit w-fit rounded-full bg-slate-100 px-2 py-1 text-xs font-bold text-slate-700">{{ visit.status }}</span>
                        <ActionToolbar>
                            <Link v-if="visit.encounter" class="rounded-md border px-3 py-2 text-sm font-bold" :href="`/admin/encounters/${visit.encounter.id}`">Open</Link>
                            <button v-else class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="startTarget = visit">Start</button>
                        </ActionToolbar>
                    </article>
                    <p v-if="visits.data.length === 0" class="p-4 text-sm text-slate-500">No checked-in patients are waiting for clinical review.</p>
                </div>
            </section>

            <section class="min-w-0 rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="font-bold">Active Encounters</h2>
                <div class="mt-3 divide-y divide-slate-100 text-sm dark:divide-slate-800">
                    <Link v-for="encounter in encounters" :key="encounter.id" class="block min-w-0 py-3" :href="`/admin/encounters/${encounter.id}`">
                        <p class="break-words font-semibold">{{ encounter.patient?.full_name }}</p>
                        <p class="break-words text-slate-500">{{ encounter.status }} - {{ encounter.clinician?.user?.full_name || 'Clinician' }}</p>
                    </Link>
                    <p v-if="encounters.length === 0" class="py-3 text-slate-500">No active encounters.</p>
                </div>
            </section>
        </div>

        <ConfirmDialog :show="Boolean(startTarget)" title="Start encounter" message="Start an outpatient encounter for this visit?" confirm-label="Start" :form="startForm" @close="startTarget = null" @confirm="startEncounter" />
    </AppLayout>
</template>
