<script setup>
import ActionToolbar from '@/Components/Admin/ActionToolbar.vue';
import ConfirmDialog from '@/Components/Admin/ConfirmDialog.vue';
import FormModal from '@/Components/Admin/FormModal.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    admissions: { type: Array, default: () => [] },
    beds: { type: Array, default: () => [] },
    wards: { type: Array, default: () => [] },
    census: { type: Array, default: () => [] },
    bedIntegrityIssues: { type: Array, default: () => [] },
    facilities: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    patients: { type: Array, default: () => [] },
    visits: { type: Array, default: () => [] },
    encounters: { type: Array, default: () => [] },
    clinicians: { type: Array, default: () => [] },
    bedClasses: { type: Array, default: () => [] },
    rooms: { type: Array, default: () => [] },
    services: { type: Array, default: () => [] },
});

const page = usePage();
const permissions = computed(() => page.props.auth.permissions || []);
const roles = computed(() => page.props.auth.roles || []);
const can = (permission) => roles.value.includes('superadmin') || permissions.value.includes(permission);
const classForm = useForm({ code: '', name: '', billable_service_id: '', description: '', is_active: true });
const wardForm = useForm({ facility_id: props.facilities[0]?.id || '', department_id: '', code: '', name: '', notes: '', status: 'active' });
const roomForm = useForm({ ward_id: '', code: '', name: '', status: 'active' });
const bedForm = useForm({ ward_id: '', ward_room_id: '', bed_class_id: '', code: '', label: '' });
const requestForm = useForm({ facility_id: props.facilities[0]?.id || '', patient_id: '', visit_id: '', clinical_encounter_id: '', attending_clinician_id: '', department_id: '', reason: '', provisional_diagnosis: '', notes: '', administrative_clearance_required: false });
const actionForm = useForm({ action: '', bed_id: '', reason: '', discharge_destination: '', discharge_outcome: '', discharge_notes: '', override: false, override_reason: '' });
const bedStateForm = useForm({ state: 'available', reason: '' });
const setupModal = ref(null);
const setupEditing = ref(null);
const setupDeleteTarget = ref(null);
const setupDeleteForm = useForm({});
const reconcileForm = useForm({});
const resetForm = useForm({ confirmation: '' });
const resetModal = ref(false);
const actionTarget = ref(null);
const bedTarget = ref(null);
const requestModal = ref(false);

function fullName(patient) {
    return [patient?.first_name, patient?.middle_name, patient?.last_name].filter(Boolean).join(' ');
}

function availableBed(except = null) {
    return props.beds.find((bed) => ['available', 'reserved'].includes(bed.effective_state || bed.state) && bed.id !== except)?.id || '';
}

const bedStateLabels = {
    available: 'Ready',
    reserved: 'Reserved',
    occupied: 'Occupied',
    cleaning: 'Needs cleaning',
    maintenance: 'Maintenance',
    blocked: 'Blocked',
    inactive: 'Inactive',
};

const bedStateHelp = {
    available: 'Ready for a patient.',
    reserved: 'Temporarily held for an approved admission.',
    occupied: 'Backed by an active admitted patient.',
    cleaning: 'Patient has left; housekeeping turnover is required.',
    maintenance: 'Unavailable while repair or technical work is in progress.',
    blocked: 'Unavailable for an operational reason.',
    inactive: 'No longer in service.',
};

function effectiveBedState(bed) {
    return bed.effective_state || bed.state;
}

function openAction(admission, actionName) {
    actionTarget.value = admission;
    actionForm.defaults({
        action: actionName,
        bed_id: ['admit', 'transfer'].includes(actionName) ? availableBed(admission.current_bed_id) : '',
        reason: '',
        discharge_destination: actionName === 'discharge' ? 'home' : '',
        discharge_outcome: actionName === 'discharge' ? 'stable' : '',
        discharge_notes: '',
        override: actionName === 'discharge',
        override_reason: actionName === 'discharge' ? 'Authorized administrative override' : '',
    });
    actionForm.reset();
}

function submitAction() {
    actionForm.patch(`/admin/admissions/${actionTarget.value.id}`, { preserveScroll: true, onSuccess: () => { actionTarget.value = null; actionForm.reset(); } });
}

function openBedState(bed, state) {
    bedTarget.value = bed;
    bedStateForm.defaults({ state, reason: `${state} from bed board` });
    bedStateForm.reset();
}

