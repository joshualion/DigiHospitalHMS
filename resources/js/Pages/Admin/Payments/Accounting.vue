<script setup>
import ActionToolbar from '@/Components/Admin/ActionToolbar.vue';
import ConfirmDialog from '@/Components/Admin/ConfirmDialog.vue';
import FormModal from '@/Components/Admin/FormModal.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    shifts: { type: Object, required: true },
    payments: { type: Array, default: () => [] },
    refunds: { type: Array, default: () => [] },
    summaries: { type: Object, default: () => ({ byMethod: [], byFacility: [] }) },
});

const reviewForm = useForm({ review_notes: '' });
const reverseForm = useForm({ reason: '' });
const refundForm = useForm({ amount_minor: '', reason: '' });
const decisionForm = useForm({ decision: '', decision_notes: '' });
const processForm = useForm({});
const activeAction = ref(null);
const actionTarget = ref(null);

function openAction(action, target, defaults = {}) {
    activeAction.value = action;
    actionTarget.value = target;
    const form = { review: reviewForm, reverse: reverseForm, refund: refundForm, decision: decisionForm, process: processForm }[action];
    form.defaults(defaults);
    form.reset();
}

function saveReview() {
    reviewForm.patch(`/admin/cashier-shifts/${actionTarget.value.id}/review`, { preserveScroll: true, onSuccess: () => { activeAction.value = null; reviewForm.reset(); } });
}

function saveReverse() {
    reverseForm.patch(`/admin/payments/${actionTarget.value.id}/reverse`, { preserveScroll: true, onSuccess: () => { activeAction.value = null; reverseForm.reset(); } });
}

function saveRefund() {
    refundForm.post(`/admin/payments/${actionTarget.value.id}/refunds`, { preserveScroll: true, onSuccess: () => { activeAction.value = null; refundForm.reset(); } });
}

function saveDecision() {
    decisionForm.patch(`/admin/refunds/${actionTarget.value.id}/decision`, { preserveScroll: true, onSuccess: () => { activeAction.value = null; decisionForm.reset(); } });
}

function saveProcess() {
    processForm.patch(`/admin/refunds/${actionTarget.value.id}/process`, { preserveScroll: true, onSuccess: () => { activeAction.value = null; } });
}
</script>

