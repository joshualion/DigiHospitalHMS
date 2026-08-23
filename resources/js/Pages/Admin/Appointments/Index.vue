<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import PrimaryButton from '../../../Components/PrimaryButton.vue';

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

const filters = useForm({ date: props.filters.date || '', status: props.filters.status || '', clinician_id: props.filters.clinician_id || '' });
const form = useForm({ patient_id: '', facility_id: props.facilities[0]?.id || '', department_id: '', clinician_id: '', appointment_type_id: props.appointmentTypes[0]?.id || '', starts_at: '', reason: '' });
const schedule = useForm({ facility_id: props.facilities[0]?.id || '', department_id: '', staff_profile_id: '', day_of_week: 1, starts_at: '09:00', ends_at: '17:00', breaks: [{ starts_at: '13:00', ends_at: '14:00' }] });
const unavailable = useForm({ facility_id: '', staff_profile_id: '', starts_at: '', ends_at: '', reason: '' });

function filter() {
    router.get('/admin/appointments', filters.data(), { preserveState: true, replace: true });
}

function transition(appointment, action) {
    const reason = ['cancel', 'no_show'].includes(action) ? window.prompt('Reason') : null;
    if (['cancel', 'no_show'].includes(action) && !reason) return;
    router.patch(`/admin/appointments/${appointment.id}/transition`, { action, reason }, { preserveScroll: true });
}
</script>

