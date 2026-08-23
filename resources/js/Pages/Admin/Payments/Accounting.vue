<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    shifts: { type: Object, required: true },
    payments: { type: Array, default: () => [] },
    refunds: { type: Array, default: () => [] },
    summaries: { type: Object, default: () => ({ byMethod: [], byFacility: [] }) },
});

function reviewShift(shift) {
    const review_notes = window.prompt('Review notes');
    if (review_notes === null) return;
    router.patch(`/admin/cashier-shifts/${shift.id}/review`, { review_notes }, { preserveScroll: true });
}

function reversePayment(payment) {
    const reason = window.prompt('Payment reversal reason');
    if (!reason) return;
    router.patch(`/admin/payments/${payment.id}/reverse`, { reason }, { preserveScroll: true });
}

function requestRefund(payment) {
    const amount = window.prompt('Refund amount in minor units');
    if (!amount) return;
    const reason = window.prompt('Refund reason');
    if (!reason) return;
    router.post(`/admin/payments/${payment.id}/refunds`, { amount_minor: Number(amount), reason }, { preserveScroll: true });
}

function decideRefund(refund, decision) {
    const decision_notes = window.prompt(`${decision} notes`);
    if (decision_notes === null) return;
    router.patch(`/admin/refunds/${refund.id}/decision`, { decision, decision_notes }, { preserveScroll: true });
}

function processRefund(refund) {
    if (!window.confirm('Process approved refund?')) return;
    router.patch(`/admin/refunds/${refund.id}/process`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Payment Accounting" />
    <AppLayout title="Payment Accounting">
        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 p-4 dark:border-slate-800">
                    <h2 class="font-black">Daily Collection Summaries</h2>
                </div>
                <div class="grid gap-4 p-4 md:grid-cols-2">
                    <div>
                        <h3 class="text-sm font-black uppercase text-slate-500">By Method</h3>
                        <p v-for="row in summaries.byMethod" :key="`${row.name}-${row.type}`" class="mt-2 text-sm">{{ row.name }}: <strong>{{ row.amount_minor }}</strong> ({{ row.count }})</p>
                    </div>
                    <div>
                        <h3 class="text-sm font-black uppercase text-slate-500">By Facility</h3>
                        <p v-for="row in summaries.byFacility" :key="row.name" class="mt-2 text-sm">{{ row.name }}: <strong>{{ row.amount_minor }}</strong> ({{ row.count }})</p>
                    </div>
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 p-4 dark:border-slate-800">
                    <h2 class="font-black">Refund Requests</h2>
                </div>
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    <article v-for="refund in refunds" :key="refund.id" class="p-4">
                        <p class="font-bold">{{ refund.payment?.receipt_number }} - {{ refund.patient?.full_name }}</p>
                        <p class="text-sm text-slate-500">{{ refund.currency }} {{ refund.amount_minor }} - {{ refund.status }} - {{ refund.reason }}</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="decideRefund(refund, 'approve')">Approve</button>
                            <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="decideRefund(refund, 'reject')">Reject</button>
                            <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="processRefund(refund)">Process</button>
                        </div>
                    </article>
                    <p v-if="refunds.length === 0" class="p-4 text-sm text-slate-500">No refund requests.</p>
                </div>
            </section>
        </div>

        <section class="mt-6 rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 p-4 dark:border-slate-800">
                <h2 class="font-black">Cashier Shifts</h2>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                <article v-for="shift in shifts.data" :key="shift.id" class="grid gap-3 p-4 md:grid-cols-[1fr_auto]">
                    <div>
                        <p class="font-bold">{{ shift.cashier?.full_name }} - {{ shift.facility?.name }}</p>
                        <p class="text-sm text-slate-500">{{ shift.currency }} expected {{ shift.expected_cash_minor }} - counted {{ shift.counted_cash_minor ?? 'open' }} - variance {{ shift.variance_minor ?? 'open' }}</p>
                    </div>
                    <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="reviewShift(shift)">Review</button>
                </article>
            </div>
        </section>

        <section class="mt-6 rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 p-4 dark:border-slate-800">
                <h2 class="font-black">Payments</h2>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                <article v-for="payment in payments" :key="payment.id" class="grid gap-3 p-4 md:grid-cols-[1fr_auto]">
                    <div>
                        <Link class="font-bold text-red-800" :href="`/admin/payments/${payment.id}/receipt`">{{ payment.receipt_number }}</Link>
                        <p class="text-sm text-slate-500">{{ payment.patient?.full_name }} - {{ payment.method?.name }} - {{ payment.currency }} {{ payment.amount_minor }} - {{ payment.status }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="requestRefund(payment)">Refund</button>
                        <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="reversePayment(payment)">Reverse</button>
                    </div>
                </article>
            </div>
        </section>
    </AppLayout>
</template>