function submitBedState() {
    bedStateForm.patch(`/admin/admissions/beds/${bedTarget.value.id}/state`, { preserveScroll: true, onSuccess: () => { bedTarget.value = null; bedStateForm.reset(); } });
}

function submitRequest() {
    requestForm.post('/admin/admissions/requests', { preserveScroll: true, onSuccess: () => { requestModal.value = false; requestForm.reset(); } });
}

function openSetup(type, item = null) {
    setupEditing.value = item ? { type, item } : null;
    setupModal.value = type;

    if (type === 'class') {
        classForm.defaults(item ? {
            code: item.code,
            name: item.name,
            billable_service_id: item.billable_service_id || '',
            description: item.description || '',
            is_active: Boolean(item.is_active),
        } : { code: '', name: '', billable_service_id: '', description: '', is_active: true });
        classForm.reset();
    }

    if (type === 'ward') {
        wardForm.defaults(item ? {
            facility_id: item.facility_id,
            department_id: item.department_id || '',
            code: item.code,
            name: item.name,
            notes: item.notes || '',
            status: item.status || 'active',
        } : { facility_id: props.facilities[0]?.id || '', department_id: '', code: '', name: '', notes: '', status: 'active' });
        wardForm.reset();
    }

    if (type === 'room') {
        roomForm.defaults(item ? {
            ward_id: item.ward_id,
            code: item.code,
            name: item.name,
            status: item.status || 'active',
        } : { ward_id: '', code: '', name: '', status: 'active' });
        roomForm.reset();
    }

    if (type === 'bed') {
        bedForm.defaults(item ? {
            ward_id: item.ward_id,
            ward_room_id: item.ward_room_id || '',
            bed_class_id: item.bed_class_id,
            code: item.code,
            label: item.label,
        } : { ward_id: '', ward_room_id: '', bed_class_id: '', code: '', label: '' });
        bedForm.reset();
    }
}

function submitSetup(type, form) {
    const editing = setupEditing.value?.type === type ? setupEditing.value.item : null;
    const base = {
        class: '/admin/admissions/bed-classes',
        ward: '/admin/admissions/wards',
        room: '/admin/admissions/rooms',
        bed: '/admin/admissions/beds',
    }[type];

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            setupModal.value = null;
            setupEditing.value = null;
            form.reset();
        },
    };

    editing ? form.patch(`${base}/${editing.id}`, options) : form.post(base, options);
}

function openSetupDelete(type, item) {
    setupDeleteForm.clearErrors();
    setupDeleteTarget.value = { type, item };
}

function deleteSetup() {
    if (!setupDeleteTarget.value) return;
    const { type, item } = setupDeleteTarget.value;
    const plural = { class: 'bed-classes', ward: 'wards', room: 'rooms', bed: 'beds' }[type];

    setupDeleteForm.delete(`/admin/admissions/${plural}/${item.id}`, {
        preserveScroll: true,
        onSuccess: () => { setupDeleteTarget.value = null; },
    });
}

function reconcileBeds() {
    reconcileForm.post('/admin/admissions/reconcile-beds', { preserveScroll: true });
}

function purgeAdmissionsDemo() {
    resetForm.delete('/admin/admissions/purge-demo', {
        preserveScroll: true,
        onSuccess: () => {
            resetModal.value = false;
            resetForm.defaults({ confirmation: '' });
            resetForm.reset();
        },
    });
}
</script>

