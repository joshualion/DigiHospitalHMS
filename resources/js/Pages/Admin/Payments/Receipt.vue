<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    payment: { type: Object, required: true },
});
</script>

<template>
    <Head :title="payment.receipt_number" />
    <AppLayout :title="payment.receipt_number">
        <div class="mb-4 flex flex-wrap justify-between gap-3 print:hidden">
            <Link href="/admin/payments/workbench" class="text-sm font-bold text-red-800">Back to workbench</Link>
            <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="window.print()">Print receipt</button>
        </div>

        <section class="mx-auto max-w-3xl rounded-lg border border-slate-200 bg-white p-6 text-slate-950 print:border-0 print:shadow-none">
            <div class="flex items-start justify-between gap-4 border-b border-slate-200 pb-4">
                <div>
                    <p class="text-xs font-black uppercase text-slate-500">Receipt</p>
                    <h1 class="text-2xl font-black">{{ payment.receipt_number }}</h1>
                    <p class="text-sm text-slate-500">{{ payment.posted_at }}</p>
                </div>
                <div class="text-right">
                    <p class="font-black">{{ payment.currency }} {{ payment.amount_minor }}</p>
                    <p class="text-sm text-slate-500">{{ payment.method?.name }} - {{ payment.status }}</p>
                </div>
            </div>

            <div class="grid gap-4 py-4 md:grid-cols-2">
                <div>
                    <p class="text-xs font-black uppercase text-slate-500">Patient</p>
                    <p class="font-bold">{{ payment.patient?.full_name }}</p>
                    <p class="text-sm text-slate-500">{{ payment.patient?.hospital_number }}</p>
                </div>
                <div>
                    <p class="text-xs font-black uppercase text-slate-500">Cashier</p>
                    <p class="font-bold">{{ payment.cashier?.full_name }}</p>
                </div>
            </div>

            <div class="border-t border-slate-200 pt-4">
                <h2 class="font-black">Allocations</h2>
                <article v-for="allocation in payment.allocations" :key="allocation.id" class="mt-3 flex justify-between gap-4 text-sm">
                    <span>{{ allocation.invoice?.invoice_number }} - {{ allocation.status }}</span>
                    <strong>{{ payment.currency }} {{ allocation.amount_minor }}</strong>
                </article>
                <p v-if="payment.allocations.length === 0" class="mt-3 text-sm text-slate-500">Unallocated patient credit.</p>
            </div>

            <div class="mt-6 grid gap-3 border-t border-slate-200 pt-4 text-sm md:grid-cols-3">
                <p>Allocated: <strong>{{ payment.allocated_minor }}</strong></p>
                <p>Unallocated: <strong>{{ payment.unallocated_minor }}</strong></p>
                <p>Refunded: <strong>{{ payment.refunded_minor }}</strong></p>
            </div>
        </section>
    </AppLayout>
</template>
