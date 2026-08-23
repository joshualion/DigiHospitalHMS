<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import PrimaryButton from '../../../Components/PrimaryButton.vue';

const props = defineProps({
    queue: { type: Array, default: () => [] },
    facilities: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    clinicians: { type: Array, default: () => [] },
    patients: { type: Array, default: () => [] },
});

const walkIn = useForm({ patient_id: '', facility_id: props.facilities[0]?.id || '', department_id: '', clinician_id: '' });

function action(entry, action) {
    const reason = ['transfer', 'skip', 'remove', 'priority'].includes(action) ? window.prompt('Reason') : null;
    if (['transfer', 'skip', 'remove', 'priority'].includes(action) && !reason) return;
    router.patch(`/admin/queues/${entry.id}`, { action, reason }, { preserveScroll: true });
}
</script>

<template>
    <Head title="Queue Board" />
    <AppLayout title="Queue Board">
        <div class="grid gap-6 xl:grid-cols-[1fr_360px]">
            <section class="grid gap-4 md:grid-cols-2">
                <article v-for="entry in queue" :key="entry.id" class="rounded-lg border border-slate-200 bg-white p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm text-slate-500">Queue #{{ entry.queue_number }}</p>
                            <h2 class="text-xl font-black">{{ entry.patient?.full_name }}</h2>
                            <p class="text-sm text-slate-500">{{ entry.patient?.hospital_number }} · priority {{ entry.priority }}</p>
                        </div>
                        <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-bold">{{ entry.status }}</span>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <button class="rounded-md border px-2 py-1 text-sm" type="button" @click="action(entry, 'call')">Call</button>
                        <button class="rounded-md border px-2 py-1 text-sm" type="button" @click="action(entry, 'recall')">Recall</button>
                        <button class="rounded-md border px-2 py-1 text-sm" type="button" @click="action(entry, 'skip')">Skip</button>
                        <button class="rounded-md border px-2 py-1 text-sm" type="button" @click="action(entry, 'remove')">Remove</button>
                    </div>
                </article>
                <p v-if="queue.length === 0" class="rounded-lg border border-slate-200 bg-white p-5 text-sm text-slate-500">No active queue entries.</p>
            </section>

            <form class="rounded-lg border border-slate-200 bg-white p-5" @submit.prevent="walkIn.post('/admin/appointments/walk-ins')">
                <h2 class="font-bold">Walk-in Check-in</h2>
                <div class="mt-4 grid gap-3">
                    <select v-model="walkIn.patient_id" class="rounded-md border-slate-300"><option value="">Patient</option><option v-for="patient in patients" :key="patient.id" :value="patient.id">{{ patient.hospital_number }} · {{ patient.full_name }}</option></select>
                    <select v-model="walkIn.facility_id" class="rounded-md border-slate-300"><option v-for="facility in facilities" :key="facility.id" :value="facility.id">{{ facility.name }}</option></select>
                    <select v-model="walkIn.department_id" class="rounded-md border-slate-300"><option value="">Department</option><option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option></select>
                    <select v-model="walkIn.clinician_id" class="rounded-md border-slate-300"><option value="">Clinician</option><option v-for="clinician in clinicians" :key="clinician.id" :value="clinician.id">{{ clinician.user?.full_name || clinician.id }}</option></select>
                    <PrimaryButton :disabled="walkIn.processing">Check in walk-in</PrimaryButton>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
