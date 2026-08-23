<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({
    openShift: { type: Object, default: null },
    issuedInvoices: { type: Array, default: () => [] },
    recentPayments: { type: Array, default: () => [] },
    patients: { type: Array, default: () => [] },
    facilities: { type: Array, default: () => [] },
    paymentMethods: { type: Array, default: () => [] },
    currency: { type: String, default: 'NGN' },
});

const defaultFacilityId = computed(() => props.openShift?.facility_id || props.facilities[0]?.id || '');
const cashMethod = computed(() => props.paymentMethods.find((method) => method.type === 'cash'));
const firstMethodId = computed(() => cashMethod.value?.id || props.paymentMethods[0]?.id || '');

const shiftForm = useForm({ facility_id: defaultFacilityId.value, currency: props.currency, opening_float_minor: 0 });
const closeForm = useForm({ counted_cash_minor: props.openShift?.expected_cash_minor || 0 });
const paymentForm = useForm({
    facility_id: defaultFacilityId.value,
    patient_id: '',
    payment_method_id: firstMethodId.value,
    currency: props.currency,
    amount_minor: '',
    idempotency_key: crypto.randomUUID(),
    reference_data: {},
    allocations: [],
    notes: '',
});

const selectedInvoice = computed(() => props.issuedInvoices.find((invoice) => invoice.id === Number(paymentForm.allocations[0]?.invoice_id)));

watch(selectedInvoice, (invoice) => {
    if (!invoice) return;
    paymentForm.patient_id = invoice.patient_id;
    paymentForm.currency = invoice.currency;
    if (!paymentForm.amount_minor) paymentForm.amount_minor = invoice.balance_minor;
    paymentForm.allocations[0].amount_minor = Math.min(Number(paymentForm.amount_minor || 0), invoice.balance_minor);
});

function addAllocation() {
    paymentForm.allocations = [{ invoice_id: '', amount_minor: '' }];
}

function clearAllocation() {
    paymentForm.allocations = [];
}

function postPayment() {
    paymentForm.post('/admin/payments', {
        preserveScroll: true,
        onSuccess: () => {
            paymentForm.reset();
            paymentForm.idempotency_key = crypto.randomUUID();
            paymentForm.facility_id = defaultFacilityId.value;
            paymentForm.payment_method_id = firstMethodId.value;
            paymentForm.currency = props.currency;
            paymentForm.allocations = [];
        },
    });
}
</script>

<template>
    <Head title="Cashier Workbench" />
    <AppLayout title="Cashier Workbench">
        <div class="grid gap-6 xl:grid-cols-[1fr_360px]">
            <section class="space-y-6">
                <div class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-black">Open Shift</h2>
                            <p class="text-sm text-slate-500" v-if="openShift">Active at {{ openShift.facility?.name }} with expected cash {{ openShift.currency }} {{ openShift.expected_cash_minor }} minor units.</p>
                            <p class="text-sm text-slate-500" v-else>Open a shift before accepting cash.</p>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-black" :class="openShift ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">{{ openShift ? 'Open' : 'No open shift' }}</span>
                    </div>
                    <form v-if="!openShift" class="mt-4 grid gap-3 md:grid-cols-4" @submit.prevent="shiftForm.post('/admin/cashier-shifts')">
                        <select v-model="shiftForm.facility_id" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950">
                            <option value="">Facility</option>
                            <option v-for="facility in facilities" :key="facility.id" :value="facility.id">{{ facility.name }}</option>
                        </select>
                        <TextInput id="shift_currency" v-model="shiftForm.currency" label="Currency" maxlength="3" />
                        <TextInput id="opening_float" v-model="shiftForm.opening_float_minor" label="Opening float minor units" type="number" />
                        <PrimaryButton :disabled="shiftForm.processing">Open shift</PrimaryButton>
                    </form>
                    <form v-else class="mt-4 grid gap-3 md:grid-cols-[1fr_auto]" @submit.prevent="closeForm.patch(`/admin/cashier-shifts/${openShift.id}/close`)">
                        <TextInput id="counted_cash" v-model="closeForm.counted_cash_minor" label="Counted cash minor units" type="number" />
                        <PrimaryButton :disabled="closeForm.processing">Close shift</PrimaryButton>
                    </form>
                </div>

                <form class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="postPayment">
                    <h2 class="text-lg font-black">Post Payment</h2>
                    <div class="mt-4 grid gap-3 md:grid-cols-2">
                        <select v-model="paymentForm.facility_id" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950">
                            <option value="">Facility</option>
                            <option v-for="facility in facilities" :key="facility.id" :value="facility.id">{{ facility.name }}</option>
                        </select>
                        <select v-model="paymentForm.payment_method_id" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950">
                            <option value="">Payment method</option>
                            <option v-for="method in paymentMethods" :key="method.id" :value="method.id">{{ method.name }}</option>
                        </select>
                        <select v-model="paymentForm.patient_id" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950">
                            <option value="">Patient</option>
                            <option v-for="patient in patients" :key="patient.id" :value="patient.id">{{ patient.hospital_number }} - {{ patient.full_name }}</option>
                        </select>
                        <TextInput id="payment_amount" v-model="paymentForm.amount_minor" label="Amount minor units" type="number" />
                        <TextInput id="payment_currency" v-model="paymentForm.currency" label="Currency" maxlength="3" />
                        <TextInput id="payment_reference" v-model="paymentForm.reference_data.reference" label="Reference" />
                    </div>
                    <div class="mt-5 rounded-md border border-slate-200 p-4 dark:border-slate-800">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="font-black">Invoice allocation</h3>
                            <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="paymentForm.allocations.length ? clearAllocation() : addAllocation()">{{ paymentForm.allocations.length ? 'Use as deposit' : 'Allocate invoice' }}</button>
                        </div>
                        <div v-if="paymentForm.allocations.length" class="mt-3 grid gap-3 md:grid-cols-2">
                            <select v-model="paymentForm.allocations[0].invoice_id" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950">
                                <option value="">Invoice</option>
                                <option v-for="invoice in issuedInvoices" :key="invoice.id" :value="invoice.id">{{ invoice.invoice_number }} - {{ invoice.patient?.full_name }} - balance {{ invoice.balance_minor }}</option>
                            </select>
                            <TextInput id="allocation_amount" v-model="paymentForm.allocations[0].amount_minor" label="Allocation amount" type="number" />
                        </div>
                        <p v-else class="mt-3 text-sm text-slate-500">Leaving allocation empty records unallocated patient credit for later allocation.</p>
                    </div>
                    <textarea v-model="paymentForm.notes" class="mt-4 w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950" rows="2" placeholder="Notes"></textarea>
                    <PrimaryButton class="mt-4" :disabled="paymentForm.processing">Post payment</PrimaryButton>
                </form>
            </section>

            <aside class="rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 p-4 dark:border-slate-800">
                    <h2 class="font-black">Recent Payments</h2>
                </div>
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    <Link v-for="payment in recentPayments" :key="payment.id" class="block p-4" :href="`/admin/payments/${payment.id}/receipt`">
                        <p class="font-bold">{{ payment.receipt_number }}</p>
                        <p class="text-sm text-slate-500">{{ payment.patient?.full_name }} - {{ payment.method?.name }} - {{ payment.currency }} {{ payment.amount_minor }}</p>
                        <p class="text-xs font-bold uppercase text-slate-500">{{ payment.status }} - unallocated {{ payment.unallocated_minor }}</p>
                    </Link>
                    <p v-if="recentPayments.length === 0" class="p-4 text-sm text-slate-500">No payments posted yet.</p>
                </div>
            </aside>
        </div>
    </AppLayout>
</template>
