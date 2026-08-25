<script setup>
import ActionToolbar from '@/Components/Admin/ActionToolbar.vue';
import FormModal from '@/Components/Admin/FormModal.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    patients: { type: Object, required: true },
    facilities: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const page = usePage();
const permissions = computed(() => page.props.auth.permissions || []);
const roles = computed(() => page.props.auth.roles || []);
const can = (permission) => roles.value.includes('superadmin') || permissions.value.includes(permission);
const duplicateWarnings = computed(() => page.props.flash.duplicate_warnings || []);
const validationErrors = computed(() => Object.values(form.errors || {}));
const search = useForm({ search: props.filters.search || '', status: props.filters.status || '' });
const blankPatient = () => ({
    registration_facility_id: props.facilities[0]?.id || '',
    first_name: '',
    middle_name: '',
    last_name: '',
    date_of_birth: '',
    estimated_age_years: '',
    is_dob_estimated: false,
    sex: 'unknown',
    marital_status: '',
    occupation: '',
    address: '',
    phone: '',
    email: '',
    identifiers: [{ type: 'NIN', value: '', is_searchable: true }],
    contacts: [{ type: 'next_of_kin', name: '', relationship: '', phone: '', email: '', address: '', is_next_of_kin: true, is_primary: true }],
    acknowledge_duplicates: false,
});
const form = useForm(blankPatient());
const showRegister = ref(false);

function filter() {
    router.get('/admin/patients', search.data(), { preserveState: true, replace: true });
}

function openRegister() {
    form.clearErrors();
    form.defaults(blankPatient());
    form.reset();
    showRegister.value = true;
}

function addIdentifier() {
    form.identifiers.push({ type: '', value: '', is_searchable: true });
}

function removeIdentifier(index) {
    form.identifiers.splice(index, 1);
}

function addContact() {
    form.contacts.push({ type: 'contact', name: '', relationship: '', phone: '', email: '', address: '', is_next_of_kin: false, is_primary: false });
}

function removeContact(index) {
    form.contacts.splice(index, 1);
}

function submit() {
    form.post('/admin/patients', {
        preserveScroll: true,
        onSuccess: () => {
            if (duplicateWarnings.value.length === 0) {
                showRegister.value = false;
                form.defaults(blankPatient());
                form.reset();
            }
        },
    });
}
</script>

