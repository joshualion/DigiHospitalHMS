<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    labRequest: { type: Object, required: true },
    specimenTypes: { type: Array, default: () => [] },
});

const collectForm = useForm({ lab_specimen_type_id: props.specimenTypes[0]?.id || '' });
const resultForms = computed(() => Object.fromEntries(props.labRequest.tests.flatMap((test) => (test.test?.components || []).map((component) => [`${test.id}-${component.id}`, useForm({ lab_test_component_id: component.id, numeric_value: '', text_value: '', qualitative_value: '', comment: '' })]))));
const amendment = useForm({ reason: '', content: '' });

function specimenAction(specimen, action) {
    const reason = action === 'reject' ? window.prompt('Rejection reason') : null;
    if (action === 'reject' && !reason) return;
    router.patch(`/admin/laboratory/specimens/${specimen.id}/transition`, { action, reason }, { preserveScroll: true });
}

function resultAction(result, action) {
    router.patch(`/admin/laboratory/results/${result.id}/transition`, { action }, { preserveScroll: true });
}

function acknowledge(result) {
    const notes = window.prompt('Critical acknowledgement and escalation notes');
    if (!notes) return;
    router.post(`/admin/laboratory/results/${result.id}/critical-acknowledgement`, { notes }, { preserveScroll: true });
}
</script>

<template>
    <Head :title="labRequest.request_number" />
    <AppLayout :title="labRequest.request_number">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <Link href="/admin/laboratory/requests" class="text-sm font-bold text-red-800">Back to worklist</Link>
            <div class="flex flex-wrap gap-2">
                <Link v-if="['approved', 'released'].includes(labRequest.status)" class="rounded-md border px-3 py-2 text-sm font-bold" :href="`/admin/laboratory/requests/${labRequest.id}/report`">Report</Link>
                <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="router.post(`/admin/laboratory/requests/${labRequest.id}/release`)">Release</button>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[1fr_360px]">
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

            <aside class="space-y-6">
                <form class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="collectForm.post(`/admin/laboratory/requests/${labRequest.id}/specimens`, { preserveScroll: true })">
                    <h2 class="font-black">Specimen</h2>
                    <div class="mt-4 grid gap-3">
                        <select v-model="collectForm.lab_specimen_type_id" class="rounded-md border-slate-300"><option v-for="type in specimenTypes" :key="type.id" :value="type.id">{{ type.name }}</option></select>
                        <PrimaryButton :disabled="collectForm.processing">Collect specimen</PrimaryButton>
                    </div>
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
                </form>

                <form class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="amendment.post(`/admin/laboratory/requests/${labRequest.id}/amendments`, { preserveScroll: true, onSuccess: () => amendment.reset() })">
                    <h2 class="font-black">Amend Approved Report</h2>
                    <input v-model="amendment.reason" class="mt-3 w-full rounded-md border-slate-300 text-sm" placeholder="Reason">
                    <textarea v-model="amendment.content" class="mt-3 w-full rounded-md border-slate-300 text-sm" rows="3" placeholder="Amendment"></textarea>
                    <PrimaryButton class="mt-3" :disabled="amendment.processing">Add amendment</PrimaryButton>
                    <p v-for="item in labRequest.amendments" :key="item.id" class="mt-3 text-sm"><strong>{{ item.reason }}</strong> - {{ item.content }}</p>
                </form>
            </aside>
        </div>
    </AppLayout>
</template>
