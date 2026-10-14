<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import { Head, router } from '@inertiajs/vue3';
import { reactive, ref, computed } from 'vue';

const props = defineProps({
    filters: { type: Object, required: true },
    summary: { type: Object, required: true },
    daily: { type: Array, default: () => [] },
    trends: { type: Object, default: () => ({}) },
    can_export: { type: Boolean, default: false },
});

const filters = reactive({ from: props.filters.from, to: props.filters.to });
const selectedMetric = ref('visits');
const metricOptions = [
    { value: 'visits', label: 'Visits', mode: 'count', href: '/admin/clinical/worklist' },
    { value: 'admissions', label: 'Admissions', mode: 'count', href: '/admin/admissions' },
    { value: 'lab_requests', label: 'Laboratory requests', mode: 'count', href: '/admin/laboratory/requests' },
    { value: 'radiology_requests', label: 'Radiology requests', mode: 'count', href: '/admin/radiology/requests' },
    { value: 'invoice_minor', label: 'Invoice value', mode: 'money', href: '/admin/billing/invoices' },
    { value: 'payment_minor', label: 'Payments received', mode: 'money', href: '/admin/payments/workbench' },
];
const metric = computed(() => metricOptions.find((option) => option.value === selectedMetric.value) || metricOptions[0]);
const chartValues = computed(() => props.trends[selectedMetric.value] || []);
const chartMax = computed(() => Math.max(1, ...chartValues.value.map((value) => Number(value) || 0)));
const chartTotal = computed(() => chartValues.value.reduce((sum, value) => sum + Number(value || 0), 0));
const chartLabel = computed(() => metric.value.mode === 'money' ? money(chartTotal.value) : chartTotal.value.toLocaleString('en-NG'));


function apply() {
    router.get('/admin/reports', filters, { preserveState: true, replace: true });
}

function money(minor, currency = 'NGN') {
    return new Intl.NumberFormat('en-NG', { style: 'currency', currency }).format(Number(minor || 0) / 100);
}

function exportUrl(type) {
    const params = new URLSearchParams({ type, from: filters.from, to: filters.to });
    return `/admin/reports/export?${params.toString()}`;
}

const cards = [
    ['Registered patients', 'registered_patients'],
    ['Visits', 'visits'],
    ['Admissions', 'admissions'],
    ['Active admissions', 'active_admissions'],
    ['Lab requests', 'lab_requests'],
    ['Radiology requests', 'radiology_requests'],
    ['Prescriptions', 'prescriptions'],
    ['Invoices issued', 'invoices_issued'],
    ['Payments posted', 'payments_posted'],
];
</script>

