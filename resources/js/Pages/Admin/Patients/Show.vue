<script setup>
import ActionToolbar from '@/Components/Admin/ActionToolbar.vue';
import FormModal from '@/Components/Admin/FormModal.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    patient: { type: Object, required: true },
    facilities: { type: Array, default: () => [] },
});

const page = usePage();
const permissions = computed(() => page.props.auth.permissions || []);
const roles = computed(() => page.props.auth.roles || []);
const can = (permission) => roles.value.includes('superadmin') || permissions.value.includes(permission);
const duplicateWarnings = computed(() => page.props.flash.duplicate_warnings || []);
const showDemographics = ref(false);
const showStatus = ref(false);
const showAllergy = ref(false);
const showAlert = ref(false);

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
    form.patch(`/admin/patients/${props.patient.id}`, { preserveScroll: true, onSuccess: () => { if (duplicateWarnings.value.length === 0) showDemographics.value = false; } });
}

function updateStatus() {
    statusForm.patch(`/admin/patients/${props.patient.id}/status`, { preserveScroll: true, onSuccess: () => { showStatus.value = false; statusForm.reset('reason'); } });
}

function saveAllergy() {
    allergyForm.post(`/admin/patients/${props.patient.id}/allergies`, { preserveScroll: true, onSuccess: () => { showAllergy.value = false; allergyForm.reset(); } });
}

function saveAlert() {
    alertForm.post(`/admin/patients/${props.patient.id}/alerts`, { preserveScroll: true, onSuccess: () => { showAlert.value = false; alertForm.reset(); } });
}
</script>

