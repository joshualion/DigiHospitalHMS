<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';

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

const classForm = useForm({ code: '', name: '', billable_service_id: '', description: '' });
const wardForm = useForm({ facility_id: props.facilities[0]?.id || '', department_id: '', code: '', name: '', notes: '' });
const roomForm = useForm({ ward_id: '', code: '', name: '' });
const bedForm = useForm({ ward_id: '', ward_room_id: '', bed_class_id: '', code: '', label: '' });
const requestForm = useForm({ facility_id: props.facilities[0]?.id || '', patient_id: '', visit_id: '', clinical_encounter_id: '', attending_clinician_id: '', department_id: '', reason: '', provisional_diagnosis: '', notes: '', administrative_clearance_required: false });

function fullName(patient) {
    return [patient?.first_name, patient?.middle_name, patient?.last_name].filter(Boolean).join(' ');
}

function availableBed(except = null) {
    return props.beds.find((bed) => ['available', 'reserved'].includes(bed.state) && bed.id !== except)?.id || '';
}

function action(admission, actionName) {
    const payload = { action: actionName };
    if (['admit', 'transfer'].includes(actionName)) payload.bed_id = availableBed(admission.current_bed_id);
    if (actionName === 'reject' || actionName === 'cancel' || actionName === 'transfer') payload.reason = window.prompt('Reason') || actionName;
    if (actionName === 'discharge') Object.assign(payload, { discharge_destination: 'home', discharge_outcome: 'stable', discharge_notes: 'Administrative discharge', override: true, override_reason: 'Authorized administrative override' });
    router.patch(`/admin/admissions/${admission.id}`, payload, { preserveScroll: true });
}

function bedState(bed, state) {
    router.patch(`/admin/admissions/beds/${bed.id}/state`, { state, reason: `${state} from bed board` }, { preserveScroll: true });
}
</script>