<template>
    <Head title="Payment Accounting" />
    <AppLayout title="Payment Accounting">
        <PageHeader title="Payment Accounting" description="Collections, cashier shifts, refunds and reconciliation actions." />

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="min-w-0 rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 p-4 dark:border-slate-800">
                    <h2 class="font-black">Daily Collection Summaries</h2>
                </div>
                <div class="grid gap-4 p-4 md:grid-cols-2">
                    <div>
                        <h3 class="text-sm font-black uppercase text-slate-500">By Method</h3>
                        <p v-for="row in summaries.byMethod" :key="`${row.name}-${row.type}`" class="mt-2 break-words text-sm">{{ row.name }}: <strong>{{ row.amount_minor }}</strong> ({{ row.count }})</p>
                    </div>
                    <div>
                        <h3 class="text-sm font-black uppercase text-slate-500">By Facility</h3>
                        <p v-for="row in summaries.byFacility" :key="row.name" class="mt-2 break-words text-sm">{{ row.name }}: <strong>{{ row.amount_minor }}</strong> ({{ row.count }})</p>
                    </div>
                </div>
            </section>

            <section class="min-w-0 rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 p-4 dark:border-slate-800">
                    <h2 class="font-black">Refund Requests</h2>
                </div>
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    <article v-for="refund in refunds" :key="refund.id" class="min-w-0 p-4">
                        <p class="break-words font-bold">{{ refund.payment?.receipt_number }} - {{ refund.patient?.full_name }}</p>
                        <p class="break-words text-sm text-slate-500">{{ refund.currency }} {{ refund.amount_minor }} - {{ refund.status }} - {{ refund.reason }}</p>
                        <ActionToolbar class="mt-3">
                            <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="openAction('decision', refund, { decision: 'approve', decision_notes: '' })">Approve</button>
                            <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="openAction('decision', refund, { decision: 'reject', decision_notes: '' })">Reject</button>
                            <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="openAction('process', refund)">Process</button>
                        </ActionToolbar>
                    </article>
                    <p v-if="refunds.length === 0" class="p-4 text-sm text-slate-500">No refund requests.</p>
                </div>
            </section>
        </div>

        <section class="mt-6 min-w-0 rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 p-4 dark:border-slate-800">
                <h2 class="font-black">Cashier Shifts</h2>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                <article v-for="shift in shifts.data" :key="shift.id" class="grid min-w-0 gap-3 p-4 md:grid-cols-[1fr_auto]">
                    <div class="min-w-0">
                        <p class="break-words font-bold">{{ shift.cashier?.full_name }} - {{ shift.facility?.name }}</p>
                        <p class="break-words text-sm text-slate-500">{{ shift.currency }} expected {{ shift.expected_cash_minor }} - counted {{ shift.counted_cash_minor ?? 'open' }} - variance {{ shift.variance_minor ?? 'open' }}</p>
                    </div>
                    <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="openAction('review', shift, { review_notes: '' })">Review</button>
                </article>
            </div>
        </section>

        <section class="mt-6 min-w-0 rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 p-4 dark:border-slate-800">
                <h2 class="font-black">Payments</h2>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                <article v-for="payment in payments" :key="payment.id" class="grid min-w-0 gap-3 p-4 md:grid-cols-[1fr_auto]">
                    <div class="min-w-0">
                        <Link class="break-words font-bold text-red-800" :href="`/admin/payments/${payment.id}/receipt`">{{ payment.receipt_number }}</Link>
                        <p class="break-words text-sm text-slate-500">{{ payment.patient?.full_name }} - {{ payment.method?.name }} - {{ payment.currency }} {{ payment.amount_minor }} - {{ payment.status }}</p>
                    </div>
                    <ActionToolbar align="end">
                        <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="openAction('refund', payment, { amount_minor: '', reason: '' })">Refund</button>
                        <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="openAction('reverse', payment, { reason: '' })">Reverse</button>
                    </ActionToolbar>
                </article>
            </div>
        </section>

        <FormModal :show="activeAction === 'review'" title="Review Shift" :form="reviewForm" submit-label="Save review" @close="activeAction = null" @submit="saveReview">
            <textarea v-model="reviewForm.review_notes" class="w-full rounded-md border-slate-300" rows="4" placeholder="Review notes"></textarea>
        </FormModal>

        <ConfirmDialog :show="activeAction === 'reverse'" title="Reverse payment" confirm-label="Reverse" :form="reverseForm" require-reason @close="activeAction = null" @confirm="saveReverse" />

        <FormModal :show="activeAction === 'refund'" title="Request Refund" :form="refundForm" submit-label="Request refund" @close="activeAction = null" @submit="saveRefund">
            <div class="grid gap-3">
                <TextInput id="refund_amount" v-model="refundForm.amount_minor" label="Refund amount minor units" type="number" />
                <textarea v-model="refundForm.reason" class="w-full rounded-md border-slate-300" rows="3" placeholder="Refund reason"></textarea>
            </div>
        </FormModal>

        <FormModal :show="activeAction === 'decision'" :title="`${decisionForm.decision} refund`" :form="decisionForm" submit-label="Save decision" @close="activeAction = null" @submit="saveDecision">
            <textarea v-model="decisionForm.decision_notes" class="w-full rounded-md border-slate-300" rows="4" placeholder="Decision notes"></textarea>
        </FormModal>

        <ConfirmDialog :show="activeAction === 'process'" title="Process approved refund" confirm-label="Process" :form="processForm" @close="activeAction = null" @confirm="saveProcess" />
    </AppLayout>
</template>
