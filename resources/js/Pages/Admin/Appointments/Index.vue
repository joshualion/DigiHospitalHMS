<script setup>
import ActionToolbar from '@/Components/Admin/ActionToolbar.vue';
import ConfirmDialog from '@/Components/Admin/ConfirmDialog.vue';
import FormModal from '@/Components/Admin/FormModal.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    appointments: { type: Object, required: true },
    requests: { type: Array, default: () => [] },
    facilities: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    clinicians: { type: Array, default: () => [] },
    appointmentTypes: { type: Array, default: () => [] },
    patients: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const page = usePage();
const permissions = computed(() => page.props.auth.permissions || []);
const roles = computed(() => page.props.auth.roles || []);
const can = (permission) => roles.value.includes('superadmin') || permissions.value.includes(permission);
const filters = useForm({ date: props.filters.date || '', status: props.filters.status || '', clinician_id: props.filters.clinician_id || '' });
const form = useForm({ patient_id: '', facility_id: props.facilities[0]?.id || '', department_id: '', clinician_id: '', appointment_type_id: props.appointmentTypes[0]?.id || '', starts_at: '', reason: '' });
const schedule = useForm({ facility_id: props.facilities[0]?.id || '', department_id: '', staff_profile_id: '', day_of_week: 1, starts_at: '09:00', ends_at: '17:00', breaks: [{ starts_at: '13:00', ends_at: '14:00' }] });
const unavailable = useForm({ facility_id: '', staff_profile_id: '', starts_at: '', ends_at: '', reason: '' });
const actionForm = useForm({ action: '', reason: '', starts_at: '' });
const reviewForm = useForm({ status: 'accepted', patient_id: '', reason: '' });
const checkInForm = useForm({});
const showBook = ref(false);
const showSchedule = ref(false);
const showUnavailable = ref(false);
const actionTarget = ref(null);
const checkInTarget = ref(null);
const reviewTarget = ref(null);

function filter() {
    router.get('/admin/appointments', filters.data(), { preserveState: true, replace: true });
}

function submitBook() {
    form.post('/admin/appointments', { preserveScroll: true, onSuccess: () => { showBook.value = false; form.reset(); } });
}

function submitSchedule() {
    schedule.post('/admin/clinician-schedules', { preserveScroll: true, onSuccess: () => { showSchedule.value = false; schedule.reset(); } });
}

function submitUnavailable() {
    unavailable.post('/admin/clinician-unavailability', { preserveScroll: true, onSuccess: () => { showUnavailable.value = false; unavailable.reset(); } });
}

function openAction(appointment, action) {
    actionTarget.value = appointment;
    actionForm.defaults({ action, reason: '', starts_at: '' });
    actionForm.reset();
}

function submitAction() {
    actionForm.patch(`/admin/appointments/${actionTarget.value.id}/transition`, { preserveScroll: true, onSuccess: () => { actionTarget.value = null; actionForm.reset(); } });
}

function submitCheckIn() {
    checkInForm.post(`/admin/appointments/${checkInTarget.value.id}/check-in`, { preserveScroll: true, onSuccess: () => { checkInTarget.value = null; } });
}

function openReview(request, status) {
    reviewTarget.value = request;
    reviewForm.defaults({ status, patient_id: '', reason: status === 'accepted' ? 'Reviewed' : 'Unavailable' });
    reviewForm.reset();
}

function submitReview() {
    reviewForm.patch(`/admin/appointment-requests/${reviewTarget.value.id}`, { preserveScroll: true, onSuccess: () => { reviewTarget.value = null; reviewForm.reset(); } });
}
</script>

