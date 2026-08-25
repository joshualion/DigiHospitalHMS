<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import ActionToolbar from '@/Components/Admin/ActionToolbar.vue';
import ConfirmDialog from '@/Components/Admin/ConfirmDialog.vue';
import FormModal from '@/Components/Admin/FormModal.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    radiologyRequest: { type: Object, required: true },
    radiologyStaff: { type: Array, default: () => [] },
});

const report = computed(() => props.radiologyRequest.report);
const canShowReport = computed(() => report.value && ['approved', 'released'].includes(report.value.status));
const schedule = useForm({ scheduled_at: '', room: props.radiologyRequest.room || '', equipment: props.radiologyRequest.equipment || '', assigned_staff_id: props.radiologyRequest.assigned_staff_id || '' });
const reportForm = useForm({
    findings: report.value?.findings || '',
    impression: report.value?.impression || '',
    recommendations: report.value?.recommendations || '',
    reporting_radiologist_id: report.value?.reporting_radiologist_id || '',
    has_critical_finding: report.value?.has_critical_finding || false,
    critical_finding_notes: report.value?.critical_finding_notes || '',
});
const communication = useForm({ communicated_to: '', method: '', notes: '' });
const amendment = useForm({ reason: '', content: '' });
const upload = useForm({ attachment: null, radiology_report_id: report.value?.id || '' });
const requestActionForm = useForm({ action: '', reason: '', performance_notes: '' });
const reportActionForm = useForm({ action: '' });
const acknowledgementForm = useForm({ notes: '' });
const retireForm = useForm({ reason: '' });
const activeModal = ref(null);
const actionTarget = ref(null);

function requestAction(action) {
    requestActionForm.defaults({ action, reason: '', performance_notes: '' });
    requestActionForm.reset();
    activeModal.value = action === 'cancel' || action === 'perform' ? 'request-action' : null;
    if (!activeModal.value) {
        requestActionForm.patch(`/admin/radiology/requests/${props.radiologyRequest.id}/transition`, { preserveScroll: true });
    }
}

function reportAction(action) {
    reportActionForm.defaults({ action });
    reportActionForm.reset();
    reportActionForm.patch(`/admin/radiology/reports/${report.value.id}/transition`, { preserveScroll: true });
}

function acknowledge(item) {
    actionTarget.value = item;
    acknowledgementForm.defaults({ notes: '' });
    acknowledgementForm.reset();
    activeModal.value = 'acknowledge';
}

function retire(item) {
    actionTarget.value = item;
    retireForm.defaults({ reason: '' });
    retireForm.reset();
    activeModal.value = 'retire';
}

function saveRequestAction() {
    requestActionForm.patch(`/admin/radiology/requests/${props.radiologyRequest.id}/transition`, { preserveScroll: true, onSuccess: () => { activeModal.value = null; requestActionForm.reset(); } });
}

function saveSchedule() {
    schedule.patch(`/admin/radiology/requests/${props.radiologyRequest.id}/schedule`, { preserveScroll: true, onSuccess: () => { activeModal.value = null; } });
}

function saveReport() {
    reportForm.post(`/admin/radiology/requests/${props.radiologyRequest.id}/report`, { preserveScroll: true });
}

function saveCommunication() {
    communication.post(`/admin/radiology/reports/${report.value.id}/critical-communications`, { preserveScroll: true, onSuccess: () => { activeModal.value = null; communication.reset(); } });
}

function saveUpload() {
    upload.post(`/admin/radiology/requests/${props.radiologyRequest.id}/attachments`, { preserveScroll: true, forceFormData: true, onSuccess: () => { activeModal.value = null; upload.reset('attachment'); } });
}

function saveAmendment() {
    amendment.post(`/admin/radiology/reports/${report.value.id}/amendments`, { preserveScroll: true, onSuccess: () => { activeModal.value = null; amendment.reset(); } });
}

function saveAcknowledgement() {
    acknowledgementForm.patch(`/admin/radiology/critical-communications/${actionTarget.value.id}/acknowledge`, { preserveScroll: true, onSuccess: () => { activeModal.value = null; acknowledgementForm.reset(); } });
}

function saveRetire() {
    retireForm.patch(`/admin/radiology/attachments/${actionTarget.value.id}/retire`, { preserveScroll: true, onSuccess: () => { activeModal.value = null; retireForm.reset(); } });
}
</script>