<template>
    <Head title="Reports" />
    <AppLayout title="Reports">
        <PageHeader title="Operational & Financial Reports" description="Review hospital activity, collections, clinical workload and receivables using live operational records." />

        <section class="rounded-lg border p-4" style="border-color: var(--admin-border); background: var(--admin-surface);">
            <div class="grid gap-3 md:grid-cols-[1fr_1fr_auto]">
                <label class="grid gap-1 text-sm font-semibold">From<input v-model="filters.from" type="date" class="rounded-md border p-2"></label>
                <label class="grid gap-1 text-sm font-semibold">To<input v-model="filters.to" type="date" class="rounded-md border p-2"></label>
                <button class="self-end rounded-md border px-4 py-2 font-bold" type="button" @click="apply">Apply</button>
            </div>
            <div v-if="can_export" class="mt-3 flex flex-wrap gap-2">
                <a :href="exportUrl('operations')" class="rounded-md border px-3 py-2 text-sm font-bold">Export operations CSV</a>
                <a :href="exportUrl('clinical')" class="rounded-md border px-3 py-2 text-sm font-bold">Export clinical CSV</a>
                <a :href="exportUrl('financial')" class="rounded-md border px-3 py-2 text-sm font-bold">Export financial CSV</a>
            </div>
        </section>

        <section class="mt-6 rounded-xl border p-5" style="border-color: var(--admin-border); background: var(--admin-surface);">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-black">Operational trends</h2>
                    <p class="mt-1 text-sm" style="color: var(--admin-text-muted);">Daily recorded events for the selected period. Figures reflect your hospital only.</p>
                </div>
                <label class="grid gap-1 text-sm font-bold">Display metric
                    <select v-model="selectedMetric" class="rounded-lg border p-2" style="background: var(--admin-surface);">
                        <option v-for="option in metricOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                </label>
            </div>
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                <p class="text-2xl font-black tabular-nums">{{ chartLabel }} <span class="text-xs font-medium" style="color: var(--admin-text-muted);">period total</span></p>
                <a :href="metric.href" class="rounded-lg border px-3 py-2 text-sm font-semibold">Open {{ metric.label.toLowerCase() }} module →</a>
            </div>
            <div class="mt-6 flex h-52 items-end gap-px overflow-hidden border-b pb-1" role="img" :aria-label="metric.label + ' daily trend chart; total ' + chartLabel">
                <div v-for="(value, index) in chartValues" :key="index" class="group relative h-full min-w-0 flex-1 flex flex-col justify-end" :title="daily[index]?.date + ': ' + (metric.mode === 'money' ? money(value) : value)">
                    <div class="min-h-[2px] w-full rounded-t-sm bg-teal-600 transition-colors hover:bg-teal-400" :style="{ height: Math.max(2, Number(value || 0) / chartMax * 190) + 'px' }"></div>
                </div>
            </div>
            <div class="mt-2 flex justify-between text-xs" style="color: var(--admin-text-muted);"><span>{{ daily[0]?.date }}</span><span>{{ daily[daily.length - 1]?.date }}</span></div>
            <p class="mt-3 text-xs" style="color: var(--admin-text-muted);">Hover or focus on individual days in the daily activity table below for exact figures. Currency charts use minor units internally and display NGN.</p>
        </section>

        <section class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
            <article v-for="[label, key] in cards" :key="key" class="rounded-lg border p-4" style="border-color: var(--admin-border); background: var(--admin-surface);">
                <p class="text-xs font-black uppercase tracking-wide" style="color: var(--admin-text-muted);">{{ label }}</p>
                <p class="mt-2 text-2xl font-black">{{ summary[key] || 0 }}</p>
            </article>
        </section>

        <section class="mt-6 grid gap-4 lg:grid-cols-4">
            <article class="rounded-lg border p-5" style="border-color: var(--admin-border); background: var(--admin-surface);">
                <p class="text-xs font-black uppercase" style="color: var(--admin-text-muted);">Invoice value</p>
                <p class="mt-2 text-xl font-black">{{ money(summary.invoice_value_minor) }}</p>
            </article>
            <article class="rounded-lg border p-5" style="border-color: var(--admin-border); background: var(--admin-surface);">
                <p class="text-xs font-black uppercase" style="color: var(--admin-text-muted);">Payments received</p>
                <p class="mt-2 text-xl font-black">{{ money(summary.payment_value_minor) }}</p>
            </article>
            <article class="rounded-lg border p-5" style="border-color: var(--admin-border); background: var(--admin-surface);">
                <p class="text-xs font-black uppercase" style="color: var(--admin-text-muted);">Outstanding invoices</p>
                <p class="mt-2 text-xl font-black">{{ money(summary.outstanding_invoice_minor) }}</p>
            </article>
            <article class="rounded-lg border p-5" style="border-color: var(--admin-border); background: var(--admin-surface);">
                <p class="text-xs font-black uppercase" style="color: var(--admin-text-muted);">Payer receivables</p>
                <p class="mt-2 text-xl font-black">{{ money(summary.claim_receivable_minor) }}</p>
            </article>
        </section>

        <section class="mt-6 overflow-hidden rounded-lg border" style="border-color: var(--admin-border); background: var(--admin-surface);">
            <div class="border-b p-4" style="border-color: var(--admin-border);">
                <h2 class="text-lg font-black">Daily Activity</h2>
                <p class="text-sm" style="color: var(--admin-text-muted);">One row per day in the selected reporting window.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr>
                            <th class="p-3 text-left">Date</th>
                            <th class="p-3 text-right">Visits</th>
                            <th class="p-3 text-right">Admissions</th>
                            <th class="p-3 text-right">Lab</th>
                            <th class="p-3 text-right">Radiology</th>
                            <th class="p-3 text-right">Rx</th>
                            <th class="p-3 text-right">Invoices</th>
                            <th class="p-3 text-right">Invoice value</th>
                            <th class="p-3 text-right">Payments</th>
                            <th class="p-3 text-right">Payment value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in daily" :key="row.date" class="border-t" style="border-color: var(--admin-border);">
                            <td class="p-3 font-bold">{{ row.date }}</td>
                            <td class="p-3 text-right">{{ row.visits }}</td>
                            <td class="p-3 text-right">{{ row.admissions }}</td>
                            <td class="p-3 text-right">{{ row.lab_requests }}</td>
                            <td class="p-3 text-right">{{ row.radiology_requests }}</td>
                            <td class="p-3 text-right">{{ row.prescriptions }}</td>
                            <td class="p-3 text-right">{{ row.invoices_count }}</td>
                            <td class="p-3 text-right">{{ money(row.invoice_minor) }}</td>
                            <td class="p-3 text-right">{{ row.payments_count }}</td>
                            <td class="p-3 text-right">{{ money(row.payment_minor) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AppLayout>
</template>
