<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '../../../Layouts/AppLayout.vue';
import PrimaryButton from '../../../Components/PrimaryButton.vue';
import TextInput from '../../../Components/TextInput.vue';

const props = defineProps({
    patient: { type: Object, required: true },
    facilities: { type: Array, default: () => [] },
});

const duplicateWarnings = computed(() => usePage().props.flash.duplicate_warnings || []);
const form = useForm({
    registration_facility_id: props.patient.registration_facility_id,
    first_name: props.patient.first_name,
    middle_name: props.patient.middle_name || '',
    last_name: props.patient.last_name,
    date_of_birth: props.patient.date_of_birth || '',
    estimated_age_years: props.patient.estimated_age_years || '',
    is_dob_estimated: props.patient.is_dob_estimated,
    sex: props.patient.sex,
    marital_status: props.patient.marital_status || '',
    occupation: props.patient.occupation || '',
    address: props.patient.address || '',
    phone: props.patient.phone || '',
    email: props.patient.email || '',
    identifiers: [],
    contacts: [],
    acknowledge_duplicates: false,
});
const statusForm = useForm({ status: props.patient.status, reason: '' });
const allergyForm = useForm({ substance: '', reaction: '', severity: 'unknown', status: 'active', notes: '' });
const alertForm = useForm({ title: '', category: 'general', severity: 'medium', status: 'active', notes: '' });

