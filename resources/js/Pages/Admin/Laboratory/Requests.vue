<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    requests: { type: Object, required: true },
    patients: { type: Array, default: () => [] },
    visits: { type: Array, default: () => [] },
    encounters: { type: Array, default: () => [] },
    facilities: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    labTests: { type: Array, default: () => [] },
    labProfiles: { type: Array, default: () => [] },
});

const form = useForm({ facility_id: props.facilities[0]?.id || '', department_id: '', patient_id: '', visit_id: '', clinical_encounter_id: '', lab_test_ids: [], lab_test_profile_ids: [], priority: 'routine', clinical_notes: '', currency: 'NGN' });
</script>

<template>
    <Head title="Laboratory Requests" />
    <AppLayout title="Laboratory Requests">
        <div class="grid gap-6 xl:grid-cols-[1fr_380px]">
            <section class="rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 p-4 dark:border-slate-800">
                    <h2 class="text-lg font-black">Lab Worklist</h2>
                </div>
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    <Link v-for="item in requests.data" :key="item.id" class="grid gap-3 p-4 md:grid-cols-[1fr_auto]" :href="`/admin/laboratory/requests/${item.id}`">
                        <div>
                            <p class="font-bold">{{ item.request_number }} - {{ item.patient?.full_name }}</p>
                            <p class="text-sm text-slate-500">{{ item.accession_number }} - {{ item.tests.map((test) => test.test_name).join(', ') }}</p>
                        </div>
                        <span class="h-fit rounded-full bg-slate-100 px-2 py-1 text-xs font-bold text-slate-700">{{ item.status }}</span>
                    </Link>
                    <p v-if="requests.data.length === 0" class="p-4 text-sm text-slate-500">No lab requests.</p>
                </div>
            </section>

            <form class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="form.post('/admin/laboratory/requests')">
                <h2 class="font-black">Order Lab Request</h2>
                <div class="mt-4 grid gap-3">
                    <select v-model="form.patient_id" class="rounded-md border-slate-300"><option value="">Patient</option><option v-for="patient in patients" :key="patient.id" :value="patient.id">{{ patient.hospital_number }} - {{ patient.full_name }}</option></select>
                    <select v-model="form.facility_id" class="rounded-md border-slate-300"><option value="">Facility</option><option v-for="facility in facilities" :key="facility.id" :value="facility.id">{{ facility.name }}</option></select>
                    <select v-model="form.department_id" class="rounded-md border-slate-300"><option value="">Department</option><option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option></select>
                    <select v-model="form.visit_id" class="rounded-md border-slate-300"><option value="">Visit</option><option v-for="visit in visits" :key="visit.id" :value="visit.id">Visit #{{ visit.id }} - {{ visit.status }}</option></select>
                    <select v-model="form.clinical_encounter_id" class="rounded-md border-slate-300"><option value="">Encounter</option><option v-for="encounter in encounters" :key="encounter.id" :value="encounter.id">Encounter #{{ encounter.id }} - {{ encounter.status }}</option></select>
                    <select v-model="form.lab_test_ids" class="rounded-md border-slate-300" multiple><option v-for="test in labTests" :key="test.id" :value="test.id">{{ test.code }} - {{ test.name }}</option></select>
                    <select v-model="form.lab_test_profile_ids" class="rounded-md border-slate-300" multiple><option v-for="profile in labProfiles" :key="profile.id" :value="profile.id">{{ profile.code }} - {{ profile.name }}</option></select>
                    <select v-model="form.priority" class="rounded-md border-slate-300"><option value="routine">Routine</option><option value="urgent">Urgent</option></select>
                    <textarea v-model="form.clinical_notes" class="rounded-md border-slate-300" rows="2" placeholder="Clinical notes for the lab"></textarea>
                    <PrimaryButton :disabled="form.processing">Order request</PrimaryButton>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