<template>
    <Head title="Appointments" />
    <AppLayout title="Appointments">
        <PageHeader title="Appointments" description="Manage bookings, availability and public request review.">
            <template #actions>
                <ActionToolbar align="end">
                    <PrimaryButton v-if="can('appointments.book')" type="button" @click="showBook = true">Book Appointment</PrimaryButton>
                    <button v-if="can('appointments.manage')" class="rounded-md border px-4 py-2 text-sm font-bold" style="border-color: var(--admin-border);" type="button" @click="showSchedule = true">Add Schedule</button>
                    <button v-if="can('appointments.manage')" class="rounded-md border px-4 py-2 text-sm font-bold" style="border-color: var(--admin-border);" type="button" @click="showUnavailable = true">Add Unavailability</button>
                </ActionToolbar>
            </template>
        </PageHeader>

        <section class="rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="grid gap-3 border-b border-slate-200 p-4 md:grid-cols-3 dark:border-slate-800">
                <input v-model="filters.date" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900" type="date" @change="filter">
                <select v-model="filters.status" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900" @change="filter"><option value="">All statuses</option><option value="scheduled">Scheduled</option><option value="confirmed">Confirmed</option><option value="checked_in">Checked in</option><option value="cancelled">Cancelled</option><option value="no_show">No-show</option></select>
                <select v-model="filters.clinician_id" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900" @change="filter"><option value="">All clinicians</option><option v-for="clinician in clinicians" :key="clinician.id" :value="clinician.id">{{ clinician.user?.full_name || clinician.job_title || clinician.id }}</option></select>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                <article v-for="appointment in appointments.data" :key="appointment.id" class="grid min-w-0 gap-3 p-4 lg:grid-cols-[1fr_160px_280px]">
                    <div class="min-w-0">
                        <p class="truncate font-bold">{{ appointment.patient?.full_name }} - {{ appointment.patient?.hospital_number }}</p>
                        <p class="truncate text-sm text-slate-500">{{ appointment.starts_at }} - {{ appointment.type?.name }} - {{ appointment.facility?.name }}</p>
                    </div>
                    <span class="h-fit w-fit rounded-full bg-slate-100 px-2 py-1 text-xs font-bold dark:bg-slate-800">{{ appointment.status }}</span>
                    <ActionToolbar>
                        <button v-if="can('appointments.manage')" class="rounded-md border px-3 py-2 text-xs font-bold" type="button" @click="openAction(appointment, 'confirm')">Confirm</button>
                        <button v-if="can('appointments.manage')" class="rounded-md border px-3 py-2 text-xs font-bold" type="button" @click="checkInTarget = appointment">Check in</button>
                        <button v-if="can('appointments.manage')" class="rounded-md border px-3 py-2 text-xs font-bold" type="button" @click="openAction(appointment, 'reschedule')">Reschedule</button>
                        <button v-if="can('appointments.manage')" class="rounded-md border px-3 py-2 text-xs font-bold" type="button" @click="openAction(appointment, 'cancel')">Cancel</button>
                        <button v-if="can('appointments.manage')" class="rounded-md border px-3 py-2 text-xs font-bold" type="button" @click="openAction(appointment, 'no_show')">No-show</button>
                    </ActionToolbar>
                </article>
                <p v-if="appointments.data.length === 0" class="p-4 text-sm text-slate-500">No appointments found.</p>
            </div>
        </section>

        <section class="mt-6 rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="font-bold">Public Requests</h2>
            <div class="mt-3 divide-y divide-slate-100 text-sm dark:divide-slate-800">
                <article v-for="request in requests" :key="request.id" class="grid gap-3 py-3 md:grid-cols-[1fr_auto]">
                    <div class="min-w-0">
                        <p class="truncate font-semibold">{{ request.name }}</p>
                        <p class="truncate text-slate-500">{{ request.phone || request.email }} - {{ request.preferred_date }}</p>
                    </div>
                    <ActionToolbar>
                        <button v-if="can('appointment-requests.review')" class="rounded-md border px-3 py-2 text-xs font-bold" type="button" @click="openReview(request, 'accepted')">Accept</button>
                        <button v-if="can('appointment-requests.review')" class="rounded-md border px-3 py-2 text-xs font-bold" type="button" @click="openReview(request, 'declined')">Decline</button>
                    </ActionToolbar>
                </article>
                <p v-if="requests.length === 0" class="py-3 text-slate-500">No pending public requests.</p>
            </div>
        </section>

        <FormModal :show="showBook" :form="form" title="Book Appointment" submit-label="Book" size="xl" @close="showBook = false" @submit="submitBook">
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="grid gap-1 text-sm font-semibold sm:col-span-2">Patient<select v-model="form.patient_id" class="rounded-md border-slate-300"><option value="">Patient</option><option v-for="patient in patients" :key="patient.id" :value="patient.id">{{ patient.hospital_number }} - {{ patient.full_name }}</option></select><span v-if="form.errors.patient_id" class="text-xs text-red-700">{{ form.errors.patient_id }}</span></label>
                <label class="grid gap-1 text-sm font-semibold">Facility<select v-model="form.facility_id" class="rounded-md border-slate-300"><option v-for="facility in facilities" :key="facility.id" :value="facility.id">{{ facility.name }}</option></select></label>
                <label class="grid gap-1 text-sm font-semibold">Department<select v-model="form.department_id" class="rounded-md border-slate-300"><option value="">Department</option><option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option></select></label>
                <label class="grid gap-1 text-sm font-semibold">Clinician<select v-model="form.clinician_id" class="rounded-md border-slate-300"><option value="">Clinician</option><option v-for="clinician in clinicians" :key="clinician.id" :value="clinician.id">{{ clinician.user?.full_name || clinician.job_title || clinician.id }}</option></select></label>
                <label class="grid gap-1 text-sm font-semibold">Type<select v-model="form.appointment_type_id" class="rounded-md border-slate-300"><option v-for="type in appointmentTypes" :key="type.id" :value="type.id">{{ type.name }} - {{ type.duration_minutes }} min</option></select></label>
                <label class="grid gap-1 text-sm font-semibold">Starts at<input v-model="form.starts_at" class="rounded-md border-slate-300" type="datetime-local"><span v-if="form.errors.starts_at" class="text-xs text-red-700">{{ form.errors.starts_at }}</span></label>
                <label class="grid gap-1 text-sm font-semibold sm:col-span-2">Reason<textarea v-model="form.reason" class="rounded-md border-slate-300" rows="2"></textarea></label>
            </div>
        </FormModal>

        <FormModal :show="showSchedule" :form="schedule" title="Clinician Schedule" submit-label="Save schedule" size="lg" @close="showSchedule = false" @submit="submitSchedule">
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="grid gap-1 text-sm font-semibold sm:col-span-2">Clinician<select v-model="schedule.staff_profile_id" class="rounded-md border-slate-300"><option value="">Clinician</option><option v-for="clinician in clinicians" :key="clinician.id" :value="clinician.id">{{ clinician.user?.full_name || clinician.id }}</option></select></label>
                <label class="grid gap-1 text-sm font-semibold">Facility<select v-model="schedule.facility_id" class="rounded-md border-slate-300"><option v-for="facility in facilities" :key="facility.id" :value="facility.id">{{ facility.name }}</option></select></label>
                <label class="grid gap-1 text-sm font-semibold">Department<select v-model="schedule.department_id" class="rounded-md border-slate-300"><option value="">Department</option><option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option></select></label>
                <label class="grid gap-1 text-sm font-semibold">Day<select v-model="schedule.day_of_week" class="rounded-md border-slate-300"><option :value="1">Monday</option><option :value="2">Tuesday</option><option :value="3">Wednesday</option><option :value="4">Thursday</option><option :value="5">Friday</option><option :value="6">Saturday</option><option :value="0">Sunday</option></select></label>
                <label class="grid gap-1 text-sm font-semibold">Starts<input v-model="schedule.starts_at" class="rounded-md border-slate-300" type="time"></label>
                <label class="grid gap-1 text-sm font-semibold">Ends<input v-model="schedule.ends_at" class="rounded-md border-slate-300" type="time"></label>
            </div>
        </FormModal>

        <FormModal :show="showUnavailable" :form="unavailable" title="Leave / Unavailability" submit-label="Save unavailable time" size="lg" @close="showUnavailable = false" @submit="submitUnavailable">
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="grid gap-1 text-sm font-semibold sm:col-span-2">Clinician<select v-model="unavailable.staff_profile_id" class="rounded-md border-slate-300"><option value="">Clinician</option><option v-for="clinician in clinicians" :key="clinician.id" :value="clinician.id">{{ clinician.user?.full_name || clinician.id }}</option></select></label>
                <label class="grid gap-1 text-sm font-semibold">Starts<input v-model="unavailable.starts_at" class="rounded-md border-slate-300" type="datetime-local"></label>
                <label class="grid gap-1 text-sm font-semibold">Ends<input v-model="unavailable.ends_at" class="rounded-md border-slate-300" type="datetime-local"></label>
                <label class="grid gap-1 text-sm font-semibold sm:col-span-2">Reason<input v-model="unavailable.reason" class="rounded-md border-slate-300"></label>
            </div>
        </FormModal>

        <ConfirmDialog :show="Boolean(actionTarget)" :form="actionForm" :title="actionForm.action ? `${actionForm.action.replace('_', ' ')} appointment` : 'Update appointment'" :require-reason="['cancel', 'no_show', 'reschedule'].includes(actionForm.action)" confirm-label="Update appointment" @close="actionTarget = null" @confirm="submitAction">
            <label v-if="actionForm.action === 'reschedule'" class="mt-4 grid gap-1 text-sm font-semibold">New start time<input v-model="actionForm.starts_at" class="rounded-md border-slate-300" type="datetime-local"><span v-if="actionForm.errors.starts_at" class="text-xs text-red-700">{{ actionForm.errors.starts_at }}</span></label>
        </ConfirmDialog>

        <ConfirmDialog :show="Boolean(checkInTarget)" :form="checkInForm" title="Check In Appointment" :message="checkInTarget ? `Check in ${checkInTarget.patient?.full_name}?` : ''" confirm-label="Check in" @close="checkInTarget = null" @confirm="submitCheckIn" />

        <FormModal :show="Boolean(reviewTarget)" :form="reviewForm" :title="reviewForm.status === 'accepted' ? 'Accept Public Request' : 'Decline Public Request'" submit-label="Save review" size="md" @close="reviewTarget = null" @submit="submitReview">
            <label class="grid gap-1 text-sm font-semibold">Link patient<select v-model="reviewForm.patient_id" class="rounded-md border-slate-300"><option value="">No patient link</option><option v-for="patient in patients" :key="patient.id" :value="patient.id">{{ patient.hospital_number }} - {{ patient.full_name }}</option></select></label>
            <label class="grid gap-1 text-sm font-semibold">Reason<textarea v-model="reviewForm.reason" class="rounded-md border-slate-300" rows="3"></textarea></label>
        </FormModal>
    </AppLayout>
</template>
