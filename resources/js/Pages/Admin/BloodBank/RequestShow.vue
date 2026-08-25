<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    bloodRequest: Object,
    availableComponents: Array,
    patientGroups: Array,
});

const actionForm = useForm({ state: 'submitted', reason: '' });
const specimenForm = useForm({ patient_confirmed_name: props.bloodRequest.patient?.full_name || '', patient_confirmed_identifier: props.bloodRequest.patient?.hospital_number || '', collection_location: '', label_status: 'matched', label_discrepancy_notes: '', notes: '' });
const groupForm = useForm({ blood_request_specimen_id: '', abo_group: 'O', rh_factor: 'positive', notes: '' });
const compatibilityForm = useForm({ blood_request_specimen_id: '', blood_component_id: '', test_type: 'manual_crossmatch', result: '', interpretation: '', notes: '' });
const reserveForm = useForm({ blood_component_id: '', expiry_minutes: '' });
const issueForm = useForm({ blood_component_reservation_id: '', received_by_name: '', receiver_role: '', destination: '', issued_at: '' });
const emergencyForm = useForm({ justification: '' });
const verifyForms = Object.fromEntries([...(props.patientGroups || []), ...(props.bloodRequest.compatibility_tests || [])].map((entry) => [entry.id, useForm({})]));
const returnForms = Object.fromEntries((props.bloodRequest.issues || []).map((issue) => [issue.id, useForm({ return_reason: '', return_assessment: '' })]));
const reverseForms = Object.fromEntries((props.bloodRequest.issues || []).map((issue) => [issue.id, useForm({ reason: '' })]));

function post(form, url) {
    form.post(url, { preserveScroll: true, onSuccess: () => form.reset() });
}
</script>