function updatePatient() {
    form.patch(`/admin/patients/${props.patient.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="patient.full_name" />
    <AppLayout :title="patient.full_name">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <Link href="/admin/patients" class="text-sm font-semibold text-red-800">Back to patients</Link>
            <div class="text-right">
                <p class="text-sm text-slate-500">Hospital number</p>
                <p class="text-xl font-black">{{ patient.hospital_number }}</p>
            </div>
        </div>

        <div v-if="duplicateWarnings.length" class="mb-4 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
            <p class="font-bold">Possible duplicate records found</p>
            <p>Review the matches, then check duplicate acknowledgement before saving if this is a distinct patient.</p>
            <ul class="mt-2 list-disc pl-5"><li v-for="warning in duplicateWarnings" :key="warning.id">{{ warning.hospital_number }} · {{ warning.full_name }}</li></ul>
        </div>

        <div class="grid gap-6 xl:grid-cols-[1fr_360px]">
            <section class="space-y-6">
                <form class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="updatePatient">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="text-lg font-bold">Demographics</h2>
                        <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-bold text-slate-700">{{ patient.status }}</span>
                    </div>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <label class="grid gap-1 text-sm sm:col-span-2">Facility<select v-model="form.registration_facility_id" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900"><option v-for="facility in facilities" :key="facility.id" :value="facility.id">{{ facility.name }}</option></select></label>
                        <TextInput id="show_first" v-model="form.first_name" label="First name" :error="form.errors.first_name" />
                        <TextInput id="show_middle" v-model="form.middle_name" label="Middle name" :error="form.errors.middle_name" />
                        <TextInput id="show_last" v-model="form.last_name" label="Last name" :error="form.errors.last_name" />
                        <label class="grid gap-1 text-sm">Sex<select v-model="form.sex" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900"><option value="female">Female</option><option value="male">Male</option><option value="intersex">Intersex</option><option value="unknown">Unknown</option></select></label>
                        <TextInput id="show_dob" v-model="form.date_of_birth" label="Date of birth" type="date" :error="form.errors.date_of_birth" />
                        <TextInput id="show_age" v-model="form.estimated_age_years" label="Estimated age" type="number" :error="form.errors.estimated_age_years" />
                        <TextInput id="show_marital" v-model="form.marital_status" label="Marital status" :error="form.errors.marital_status" />
                        <TextInput id="show_occupation" v-model="form.occupation" label="Occupation" :error="form.errors.occupation" />
                        <TextInput id="show_phone" v-model="form.phone" label="Phone" :error="form.errors.phone" />
                        <TextInput id="show_email" v-model="form.email" label="Email" type="email" :error="form.errors.email" />
                        <label class="flex items-center gap-2 text-sm"><input v-model="form.acknowledge_duplicates" class="rounded border-slate-300" type="checkbox"> Duplicate warning reviewed</label>
                        <label class="grid gap-1 text-sm sm:col-span-2">Address<textarea v-model="form.address" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900" rows="2"></textarea></label>
                    </div>
                    <PrimaryButton class="mt-4" :disabled="form.processing">Save demographics</PrimaryButton>
                </form>

                <div class="grid gap-6 md:grid-cols-2">
                    <section class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                        <h2 class="font-bold">Contacts and Next of Kin</h2>
                        <div class="mt-3 divide-y divide-slate-100 text-sm dark:divide-slate-800">
                            <div v-for="contact in patient.contacts" :key="contact.id" class="py-3">
                                <p class="font-semibold">{{ contact.name }} <span v-if="contact.is_next_of_kin" class="text-xs text-red-800">Next of kin</span></p>
                                <p class="text-slate-500">{{ contact.relationship }} · {{ contact.phone || 'No phone' }}</p>
                            </div>
                            <p v-if="patient.contacts.length === 0" class="py-3 text-slate-500">No contacts recorded.</p>
                        </div>
                    </section>

                    <section class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                        <h2 class="font-bold">Identifiers</h2>
                        <div class="mt-3 divide-y divide-slate-100 text-sm dark:divide-slate-800">
                            <div v-for="identifier in patient.identifiers" :key="identifier.id" class="py-3">
                                <p class="font-semibold">{{ identifier.type }}</p>
                                <p class="text-slate-500">{{ identifier.value }}</p>
                            </div>
                            <p v-if="patient.identifiers.length === 0" class="py-3 text-slate-500">No identifiers recorded.</p>
                        </div>
                    </section>
                </div>
            </section>

            <aside class="space-y-6">
                <form class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="statusForm.patch(`/admin/patients/${patient.id}/status`, { preserveScroll: true })">
                    <h2 class="font-bold">Record State</h2>
                    <select v-model="statusForm.status" class="mt-3 w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900"><option value="active">Active</option><option value="archived">Archived</option><option value="deceased">Deceased</option></select>
                    <textarea v-model="statusForm.reason" class="mt-3 w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900" rows="2" placeholder="Reason"></textarea>
                    <PrimaryButton class="mt-3" :disabled="statusForm.processing">Update state</PrimaryButton>
                </form>

                <form class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="allergyForm.post(`/admin/patients/${patient.id}/allergies`, { preserveScroll: true, onSuccess: () => allergyForm.reset() })">
                    <h2 class="font-bold">Allergies</h2>
                    <div class="mt-3 space-y-2">
                        <input v-model="allergyForm.substance" class="w-full rounded-md border-slate-300 text-sm" placeholder="Substance">
                        <input v-model="allergyForm.reaction" class="w-full rounded-md border-slate-300 text-sm" placeholder="Reaction">
                        <select v-model="allergyForm.severity" class="w-full rounded-md border-slate-300 text-sm"><option value="unknown">Unknown</option><option value="mild">Mild</option><option value="moderate">Moderate</option><option value="severe">Severe</option></select>
                        <textarea v-model="allergyForm.notes" class="w-full rounded-md border-slate-300 text-sm" rows="2" placeholder="Notes"></textarea>
                        <PrimaryButton :disabled="allergyForm.processing">Record allergy</PrimaryButton>
                    </div>
                    <div class="mt-4 divide-y divide-slate-100 text-sm dark:divide-slate-800">
                        <p v-for="allergy in patient.allergies" :key="allergy.id" class="py-2"><strong>{{ allergy.substance }}</strong> · {{ allergy.severity }} · {{ allergy.status }}</p>
                    </div>
                </form>

                <form class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="alertForm.post(`/admin/patients/${patient.id}/alerts`, { preserveScroll: true, onSuccess: () => alertForm.reset() })">
                    <h2 class="font-bold">Important Alerts</h2>
                    <div class="mt-3 space-y-2">
                        <input v-model="alertForm.title" class="w-full rounded-md border-slate-300 text-sm" placeholder="Title">
                        <input v-model="alertForm.category" class="w-full rounded-md border-slate-300 text-sm" placeholder="Category">
                        <select v-model="alertForm.severity" class="w-full rounded-md border-slate-300 text-sm"><option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option><option value="critical">Critical</option></select>
                        <textarea v-model="alertForm.notes" class="w-full rounded-md border-slate-300 text-sm" rows="2" placeholder="Notes"></textarea>
                        <PrimaryButton :disabled="alertForm.processing">Record alert</PrimaryButton>
                    </div>
                    <div class="mt-4 divide-y divide-slate-100 text-sm dark:divide-slate-800">
                        <p v-for="alert in patient.alerts" :key="alert.id" class="py-2"><strong>{{ alert.title }}</strong> · {{ alert.severity }} · {{ alert.status }}</p>
                    </div>
                </form>

                <section class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="font-bold">Activity Timeline</h2>
                    <div class="mt-3 divide-y divide-slate-100 text-sm dark:divide-slate-800">
                        <p v-for="event in patient.activity_events" :key="event.id" class="py-2">{{ event.action }} · {{ event.occurred_at }}</p>
                    </div>
                </section>
                <section class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="font-bold">Blood Requests</h2>
                    <div class="mt-3 divide-y divide-slate-100 text-sm dark:divide-slate-800">
                        <Link v-for="request in patient.blood_requests" :key="request.id" class="block py-2 font-semibold" :href="`/admin/blood-bank/requests/${request.id}`">{{ request.request_number }} - {{ request.component_type?.name }} - {{ request.state }}</Link>
                        <p v-if="patient.blood_requests.length === 0" class="py-2 text-slate-500">No blood requests recorded.</p>
                    </div>
                </section>
            </aside>
        </div>
    </AppLayout>
</template>
