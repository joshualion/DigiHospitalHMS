<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    filters: { type: Object, required: true },
    summary: { type: Object, required: true },
    daily: { type: Array, default: () => [] },
});

const filters = reactive({ from: props.filters.from, to: props.filters.to });

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
            <div class="mt-3 flex flex-wrap gap-2">
                <a :href="exportUrl('operations')" class="rounded-md border px-3 py-2 text-sm font-bold">Export operations CSV</a>
                <a :href="exportUrl('clinical')" class="rounded-md border px-3 py-2 text-sm font-bold">Export clinical CSV</a>
                <a :href="exportUrl('financial')" class="rounded-md border px-3 py-2 text-sm font-bold">Export financial CSV</a>
            </div>
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
