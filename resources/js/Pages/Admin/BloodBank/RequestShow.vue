<script setup>
import ActionToolbar from '@/Components/Admin/ActionToolbar.vue';
import ConfirmDialog from '@/Components/Admin/ConfirmDialog.vue';
import FormModal from '@/Components/Admin/FormModal.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    bloodRequest: Object,
    availableComponents: Array,
    patientGroups: Array,
});

const activeModal = ref(null);
const target = ref(null);
const verifyTarget = ref(null);
const verifyKind = ref(null);

const actionForm = useForm({ state: 'submitted', reason: '' });
const specimenForm = useForm({ patient_confirmed_name: props.bloodRequest.patient?.full_name || '', patient_confirmed_identifier: props.bloodRequest.patient?.hospital_number || '', collection_location: '', label_status: 'matched', label_discrepancy_notes: '', notes: '' });
const groupForm = useForm({ blood_request_specimen_id: '', abo_group: 'O', rh_factor: 'positive', notes: '' });
const compatibilityForm = useForm({ blood_request_specimen_id: '', blood_component_id: '', test_type: 'manual_crossmatch', result: '', interpretation: '', notes: '' });
const reserveForm = useForm({ blood_component_id: '', expiry_minutes: '' });
const issueForm = useForm({ blood_component_reservation_id: '', received_by_name: '', receiver_role: '', destination: '', issued_at: '' });
const emergencyForm = useForm({ justification: '' });
const returnForm = useForm({ return_reason: '', return_assessment: '' });
const reverseForm = useForm({ reason: '' });
const blank = useForm({});

const activeReservations = computed(() => (props.bloodRequest.reservations || []).filter((entry) => entry.status === 'active'));
const hasDiscrepancy = computed(() => props.bloodRequest.identity_discrepancy_unresolved || props.bloodRequest.specimen_label_discrepancy_unresolved || props.bloodRequest.blood_group_discrepancy_unresolved);

function closeModal() {
    activeModal.value = null;
    target.value = null;
}

function postForm(form, url) {
    form.post(url, {
        preserveScroll: true,
        onSuccess: () => {
            closeModal();
            form.reset();
        },
    });
}

function submitAction() {
    actionForm.patch(`/admin/blood-bank/requests/${props.bloodRequest.id}`, {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    });
}

function openIssueAction(kind, issue) {
    target.value = issue;
    activeModal.value = kind;
    if (kind === 'return') {
        returnForm.reset();
    } else {
        reverseForm.reset();
    }
}

function submitIssueReturn() {
    postForm(returnForm, `/admin/blood-bank/issues/${target.value.id}/return`);
}

function submitIssueReverse() {
    postForm(reverseForm, `/admin/blood-bank/issues/${target.value.id}/reverse`);
}

function confirmVerify(kind, item) {
    verifyKind.value = kind;
    verifyTarget.value = item;
}

function submitVerify() {
    const url = verifyKind.value === 'group'
        ? `/admin/blood-bank/patient-groups/${verifyTarget.value.id}/verify`
        : `/admin/blood-bank/compatibility-tests/${verifyTarget.value.id}/authorize`;

    blank.post(url, {
        preserveScroll: true,
        onSuccess: () => {
            verifyKind.value = null;
            verifyTarget.value = null;
        },
    });
}
</script>