<template>
    <Head title="Appointments" />
    <AppLayout title="Appointments">
        <div class="grid gap-6 xl:grid-cols-[1fr_420px]">
            <section class="rounded-lg border border-slate-200 bg-white">
                <div class="grid gap-3 border-b border-slate-200 p-4 md:grid-cols-3">
                    <input v-model="filters.date" class="rounded-md border-slate-300" type="date" @change="filter">
                    <select v-model="filters.status" class="rounded-md border-slate-300" @change="filter"><option value="">All statuses</option><option value="scheduled">Scheduled</option><option value="confirmed">Confirmed</option><option value="checked_in">Checked in</option><option value="cancelled">Cancelled</option><option value="no_show">No-show</option></select>
                    <select v-model="filters.clinician_id" class="rounded-md border-slate-300" @change="filter"><option value="">All clinicians</option><option v-for="clinician in clinicians" :key="clinician.id" :value="clinician.id">{{ clinician.user?.full_name || clinician.job_title || clinician.id }}</option></select>
                </div>
                <div class="divide-y divide-slate-100">
                    <article v-for="appointment in appointments.data" :key="appointment.id" class="grid gap-3 p-4 md:grid-cols-[1fr_160px_220px]">
                        <div>
                            <p class="font-bold">{{ appointment.patient?.full_name }} · {{ appointment.patient?.hospital_number }}</p>
                            <p class="text-sm text-slate-500">{{ appointment.starts_at }} · {{ appointment.type?.name }} · {{ appointment.facility?.name }}</p>
                        </div>
                        <span class="h-fit w-fit rounded-full bg-slate-100 px-2 py-1 text-xs font-bold">{{ appointment.status }}</span>
                        <div class="flex flex-wrap gap-2">
                            <button class="rounded-md border px-2 py-1 text-sm" type="button" @click="transition(appointment, 'confirm')">Confirm</button>
                            <button class="rounded-md border px-2 py-1 text-sm" type="button" @click="router.post(`/admin/appointments/${appointment.id}/check-in`)">Check in</button>
                            <button class="rounded-md border px-2 py-1 text-sm" type="button" @click="transition(appointment, 'cancel')">Cancel</button>
                            <button class="rounded-md border px-2 py-1 text-sm" type="button" @click="transition(appointment, 'no_show')">No-show</button>
                        </div>
                    </article>
                    <p v-if="appointments.data.length === 0" class="p-4 text-sm text-slate-500">No appointments found.</p>
                </div>
            </section>

            <aside class="space-y-6">
                <form class="rounded-lg border border-slate-200 bg-white p-5" @submit.prevent="form.post('/admin/appointments', { preserveScroll: true })">
                    <h2 class="font-bold">Book Appointment</h2>
                    <div class="mt-4 grid gap-3">
                        <select v-model="form.patient_id" class="rounded-md border-slate-300"><option value="">Patient</option><option v-for="patient in patients" :key="patient.id" :value="patient.id">{{ patient.hospital_number }} · {{ patient.full_name }}</option></select>
                        <select v-model="form.facility_id" class="rounded-md border-slate-300"><option v-for="facility in facilities" :key="facility.id" :value="facility.id">{{ facility.name }}</option></select>
                        <select v-model="form.department_id" class="rounded-md border-slate-300"><option value="">Department</option><option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option></select>
                        <select v-model="form.clinician_id" class="rounded-md border-slate-300"><option value="">Clinician</option><option v-for="clinician in clinicians" :key="clinician.id" :value="clinician.id">{{ clinician.user?.full_name || clinician.job_title || clinician.id }}</option></select>
                        <select v-model="form.appointment_type_id" class="rounded-md border-slate-300"><option v-for="type in appointmentTypes" :key="type.id" :value="type.id">{{ type.name }} · {{ type.duration_minutes }} min</option></select>
                        <input v-model="form.starts_at" class="rounded-md border-slate-300" type="datetime-local">
                        <textarea v-model="form.reason" class="rounded-md border-slate-300" rows="2" placeholder="Reason"></textarea>
                        <PrimaryButton :disabled="form.processing">Book</PrimaryButton>
                    </div>
                </form>

                <form class="rounded-lg border border-slate-200 bg-white p-5" @submit.prevent="schedule.post('/admin/clinician-schedules', { preserveScroll: true })">
                    <h2 class="font-bold">Clinician Schedule</h2>
                    <div class="mt-4 grid gap-3">
                        <select v-model="schedule.staff_profile_id" class="rounded-md border-slate-300"><option value="">Clinician</option><option v-for="clinician in clinicians" :key="clinician.id" :value="clinician.id">{{ clinician.user?.full_name || clinician.id }}</option></select>
                        <select v-model="schedule.facility_id" class="rounded-md border-slate-300"><option v-for="facility in facilities" :key="facility.id" :value="facility.id">{{ facility.name }}</option></select>
                        <select v-model="schedule.day_of_week" class="rounded-md border-slate-300"><option :value="1">Monday</option><option :value="2">Tuesday</option><option :value="3">Wednesday</option><option :value="4">Thursday</option><option :value="5">Friday</option><option :value="6">Saturday</option><option :value="0">Sunday</option></select>
                        <div class="grid grid-cols-2 gap-2"><input v-model="schedule.starts_at" class="rounded-md border-slate-300" type="time"><input v-model="schedule.ends_at" class="rounded-md border-slate-300" type="time"></div>
                        <PrimaryButton :disabled="schedule.processing">Save schedule</PrimaryButton>
                    </div>
                </form>

                <form class="rounded-lg border border-slate-200 bg-white p-5" @submit.prevent="unavailable.post('/admin/clinician-unavailability', { preserveScroll: true })">
                    <h2 class="font-bold">Leave / Unavailability</h2>
                    <div class="mt-4 grid gap-3">
                        <select v-model="unavailable.staff_profile_id" class="rounded-md border-slate-300"><option value="">Clinician</option><option v-for="clinician in clinicians" :key="clinician.id" :value="clinician.id">{{ clinician.user?.full_name || clinician.id }}</option></select>
                        <input v-model="unavailable.starts_at" class="rounded-md border-slate-300" type="datetime-local">
                        <input v-model="unavailable.ends_at" class="rounded-md border-slate-300" type="datetime-local">
                        <input v-model="unavailable.reason" class="rounded-md border-slate-300" placeholder="Reason">
                        <PrimaryButton :disabled="unavailable.processing">Save unavailable time</PrimaryButton>
                    </div>
                </form>

                <section class="rounded-lg border border-slate-200 bg-white p-5">
                    <h2 class="font-bold">Public Requests</h2>
                    <div class="mt-3 divide-y divide-slate-100 text-sm">
                        <article v-for="request in requests" :key="request.id" class="py-3">
                            <p class="font-semibold">{{ request.name }}</p>
                            <p class="text-slate-500">{{ request.phone || request.email }} · {{ request.preferred_date }}</p>
                            <div class="mt-2 flex gap-2">
                                <button class="rounded-md border px-2 py-1" type="button" @click="router.patch(`/admin/appointment-requests/${request.id}`, { status: 'accepted', reason: 'Reviewed' }, { preserveScroll: true })">Accept</button>
                                <button class="rounded-md border px-2 py-1" type="button" @click="router.patch(`/admin/appointment-requests/${request.id}`, { status: 'declined', reason: 'Unavailable' }, { preserveScroll: true })">Decline</button>
                            </div>
                        </article>
                    </div>
                </section>
            </aside>
        </div>
    </AppLayout>
</template>
