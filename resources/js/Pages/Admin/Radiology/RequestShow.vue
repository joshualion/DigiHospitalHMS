<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

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

function requestAction(action) {
    const reason = action === 'cancel' ? window.prompt('Cancellation reason') : null;
    if (action === 'cancel' && !reason) return;
    const performance_notes = action === 'perform' ? window.prompt('Performance notes') : null;
    router.patch(`/admin/radiology/requests/${props.radiologyRequest.id}/transition`, { action, reason, performance_notes }, { preserveScroll: true });
}

function reportAction(action) {
    router.patch(`/admin/radiology/reports/${report.value.id}/transition`, { action }, { preserveScroll: true });
}

function acknowledge(item) {
    const notes = window.prompt('Acknowledgement and escalation notes');
    if (!notes) return;
    router.patch(`/admin/radiology/critical-communications/${item.id}/acknowledge`, { notes }, { preserveScroll: true });
}

function retire(item) {
    const reason = window.prompt('Retirement reason');
    if (!reason) return;
    router.patch(`/admin/radiology/attachments/${item.id}/retire`, { reason }, { preserveScroll: true });
}
</script>

<template>
    <Head :title="radiologyRequest.request_number" />
    <AppLayout :title="radiologyRequest.request_number">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <Link href="/admin/radiology/requests" class="text-sm font-bold text-red-800">Back to worklist</Link>
            <div class="flex flex-wrap gap-2">
                <Link v-if="canShowReport" class="rounded-md border px-3 py-2 text-sm font-bold" :href="`/admin/radiology/requests/${radiologyRequest.id}/report`">Report</Link>
                <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="requestAction('arrive')">Arrived</button>
                <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="requestAction('perform')">Performed</button>
                <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="requestAction('reporting')">Reporting</button>
                <button class="rounded-md border px-3 py-2 text-sm font-bold text-red-700" type="button" @click="requestAction('cancel')">Cancel</button>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[1fr_360px]">
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

                <form class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="reportForm.post(`/admin/radiology/requests/${radiologyRequest.id}/report`, { preserveScroll: true })">
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

            <aside class="space-y-6">
                <form class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="schedule.patch(`/admin/radiology/requests/${radiologyRequest.id}/schedule`, { preserveScroll: true })">
                    <h2 class="font-black">Schedule</h2>
                    <div class="mt-4 grid gap-3">
                        <TextInput id="scheduled_at" v-model="schedule.scheduled_at" label="Scheduled time" type="datetime-local" />
                        <TextInput id="room" v-model="schedule.room" label="Room" />
                        <TextInput id="equipment" v-model="schedule.equipment" label="Equipment" />
                        <select v-model="schedule.assigned_staff_id" class="rounded-md border-slate-300">
                            <option value="">Assigned staff</option>
                            <option v-for="staff in radiologyStaff" :key="staff.id" :value="staff.id">{{ staff.user?.firstname }} {{ staff.user?.lastname }}</option>
                        </select>
                        <PrimaryButton :disabled="schedule.processing">Save schedule</PrimaryButton>
                    </div>
                </form>

                <form v-if="report?.has_critical_finding" class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="communication.post(`/admin/radiology/reports/${report.id}/critical-communications`, { preserveScroll: true, onSuccess: () => communication.reset() })">
                    <h2 class="font-black">Critical Finding Communication</h2>
                    <div class="mt-4 grid gap-3">
                        <TextInput id="communicated_to" v-model="communication.communicated_to" label="Communicated to" />
                        <TextInput id="method" v-model="communication.method" label="Method" />
                        <textarea v-model="communication.notes" class="rounded-md border-slate-300" rows="3" placeholder="Communication and escalation notes"></textarea>
                        <PrimaryButton :disabled="communication.processing">Record communication</PrimaryButton>
                    </div>
                    <article v-for="item in report.communications || []" :key="item.id" class="mt-3 text-sm">
                        <p class="font-bold">{{ item.communicated_to }} - {{ item.method }}</p>
                        <p class="text-slate-500">{{ item.notes }}</p>
                        <button v-if="!item.acknowledged_at" class="mt-2 rounded-md border px-2 py-1 text-xs font-bold" type="button" @click="acknowledge(item)">Acknowledge</button>
                    </article>
                </form>

                <form class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="upload.post(`/admin/radiology/requests/${radiologyRequest.id}/attachments`, { preserveScroll: true, forceFormData: true, onSuccess: () => upload.reset('attachment') })">
                    <h2 class="font-black">Secure Attachments</h2>
                    <input class="mt-4 block w-full text-sm" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" @input="upload.attachment = $event.target.files[0]">
                    <PrimaryButton class="mt-3" :disabled="upload.processing">Upload to quarantine</PrimaryButton>
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
                </form>

                <form v-if="report" class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="amendment.post(`/admin/radiology/reports/${report.id}/amendments`, { preserveScroll: true, onSuccess: () => amendment.reset() })">
                    <h2 class="font-black">Amend Approved Report</h2>
                    <input v-model="amendment.reason" class="mt-3 w-full rounded-md border-slate-300 text-sm" placeholder="Reason">
                    <textarea v-model="amendment.content" class="mt-3 w-full rounded-md border-slate-300 text-sm" rows="3" placeholder="Amendment"></textarea>
                    <PrimaryButton class="mt-3" :disabled="amendment.processing">Add amendment</PrimaryButton>
                    <p v-for="item in report.amendments || []" :key="item.id" class="mt-3 text-sm"><strong>{{ item.reason }}</strong> - {{ item.content }}</p>
                </form>
            </aside>
        </div>
    </AppLayout>
</template>