<template>
    <AppLayout :title="bloodRequest.request_number">
        <PageHeader :title="bloodRequest.request_number" :description="`${bloodRequest.patient?.full_name} - ${bloodRequest.patient?.hospital_number} - ${bloodRequest.state}`">
            <template #actions>
                <ActionToolbar align="end">
                    <Link href="/admin/blood-bank" class="rounded-md border px-3 py-2 text-sm font-bold" style="border-color: var(--admin-border);">Back</Link>
                    <button class="rounded-md border px-3 py-2 text-sm font-bold" style="border-color: var(--admin-border);" type="button" @click="activeModal = 'state'">Request State</button>
                    <PrimaryButton type="button" @click="activeModal = 'specimen'">Collect Specimen</PrimaryButton>
                    <button class="rounded-md border px-3 py-2 text-sm font-bold" style="border-color: var(--admin-border);" type="button" @click="activeModal = 'group'">Enter ABO/Rh</button>
                    <button class="rounded-md border px-3 py-2 text-sm font-bold" style="border-color: var(--admin-border);" type="button" @click="activeModal = 'crossmatch'">Crossmatch</button>
                    <button class="rounded-md border px-3 py-2 text-sm font-bold" style="border-color: var(--admin-border);" type="button" @click="activeModal = 'reserve'">Reserve</button>
                    <button class="rounded-md border px-3 py-2 text-sm font-bold" style="border-color: var(--admin-border);" type="button" @click="activeModal = 'issue'">Issue</button>
                    <button class="rounded-md border px-3 py-2 text-sm font-bold" style="border-color: var(--admin-border);" type="button" @click="activeModal = 'emergency'">Emergency Release</button>
                </ActionToolbar>
            </template>
        </PageHeader>

        <div class="space-y-5 overflow-x-hidden">
            <section class="grid gap-3 md:grid-cols-4">
                <div class="rounded-md border p-3" style="border-color: var(--admin-border); background: var(--admin-surface);"><p class="text-xs font-black uppercase">Component</p><p class="font-bold">{{ bloodRequest.component_type?.name }}</p></div>
                <div class="rounded-md border p-3" style="border-color: var(--admin-border); background: var(--admin-surface);"><p class="text-xs font-black uppercase">Requested</p><p class="font-bold">{{ bloodRequest.quantity_requested }}</p></div>
                <div class="rounded-md border p-3" style="border-color: var(--admin-border); background: var(--admin-surface);"><p class="text-xs font-black uppercase">Reserved</p><p class="font-bold">{{ bloodRequest.quantity_reserved }}</p></div>
                <div class="rounded-md border p-3" style="border-color: var(--admin-border); background: var(--admin-surface);"><p class="text-xs font-black uppercase">Issued</p><p class="font-bold">{{ bloodRequest.quantity_issued }}</p></div>
            </section>

            <div v-if="hasDiscrepancy" class="rounded-md border border-red-300 bg-red-50 p-4 text-sm font-bold text-red-900">
                Unresolved discrepancy is active. Request, specimen, group, compatibility, reservation and issue actions are hard-stopped until resolved by authorized governance.
            </div>

            <section class="rounded-md border p-4 sm:p-5" style="border-color: var(--admin-border); background: var(--admin-surface);">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="font-black">Specimens</h2>
                    <button class="rounded-md border px-3 py-2 text-sm font-bold" style="border-color: var(--admin-border);" type="button" @click="activeModal = 'specimen'">Collect Specimen</button>
                </div>
                <div class="mt-3 grid gap-3">
                    <div v-for="specimen in bloodRequest.specimens" :key="specimen.id" class="rounded-md border p-3 text-sm" style="border-color: var(--admin-border);">
                        <p class="font-bold">{{ specimen.label }} - {{ specimen.status }}</p>
                        <p style="color: var(--admin-text-muted);">{{ specimen.patient_confirmed_name }} - {{ specimen.patient_confirmed_identifier }} - {{ specimen.label_status }}</p>
                    </div>
                    <p v-if="bloodRequest.specimens.length === 0" class="text-sm" style="color: var(--admin-text-muted);">No specimens collected.</p>
                </div>
            </section>

            <section class="grid gap-5 xl:grid-cols-2">
                <div class="rounded-md border p-4 sm:p-5" style="border-color: var(--admin-border); background: var(--admin-surface);">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="font-black">Patient Blood Groups</h2>
                        <button class="rounded-md border px-3 py-2 text-sm font-bold" style="border-color: var(--admin-border);" type="button" @click="activeModal = 'group'">Enter ABO/Rh</button>
                    </div>
                    <div v-for="group in patientGroups" :key="group.id" class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-md border p-3 text-sm" style="border-color: var(--admin-border);">
                        <p><strong>{{ group.abo_group }} {{ group.rh_factor }}</strong> - {{ group.status }}</p>
                        <button v-if="group.status === 'draft'" class="rounded-md border px-3 py-2 text-sm font-bold" style="border-color: var(--admin-border);" type="button" @click="confirmVerify('group', group)">Verify</button>
                    </div>
                    <p v-if="patientGroups.length === 0" class="mt-3 text-sm" style="color: var(--admin-text-muted);">No patient group entries.</p>
                </div>

                <div class="rounded-md border p-4 sm:p-5" style="border-color: var(--admin-border); background: var(--admin-surface);">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="font-black">Manual Compatibility Tests</h2>
                        <button class="rounded-md border px-3 py-2 text-sm font-bold" style="border-color: var(--admin-border);" type="button" @click="activeModal = 'crossmatch'">Crossmatch</button>
                    </div>
                    <div v-for="test in bloodRequest.compatibility_tests" :key="test.id" class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-md border p-3 text-sm" style="border-color: var(--admin-border);">
                        <div class="min-w-0">
                            <p class="font-bold break-words">{{ test.test_type }} - {{ test.result }} - {{ test.status }}</p>
                            <p style="color: var(--admin-text-muted);">{{ test.component?.component_number }} - {{ test.interpretation }}</p>
                        </div>
                        <button v-if="test.status === 'draft'" class="rounded-md border px-3 py-2 text-sm font-bold" style="border-color: var(--admin-border);" type="button" @click="confirmVerify('crossmatch', test)">Authorize</button>
                    </div>
                    <p v-if="bloodRequest.compatibility_tests.length === 0" class="mt-3 text-sm" style="color: var(--admin-text-muted);">No compatibility tests entered.</p>
                </div>
            </section>

            <section class="rounded-md border p-4 sm:p-5" style="border-color: var(--admin-border); background: var(--admin-surface);">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="font-black">Reservations and Issues</h2>
                    <ActionToolbar align="end">
                        <button class="rounded-md border px-3 py-2 text-sm font-bold" style="border-color: var(--admin-border);" type="button" @click="activeModal = 'reserve'">Reserve</button>
                        <button class="rounded-md border px-3 py-2 text-sm font-bold" style="border-color: var(--admin-border);" type="button" @click="activeModal = 'issue'">Issue</button>
                    </ActionToolbar>
                </div>

                <div class="mt-3 grid gap-3 lg:grid-cols-2">
                    <div v-for="reservation in bloodRequest.reservations" :key="reservation.id" class="rounded-md border p-3 text-sm" style="border-color: var(--admin-border);">
                        <p class="font-bold">{{ reservation.component?.component_number }} - {{ reservation.status }}</p>
                        <p style="color: var(--admin-text-muted);">Expires {{ reservation.expires_at }}</p>
                    </div>
                </div>
                <p v-if="bloodRequest.reservations.length === 0" class="mt-3 text-sm" style="color: var(--admin-text-muted);">No reservations recorded.</p>

                <div class="mt-5 grid gap-3">
                    <article v-for="issue in bloodRequest.issues" :key="issue.id" class="rounded-md border p-4 text-sm" style="border-color: var(--admin-border);">
                        <div class="flex min-w-0 flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <p class="font-bold break-words">{{ issue.issue_number }} - {{ issue.component?.component_number }} - {{ issue.status }}</p>
                                <p style="color: var(--admin-text-muted);">Issued to {{ issue.received_by_name }} for {{ issue.destination }}</p>
                            </div>
                            <ActionToolbar align="end">
                                <Link class="rounded-md border px-3 py-2 font-bold" style="border-color: var(--admin-border);" :href="`/admin/blood-bank/issues/${issue.id}/document`">Print</Link>
                                <button class="rounded-md border px-3 py-2 font-bold" style="border-color: var(--admin-border);" type="button" @click="openIssueAction('return', issue)">Return</button>
                                <button class="rounded-md border px-3 py-2 font-bold" style="border-color: var(--admin-border);" type="button" @click="openIssueAction('reverse', issue)">Reverse</button>
                            </ActionToolbar>
                        </div>
                    </article>
                    <p v-if="bloodRequest.issues.length === 0" class="text-sm" style="color: var(--admin-text-muted);">No issues recorded.</p>
                </div>
            </section>
        </div>

        <FormModal :show="activeModal === 'state'" title="Request State" size="lg" :form="actionForm" submit-label="Update" @close="closeModal" @submit="submitAction">
            <div class="grid gap-3">
                <select v-model="actionForm.state" class="rounded-md border p-2" style="border-color: var(--admin-border);"><option value="submitted">Submit</option><option value="accepted">Accept</option><option value="cancelled">Cancel</option><option value="rejected">Reject</option></select>
                <textarea v-model="actionForm.reason" class="min-h-24 rounded-md border p-2" style="border-color: var(--admin-border);" placeholder="Reason" required></textarea>
            </div>
        </FormModal>

        <FormModal :show="activeModal === 'specimen'" title="Collect Specimen" size="xl" :form="specimenForm" submit-label="Collect" @close="closeModal" @submit="postForm(specimenForm, `/admin/blood-bank/requests/${bloodRequest.id}/specimens`)">
            <div class="grid gap-3 md:grid-cols-2">
                <input v-model="specimenForm.patient_confirmed_name" class="rounded-md border p-2" style="border-color: var(--admin-border);" required>
                <input v-model="specimenForm.patient_confirmed_identifier" class="rounded-md border p-2" style="border-color: var(--admin-border);" required>
                <input v-model="specimenForm.collection_location" class="rounded-md border p-2" style="border-color: var(--admin-border);" placeholder="Collection location">
                <select v-model="specimenForm.label_status" class="rounded-md border p-2" style="border-color: var(--admin-border);"><option value="matched">Matched</option><option value="discrepant">Discrepant</option></select>
                <textarea v-model="specimenForm.label_discrepancy_notes" class="min-h-24 rounded-md border p-2 md:col-span-2" style="border-color: var(--admin-border);" placeholder="Discrepancy notes"></textarea>
                <textarea v-model="specimenForm.notes" class="min-h-24 rounded-md border p-2 md:col-span-2" style="border-color: var(--admin-border);" placeholder="Notes"></textarea>
            </div>
        </FormModal>

        <FormModal :show="activeModal === 'group'" title="Enter Patient ABO/Rh" size="lg" :form="groupForm" submit-label="Save group" @close="closeModal" @submit="postForm(groupForm, `/admin/blood-bank/requests/${bloodRequest.id}/patient-groups`)">
            <div class="grid gap-3 md:grid-cols-2">
                <select v-model="groupForm.blood_request_specimen_id" class="rounded-md border p-2" style="border-color: var(--admin-border);"><option value="">Latest specimen</option><option v-for="specimen in bloodRequest.specimens" :key="specimen.id" :value="specimen.id">{{ specimen.label }}</option></select>
                <select v-model="groupForm.abo_group" class="rounded-md border p-2" style="border-color: var(--admin-border);"><option>A</option><option>B</option><option>AB</option><option>O</option></select>
                <select v-model="groupForm.rh_factor" class="rounded-md border p-2" style="border-color: var(--admin-border);"><option value="positive">Positive</option><option value="negative">Negative</option></select>
                <textarea v-model="groupForm.notes" class="min-h-24 rounded-md border p-2 md:col-span-2" style="border-color: var(--admin-border);" placeholder="Notes"></textarea>
            </div>
        </FormModal>

        <FormModal :show="activeModal === 'crossmatch'" title="Manual Crossmatch" size="xl" :form="compatibilityForm" submit-label="Record result" @close="closeModal" @submit="postForm(compatibilityForm, `/admin/blood-bank/requests/${bloodRequest.id}/compatibility-tests`)">
            <div class="grid gap-3 md:grid-cols-2">
                <select v-model="compatibilityForm.blood_request_specimen_id" class="rounded-md border p-2" style="border-color: var(--admin-border);"><option value="">Latest specimen</option><option v-for="specimen in bloodRequest.specimens" :key="specimen.id" :value="specimen.id">{{ specimen.label }}</option></select>
                <select v-model="compatibilityForm.blood_component_id" class="rounded-md border p-2" style="border-color: var(--admin-border);"><option value="">Component</option><option v-for="component in availableComponents" :key="component.id" :value="component.id">{{ component.component_number }}</option></select>
                <input v-model="compatibilityForm.result" class="rounded-md border p-2" style="border-color: var(--admin-border);" placeholder="Manual result" required>
                <textarea v-model="compatibilityForm.interpretation" class="min-h-24 rounded-md border p-2" style="border-color: var(--admin-border);" placeholder="Authorized staff interpretation"></textarea>
                <textarea v-model="compatibilityForm.notes" class="min-h-24 rounded-md border p-2 md:col-span-2" style="border-color: var(--admin-border);" placeholder="Notes"></textarea>
            </div>
        </FormModal>

        <FormModal :show="activeModal === 'reserve'" title="Reserve Component" size="lg" :form="reserveForm" submit-label="Reserve" @close="closeModal" @submit="postForm(reserveForm, `/admin/blood-bank/requests/${bloodRequest.id}/reservations`)">
            <div class="grid gap-3 md:grid-cols-2">
                <select v-model="reserveForm.blood_component_id" class="rounded-md border p-2" style="border-color: var(--admin-border);" required><option value="">Component</option><option v-for="component in availableComponents" :key="component.id" :value="component.id">{{ component.component_number }}</option></select>
                <input v-model="reserveForm.expiry_minutes" type="number" class="rounded-md border p-2" style="border-color: var(--admin-border);" placeholder="Expiry minutes">
            </div>
        </FormModal>

        <FormModal :show="activeModal === 'issue'" title="Issue Component" size="xl" :form="issueForm" submit-label="Issue" @close="closeModal" @submit="postForm(issueForm, `/admin/blood-bank/requests/${bloodRequest.id}/issues`)">
            <div class="grid gap-3 md:grid-cols-2">
                <select v-model="issueForm.blood_component_reservation_id" class="rounded-md border p-2" style="border-color: var(--admin-border);" required><option value="">Reservation</option><option v-for="reservation in activeReservations" :key="reservation.id" :value="reservation.id">{{ reservation.component?.component_number }}</option></select>
                <input v-model="issueForm.received_by_name" class="rounded-md border p-2" style="border-color: var(--admin-border);" placeholder="Receiver" required>
                <input v-model="issueForm.receiver_role" class="rounded-md border p-2" style="border-color: var(--admin-border);" placeholder="Receiver role">
                <input v-model="issueForm.destination" class="rounded-md border p-2" style="border-color: var(--admin-border);" placeholder="Destination" required>
                <input v-model="issueForm.issued_at" type="datetime-local" class="rounded-md border p-2" style="border-color: var(--admin-border);">
            </div>
        </FormModal>

        <FormModal :show="activeModal === 'emergency'" title="Emergency Release" description="Requires explicit authorization and justification; no compatibility decision is calculated automatically." size="lg" :form="emergencyForm" submit-label="Authorize" @close="closeModal" @submit="postForm(emergencyForm, `/admin/blood-bank/requests/${bloodRequest.id}/emergency-release`)">
            <textarea v-model="emergencyForm.justification" class="min-h-32 w-full rounded-md border p-2" style="border-color: var(--admin-border);" placeholder="Explicit authorization justification" required></textarea>
        </FormModal>

        <FormModal :show="activeModal === 'return'" :title="target ? `Return ${target.issue_number}` : 'Return Issue'" size="lg" :form="returnForm" submit-label="Return" @close="closeModal" @submit="submitIssueReturn">
            <div class="grid gap-3">
                <input v-model="returnForm.return_reason" class="rounded-md border p-2" style="border-color: var(--admin-border);" placeholder="Return reason">
                <textarea v-model="returnForm.return_assessment" class="min-h-28 rounded-md border p-2" style="border-color: var(--admin-border);" placeholder="Authorized suitability assessment"></textarea>
            </div>
        </FormModal>

        <ConfirmDialog :show="activeModal === 'reverse'" :title="target ? `Reverse ${target.issue_number}` : 'Reverse Issue'" message="This preserves the issue record and records a controlled reversal." :form="reverseForm" confirm-label="Reverse" require-reason @close="closeModal" @confirm="submitIssueReverse" />

        <ConfirmDialog :show="Boolean(verifyTarget)" title="Authorize Verification" :message="verifyKind === 'group' ? 'Confirm independent patient blood group verification.' : 'Confirm manual compatibility result authorization.'" :form="blank" confirm-label="Authorize" @close="verifyTarget = null" @confirm="submitVerify" />
    </AppLayout>
</template>
