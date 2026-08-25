<script setup>
import ActionToolbar from '@/Components/Admin/ActionToolbar.vue';
import FormModal from '@/Components/Admin/FormModal.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    donors: Object,
    donations: Array,
    components: Array,
    canViewInventory: Boolean,
    requests: Array,
    patients: Array,
    encounters: Array,
    admissions: Array,
    clinicians: Array,
    reports: Object,
    facilities: Array,
    locations: Array,
    categories: Array,
    componentTypes: Array,
    screeningTests: Array,
    labTests: Array,
});

const activeModal = ref(null);
const donorForm = useForm({ blood_donor_category_id: '', first_name: '', last_name: '', phone: '', email: '', identifier_type: '', identifier_value: '', consented_at: '', consent_reference: '' });
const locationForm = useForm({ facility_id: props.facilities?.[0]?.id || '', code: '', name: '', type: 'blood_bank', notes: '' });
const storageForm = useForm({ blood_bank_location_id: props.locations?.[0]?.id || '', code: '', name: '', storage_type: 'refrigerator', notes: '' });
const categoryForm = useForm({ code: '', name: '', description: '' });
const componentTypeForm = useForm({ code: '', name: '', default_shelf_life_days: '', notes: '' });
const screeningTestForm = useForm({ lab_test_id: '', code: '', name: '', is_required_for_release: true, notes: '' });
const collectionForm = useForm({ facility_id: props.facilities?.[0]?.id || '', blood_donor_id: '', blood_donation_appointment_id: '', blood_bank_location_id: props.locations?.[0]?.id || '', collected_at: '', bag_type: '', volume_ml: '', notes: '' });
const requestForm = useForm({ facility_id: props.facilities?.[0]?.id || '', patient_id: '', clinical_encounter_id: '', admission_id: '', requesting_clinician_id: props.clinicians?.[0]?.id || '', blood_component_type_id: props.componentTypes?.[0]?.id || '', quantity_requested: 1, clinical_indication: '', priority: 'routine', required_at: '' });

const modalForms = {
    request: requestForm,
    donor: donorForm,
    collection: collectionForm,
    location: locationForm,
    storage: storageForm,
    category: categoryForm,
    componentType: componentTypeForm,
    screeningTest: screeningTestForm,
};

const modalTitles = {
    request: 'Patient Blood Request',
    donor: 'Register Donor',
    collection: 'Record Collection',
    location: 'Add Location',
    storage: 'Add Storage Unit',
    category: 'Add Donor Category',
    componentType: 'Add Component Type',
    screeningTest: 'Add Screening Test',
};

const modalUrls = {
    request: '/admin/blood-bank/requests',
    donor: '/admin/blood-bank/donors',
    collection: '/admin/blood-bank/collections',
    location: '/admin/blood-bank/locations',
    storage: '/admin/blood-bank/storage-units',
    category: '/admin/blood-bank/categories',
    componentType: '/admin/blood-bank/component-types',
    screeningTest: '/admin/blood-bank/screening-tests',
};

function submitActive() {
    const key = activeModal.value;
    const form = modalForms[key];

    form.post(modalUrls[key], {
        preserveScroll: true,
        onSuccess: () => {
            activeModal.value = null;
            form.reset();
        },
    });
}
</script>