<template>
    <Head title="Admissions" />
    <AppLayout title="Admissions">
        <div class="grid gap-6 xl:grid-cols-[1fr_380px]">
            <section class="space-y-6">
                <section class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="font-black">Bed Census</h2>
                    <div class="mt-4 grid gap-3 md:grid-cols-4">
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
                            <div>
                                <p class="font-bold">{{ admission.admission_number || `Request #${admission.id}` }} - {{ admission.status }}</p>
                                <p class="text-sm text-slate-500">{{ admission.patient?.hospital_number }} - {{ fullName(admission.patient) }}</p>
                                <p class="text-xs text-slate-500">{{ admission.ward?.name || 'No ward' }} {{ admission.bed?.label || '' }}</p>
                                <p v-if="admission.invoice" class="text-xs font-semibold text-emerald-600">Invoice {{ admission.invoice.total_minor }}</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button class="rounded-md border px-3 py-2 text-xs font-bold" type="button" @click="action(admission, 'approve')">Approve</button>
                                <button class="rounded-md border px-3 py-2 text-xs font-bold" type="button" @click="action(admission, 'admit')">Allocate bed</button>
                                <button class="rounded-md border px-3 py-2 text-xs font-bold" type="button" @click="action(admission, 'transfer')">Transfer</button>
                                <button class="rounded-md border px-3 py-2 text-xs font-bold" type="button" @click="action(admission, 'discharge')">Discharge</button>
                                <button class="rounded-md border px-3 py-2 text-xs font-bold" type="button" @click="action(admission, 'cancel')">Cancel</button>
                            </div>
                        </article>
                    </div>
                </section>

                <section class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="font-black">Bed Board</h2>
                    <div class="mt-4 grid gap-3 md:grid-cols-2">
                        <article v-for="bed in beds" :key="bed.id" class="rounded-md border border-slate-200 p-3 text-sm dark:border-slate-800">
                            <p class="font-bold">{{ bed.label }} - {{ bed.state }}</p>
                            <p class="text-slate-500">{{ bed.ward?.name }} {{ bed.room?.name || '' }} - {{ bed.bed_class?.name }}</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <button class="rounded-md border px-2 py-1 text-xs font-bold" type="button" @click="bedState(bed, 'reserved')">Hold</button>
                                <button class="rounded-md border px-2 py-1 text-xs font-bold" type="button" @click="bedState(bed, 'available')">Release</button>
                                <button class="rounded-md border px-2 py-1 text-xs font-bold" type="button" @click="bedState(bed, 'cleaning')">Cleaning</button>
                                <button class="rounded-md border px-2 py-1 text-xs font-bold" type="button" @click="bedState(bed, 'maintenance')">Maintenance</button>
                            </div>
                        </article>
                    </div>
                </section>
            </section>

            <aside class="space-y-6">
                <form class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="requestForm.post('/admin/admissions/requests', { preserveScroll: true })">
                    <h2 class="font-black">Admission Request</h2>
                    <div class="mt-4 grid gap-3">
                        <select v-model="requestForm.patient_id" class="rounded-md border-slate-300"><option value="">Patient</option><option v-for="patient in patients" :key="patient.id" :value="patient.id">{{ patient.hospital_number }} - {{ fullName(patient) }}</option></select>
                        <select v-model="requestForm.facility_id" class="rounded-md border-slate-300"><option v-for="facility in facilities" :key="facility.id" :value="facility.id">{{ facility.name }}</option></select>
                        <select v-model="requestForm.visit_id" class="rounded-md border-slate-300"><option value="">Visit</option><option v-for="visit in visits" :key="visit.id" :value="visit.id">Visit #{{ visit.id }} - {{ visit.status }}</option></select>
                        <select v-model="requestForm.clinical_encounter_id" class="rounded-md border-slate-300"><option value="">Encounter</option><option v-for="encounter in encounters" :key="encounter.id" :value="encounter.id">Encounter #{{ encounter.id }} - {{ encounter.status }}</option></select>
                        <select v-model="requestForm.department_id" class="rounded-md border-slate-300"><option value="">Department</option><option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option></select>
                        <textarea v-model="requestForm.reason" class="rounded-md border-slate-300" rows="2" placeholder="Admission reason"></textarea>
                        <textarea v-model="requestForm.provisional_diagnosis" class="rounded-md border-slate-300" rows="2" placeholder="Provisional diagnosis"></textarea>
                        <label class="text-sm"><input v-model="requestForm.administrative_clearance_required" type="checkbox"> Administrative clearance required</label>
                        <PrimaryButton :disabled="requestForm.processing">Request admission</PrimaryButton>
                    </div>
                </form>

                <form class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="classForm.post('/admin/admissions/bed-classes', { preserveScroll: true })">
                    <h2 class="font-black">Bed Class</h2>
                    <div class="mt-4 grid gap-3">
                        <TextInput id="bed_class_code" v-model="classForm.code" label="Code" />
                        <TextInput id="bed_class_name" v-model="classForm.name" label="Name" />
                        <select v-model="classForm.billable_service_id" class="rounded-md border-slate-300"><option value="">Accommodation service</option><option v-for="service in services" :key="service.id" :value="service.id">{{ service.code }} - {{ service.name }}</option></select>
                        <PrimaryButton :disabled="classForm.processing">Create class</PrimaryButton>
                    </div>
                </form>

                <form class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="wardForm.post('/admin/admissions/wards', { preserveScroll: true })">
                    <h2 class="font-black">Ward</h2>
                    <div class="mt-4 grid gap-3">
                        <select v-model="wardForm.facility_id" class="rounded-md border-slate-300"><option v-for="facility in facilities" :key="facility.id" :value="facility.id">{{ facility.name }}</option></select>
                        <select v-model="wardForm.department_id" class="rounded-md border-slate-300"><option value="">Department</option><option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option></select>
                        <TextInput id="ward_code" v-model="wardForm.code" label="Code" />
                        <TextInput id="ward_name" v-model="wardForm.name" label="Name" />
                        <PrimaryButton :disabled="wardForm.processing">Create ward</PrimaryButton>
                    </div>
                </form>

                <form class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="roomForm.post('/admin/admissions/rooms', { preserveScroll: true })">
                    <h2 class="font-black">Room</h2>
                    <div class="mt-4 grid gap-3">
                        <select v-model="roomForm.ward_id" class="rounded-md border-slate-300"><option value="">Ward</option><option v-for="ward in wards" :key="ward.id" :value="ward.id">{{ ward.name }}</option></select>
                        <TextInput id="room_code" v-model="roomForm.code" label="Code" />
                        <TextInput id="room_name" v-model="roomForm.name" label="Name" />
                        <PrimaryButton :disabled="roomForm.processing">Create room</PrimaryButton>
                    </div>
                </form>

                <form class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="bedForm.post('/admin/admissions/beds', { preserveScroll: true })">
                    <h2 class="font-black">Bed</h2>
                    <div class="mt-4 grid gap-3">
                        <select v-model="bedForm.ward_id" class="rounded-md border-slate-300"><option value="">Ward</option><option v-for="ward in wards" :key="ward.id" :value="ward.id">{{ ward.name }}</option></select>
                        <select v-model="bedForm.ward_room_id" class="rounded-md border-slate-300"><option value="">Room</option><option v-for="room in rooms" :key="room.id" :value="room.id">{{ room.name }}</option></select>
                        <select v-model="bedForm.bed_class_id" class="rounded-md border-slate-300"><option value="">Class</option><option v-for="bedClass in bedClasses" :key="bedClass.id" :value="bedClass.id">{{ bedClass.name }}</option></select>
                        <TextInput id="bed_code" v-model="bedForm.code" label="Code" />
                        <TextInput id="bed_label" v-model="bedForm.label" label="Label" />
                        <PrimaryButton :disabled="bedForm.processing">Create bed</PrimaryButton>
                    </div>
                </form>
            </aside>
        </div>
    </AppLayout>
</template>