<template>
    <AppLayout :title="bloodRequest.request_number">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <Link href="/admin/blood-bank" class="text-sm font-bold" style="color: var(--public-accent);">Back to blood bank</Link>
            <div class="text-right text-sm">
                <p class="font-black">{{ bloodRequest.patient?.full_name }}</p>
                <p style="color: var(--admin-text-muted);">{{ bloodRequest.patient?.hospital_number }} - {{ bloodRequest.state }}</p>
            </div>
        </div>

        <section class="mb-6 grid gap-3 md:grid-cols-4">
            <div class="rounded-md border p-3" style="border-color: var(--admin-border);"><p class="text-xs font-black uppercase">Component</p><p class="font-bold">{{ bloodRequest.component_type?.name }}</p></div>
            <div class="rounded-md border p-3" style="border-color: var(--admin-border);"><p class="text-xs font-black uppercase">Requested</p><p class="font-bold">{{ bloodRequest.quantity_requested }}</p></div>
            <div class="rounded-md border p-3" style="border-color: var(--admin-border);"><p class="text-xs font-black uppercase">Reserved</p><p class="font-bold">{{ bloodRequest.quantity_reserved }}</p></div>
            <div class="rounded-md border p-3" style="border-color: var(--admin-border);"><p class="text-xs font-black uppercase">Issued</p><p class="font-bold">{{ bloodRequest.quantity_issued }}</p></div>
        </section>

        <div v-if="bloodRequest.identity_discrepancy_unresolved || bloodRequest.specimen_label_discrepancy_unresolved || bloodRequest.blood_group_discrepancy_unresolved" class="mb-6 rounded-md border border-red-300 bg-red-50 p-4 text-sm font-bold text-red-900">
            Unresolved discrepancy is active. Request, specimen, group, compatibility, reservation and issue actions are hard-stopped until resolved by authorized governance.
        </div>

        <div class="grid gap-6 xl:grid-cols-[1fr_380px]">
            <section class="space-y-6">
                <div class="rounded-md border p-4" style="border-color: var(--admin-border);">
                    <h2 class="font-black">Specimens</h2>
                    <div v-for="specimen in bloodRequest.specimens" :key="specimen.id" class="mt-3 rounded-md border p-3 text-sm" style="border-color: var(--admin-border);">
                        <p class="font-bold">{{ specimen.label }} - {{ specimen.status }}</p>
                        <p style="color: var(--admin-text-muted);">{{ specimen.patient_confirmed_name }} - {{ specimen.patient_confirmed_identifier }} - {{ specimen.label_status }}</p>
                    </div>
                </div>

                <div class="rounded-md border p-4" style="border-color: var(--admin-border);">
                    <h2 class="font-black">Patient Blood Groups</h2>
                    <div v-for="group in patientGroups" :key="group.id" class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-md border p-3 text-sm" style="border-color: var(--admin-border);">
                        <p><strong>{{ group.abo_group }} {{ group.rh_factor }}</strong> - {{ group.status }}</p>
                        <form v-if="group.status === 'draft'" @submit.prevent="post(verifyForms[group.id], `/admin/blood-bank/patient-groups/${group.id}/verify`)">
                            <button class="rounded-md border px-3 py-2 text-sm font-bold">Verify</button>
                        </form>
                    </div>
                </div>

                <div class="rounded-md border p-4" style="border-color: var(--admin-border);">
                    <h2 class="font-black">Manual Compatibility Tests</h2>
                    <div v-for="test in bloodRequest.compatibility_tests" :key="test.id" class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-md border p-3 text-sm" style="border-color: var(--admin-border);">
                        <div><p class="font-bold">{{ test.test_type }} - {{ test.result }} - {{ test.status }}</p><p style="color: var(--admin-text-muted);">{{ test.component?.component_number }} - {{ test.interpretation }}</p></div>
                        <form v-if="test.status === 'draft'" @submit.prevent="post(verifyForms[test.id], `/admin/blood-bank/compatibility-tests/${test.id}/authorize`)">
                            <button class="rounded-md border px-3 py-2 text-sm font-bold">Authorize</button>
                        </form>
                    </div>
                </div>

                <div class="rounded-md border p-4" style="border-color: var(--admin-border);">
                    <h2 class="font-black">Reservations and Issues</h2>
                    <div v-for="reservation in bloodRequest.reservations" :key="reservation.id" class="mt-3 rounded-md border p-3 text-sm" style="border-color: var(--admin-border);">
                        <p class="font-bold">{{ reservation.component?.component_number }} - {{ reservation.status }}</p>
                        <p style="color: var(--admin-text-muted);">Expires {{ reservation.expires_at }}</p>
                    </div>
                    <div v-for="issue in bloodRequest.issues" :key="issue.id" class="mt-3 rounded-md border p-3 text-sm" style="border-color: var(--admin-border);">
                        <p class="font-bold">{{ issue.issue_number }} - {{ issue.component?.component_number }} - {{ issue.status }}</p>
                        <p style="color: var(--admin-text-muted);">Issued to {{ issue.received_by_name }} for {{ issue.destination }}</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <Link class="rounded-md border px-3 py-2 font-bold" :href="`/admin/blood-bank/issues/${issue.id}/document`">Print document</Link>
                            <form @submit.prevent="post(returnForms[issue.id], `/admin/blood-bank/issues/${issue.id}/return`)">
                                <input v-model="returnForms[issue.id].return_reason" class="rounded-md border p-2" placeholder="Return reason">
                                <input v-model="returnForms[issue.id].return_assessment" class="rounded-md border p-2" placeholder="Suitability assessment">
                                <button class="rounded-md border px-3 py-2 font-bold">Return</button>
                            </form>
                            <form @submit.prevent="post(reverseForms[issue.id], `/admin/blood-bank/issues/${issue.id}/reverse`)">
                                <input v-model="reverseForms[issue.id].reason" class="rounded-md border p-2" placeholder="Reversal reason">
                                <button class="rounded-md border px-3 py-2 font-bold">Reverse</button>
                            </form>
                        </div>
                    </div>
                </div>
            </section>

            <aside class="space-y-4">
                <form class="rounded-md border p-4" style="border-color: var(--admin-border);" @submit.prevent="actionForm.patch(`/admin/blood-bank/requests/${bloodRequest.id}`, { preserveScroll: true })">
                    <h2 class="font-black">Request State</h2>
                    <select v-model="actionForm.state" class="mt-3 w-full rounded-md border p-2"><option value="submitted">Submit</option><option value="accepted">Accept</option><option value="cancelled">Cancel</option><option value="rejected">Reject</option></select>
                    <textarea v-model="actionForm.reason" class="mt-3 w-full rounded-md border p-2" rows="2" placeholder="Reason" required></textarea>
                    <button class="mt-3 rounded-md border px-3 py-2 font-bold">Update</button>
                </form>

                <form class="rounded-md border p-4" style="border-color: var(--admin-border);" @submit.prevent="post(specimenForm, `/admin/blood-bank/requests/${bloodRequest.id}/specimens`)">
                    <h2 class="font-black">Collect Specimen</h2>
                    <input v-model="specimenForm.patient_confirmed_name" class="mt-3 w-full rounded-md border p-2" required>
                    <input v-model="specimenForm.patient_confirmed_identifier" class="mt-3 w-full rounded-md border p-2" required>
                    <input v-model="specimenForm.collection_location" class="mt-3 w-full rounded-md border p-2" placeholder="Collection location">
                    <select v-model="specimenForm.label_status" class="mt-3 w-full rounded-md border p-2"><option value="matched">Matched</option><option value="discrepant">Discrepant</option></select>
                    <textarea v-model="specimenForm.label_discrepancy_notes" class="mt-3 w-full rounded-md border p-2" rows="2" placeholder="Discrepancy notes"></textarea>
                    <button class="mt-3 rounded-md border px-3 py-2 font-bold">Collect</button>
                </form>

                <form class="rounded-md border p-4" style="border-color: var(--admin-border);" @submit.prevent="post(groupForm, `/admin/blood-bank/requests/${bloodRequest.id}/patient-groups`)">
                    <h2 class="font-black">Enter Patient ABO/Rh</h2>
                    <select v-model="groupForm.blood_request_specimen_id" class="mt-3 w-full rounded-md border p-2"><option value="">Latest specimen</option><option v-for="specimen in bloodRequest.specimens" :key="specimen.id" :value="specimen.id">{{ specimen.label }}</option></select>
                    <select v-model="groupForm.abo_group" class="mt-3 w-full rounded-md border p-2"><option>A</option><option>B</option><option>AB</option><option>O</option></select>
                    <select v-model="groupForm.rh_factor" class="mt-3 w-full rounded-md border p-2"><option value="positive">Positive</option><option value="negative">Negative</option></select>
                    <textarea v-model="groupForm.notes" class="mt-3 w-full rounded-md border p-2" rows="2" placeholder="Notes"></textarea>
                    <button class="mt-3 rounded-md border px-3 py-2 font-bold">Save group</button>
                </form>

                <form class="rounded-md border p-4" style="border-color: var(--admin-border);" @submit.prevent="post(compatibilityForm, `/admin/blood-bank/requests/${bloodRequest.id}/compatibility-tests`)">
                    <h2 class="font-black">Manual Crossmatch</h2>
                    <select v-model="compatibilityForm.blood_component_id" class="mt-3 w-full rounded-md border p-2"><option value="">Component</option><option v-for="component in availableComponents" :key="component.id" :value="component.id">{{ component.component_number }}</option></select>
                    <input v-model="compatibilityForm.result" class="mt-3 w-full rounded-md border p-2" placeholder="Manual result" required>
                    <textarea v-model="compatibilityForm.interpretation" class="mt-3 w-full rounded-md border p-2" rows="2" placeholder="Authorized staff interpretation"></textarea>
                    <button class="mt-3 rounded-md border px-3 py-2 font-bold">Record result</button>
                </form>

                <form class="rounded-md border p-4" style="border-color: var(--admin-border);" @submit.prevent="post(reserveForm, `/admin/blood-bank/requests/${bloodRequest.id}/reservations`)">
                    <h2 class="font-black">Reserve Component</h2>
                    <select v-model="reserveForm.blood_component_id" class="mt-3 w-full rounded-md border p-2" required><option value="">Component</option><option v-for="component in availableComponents" :key="component.id" :value="component.id">{{ component.component_number }}</option></select>
                    <input v-model="reserveForm.expiry_minutes" type="number" class="mt-3 w-full rounded-md border p-2" placeholder="Expiry minutes">
                    <button class="mt-3 rounded-md border px-3 py-2 font-bold">Reserve</button>
                </form>

                <form class="rounded-md border p-4" style="border-color: var(--admin-border);" @submit.prevent="post(issueForm, `/admin/blood-bank/requests/${bloodRequest.id}/issues`)">
                    <h2 class="font-black">Issue Component</h2>
                    <select v-model="issueForm.blood_component_reservation_id" class="mt-3 w-full rounded-md border p-2" required><option value="">Reservation</option><option v-for="reservation in bloodRequest.reservations.filter((entry) => entry.status === 'active')" :key="reservation.id" :value="reservation.id">{{ reservation.component?.component_number }}</option></select>
                    <input v-model="issueForm.received_by_name" class="mt-3 w-full rounded-md border p-2" placeholder="Receiver" required>
                    <input v-model="issueForm.receiver_role" class="mt-3 w-full rounded-md border p-2" placeholder="Receiver role">
                    <input v-model="issueForm.destination" class="mt-3 w-full rounded-md border p-2" placeholder="Destination" required>
                    <button class="mt-3 rounded-md border px-3 py-2 font-bold">Issue</button>
                </form>

                <form class="rounded-md border p-4" style="border-color: var(--admin-border);" @submit.prevent="post(emergencyForm, `/admin/blood-bank/requests/${bloodRequest.id}/emergency-release`)">
                    <h2 class="font-black">Emergency Release</h2>
                    <textarea v-model="emergencyForm.justification" class="mt-3 w-full rounded-md border p-2" rows="3" placeholder="Explicit authorization justification" required></textarea>
                    <button class="mt-3 rounded-md border px-3 py-2 font-bold">Authorize</button>
                </form>
            </aside>
        </div>
    </AppLayout>
</template>