<template>
    <Head :title="patient.full_name" />
    <AppLayout :title="patient.full_name">
        <PageHeader :title="patient.full_name" :description="`Hospital number ${patient.hospital_number}`">
            <template #actions>
                <ActionToolbar align="end">
                    <Link href="/admin/patients" class="rounded-md border px-4 py-2 text-sm font-bold" style="border-color: var(--admin-border);">Back</Link>
                    <PrimaryButton v-if="can('patients.update')" type="button" @click="showDemographics = true">Edit Patient</PrimaryButton>
                    <button v-if="can('patients.archive')" class="rounded-md border px-4 py-2 text-sm font-bold" style="border-color: var(--admin-border);" type="button" @click="showStatus = true">Record State</button>
                    <button v-if="can('patients.record-alerts')" class="rounded-md border px-4 py-2 text-sm font-bold" style="border-color: var(--admin-border);" type="button" @click="showAllergy = true">Add Allergy</button>
                    <button v-if="can('patients.record-alerts')" class="rounded-md border px-4 py-2 text-sm font-bold" style="border-color: var(--admin-border);" type="button" @click="showAlert = true">Add Alert</button>
                </ActionToolbar>
            </template>
        </PageHeader>

        <div v-if="duplicateWarnings.length" class="mb-4 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
            <p class="font-bold">Possible duplicate records found</p>
            <p>Review the matches, then acknowledge duplicate review in the edit modal before saving if this is a distinct patient.</p>
        </div>

        <div class="space-y-6">
            <section class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <div class="grid gap-4 md:grid-cols-3">
                    <div><p class="text-xs font-black uppercase text-slate-500">Status</p><p class="font-bold">{{ patient.status }}</p></div>
                    <div><p class="text-xs font-black uppercase text-slate-500">Facility</p><p class="font-bold">{{ patient.registration_facility?.name }}</p></div>
                    <div><p class="text-xs font-black uppercase text-slate-500">Sex</p><p class="font-bold">{{ patient.sex }}</p></div>
                    <div><p class="text-xs font-black uppercase text-slate-500">Date of birth</p><p class="font-bold">{{ patient.date_of_birth || `Age ${patient.estimated_age_years || 'unknown'}` }}</p></div>
                    <div><p class="text-xs font-black uppercase text-slate-500">Phone</p><p class="font-bold">{{ patient.phone || 'No phone' }}</p></div>
                    <div><p class="text-xs font-black uppercase text-slate-500">Email</p><p class="font-bold">{{ patient.email || 'No email' }}</p></div>
                </div>
            </section>

            <section class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="font-bold">Contacts and Next of Kin</h2>
                    <div class="mt-3 divide-y divide-slate-100 text-sm dark:divide-slate-800">
                        <div v-for="contact in patient.contacts" :key="contact.id" class="py-3">
                            <p class="font-semibold">{{ contact.name }} <span v-if="contact.is_next_of_kin" class="text-xs text-red-800">Next of kin</span></p>
                            <p class="text-slate-500">{{ contact.relationship }} - {{ contact.phone || 'No phone' }}</p>
                        </div>
                        <p v-if="patient.contacts.length === 0" class="py-3 text-slate-500">No contacts recorded.</p>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="font-bold">Identifiers</h2>
                    <div class="mt-3 divide-y divide-slate-100 text-sm dark:divide-slate-800">
                        <div v-for="identifier in patient.identifiers" :key="identifier.id" class="py-3">
                            <p class="font-semibold">{{ identifier.type }}</p>
                            <p class="text-slate-500">{{ identifier.value }}</p>
                        </div>
                        <p v-if="patient.identifiers.length === 0" class="py-3 text-slate-500">No identifiers recorded.</p>
                    </div>
                </div>
            </section>

            <section class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="font-bold">Allergies</h2>
                    <div class="mt-3 divide-y divide-slate-100 text-sm dark:divide-slate-800">
                        <p v-for="allergy in patient.allergies" :key="allergy.id" class="py-2"><strong>{{ allergy.substance }}</strong> - {{ allergy.severity }} - {{ allergy.status }}</p>
                        <p v-if="patient.allergies.length === 0" class="py-2 text-slate-500">No allergies recorded.</p>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="font-bold">Important Alerts</h2>
                    <div class="mt-3 divide-y divide-slate-100 text-sm dark:divide-slate-800">
                        <p v-for="alert in patient.alerts" :key="alert.id" class="py-2"><strong>{{ alert.title }}</strong> - {{ alert.severity }} - {{ alert.status }}</p>
                        <p v-if="patient.alerts.length === 0" class="py-2 text-slate-500">No alerts recorded.</p>
                    </div>
                </div>
            </section>

            <section class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="font-bold">Activity Timeline</h2>
                    <div class="mt-3 divide-y divide-slate-100 text-sm dark:divide-slate-800">
                        <p v-for="event in patient.activity_events" :key="event.id" class="py-2">{{ event.action }} - {{ event.occurred_at }}</p>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="font-bold">Blood Requests</h2>
                    <div class="mt-3 divide-y divide-slate-100 text-sm dark:divide-slate-800">
                        <Link v-for="request in patient.blood_requests" :key="request.id" class="block py-2 font-semibold" :href="`/admin/blood-bank/requests/${request.id}`">{{ request.request_number }} - {{ request.component_type?.name }} - {{ request.state }}</Link>
                        <p v-if="patient.blood_requests.length === 0" class="py-2 text-slate-500">No blood requests recorded.</p>
                    </div>
                </div>
            </section>
        </div>

        <FormModal :show="showDemographics" :form="form" title="Edit Patient" submit-label="Save demographics" size="full" @close="showDemographics = false" @submit="updatePatient">
            <div v-if="duplicateWarnings.length" class="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                <p class="font-bold">Possible duplicate records found</p>
                <ul class="mt-2 list-disc pl-5"><li v-for="warning in duplicateWarnings" :key="warning.id">{{ warning.hospital_number }} - {{ warning.full_name }}</li></ul>
                <label class="mt-3 flex items-center gap-2 font-semibold"><input v-model="form.acknowledge_duplicates" class="rounded border-amber-400" type="checkbox"> Duplicate warning reviewed</label>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="grid gap-1 text-sm font-semibold sm:col-span-2">Facility<select v-model="form.registration_facility_id" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900"><option v-for="facility in facilities" :key="facility.id" :value="facility.id">{{ facility.name }}</option></select></label>
                <TextInput id="show_first" v-model="form.first_name" label="First name" :error="form.errors.first_name" />
                <TextInput id="show_middle" v-model="form.middle_name" label="Middle name" :error="form.errors.middle_name" />
                <TextInput id="show_last" v-model="form.last_name" label="Last name" :error="form.errors.last_name" />
                <label class="grid gap-1 text-sm font-semibold">Sex<select v-model="form.sex" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900"><option value="female">Female</option><option value="male">Male</option><option value="intersex">Intersex</option><option value="unknown">Unknown</option></select></label>
                <TextInput id="show_dob" v-model="form.date_of_birth" label="Date of birth" type="date" :error="form.errors.date_of_birth" />
                <TextInput id="show_age" v-model="form.estimated_age_years" label="Estimated age" type="number" :error="form.errors.estimated_age_years" />
                <label class="flex items-center gap-2 text-sm"><input v-model="form.is_dob_estimated" class="rounded border-slate-300" type="checkbox"> Date of birth is estimated</label>
                <TextInput id="show_marital" v-model="form.marital_status" label="Marital status" :error="form.errors.marital_status" />
                <TextInput id="show_occupation" v-model="form.occupation" label="Occupation" :error="form.errors.occupation" />
                <TextInput id="show_phone" v-model="form.phone" label="Phone" :error="form.errors.phone" />
                <TextInput id="show_email" v-model="form.email" label="Email" type="email" :error="form.errors.email" />
                <label class="grid gap-1 text-sm font-semibold sm:col-span-2">Address<textarea v-model="form.address" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900" rows="2"></textarea></label>
            </div>
        </FormModal>

        <FormModal :show="showStatus" :form="statusForm" title="Record State" submit-label="Update state" size="md" @close="showStatus = false" @submit="updateStatus">
            <label class="grid gap-1 text-sm font-semibold">Status<select v-model="statusForm.status" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900"><option value="active">Active</option><option value="archived">Archived</option><option value="deceased">Deceased</option></select></label>
            <label class="grid gap-1 text-sm font-semibold">Reason<textarea v-model="statusForm.reason" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900" rows="3"></textarea><span v-if="statusForm.errors.reason" class="text-xs text-red-700">{{ statusForm.errors.reason }}</span></label>
        </FormModal>

        <FormModal :show="showAllergy" :form="allergyForm" title="Record Allergy" submit-label="Record allergy" size="md" @close="showAllergy = false" @submit="saveAllergy">
            <TextInput id="allergy_substance" v-model="allergyForm.substance" label="Substance" :error="allergyForm.errors.substance" />
            <TextInput id="allergy_reaction" v-model="allergyForm.reaction" label="Reaction" :error="allergyForm.errors.reaction" />
            <label class="grid gap-1 text-sm font-semibold">Severity<select v-model="allergyForm.severity" class="rounded-md border-slate-300"><option value="unknown">Unknown</option><option value="mild">Mild</option><option value="moderate">Moderate</option><option value="severe">Severe</option></select></label>
            <label class="grid gap-1 text-sm font-semibold">Status<select v-model="allergyForm.status" class="rounded-md border-slate-300"><option value="active">Active</option><option value="inactive">Inactive</option><option value="entered-in-error">Entered in error</option></select></label>
            <label class="grid gap-1 text-sm font-semibold">Notes<textarea v-model="allergyForm.notes" class="rounded-md border-slate-300" rows="3"></textarea></label>
        </FormModal>

        <FormModal :show="showAlert" :form="alertForm" title="Record Alert" submit-label="Record alert" size="md" @close="showAlert = false" @submit="saveAlert">
            <TextInput id="alert_title" v-model="alertForm.title" label="Title" :error="alertForm.errors.title" />
            <TextInput id="alert_category" v-model="alertForm.category" label="Category" :error="alertForm.errors.category" />
            <label class="grid gap-1 text-sm font-semibold">Severity<select v-model="alertForm.severity" class="rounded-md border-slate-300"><option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option><option value="critical">Critical</option></select></label>
            <label class="grid gap-1 text-sm font-semibold">Status<select v-model="alertForm.status" class="rounded-md border-slate-300"><option value="active">Active</option><option value="inactive">Inactive</option><option value="resolved">Resolved</option></select></label>
            <label class="grid gap-1 text-sm font-semibold">Notes<textarea v-model="alertForm.notes" class="rounded-md border-slate-300" rows="3"></textarea></label>
        </FormModal>
    </AppLayout>
</template>
