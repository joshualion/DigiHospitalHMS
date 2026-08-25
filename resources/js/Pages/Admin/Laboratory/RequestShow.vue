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
    labRequest: { type: Object, required: true },
    specimenTypes: { type: Array, default: () => [] },
});

const collectForm = useForm({ lab_specimen_type_id: props.specimenTypes[0]?.id || '' });
const resultForms = computed(() => Object.fromEntries(props.labRequest.tests.flatMap((test) => (test.test?.components || []).map((component) => [`${test.id}-${component.id}`, useForm({ lab_test_component_id: component.id, numeric_value: '', text_value: '', qualitative_value: '', comment: '' })]))));
const amendment = useForm({ reason: '', content: '' });
const actionForm = useForm({ action: '', reason: '', notes: '' });
const releaseForm = useForm({});
const activeModal = ref(null);
const actionTarget = ref(null);

function specimenAction(specimen, action) {
    actionTarget.value = specimen;
    actionForm.defaults({ action, reason: '', notes: '' });
    actionForm.reset();
    activeModal.value = 'specimen-action';
}

function resultAction(result, action) {
    actionTarget.value = result;
    actionForm.defaults({ action, reason: '', notes: '' });
    actionForm.reset();
    activeModal.value = 'result-action';
}

function acknowledge(result) {
    actionTarget.value = result;
    actionForm.defaults({ action: 'acknowledge', reason: '', notes: '' });
    actionForm.reset();
    activeModal.value = 'acknowledge';
}

function saveSpecimenAction() {
    actionForm.patch(`/admin/laboratory/specimens/${actionTarget.value.id}/transition`, { preserveScroll: true, onSuccess: () => { activeModal.value = null; actionForm.reset(); } });
}

function saveResultAction() {
    actionForm.patch(`/admin/laboratory/results/${actionTarget.value.id}/transition`, { preserveScroll: true, onSuccess: () => { activeModal.value = null; actionForm.reset(); } });
}

function saveAcknowledgement() {
    actionForm.post(`/admin/laboratory/results/${actionTarget.value.id}/critical-acknowledgement`, { preserveScroll: true, onSuccess: () => { activeModal.value = null; actionForm.reset(); } });
}

function saveSpecimen() {
    collectForm.post(`/admin/laboratory/requests/${props.labRequest.id}/specimens`, { preserveScroll: true, onSuccess: () => { activeModal.value = null; collectForm.reset(); } });
}

function saveAmendment() {
    amendment.post(`/admin/laboratory/requests/${props.labRequest.id}/amendments`, { preserveScroll: true, onSuccess: () => { activeModal.value = null; amendment.reset(); } });
}
</script>