<template>
    <Head :title="radiologyRequest.request_number" />
    <AppLayout :title="radiologyRequest.request_number">
        <PageHeader :title="radiologyRequest.request_number" description="Radiology request, structured report and workflow controls.">
            <template #actions>
                <ActionToolbar align="end">
                    <Link href="/admin/radiology/requests" class="rounded-md border px-3 py-2 text-sm font-bold">Back</Link>
                    <PrimaryButton type="button" @click="activeModal = 'schedule'">Schedule</PrimaryButton>
                    <PrimaryButton type="button" @click="activeModal = 'upload'">Upload Attachment</PrimaryButton>
                    <PrimaryButton v-if="report?.has_critical_finding" type="button" @click="activeModal = 'communication'">Record Communication</PrimaryButton>
                    <PrimaryButton v-if="report" type="button" @click="activeModal = 'amendment'">Add Amendment</PrimaryButton>
                <Link v-if="canShowReport" class="rounded-md border px-3 py-2 text-sm font-bold" :href="`/admin/radiology/requests/${radiologyRequest.id}/report`">Report</Link>
                <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="requestAction('arrive')">Arrived</button>
                <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="requestAction('perform')">Performed</button>
                <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="requestAction('reporting')">Reporting</button>
                <button class="rounded-md border px-3 py-2 text-sm font-bold text-red-700" type="button" @click="requestAction('cancel')">Cancel</button>
                </ActionToolbar>
            </template>
        </PageHeader>

        <div class="grid gap-6">
            <section class="space-y-6">
                <section class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <div class="grid gap-3 md:grid-cols-4">
                        <div><p class="text-xs text-slate-500">Patient</p><p class="font-bold">{{ radiologyRequest.patient?.full_name }}</p></div>
                        <div><p class="text-xs text-slate-500">Accession</p><p class="font-bold">{{ radiologyRequest.accession_number }}</p></div>
                        <div><p class="text-xs text-slate-500">Status</p><p class="font-bold">{{ radiologyRequest.status }}</p></div>
                        <div><p class="text-xs text-slate-500">Priority</p><p class="font-bold">{{ radiologyRequest.priority }}</p></div>
                    </div>
                    <p class="mt-4 text-sm text-slate-500">{{ radiologyRequest.clinical_indication }}</p>
                    <p v-if="radiologyRequest.invoice" class="mt-4 text-sm text-slate-500">Billing invoice: {{ radiologyRequest.invoice.invoice_number || `Draft #${radiologyRequest.invoice.id}` }} - {{ radiologyRequest.invoice.total_minor }} minor units</p>
                </section>

                <section class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="font-black">Studies</h2>
                    <div class="mt-4 divide-y divide-slate-100 dark:divide-slate-800">
                        <article v-for="item in radiologyRequest.studies" :key="item.id" class="py-3">
                            <p class="font-bold">{{ item.study_code }} - {{ item.study_name }}</p>
                            <p class="text-sm text-slate-500">{{ item.study?.modality?.name || 'No modality' }}</p>
                        </article>
                    </div>
                </section>

                <form class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="saveReport">
                    <h2 class="font-black">Structured Report</h2>
                    <div class="mt-4 grid gap-3">
                        <select v-model="reportForm.reporting_radiologist_id" class="rounded-md border-slate-300">
                            <option value="">Reporting radiologist</option>
                            <option v-for="staff in radiologyStaff" :key="staff.id" :value="staff.id">{{ staff.user?.firstname }} {{ staff.user?.lastname }}</option>
                        </select>
                        <textarea v-model="reportForm.findings" class="rounded-md border-slate-300" rows="4" placeholder="Findings"></textarea>
                        <textarea v-model="reportForm.impression" class="rounded-md border-slate-300" rows="3" placeholder="Impression"></textarea>
                        <textarea v-model="reportForm.recommendations" class="rounded-md border-slate-300" rows="2" placeholder="Recommendations"></textarea>
                        <label class="flex items-center gap-2 text-sm"><input v-model="reportForm.has_critical_finding" type="checkbox"> Critical finding</label>
                        <textarea v-model="reportForm.critical_finding_notes" class="rounded-md border-slate-300" rows="2" placeholder="Critical finding notes"></textarea>
                        <PrimaryButton :disabled="reportForm.processing">Save draft report</PrimaryButton>
                    </div>
                </form>

                <section v-if="report" class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="font-black">Report Workflow</h2>
                            <p class="text-sm text-slate-500">Status: {{ report.status }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="reportAction('verify')">Verify</button>
                            <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="reportAction('approve')">Approve</button>
                            <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="reportAction('release')">Release</button>
                        </div>
                    </div>
                </section>
            </section>

            <section class="space-y-6">
                <section v-if="report?.has_critical_finding" class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="font-black">Critical Finding Communications</h2>
                    <article v-for="item in report.communications || []" :key="item.id" class="mt-3 text-sm">
                        <p class="font-bold">{{ item.communicated_to }} - {{ item.method }}</p>
                        <p class="text-slate-500">{{ item.notes }}</p>
                        <button v-if="!item.acknowledged_at" class="mt-2 rounded-md border px-2 py-1 text-xs font-bold" type="button" @click="acknowledge(item)">Acknowledge</button>
                    </article>
                </section>

                <section class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="font-black">Secure Attachments</h2>
                    <div class="mt-4 divide-y divide-slate-100 text-sm dark:divide-slate-800">
                        <article v-for="item in radiologyRequest.attachments" :key="item.id" class="py-3">
                            <p class="font-bold">{{ item.original_name }}</p>
                            <p class="text-slate-500">{{ item.scan_status }} - {{ item.status }}</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <button class="rounded-md border px-2 py-1 text-xs font-bold" type="button" @click="router.patch(`/admin/radiology/attachments/${item.id}/clear`, {}, { preserveScroll: true })">Clear</button>
                                <a class="rounded-md border px-2 py-1 text-xs font-bold" :href="`/admin/radiology/attachments/${item.id}/download`">Download</a>
                                <button class="rounded-md border px-2 py-1 text-xs font-bold" type="button" @click="retire(item)">Retire</button>
                            </div>
                        </article>
                    </div>
                </section>

                <section v-if="report" class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="font-black">Amendments</h2>
                    <p v-for="item in report.amendments || []" :key="item.id" class="mt-3 text-sm"><strong>{{ item.reason }}</strong> - {{ item.content }}</p>
                </section>
            </section>
        </div>

        <ConfirmDialog :show="activeModal === 'request-action'" :title="`${requestActionForm.action} request`" confirm-label="Save action" :form="requestActionForm" :require-reason="requestActionForm.action === 'cancel'" @close="activeModal = null" @confirm="saveRequestAction">
            <textarea v-if="requestActionForm.action === 'perform'" v-model="requestActionForm.performance_notes" class="mt-3 w-full rounded-md border-slate-300 text-sm" rows="4" placeholder="Performance notes"></textarea>
        </ConfirmDialog>

        <FormModal :show="activeModal === 'schedule'" title="Schedule" :form="schedule" submit-label="Save schedule" @close="activeModal = null" @submit="saveSchedule">
            <div class="grid gap-3">
                <TextInput id="scheduled_at" v-model="schedule.scheduled_at" label="Scheduled time" type="datetime-local" />
                <TextInput id="room" v-model="schedule.room" label="Room" />
                <TextInput id="equipment" v-model="schedule.equipment" label="Equipment" />
                <select v-model="schedule.assigned_staff_id" class="rounded-md border-slate-300">
                    <option value="">Assigned staff</option>
                    <option v-for="staff in radiologyStaff" :key="staff.id" :value="staff.id">{{ staff.user?.firstname }} {{ staff.user?.lastname }}</option>
                </select>
            </div>
        </FormModal>

        <FormModal :show="activeModal === 'communication'" title="Critical Finding Communication" :form="communication" submit-label="Record communication" @close="activeModal = null" @submit="saveCommunication">
            <div class="grid gap-3">
                <TextInput id="communicated_to" v-model="communication.communicated_to" label="Communicated to" />
                <TextInput id="method" v-model="communication.method" label="Method" />
                <textarea v-model="communication.notes" class="rounded-md border-slate-300" rows="3" placeholder="Communication and escalation notes"></textarea>
            </div>
        </FormModal>

        <FormModal :show="activeModal === 'upload'" title="Upload Attachment" :form="upload" submit-label="Upload to quarantine" @close="activeModal = null" @submit="saveUpload">
            <input class="block w-full text-sm" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" @input="upload.attachment = $event.target.files[0]">
        </FormModal>

        <FormModal :show="activeModal === 'amendment'" title="Amend Approved Report" :form="amendment" submit-label="Add amendment" @close="activeModal = null" @submit="saveAmendment">
            <div class="grid gap-3">
                <input v-model="amendment.reason" class="w-full rounded-md border-slate-300 text-sm" placeholder="Reason">
                <textarea v-model="amendment.content" class="w-full rounded-md border-slate-300 text-sm" rows="4" placeholder="Amendment"></textarea>
            </div>
        </FormModal>

        <FormModal :show="activeModal === 'acknowledge'" title="Acknowledge Communication" :form="acknowledgementForm" submit-label="Acknowledge" @close="activeModal = null" @submit="saveAcknowledgement">
            <textarea v-model="acknowledgementForm.notes" class="w-full rounded-md border-slate-300 text-sm" rows="4" placeholder="Acknowledgement and escalation notes"></textarea>
        </FormModal>

        <ConfirmDialog :show="activeModal === 'retire'" title="Retire attachment" confirm-label="Retire" :form="retireForm" require-reason @close="activeModal = null" @confirm="saveRetire" />
    </AppLayout>
</template>