<template>
    <Head title="Patients" />
    <AppLayout title="Patients">
        <PageHeader title="Patients" description="Search and manage patient registration records.">
            <template #actions>
                <ActionToolbar align="end">
                    <PrimaryButton v-if="can('patients.register')" type="button" @click="openRegister">Register Patient</PrimaryButton>
                </ActionToolbar>
            </template>
        </PageHeader>

        <section class="min-w-0 rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="grid gap-3 border-b border-slate-200 p-4 md:grid-cols-[1fr_180px] dark:border-slate-800">
                <input v-model="search.search" class="min-w-0 rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900" placeholder="Search hospital number, name, phone or identifier" @change="filter">
                <select v-model="search.status" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900" @change="filter">
                    <option value="">All records</option>
                    <option value="active">Active</option>
                    <option value="archived">Archived</option>
                    <option value="deceased">Deceased</option>
                </select>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                <Link v-for="patient in patients.data" :key="patient.id" :href="`/admin/patients/${patient.id}`" class="grid min-w-0 gap-2 p-4 hover:bg-slate-50 md:grid-cols-[1fr_180px_120px] dark:hover:bg-slate-800">
                    <div class="min-w-0">
                        <p class="truncate font-bold">{{ patient.full_name }}</p>
                        <p class="truncate text-sm text-slate-500">{{ patient.hospital_number }} - {{ patient.sex }} - {{ patient.phone || 'No phone' }}</p>
                    </div>
                    <p class="text-sm text-slate-600">{{ patient.registration_facility?.name }}</p>
                    <span class="h-fit w-fit rounded-full px-2 py-1 text-xs font-bold" :class="patient.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'">{{ patient.status }}</span>
                </Link>
                <p v-if="patients.data.length === 0" class="p-4 text-sm text-slate-500">No patients found.</p>
            </div>
        </section>

        <FormModal :show="showRegister" :form="form" title="Register Patient" submit-label="Register patient" size="full" @close="showRegister = false" @submit="submit">
            <div v-if="duplicateWarnings.length" class="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                <p class="font-bold">Possible duplicate records found</p>
                <ul class="mt-2 list-disc pl-5">
                    <li v-for="warning in duplicateWarnings" :key="warning.id">{{ warning.hospital_number }} - {{ warning.full_name }} - {{ warning.status }}</li>
                </ul>
                <label class="mt-3 flex items-center gap-2 font-semibold"><input v-model="form.acknowledge_duplicates" class="rounded border-amber-400" type="checkbox"> Register anyway after manual review</label>
            </div>

            <div v-if="validationErrors.length" class="rounded-md border border-rose-300 bg-rose-50 p-3 text-sm text-rose-900">
                <p class="font-bold">Fix these registration fields</p>
                <ul class="mt-2 list-disc pl-5">
                    <li v-for="(error, index) in validationErrors" :key="index">{{ error }}</li>
                </ul>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="grid gap-1 text-sm font-semibold sm:col-span-2">Facility<select v-model="form.registration_facility_id" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900"><option v-for="facility in facilities" :key="facility.id" :value="facility.id">{{ facility.name }}</option></select><span v-if="form.errors.registration_facility_id" class="text-xs text-red-700">{{ form.errors.registration_facility_id }}</span></label>
                <TextInput id="patient_first" v-model="form.first_name" label="First name" :error="form.errors.first_name" />
                <TextInput id="patient_middle" v-model="form.middle_name" label="Middle name" :error="form.errors.middle_name" />
                <TextInput id="patient_last" v-model="form.last_name" label="Last name" :error="form.errors.last_name" />
                <label class="grid gap-1 text-sm font-semibold">Sex<select v-model="form.sex" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900"><option value="female">Female</option><option value="male">Male</option><option value="intersex">Intersex</option><option value="unknown">Unknown</option></select></label>
                <TextInput id="patient_dob" v-model="form.date_of_birth" label="Date of birth" type="date" :error="form.errors.date_of_birth" />
                <TextInput id="patient_age" v-model="form.estimated_age_years" label="Estimated age" type="number" :error="form.errors.estimated_age_years" />
                <label class="flex items-center gap-2 text-sm font-semibold"><input v-model="form.is_dob_estimated" class="rounded border-slate-300" type="checkbox"> Date of birth is estimated</label>
                <TextInput id="patient_marital" v-model="form.marital_status" label="Marital status" :error="form.errors.marital_status" />
                <TextInput id="patient_occupation" v-model="form.occupation" label="Occupation" :error="form.errors.occupation" />
                <TextInput id="patient_phone" v-model="form.phone" label="Phone" :error="form.errors.phone" />
                <TextInput id="patient_email" v-model="form.email" label="Email" type="email" :error="form.errors.email" />
                <label class="grid gap-1 text-sm font-semibold sm:col-span-2">Address<textarea v-model="form.address" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900" rows="2"></textarea></label>
            </div>

            <div class="space-y-3">
                <div class="flex items-center justify-between gap-3"><h3 class="font-bold">Identifiers</h3><button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="addIdentifier">Add</button></div>
                <div v-for="(identifier, index) in form.identifiers" :key="index" class="grid gap-2 sm:grid-cols-[1fr_1fr_auto]">
                    <input v-model="identifier.type" class="rounded-md border-slate-300 text-sm" placeholder="Type">
                    <input v-model="identifier.value" class="rounded-md border-slate-300 text-sm" placeholder="Value">
                    <button class="rounded-md border border-rose-300 px-3 py-2 text-sm font-bold text-rose-700" type="button" @click="removeIdentifier(index)">Remove</button>
                </div>
            </div>

            <div class="space-y-3">
                <div class="flex items-center justify-between gap-3"><h3 class="font-bold">Contacts and Next of Kin</h3><button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="addContact">Add</button></div>
                <div v-for="(contact, index) in form.contacts" :key="index" class="grid gap-2 rounded-md border border-slate-200 p-3">
                    <input v-model="contact.name" class="rounded-md border-slate-300 text-sm" placeholder="Name">
                    <div class="grid gap-2 sm:grid-cols-2">
                        <input v-model="contact.relationship" class="rounded-md border-slate-300 text-sm" placeholder="Relationship">
                        <input v-model="contact.phone" class="rounded-md border-slate-300 text-sm" placeholder="Phone">
                        <input v-model="contact.email" class="rounded-md border-slate-300 text-sm" placeholder="Email">
                        <label class="flex items-center gap-2 text-sm"><input v-model="contact.is_next_of_kin" class="rounded border-slate-300" type="checkbox"> Next of kin</label>
                    </div>
                    <button class="w-fit rounded-md border border-rose-300 px-3 py-2 text-sm font-bold text-rose-700" type="button" @click="removeContact(index)">Remove contact</button>
                </div>
            </div>
        </FormModal>
    </AppLayout>
</template>