<template>
    <Head title="Admissions" />
    <AppLayout title="Admissions">
        <PageHeader title="Admissions" description="Manage admission requests, bed allocation and bed-board state.">
            <template #actions>
                <ActionToolbar align="end">
                    <PrimaryButton v-if="can('admissions.request')" type="button" @click="requestModal = true">Request Admission</PrimaryButton>
                    <button v-if="can('admissions.manage')" class="rounded-md border px-4 py-2 text-sm font-bold" style="border-color: var(--admin-border);" type="button" @click="openSetup('class')">Add Bed Class</button>
                    <button v-if="can('admissions.manage')" class="rounded-md border px-4 py-2 text-sm font-bold" style="border-color: var(--admin-border);" type="button" @click="openSetup('ward')">Add Ward</button>
                    <button v-if="can('admissions.manage')" class="rounded-md border px-4 py-2 text-sm font-bold" style="border-color: var(--admin-border);" type="button" @click="openSetup('room')">Add Room</button>
                    <button v-if="can('admissions.manage')" class="rounded-md border px-4 py-2 text-sm font-bold" style="border-color: var(--admin-border);" type="button" @click="openSetup('bed')">Add Bed</button>
                </ActionToolbar>
            </template>
        </PageHeader>

        <div class="space-y-6">
            <section class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="font-black">Bed Census</h2>
                        <p class="mt-1 text-sm text-slate-500">Occupied counts come from active admissions, not from a manually stored bed flag.</p>
                    </div>
                    <button v-if="can('admissions.manage')" class="rounded-md border px-3 py-2 text-sm font-bold" type="button" :disabled="reconcileForm.processing" @click="reconcileBeds">
                        {{ reconcileForm.processing ? 'Reconciling…' : 'Reconcile bed status' }}
                    </button>
                </div>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div v-for="row in census" :key="row.state" class="rounded-md border border-slate-200 p-3 dark:border-slate-800">
                        <p class="text-xs font-bold uppercase text-slate-500">{{ bedStateLabels[row.state] || row.state }}</p>
                        <p class="text-2xl font-black">{{ row.count }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ bedStateHelp[row.state] || '' }}</p>
                    </div>
                </div>
                <div v-if="bedIntegrityIssues.length" class="mt-4 rounded-md border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
                    <p class="font-black">Bed-status inconsistency detected</p>
                    <p class="mt-1">{{ bedIntegrityIssues.length }} bed(s) disagree with the active admission records. Use “Reconcile bed status” to repair the stored state safely.</p>
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="font-black">Admission Worklist</h2>
                <div class="mt-4 divide-y divide-slate-100 dark:divide-slate-800">
                    <article v-for="admission in admissions" :key="admission.id" class="grid gap-3 py-3 lg:grid-cols-[1fr_auto]">
                        <div class="min-w-0">
                            <p class="truncate font-bold">{{ admission.admission_number || `Request #${admission.id}` }} - {{ admission.status }}</p>
                            <p class="truncate text-sm text-slate-500">{{ admission.patient?.hospital_number }} - {{ fullName(admission.patient) }}</p>
                            <p class="truncate text-xs text-slate-500">{{ admission.ward?.name || 'No ward' }} {{ admission.bed?.label || '' }}</p>
                            <p v-if="admission.invoice" class="text-xs font-semibold text-emerald-600">Invoice {{ admission.invoice.total_minor }}</p>
                        </div>
                        <ActionToolbar>
                            <button v-if="can('admissions.approve')" class="rounded-md border px-3 py-2 text-xs font-bold" type="button" @click="openAction(admission, 'approve')">Approve</button>
                            <button v-if="can('admissions.manage')" class="rounded-md border px-3 py-2 text-xs font-bold" type="button" @click="openAction(admission, 'admit')">Allocate bed</button>
                            <button v-if="can('admissions.manage')" class="rounded-md border px-3 py-2 text-xs font-bold" type="button" @click="openAction(admission, 'transfer')">Transfer</button>
                            <button v-if="can('admissions.discharge')" class="rounded-md border px-3 py-2 text-xs font-bold" type="button" @click="openAction(admission, 'discharge')">Discharge</button>
                            <button v-if="can('admissions.approve')" class="rounded-md border px-3 py-2 text-xs font-bold" type="button" @click="openAction(admission, 'reject')">Reject</button>
                            <button v-if="can('admissions.manage')" class="rounded-md border px-3 py-2 text-xs font-bold" type="button" @click="openAction(admission, 'cancel')">Cancel</button>
                        </ActionToolbar>
                    </article>
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <div>
                    <h2 class="font-black">Operational Bed Board</h2>
                    <p class="mt-1 text-sm text-slate-500">Day-to-day availability only. “Occupied” is controlled by admissions and cannot be set manually.</p>
                </div>
                <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <article v-for="bed in beds" :key="bed.id" class="rounded-md border p-3 text-sm" :class="bed.integrity_issue ? 'border-amber-400' : 'border-slate-200 dark:border-slate-800'">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="font-bold">{{ bed.label }} · {{ bedStateLabels[effectiveBedState(bed)] || effectiveBedState(bed) }}</p>
                                <p class="text-slate-500">{{ bed.ward?.name }}<span v-if="bed.room?.name"> · {{ bed.room.name }}</span><span v-if="bed.bed_class?.name"> · {{ bed.bed_class.name }}</span></p>
                            </div>
                            <span class="rounded-full border px-2 py-1 text-xs font-bold">{{ bed.code }}</span>
                        </div>
                        <p class="mt-2 text-xs text-slate-500">{{ bedStateHelp[effectiveBedState(bed)] }}</p>
                        <p v-if="bed.integrity_issue" class="mt-2 rounded bg-amber-50 p-2 text-xs font-semibold text-amber-900 dark:bg-amber-950/30 dark:text-amber-200">{{ bed.integrity_issue }}</p>
                        <ActionToolbar v-if="can('admissions.manage')" class="mt-3">
                            <button v-if="effectiveBedState(bed) !== 'occupied'" class="rounded-md border px-2 py-1 text-xs font-bold" type="button" @click="openBedState(bed, 'reserved')">Reserve</button>
                            <button v-if="effectiveBedState(bed) !== 'occupied'" class="rounded-md border px-2 py-1 text-xs font-bold" type="button" @click="openBedState(bed, 'available')">Mark ready</button>
                            <button v-if="effectiveBedState(bed) !== 'occupied'" class="rounded-md border px-2 py-1 text-xs font-bold" type="button" @click="openBedState(bed, 'cleaning')">Needs cleaning</button>
                            <button v-if="effectiveBedState(bed) !== 'occupied'" class="rounded-md border px-2 py-1 text-xs font-bold" type="button" @click="openBedState(bed, 'maintenance')">Maintenance</button>
                            <button class="rounded-md border px-2 py-1 text-xs font-bold" type="button" @click="openSetup('bed', bed)">Edit</button>
                            <button v-if="effectiveBedState(bed) !== 'occupied'" class="rounded-md border border-rose-300 px-2 py-1 text-xs font-bold text-rose-700" type="button" @click="openSetupDelete('bed', bed)">Delete</button>
                        </ActionToolbar>
                    </article>
                    <p v-if="!beds.length" class="text-sm text-slate-500">No beds configured yet.</p>
                </div>
            </section>

            <section v-if="can('admissions.manage')" class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="font-black">Setup & Capacity</h2>
                        <p class="mt-1 text-sm text-slate-500">Configure accommodation classes, wards, rooms and beds. These are master records, not patient activity.</p>
                    </div>
                    <button v-if="roles.includes('superadmin') || roles.includes('hospital-admin')" class="rounded-md border border-amber-400 px-3 py-2 text-sm font-bold text-amber-800 dark:text-amber-300" type="button" @click="resetModal = true">Reset pre-production admissions data</button>
                </div>

                <div class="mt-5 grid gap-5 xl:grid-cols-3">
                    <div>
                        <div class="flex items-center justify-between"><h3 class="font-bold">Bed classes</h3><button class="text-xs font-bold underline" type="button" @click="openSetup('class')">Add</button></div>
                        <div class="mt-2 space-y-2">
                            <div v-for="bedClass in bedClasses" :key="bedClass.id" class="rounded-md border border-slate-200 p-3 dark:border-slate-800">
                                <p class="font-semibold">{{ bedClass.name }} <span class="text-xs text-slate-500">({{ bedClass.code }})</span></p>
                                <p class="text-xs text-slate-500">{{ bedClass.is_active ? 'Active' : 'Inactive' }}</p>
                                <ActionToolbar class="mt-2"><button class="rounded border px-2 py-1 text-xs font-bold" type="button" @click="openSetup('class', bedClass)">Edit</button><button class="rounded border border-rose-300 px-2 py-1 text-xs font-bold text-rose-700" type="button" @click="openSetupDelete('class', bedClass)">Delete</button></ActionToolbar>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between"><h3 class="font-bold">Wards</h3><button class="text-xs font-bold underline" type="button" @click="openSetup('ward')">Add</button></div>
                        <div class="mt-2 space-y-2">
                            <div v-for="ward in wards" :key="ward.id" class="rounded-md border border-slate-200 p-3 dark:border-slate-800">
                                <p class="font-semibold">{{ ward.name }} <span class="text-xs text-slate-500">({{ ward.code }})</span></p>
                                <p class="text-xs text-slate-500">{{ ward.facility?.name }} · {{ ward.status }}</p>
                                <ActionToolbar class="mt-2"><button class="rounded border px-2 py-1 text-xs font-bold" type="button" @click="openSetup('ward', ward)">Edit</button><button class="rounded border border-rose-300 px-2 py-1 text-xs font-bold text-rose-700" type="button" @click="openSetupDelete('ward', ward)">Delete</button></ActionToolbar>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between"><h3 class="font-bold">Rooms</h3><button class="text-xs font-bold underline" type="button" @click="openSetup('room')">Add</button></div>
                        <div class="mt-2 space-y-2">
                            <div v-for="room in rooms" :key="room.id" class="rounded-md border border-slate-200 p-3 dark:border-slate-800">
                                <p class="font-semibold">{{ room.name }} <span class="text-xs text-slate-500">({{ room.code }})</span></p>
                                <p class="text-xs text-slate-500">{{ wards.find((ward) => ward.id === room.ward_id)?.name || 'Ward' }} · {{ room.status }}</p>
                                <ActionToolbar class="mt-2"><button class="rounded border px-2 py-1 text-xs font-bold" type="button" @click="openSetup('room', room)">Edit</button><button class="rounded border border-rose-300 px-2 py-1 text-xs font-bold text-rose-700" type="button" @click="openSetupDelete('room', room)">Delete</button></ActionToolbar>
                            </div>
                        </div>
                    </div>
                </div>
                <p v-if="setupDeleteForm.errors.setup" class="mt-4 rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-800">{{ setupDeleteForm.errors.setup }}</p>
            </section>
        </div>

        <FormModal :show="requestModal" :form="requestForm" title="Admission Request" submit-label="Request admission" size="full" @close="requestModal = false" @submit="submitRequest">
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="grid gap-1 text-sm font-semibold sm:col-span-2">Patient<select v-model="requestForm.patient_id" class="rounded-md border-slate-300"><option value="">Patient</option><option v-for="patient in patients" :key="patient.id" :value="patient.id">{{ patient.hospital_number }} - {{ fullName(patient) }}</option></select></label>
                <label class="grid gap-1 text-sm font-semibold">Facility<select v-model="requestForm.facility_id" class="rounded-md border-slate-300"><option v-for="facility in facilities" :key="facility.id" :value="facility.id">{{ facility.name }}</option></select></label>
                <label class="grid gap-1 text-sm font-semibold">Visit<select v-model="requestForm.visit_id" class="rounded-md border-slate-300"><option value="">Visit</option><option v-for="visit in visits" :key="visit.id" :value="visit.id">Visit #{{ visit.id }} - {{ visit.status }}</option></select></label>
                <label class="grid gap-1 text-sm font-semibold">Encounter<select v-model="requestForm.clinical_encounter_id" class="rounded-md border-slate-300"><option value="">Encounter</option><option v-for="encounter in encounters" :key="encounter.id" :value="encounter.id">Encounter #{{ encounter.id }} - {{ encounter.status }}</option></select></label>
                <label class="grid gap-1 text-sm font-semibold">Department<select v-model="requestForm.department_id" class="rounded-md border-slate-300"><option value="">Department</option><option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option></select></label>
                <label class="grid gap-1 text-sm font-semibold">Clinician<select v-model="requestForm.attending_clinician_id" class="rounded-md border-slate-300"><option value="">Clinician</option><option v-for="clinician in clinicians" :key="clinician.id" :value="clinician.id">{{ clinician.user?.full_name || clinician.job_title }}</option></select></label>
                <label class="grid gap-1 text-sm font-semibold sm:col-span-2">Admission reason<textarea v-model="requestForm.reason" class="rounded-md border-slate-300" rows="2"></textarea></label>
                <label class="grid gap-1 text-sm font-semibold sm:col-span-2">Provisional diagnosis<textarea v-model="requestForm.provisional_diagnosis" class="rounded-md border-slate-300" rows="2"></textarea></label>
                <label class="grid gap-1 text-sm font-semibold sm:col-span-2">Notes<textarea v-model="requestForm.notes" class="rounded-md border-slate-300" rows="2"></textarea></label>
                <label class="flex items-center gap-2 text-sm"><input v-model="requestForm.administrative_clearance_required" type="checkbox"> Administrative clearance required</label>
            </div>
        </FormModal>

        <FormModal :show="setupModal === 'class'" :form="classForm" :title="setupEditing?.type === 'class' ? 'Edit Bed Class' : 'Add Bed Class'" :submit-label="setupEditing?.type === 'class' ? 'Save changes' : 'Create class'" size="md" @close="setupModal = null" @submit="submitSetup('class', classForm)">
            <TextInput id="bed_class_code" v-model="classForm.code" label="Code" :error="classForm.errors.code" />
            <TextInput id="bed_class_name" v-model="classForm.name" label="Name" :error="classForm.errors.name" />
            <label class="grid gap-1 text-sm font-semibold">Accommodation service<select v-model="classForm.billable_service_id" class="rounded-md border-slate-300"><option value="">Accommodation service</option><option v-for="service in services" :key="service.id" :value="service.id">{{ service.code }} - {{ service.name }}</option></select></label>
            <label class="grid gap-1 text-sm font-semibold">Description<textarea v-model="classForm.description" class="rounded-md border-slate-300" rows="3"></textarea></label>
            <label class="flex items-center gap-2 text-sm font-semibold"><input v-model="classForm.is_active" type="checkbox"> Active</label>
        </FormModal>

        <FormModal :show="setupModal === 'ward'" :form="wardForm" :title="setupEditing?.type === 'ward' ? 'Edit Ward' : 'Add Ward'" :submit-label="setupEditing?.type === 'ward' ? 'Save changes' : 'Create ward'" size="lg" @close="setupModal = null" @submit="submitSetup('ward', wardForm)">
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="grid gap-1 text-sm font-semibold">Facility<select v-model="wardForm.facility_id" class="rounded-md border-slate-300"><option v-for="facility in facilities" :key="facility.id" :value="facility.id">{{ facility.name }}</option></select></label>
                <label class="grid gap-1 text-sm font-semibold">Department<select v-model="wardForm.department_id" class="rounded-md border-slate-300"><option value="">Department</option><option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option></select></label>
                <TextInput id="ward_code" v-model="wardForm.code" label="Code" :error="wardForm.errors.code" />
                <TextInput id="ward_name" v-model="wardForm.name" label="Name" :error="wardForm.errors.name" />
                <label class="grid gap-1 text-sm font-semibold">Status<select v-model="wardForm.status" class="rounded-md border-slate-300"><option value="active">Active</option><option value="inactive">Inactive</option></select></label>
                <label class="grid gap-1 text-sm font-semibold sm:col-span-2">Notes<textarea v-model="wardForm.notes" class="rounded-md border-slate-300" rows="3"></textarea></label>
            </div>
        </FormModal>

        <FormModal :show="setupModal === 'room'" :form="roomForm" :title="setupEditing?.type === 'room' ? 'Edit Room' : 'Add Room'" :submit-label="setupEditing?.type === 'room' ? 'Save changes' : 'Create room'" size="md" @close="setupModal = null" @submit="submitSetup('room', roomForm)">
            <label class="grid gap-1 text-sm font-semibold">Ward<select v-model="roomForm.ward_id" class="rounded-md border-slate-300"><option value="">Ward</option><option v-for="ward in wards" :key="ward.id" :value="ward.id">{{ ward.name }}</option></select></label>
            <TextInput id="room_code" v-model="roomForm.code" label="Code" :error="roomForm.errors.code" />
            <TextInput id="room_name" v-model="roomForm.name" label="Name" :error="roomForm.errors.name" />
            <label class="grid gap-1 text-sm font-semibold">Status<select v-model="roomForm.status" class="rounded-md border-slate-300"><option value="active">Active</option><option value="inactive">Inactive</option></select></label>
        </FormModal>

        <FormModal :show="setupModal === 'bed'" :form="bedForm" :title="setupEditing?.type === 'bed' ? 'Edit Bed' : 'Add Bed'" :submit-label="setupEditing?.type === 'bed' ? 'Save changes' : 'Create bed'" size="lg" @close="setupModal = null" @submit="submitSetup('bed', bedForm)">
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="grid gap-1 text-sm font-semibold">Ward<select v-model="bedForm.ward_id" class="rounded-md border-slate-300"><option value="">Ward</option><option v-for="ward in wards" :key="ward.id" :value="ward.id">{{ ward.name }}</option></select></label>
                <label class="grid gap-1 text-sm font-semibold">Room<select v-model="bedForm.ward_room_id" class="rounded-md border-slate-300"><option value="">Room</option><option v-for="room in rooms" :key="room.id" :value="room.id">{{ room.name }}</option></select></label>
                <label class="grid gap-1 text-sm font-semibold">Class<select v-model="bedForm.bed_class_id" class="rounded-md border-slate-300"><option value="">Class</option><option v-for="bedClass in bedClasses" :key="bedClass.id" :value="bedClass.id">{{ bedClass.name }}</option></select></label>
                <TextInput id="bed_code" v-model="bedForm.code" label="Code" :error="bedForm.errors.code" />
                <TextInput id="bed_label" v-model="bedForm.label" label="Label" :error="bedForm.errors.label" />
            </div>
        </FormModal>

        <FormModal :show="Boolean(actionTarget)" :form="actionForm" :title="actionForm.action ? `${actionForm.action} admission` : 'Admission action'" submit-label="Save action" size="lg" @close="actionTarget = null" @submit="submitAction">
            <div class="grid gap-4 sm:grid-cols-2">
                <label v-if="['admit', 'transfer'].includes(actionForm.action)" class="grid gap-1 text-sm font-semibold sm:col-span-2">Bed<select v-model="actionForm.bed_id" class="rounded-md border-slate-300"><option value="">Bed</option><option v-for="bed in beds.filter((entry) => ['available', 'reserved'].includes(effectiveBedState(entry)))" :key="bed.id" :value="bed.id">{{ bed.label }} - {{ bed.ward?.name }}</option></select></label>
                <label v-if="['reject', 'cancel', 'transfer', 'approve'].includes(actionForm.action)" class="grid gap-1 text-sm font-semibold sm:col-span-2">Reason<textarea v-model="actionForm.reason" class="rounded-md border-slate-300" rows="3"></textarea></label>
                <label v-if="actionForm.action === 'discharge'" class="grid gap-1 text-sm font-semibold">Destination<input v-model="actionForm.discharge_destination" class="rounded-md border-slate-300"></label>
                <label v-if="actionForm.action === 'discharge'" class="grid gap-1 text-sm font-semibold">Outcome<input v-model="actionForm.discharge_outcome" class="rounded-md border-slate-300"></label>
                <label v-if="actionForm.action === 'discharge'" class="grid gap-1 text-sm font-semibold sm:col-span-2">Discharge notes<textarea v-model="actionForm.discharge_notes" class="rounded-md border-slate-300" rows="3"></textarea></label>
                <label v-if="actionForm.action === 'discharge'" class="flex items-center gap-2 text-sm"><input v-model="actionForm.override" type="checkbox"> Use discharge override</label>
                <label v-if="actionForm.action === 'discharge'" class="grid gap-1 text-sm font-semibold sm:col-span-2">Override reason<textarea v-model="actionForm.override_reason" class="rounded-md border-slate-300" rows="2"></textarea></label>
            </div>
        </FormModal>

        <ConfirmDialog :show="Boolean(bedTarget)" :form="bedStateForm" title="Update Bed Availability" :message="bedTarget ? `Set ${bedTarget.label} to ${bedStateLabels[bedStateForm.state] || bedStateForm.state}?` : ''" require-reason confirm-label="Update bed" @close="bedTarget = null" @confirm="submitBedState" />

        <ConfirmDialog
            :show="Boolean(setupDeleteTarget)"
            :form="setupDeleteForm"
            title="Delete Admissions Setup"
            :message="setupDeleteTarget ? `Delete ${setupDeleteTarget.item.name || setupDeleteTarget.item.label || setupDeleteTarget.item.code}? Active dependencies will block unsafe deletion.` : ''"
            confirm-label="Delete"
            @close="setupDeleteTarget = null"
            @confirm="deleteSetup"
        />

        <FormModal :show="resetModal" :form="resetForm" title="Reset Pre-production Admissions Data" submit-label="Permanently reset admissions data" size="md" @close="resetModal = false" @submit="purgeAdmissionsDemo">
            <div class="space-y-4">
                <p class="text-sm leading-6 text-amber-800 dark:text-amber-200">Use this only before the hospital starts recording real inpatient activity. It permanently removes this hospital’s admission records, inpatient records, wards, rooms, beds and bed classes. Patients, staff, facilities and unrelated modules are not reset.</p>
                <TextInput id="admissions_reset_confirmation" v-model="resetForm.confirmation" label="Type PURGE ADMISSIONS to confirm" :error="resetForm.errors.confirmation || resetForm.errors.reset" />
            </div>
        </FormModal>
    </AppLayout>
</template>
