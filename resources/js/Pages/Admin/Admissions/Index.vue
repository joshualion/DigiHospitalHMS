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
const classForm = useForm({ code: '', name: '', billable_service_id: '', description: '' });
const wardForm = useForm({ facility_id: props.facilities[0]?.id || '', department_id: '', code: '', name: '', notes: '' });
const roomForm = useForm({ ward_id: '', code: '', name: '' });
const bedForm = useForm({ ward_id: '', ward_room_id: '', bed_class_id: '', code: '', label: '' });
const requestForm = useForm({ facility_id: props.facilities[0]?.id || '', patient_id: '', visit_id: '', clinical_encounter_id: '', attending_clinician_id: '', department_id: '', reason: '', provisional_diagnosis: '', notes: '', administrative_clearance_required: false });
const actionForm = useForm({ action: '', bed_id: '', reason: '', discharge_destination: '', discharge_outcome: '', discharge_notes: '', override: false, override_reason: '' });
const bedStateForm = useForm({ state: 'available', reason: '' });
const setupModal = ref(null);
const actionTarget = ref(null);
const bedTarget = ref(null);
const requestModal = ref(false);

function fullName(patient) {
    return [patient?.first_name, patient?.middle_name, patient?.last_name].filter(Boolean).join(' ');
}

function availableBed(except = null) {
    return props.beds.find((bed) => ['available', 'reserved'].includes(bed.state) && bed.id !== except)?.id || '';
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

function submitSetup(form, url) {
    form.post(url, { preserveScroll: true, onSuccess: () => { setupModal.value = null; form.reset(); } });
}
</script>

<template>
    <Head title="Admissions" />
    <AppLayout title="Admissions">
        <PageHeader title="Admissions" description="Manage admission requests, bed allocation and bed-board state.">
            <template #actions>
                <ActionToolbar align="end">
                    <PrimaryButton v-if="can('admissions.request')" type="button" @click="requestModal = true">Request Admission</PrimaryButton>
                    <button v-if="can('admissions.manage')" class="rounded-md border px-4 py-2 text-sm font-bold" style="border-color: var(--admin-border);" type="button" @click="setupModal = 'class'">Add Bed Class</button>
                    <button v-if="can('admissions.manage')" class="rounded-md border px-4 py-2 text-sm font-bold" style="border-color: var(--admin-border);" type="button" @click="setupModal = 'ward'">Add Ward</button>
                    <button v-if="can('admissions.manage')" class="rounded-md border px-4 py-2 text-sm font-bold" style="border-color: var(--admin-border);" type="button" @click="setupModal = 'room'">Add Room</button>
                    <button v-if="can('admissions.manage')" class="rounded-md border px-4 py-2 text-sm font-bold" style="border-color: var(--admin-border);" type="button" @click="setupModal = 'bed'">Add Bed</button>
                </ActionToolbar>
            </template>
        </PageHeader>

        <div class="space-y-6">
            <section class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="font-black">Bed Census</h2>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div v-for="row in census" :key="row.state" class="rounded-md border border-slate-200 p-3 dark:border-slate-800">
                        <p class="text-xs font-bold uppercase text-slate-500">{{ row.state }}</p>
                        <p class="text-2xl font-black">{{ row.count }}</p>
                    </div>
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
                <h2 class="font-black">Bed Board</h2>
                <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <article v-for="bed in beds" :key="bed.id" class="rounded-md border border-slate-200 p-3 text-sm dark:border-slate-800">
                        <p class="font-bold">{{ bed.label }} - {{ bed.state }}</p>
                        <p class="text-slate-500">{{ bed.ward?.name }} {{ bed.room?.name || '' }} - {{ bed.bed_class?.name }}</p>
                        <ActionToolbar class="mt-3">
                            <button v-if="can('admissions.manage')" class="rounded-md border px-2 py-1 text-xs font-bold" type="button" @click="openBedState(bed, 'reserved')">Hold</button>
                            <button v-if="can('admissions.manage')" class="rounded-md border px-2 py-1 text-xs font-bold" type="button" @click="openBedState(bed, 'available')">Release</button>
                            <button v-if="can('admissions.manage')" class="rounded-md border px-2 py-1 text-xs font-bold" type="button" @click="openBedState(bed, 'cleaning')">Cleaning</button>
                            <button v-if="can('admissions.manage')" class="rounded-md border px-2 py-1 text-xs font-bold" type="button" @click="openBedState(bed, 'maintenance')">Maintenance</button>
                        </ActionToolbar>
                    </article>
                </div>
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

        <FormModal :show="setupModal === 'class'" :form="classForm" title="Bed Class" submit-label="Create class" size="md" @close="setupModal = null" @submit="submitSetup(classForm, '/admin/admissions/bed-classes')">
            <TextInput id="bed_class_code" v-model="classForm.code" label="Code" :error="classForm.errors.code" />
            <TextInput id="bed_class_name" v-model="classForm.name" label="Name" :error="classForm.errors.name" />
            <label class="grid gap-1 text-sm font-semibold">Accommodation service<select v-model="classForm.billable_service_id" class="rounded-md border-slate-300"><option value="">Accommodation service</option><option v-for="service in services" :key="service.id" :value="service.id">{{ service.code }} - {{ service.name }}</option></select></label>
            <label class="grid gap-1 text-sm font-semibold">Description<textarea v-model="classForm.description" class="rounded-md border-slate-300" rows="3"></textarea></label>
        </FormModal>

        <FormModal :show="setupModal === 'ward'" :form="wardForm" title="Ward" submit-label="Create ward" size="lg" @close="setupModal = null" @submit="submitSetup(wardForm, '/admin/admissions/wards')">
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="grid gap-1 text-sm font-semibold">Facility<select v-model="wardForm.facility_id" class="rounded-md border-slate-300"><option v-for="facility in facilities" :key="facility.id" :value="facility.id">{{ facility.name }}</option></select></label>
                <label class="grid gap-1 text-sm font-semibold">Department<select v-model="wardForm.department_id" class="rounded-md border-slate-300"><option value="">Department</option><option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option></select></label>
                <TextInput id="ward_code" v-model="wardForm.code" label="Code" :error="wardForm.errors.code" />
                <TextInput id="ward_name" v-model="wardForm.name" label="Name" :error="wardForm.errors.name" />
                <label class="grid gap-1 text-sm font-semibold sm:col-span-2">Notes<textarea v-model="wardForm.notes" class="rounded-md border-slate-300" rows="3"></textarea></label>
            </div>
        </FormModal>

        <FormModal :show="setupModal === 'room'" :form="roomForm" title="Room" submit-label="Create room" size="md" @close="setupModal = null" @submit="submitSetup(roomForm, '/admin/admissions/rooms')">
            <label class="grid gap-1 text-sm font-semibold">Ward<select v-model="roomForm.ward_id" class="rounded-md border-slate-300"><option value="">Ward</option><option v-for="ward in wards" :key="ward.id" :value="ward.id">{{ ward.name }}</option></select></label>
            <TextInput id="room_code" v-model="roomForm.code" label="Code" :error="roomForm.errors.code" />
            <TextInput id="room_name" v-model="roomForm.name" label="Name" :error="roomForm.errors.name" />
        </FormModal>

        <FormModal :show="setupModal === 'bed'" :form="bedForm" title="Bed" submit-label="Create bed" size="lg" @close="setupModal = null" @submit="submitSetup(bedForm, '/admin/admissions/beds')">
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
                <label v-if="['admit', 'transfer'].includes(actionForm.action)" class="grid gap-1 text-sm font-semibold sm:col-span-2">Bed<select v-model="actionForm.bed_id" class="rounded-md border-slate-300"><option value="">Bed</option><option v-for="bed in beds.filter((entry) => ['available', 'reserved'].includes(entry.state))" :key="bed.id" :value="bed.id">{{ bed.label }} - {{ bed.ward?.name }}</option></select></label>
                <label v-if="['reject', 'cancel', 'transfer', 'approve'].includes(actionForm.action)" class="grid gap-1 text-sm font-semibold sm:col-span-2">Reason<textarea v-model="actionForm.reason" class="rounded-md border-slate-300" rows="3"></textarea></label>
                <label v-if="actionForm.action === 'discharge'" class="grid gap-1 text-sm font-semibold">Destination<input v-model="actionForm.discharge_destination" class="rounded-md border-slate-300"></label>
                <label v-if="actionForm.action === 'discharge'" class="grid gap-1 text-sm font-semibold">Outcome<input v-model="actionForm.discharge_outcome" class="rounded-md border-slate-300"></label>
                <label v-if="actionForm.action === 'discharge'" class="grid gap-1 text-sm font-semibold sm:col-span-2">Discharge notes<textarea v-model="actionForm.discharge_notes" class="rounded-md border-slate-300" rows="3"></textarea></label>
                <label v-if="actionForm.action === 'discharge'" class="flex items-center gap-2 text-sm"><input v-model="actionForm.override" type="checkbox"> Use discharge override</label>
                <label v-if="actionForm.action === 'discharge'" class="grid gap-1 text-sm font-semibold sm:col-span-2">Override reason<textarea v-model="actionForm.override_reason" class="rounded-md border-slate-300" rows="2"></textarea></label>
            </div>
        </FormModal>

        <ConfirmDialog :show="Boolean(bedTarget)" :form="bedStateForm" title="Update Bed State" :message="bedTarget ? `Set ${bedTarget.label} to ${bedStateForm.state}?` : ''" require-reason confirm-label="Update bed" @close="bedTarget = null" @confirm="submitBedState" />
    </AppLayout>
</template>