<template>
    <Head :title="labRequest.request_number" />
    <AppLayout :title="labRequest.request_number">
        <PageHeader :title="labRequest.request_number" description="Laboratory request, specimen tracking and result workflow.">
            <template #actions>
                <ActionToolbar align="end">
                    <Link href="/admin/laboratory/requests" class="rounded-md border px-3 py-2 text-sm font-bold">Back</Link>
                    <PrimaryButton type="button" @click="activeModal = 'specimen'">Collect Specimen</PrimaryButton>
                    <PrimaryButton type="button" @click="activeModal = 'amendment'">Add Amendment</PrimaryButton>
                <Link v-if="['approved', 'released'].includes(labRequest.status)" class="rounded-md border px-3 py-2 text-sm font-bold" :href="`/admin/laboratory/requests/${labRequest.id}/report`">Report</Link>
                    <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" :disabled="releaseForm.processing" @click="releaseForm.post(`/admin/laboratory/requests/${labRequest.id}/release`, { preserveScroll: true })">Release</button>
                </ActionToolbar>
            </template>
        </PageHeader>

        <div class="grid gap-6">
            <section class="space-y-6">
                <section class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <div class="grid gap-3 md:grid-cols-4">
                        <div><p class="text-xs text-slate-500">Patient</p><p class="font-bold">{{ labRequest.patient?.full_name }}</p></div>
                        <div><p class="text-xs text-slate-500">Accession</p><p class="font-bold">{{ labRequest.accession_number }}</p></div>
                        <div><p class="text-xs text-slate-500">Status</p><p class="font-bold">{{ labRequest.status }}</p></div>
                        <div><p class="text-xs text-slate-500">Priority</p><p class="font-bold">{{ labRequest.priority }}</p></div>
                    </div>
                    <p v-if="labRequest.invoice" class="mt-4 text-sm text-slate-500">Billing invoice: {{ labRequest.invoice.invoice_number || `Draft #${labRequest.invoice.id}` }} - {{ labRequest.invoice.total_minor }} minor units</p>
                </section>

                <section v-for="requestTest in labRequest.tests" :key="requestTest.id" class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-lg font-black">{{ requestTest.test_name }}</h2>
                    <div class="mt-4 grid gap-4">
                        <form v-for="component in requestTest.test?.components || []" :key="component.id" class="grid gap-3 rounded-md border border-slate-200 p-3 md:grid-cols-5" @submit.prevent="resultForms[`${requestTest.id}-${component.id}`].post(`/admin/laboratory/request-tests/${requestTest.id}/results`, { preserveScroll: true })">
                            <div class="md:col-span-5">
                                <p class="font-bold">{{ component.name }} <span class="text-sm text-slate-500">{{ component.unit?.code }}</span></p>
                                <p v-if="component.reference_ranges?.[0]?.display_text" class="text-xs text-slate-500">Range: {{ component.reference_ranges[0].display_text }}</p>
                            </div>
                            <TextInput :id="`num_${component.id}`" v-model="resultForms[`${requestTest.id}-${component.id}`].numeric_value" label="Numeric" type="number" />
                            <TextInput :id="`qual_${component.id}`" v-model="resultForms[`${requestTest.id}-${component.id}`].qualitative_value" label="Qualitative" />
                            <input v-model="resultForms[`${requestTest.id}-${component.id}`].text_value" class="rounded-md border-slate-300 md:col-span-2" placeholder="Text value">
                            <PrimaryButton :disabled="resultForms[`${requestTest.id}-${component.id}`].processing">Save draft</PrimaryButton>
                        </form>
                    </div>
                </section>

                <section class="rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                    <div class="border-b border-slate-200 p-4 dark:border-slate-800">
                        <h2 class="font-black">Results</h2>
                    </div>
                    <div class="divide-y divide-slate-100 dark:divide-slate-800">
                        <article v-for="result in labRequest.results" :key="result.id" class="grid gap-3 p-4 md:grid-cols-[1fr_auto]">
                            <div>
                                <p class="font-bold">{{ result.component_name }} - {{ result.flag }} <span v-if="result.is_critical" class="text-red-700">Critical</span></p>
                                <p class="text-sm text-slate-500">{{ result.numeric_value ?? result.qualitative_value ?? result.text_value ?? result.comment }} {{ result.component?.unit?.code || '' }} - {{ result.status }}</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="resultAction(result, 'verify')">Verify</button>
                                <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="resultAction(result, 'approve')">Approve</button>
                                <button v-if="result.is_critical" class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="acknowledge(result)">Acknowledge</button>
                            </div>
                        </article>
                    </div>
                </section>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="font-black">Specimens</h2>
                    <div class="mt-4 divide-y divide-slate-100 text-sm dark:divide-slate-800">
                        <article v-for="specimen in labRequest.specimens" :key="specimen.id" class="py-3">
                            <p class="font-bold">{{ specimen.label_number }} - {{ specimen.status }}</p>
                            <p class="text-slate-500">{{ specimen.type?.name }}</p>
                            <div class="mt-2 flex gap-2">
                                <button class="rounded-md border px-2 py-1 text-xs font-bold" type="button" @click="specimenAction(specimen, 'receive')">Receive</button>
                                <button class="rounded-md border px-2 py-1 text-xs font-bold" type="button" @click="specimenAction(specimen, 'reject')">Reject</button>
                            </div>
                        </article>
                    </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="font-black">Amendments</h2>
                <p v-for="item in labRequest.amendments" :key="item.id" class="mt-3 break-words text-sm"><strong>{{ item.reason }}</strong> - {{ item.content }}</p>
            </section>
        </div>

        <FormModal :show="activeModal === 'specimen'" title="Collect Specimen" :form="collectForm" submit-label="Collect specimen" @close="activeModal = null" @submit="saveSpecimen">
            <select v-model="collectForm.lab_specimen_type_id" class="w-full rounded-md border-slate-300"><option v-for="type in specimenTypes" :key="type.id" :value="type.id">{{ type.name }}</option></select>
        </FormModal>

        <FormModal :show="activeModal === 'amendment'" title="Amend Approved Report" :form="amendment" submit-label="Add amendment" @close="activeModal = null" @submit="saveAmendment">
            <div class="grid gap-3">
                <input v-model="amendment.reason" class="w-full rounded-md border-slate-300 text-sm" placeholder="Reason">
                <textarea v-model="amendment.content" class="w-full rounded-md border-slate-300 text-sm" rows="4" placeholder="Amendment"></textarea>
            </div>
        </FormModal>

        <ConfirmDialog :show="activeModal === 'specimen-action'" :title="`${actionForm.action} specimen`" confirm-label="Save action" :form="actionForm" :require-reason="actionForm.action === 'reject'" @close="activeModal = null" @confirm="saveSpecimenAction" />
        <ConfirmDialog :show="activeModal === 'result-action'" :title="`${actionForm.action} result`" confirm-label="Save action" :form="actionForm" @close="activeModal = null" @confirm="saveResultAction" />
        <FormModal :show="activeModal === 'acknowledge'" title="Critical Acknowledgement" :form="actionForm" submit-label="Record acknowledgement" @close="activeModal = null" @submit="saveAcknowledgement">
            <textarea v-model="actionForm.notes" class="w-full rounded-md border-slate-300 text-sm" rows="4" placeholder="Critical acknowledgement and escalation notes"></textarea>
        </FormModal>
    </AppLayout>
</template>