<template>
    <AppLayout title="Blood Bank">
        <PageHeader title="Blood Bank" description="Patient requests, donor collections, component stock and blood-bank setup.">
            <template #actions>
                <ActionToolbar align="end">
                    <PrimaryButton type="button" @click="activeModal = 'request'">Create Request</PrimaryButton>
                    <button v-if="canViewInventory" class="rounded-md border px-3 py-2 text-sm font-bold" style="border-color: var(--admin-border);" type="button" @click="activeModal = 'donor'">Add Donor</button>
                    <button v-if="canViewInventory" class="rounded-md border px-3 py-2 text-sm font-bold" style="border-color: var(--admin-border);" type="button" @click="activeModal = 'collection'">Record Collection</button>
                </ActionToolbar>
            </template>
        </PageHeader>

        <div class="space-y-5 overflow-x-hidden">
            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div v-for="(value, key) in reports" :key="key" class="rounded-md border p-4" style="border-color: var(--admin-border); background: var(--admin-surface);">
                    <p class="text-xs font-black uppercase" style="color: var(--admin-text-muted);">{{ key.replace('_', ' ') }}</p>
                    <p class="mt-2 text-2xl font-black">{{ Array.isArray(value) ? value.length : value }}</p>
                </div>
            </section>

            <section class="rounded-md border p-4 sm:p-5" style="border-color: var(--admin-border); background: var(--admin-surface);">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="font-black">Request Worklist</h2>
                    <button class="rounded-md border px-3 py-2 text-sm font-bold" style="border-color: var(--admin-border);" type="button" @click="activeModal = 'request'">Create Request</button>
                </div>
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead style="color: var(--admin-text-muted);">
                            <tr>
                                <th class="whitespace-nowrap py-2 pr-4">Request</th>
                                <th class="whitespace-nowrap py-2 pr-4">Patient</th>
                                <th class="whitespace-nowrap py-2 pr-4">Component</th>
                                <th class="whitespace-nowrap py-2 pr-4">State</th>
                                <th class="whitespace-nowrap py-2 pr-4">Outstanding</th>
                                <th class="whitespace-nowrap py-2 pr-4 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="request in requests" :key="request.id" class="border-t" style="border-color: var(--admin-border);">
                                <td class="py-3 pr-4 font-bold">{{ request.request_number }}</td>
                                <td class="py-3 pr-4">{{ request.patient?.full_name }}</td>
                                <td class="py-3 pr-4">{{ request.component_type?.name }} x {{ request.quantity_requested }}</td>
                                <td class="py-3 pr-4">{{ request.state }}</td>
                                <td class="py-3 pr-4">{{ Math.max(0, request.quantity_requested - request.quantity_issued) }}</td>
                                <td class="py-3 pr-4 text-right"><Link class="font-bold" style="color: var(--public-accent);" :href="`/admin/blood-bank/requests/${request.id}`">Open</Link></td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="requests.length === 0" class="py-5 text-sm" style="color: var(--admin-text-muted);">No patient blood requests.</p>
                </div>
            </section>

            <section v-if="canViewInventory" class="grid gap-5 xl:grid-cols-2">
                <div class="rounded-md border p-4 sm:p-5" style="border-color: var(--admin-border); background: var(--admin-surface);">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="font-black">Donors</h2>
                        <button class="rounded-md border px-3 py-2 text-sm font-bold" style="border-color: var(--admin-border);" type="button" @click="activeModal = 'donor'">Add Donor</button>
                    </div>
                    <div class="mt-3 grid gap-3">
                        <div v-for="donor in donors.data" :key="donor.id" class="flex min-w-0 flex-wrap items-center justify-between gap-3 rounded-md border p-3" style="border-color: var(--admin-border);">
                            <div class="min-w-0">
                                <p class="font-bold break-words">{{ donor.full_name }}</p>
                                <p class="text-sm" style="color: var(--admin-text-muted);">{{ donor.donor_number }} - {{ donor.status }}</p>
                            </div>
                            <Link class="font-bold" style="color: var(--public-accent);" :href="`/admin/blood-bank/donors/${donor.id}`">Open</Link>
                        </div>
                    </div>
                </div>

                <div class="rounded-md border p-4 sm:p-5" style="border-color: var(--admin-border); background: var(--admin-surface);">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="font-black">Recent Donations</h2>
                        <button class="rounded-md border px-3 py-2 text-sm font-bold" style="border-color: var(--admin-border);" type="button" @click="activeModal = 'collection'">Record Collection</button>
                    </div>
                    <div class="mt-3 grid gap-3">
                        <div v-for="donation in donations" :key="donation.id" class="flex min-w-0 flex-wrap items-center justify-between gap-3 rounded-md border p-3" style="border-color: var(--admin-border);">
                            <div class="min-w-0">
                                <p class="font-bold break-words">{{ donation.donation_number }}</p>
                                <p class="text-sm" style="color: var(--admin-text-muted);">{{ donation.donor?.full_name }} - {{ donation.status }}</p>
                            </div>
                            <Link class="font-bold" style="color: var(--public-accent);" :href="`/admin/blood-bank/donations/${donation.id}`">Open</Link>
                        </div>
                    </div>
                </div>
            </section>

            <section v-if="canViewInventory" class="rounded-md border p-4 sm:p-5" style="border-color: var(--admin-border); background: var(--admin-surface);">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="font-black">Configuration</h2>
                    <ActionToolbar align="end">
                        <button class="rounded-md border px-3 py-2 text-sm font-bold" style="border-color: var(--admin-border);" type="button" @click="activeModal = 'location'">Location</button>
                        <button class="rounded-md border px-3 py-2 text-sm font-bold" style="border-color: var(--admin-border);" type="button" @click="activeModal = 'storage'">Storage</button>
                        <button class="rounded-md border px-3 py-2 text-sm font-bold" style="border-color: var(--admin-border);" type="button" @click="activeModal = 'category'">Category</button>
                        <button class="rounded-md border px-3 py-2 text-sm font-bold" style="border-color: var(--admin-border);" type="button" @click="activeModal = 'componentType'">Component Type</button>
                        <button class="rounded-md border px-3 py-2 text-sm font-bold" style="border-color: var(--admin-border);" type="button" @click="activeModal = 'screeningTest'">Screening Test</button>
                    </ActionToolbar>
                </div>
                <div class="mt-4 grid gap-3 md:grid-cols-3">
                    <div class="rounded-md border p-3" style="border-color: var(--admin-border);">
                        <p class="font-bold">Locations</p>
                        <p class="text-sm" style="color: var(--admin-text-muted);">{{ locations.length }} configured</p>
                    </div>
                    <div class="rounded-md border p-3" style="border-color: var(--admin-border);">
                        <p class="font-bold">Component Types</p>
                        <p class="text-sm" style="color: var(--admin-text-muted);">{{ componentTypes.length }} configured</p>
                    </div>
                    <div class="rounded-md border p-3" style="border-color: var(--admin-border);">
                        <p class="font-bold">Screening Tests</p>
                        <p class="text-sm" style="color: var(--admin-text-muted);">{{ screeningTests.length }} configured</p>
                    </div>
                </div>
            </section>
        </div>

        <FormModal :show="Boolean(activeModal)" :title="modalTitles[activeModal] || 'Blood Bank Form'" size="xl" :form="modalForms[activeModal] || requestForm" submit-label="Save" @close="activeModal = null" @submit="submitActive">
            <div v-if="activeModal === 'request'" class="grid gap-3 md:grid-cols-2">
                <select v-model="requestForm.patient_id" class="rounded-md border p-2" style="border-color: var(--admin-border);" required><option value="">Patient</option><option v-for="patient in patients" :key="patient.id" :value="patient.id">{{ patient.hospital_number }} - {{ patient.full_name }}</option></select>
                <select v-model="requestForm.facility_id" class="rounded-md border p-2" style="border-color: var(--admin-border);" required><option v-for="facility in facilities" :key="facility.id" :value="facility.id">{{ facility.name }}</option></select>
                <select v-model="requestForm.requesting_clinician_id" class="rounded-md border p-2" style="border-color: var(--admin-border);" required><option v-for="clinician in clinicians" :key="clinician.id" :value="clinician.id">{{ clinician.user?.full_name || clinician.job_title }}</option></select>
                <select v-model="requestForm.blood_component_type_id" class="rounded-md border p-2" style="border-color: var(--admin-border);" required><option v-for="type in componentTypes" :key="type.id" :value="type.id">{{ type.name }}</option></select>
                <input v-model="requestForm.quantity_requested" type="number" min="1" class="rounded-md border p-2" style="border-color: var(--admin-border);" required>
                <select v-model="requestForm.priority" class="rounded-md border p-2" style="border-color: var(--admin-border);"><option value="routine">Routine</option><option value="urgent">Urgent</option><option value="emergency">Emergency</option></select>
                <input v-model="requestForm.required_at" type="datetime-local" class="rounded-md border p-2" style="border-color: var(--admin-border);">
                <select v-model="requestForm.clinical_encounter_id" class="rounded-md border p-2" style="border-color: var(--admin-border);"><option value="">Encounter</option><option v-for="encounter in encounters" :key="encounter.id" :value="encounter.id">Encounter #{{ encounter.id }} - {{ encounter.status }}</option></select>
                <select v-model="requestForm.admission_id" class="rounded-md border p-2" style="border-color: var(--admin-border);"><option value="">Admission</option><option v-for="admission in admissions" :key="admission.id" :value="admission.id">{{ admission.admission_number }} - {{ admission.status }}</option></select>
                <textarea v-model="requestForm.clinical_indication" class="min-h-28 rounded-md border p-2 md:col-span-2" style="border-color: var(--admin-border);" placeholder="Clinical indication" required></textarea>
            </div>
            <div v-else-if="activeModal === 'donor'" class="grid gap-3 md:grid-cols-2">
                <select v-model="donorForm.blood_donor_category_id" class="rounded-md border p-2" style="border-color: var(--admin-border);"><option value="">Category</option><option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option></select>
                <input v-model="donorForm.first_name" class="rounded-md border p-2" style="border-color: var(--admin-border);" placeholder="First name" required>
                <input v-model="donorForm.last_name" class="rounded-md border p-2" style="border-color: var(--admin-border);" placeholder="Last name" required>
                <input v-model="donorForm.phone" class="rounded-md border p-2" style="border-color: var(--admin-border);" placeholder="Phone">
                <input v-model="donorForm.email" class="rounded-md border p-2" style="border-color: var(--admin-border);" placeholder="Email">
                <input v-model="donorForm.identifier_type" class="rounded-md border p-2" style="border-color: var(--admin-border);" placeholder="Identifier type">
                <input v-model="donorForm.identifier_value" class="rounded-md border p-2" style="border-color: var(--admin-border);" placeholder="Identifier value">
                <input v-model="donorForm.consented_at" type="datetime-local" class="rounded-md border p-2" style="border-color: var(--admin-border);">
                <input v-model="donorForm.consent_reference" class="rounded-md border p-2 md:col-span-2" style="border-color: var(--admin-border);" placeholder="Consent reference">
            </div>
            <div v-else-if="activeModal === 'collection'" class="grid gap-3 md:grid-cols-2">
                <select v-model="collectionForm.blood_donor_id" class="rounded-md border p-2" style="border-color: var(--admin-border);" required><option value="">Donor</option><option v-for="donor in donors.data" :key="donor.id" :value="donor.id">{{ donor.donor_number }} - {{ donor.full_name }}</option></select>
                <select v-model="collectionForm.facility_id" class="rounded-md border p-2" style="border-color: var(--admin-border);" required><option v-for="facility in facilities" :key="facility.id" :value="facility.id">{{ facility.name }}</option></select>
                <select v-model="collectionForm.blood_bank_location_id" class="rounded-md border p-2" style="border-color: var(--admin-border);" required><option v-for="location in locations" :key="location.id" :value="location.id">{{ location.name }}</option></select>
                <input v-model="collectionForm.collected_at" type="datetime-local" class="rounded-md border p-2" style="border-color: var(--admin-border);">
                <input v-model="collectionForm.bag_type" class="rounded-md border p-2" style="border-color: var(--admin-border);" placeholder="Bag type" required>
                <input v-model="collectionForm.volume_ml" type="number" class="rounded-md border p-2" style="border-color: var(--admin-border);" placeholder="Volume ml">
                <textarea v-model="collectionForm.notes" class="min-h-24 rounded-md border p-2 md:col-span-2" style="border-color: var(--admin-border);" placeholder="Notes"></textarea>
            </div>
            <div v-else-if="activeModal === 'location'" class="grid gap-3 md:grid-cols-2">
                <select v-model="locationForm.facility_id" class="rounded-md border p-2" style="border-color: var(--admin-border);"><option v-for="facility in facilities" :key="facility.id" :value="facility.id">{{ facility.name }}</option></select>
                <input v-model="locationForm.code" class="rounded-md border p-2" style="border-color: var(--admin-border);" placeholder="Location code">
                <input v-model="locationForm.name" class="rounded-md border p-2" style="border-color: var(--admin-border);" placeholder="Location name">
                <textarea v-model="locationForm.notes" class="min-h-24 rounded-md border p-2 md:col-span-2" style="border-color: var(--admin-border);" placeholder="Notes"></textarea>
            </div>
            <div v-else-if="activeModal === 'storage'" class="grid gap-3 md:grid-cols-2">
                <select v-model="storageForm.blood_bank_location_id" class="rounded-md border p-2" style="border-color: var(--admin-border);"><option v-for="location in locations" :key="location.id" :value="location.id">{{ location.name }}</option></select>
                <input v-model="storageForm.code" class="rounded-md border p-2" style="border-color: var(--admin-border);" placeholder="Storage code">
                <input v-model="storageForm.name" class="rounded-md border p-2" style="border-color: var(--admin-border);" placeholder="Storage name">
                <textarea v-model="storageForm.notes" class="min-h-24 rounded-md border p-2 md:col-span-2" style="border-color: var(--admin-border);" placeholder="Notes"></textarea>
            </div>
            <div v-else-if="activeModal === 'category'" class="grid gap-3 md:grid-cols-2">
                <input v-model="categoryForm.code" class="rounded-md border p-2" style="border-color: var(--admin-border);" placeholder="Category code">
                <input v-model="categoryForm.name" class="rounded-md border p-2" style="border-color: var(--admin-border);" placeholder="Category name">
                <textarea v-model="categoryForm.description" class="min-h-24 rounded-md border p-2 md:col-span-2" style="border-color: var(--admin-border);" placeholder="Description"></textarea>
            </div>
            <div v-else-if="activeModal === 'componentType'" class="grid gap-3 md:grid-cols-2">
                <input v-model="componentTypeForm.code" class="rounded-md border p-2" style="border-color: var(--admin-border);" placeholder="Component code">
                <input v-model="componentTypeForm.name" class="rounded-md border p-2" style="border-color: var(--admin-border);" placeholder="Component name">
                <input v-model="componentTypeForm.default_shelf_life_days" type="number" class="rounded-md border p-2" style="border-color: var(--admin-border);" placeholder="Shelf life days">
                <textarea v-model="componentTypeForm.notes" class="min-h-24 rounded-md border p-2 md:col-span-2" style="border-color: var(--admin-border);" placeholder="Notes"></textarea>
            </div>
            <div v-else-if="activeModal === 'screeningTest'" class="grid gap-3 md:grid-cols-2">
                <select v-model="screeningTestForm.lab_test_id" class="rounded-md border p-2" style="border-color: var(--admin-border);"><option value="">Lab test</option><option v-for="test in labTests" :key="test.id" :value="test.id">{{ test.name }}</option></select>
                <input v-model="screeningTestForm.code" class="rounded-md border p-2" style="border-color: var(--admin-border);" placeholder="Screening code">
                <input v-model="screeningTestForm.name" class="rounded-md border p-2" style="border-color: var(--admin-border);" placeholder="Screening name">
                <label class="text-sm font-semibold"><input v-model="screeningTestForm.is_required_for_release" type="checkbox"> Required for release</label>
                <textarea v-model="screeningTestForm.notes" class="min-h-24 rounded-md border p-2 md:col-span-2" style="border-color: var(--admin-border);" placeholder="Notes"></textarea>
            </div>
        </FormModal>
    </AppLayout>
</template>
