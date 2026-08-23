<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import PrimaryButton from '../../../Components/PrimaryButton.vue';

const props = defineProps({
    invoices: { type: Object, required: true },
    patients: { type: Array, default: () => [] },
    visits: { type: Array, default: () => [] },
    encounters: { type: Array, default: () => [] },
    facilities: { type: Array, default: () => [] },
});

const form = useForm({ patient_id: '', facility_id: '', visit_id: '', clinical_encounter_id: '', currency: 'NGN' });
</script>

<template>
    <Head title="Invoices" />
    <AppLayout title="Invoices">
        <div class="grid gap-6 xl:grid-cols-[1fr_360px]">
            <section class="rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 p-4 dark:border-slate-800">
                    <h2 class="text-lg font-black">Patient Invoices</h2>
                </div>
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    <Link v-for="invoice in invoices.data" :key="invoice.id" class="grid gap-3 p-4 md:grid-cols-[1fr_160px]" :href="`/admin/billing/invoices/${invoice.id}`">
                        <div>
                            <p class="font-bold">{{ invoice.invoice_number || 'Draft invoice' }} · {{ invoice.patient?.full_name }}</p>
                            <p class="text-sm text-slate-500">{{ invoice.patient?.hospital_number }} · {{ invoice.currency }} {{ invoice.total_minor }} minor units</p>
                        </div>
                        <span class="h-fit w-fit rounded-full bg-slate-100 px-2 py-1 text-xs font-bold text-slate-700">{{ invoice.status }}</span>
                    </Link>
                    <p v-if="invoices.data.length === 0" class="p-4 text-sm text-slate-500">No invoices found.</p>
                </div>
            </section>

            <form class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="form.post('/admin/billing/invoices')">
                <h2 class="font-black">Create Draft Invoice</h2>
                <div class="mt-4 grid gap-3">
                    <select v-model="form.patient_id" class="rounded-md border-slate-300"><option value="">Patient</option><option v-for="patient in patients" :key="patient.id" :value="patient.id">{{ patient.hospital_number }} · {{ patient.full_name }}</option></select>
                    <select v-model="form.facility_id" class="rounded-md border-slate-300"><option value="">Facility</option><option v-for="facility in facilities" :key="facility.id" :value="facility.id">{{ facility.name }}</option></select>
                    <select v-model="form.visit_id" class="rounded-md border-slate-300"><option value="">Visit</option><option v-for="visit in visits" :key="visit.id" :value="visit.id">Visit #{{ visit.id }} · {{ visit.status }}</option></select>
                    <select v-model="form.clinical_encounter_id" class="rounded-md border-slate-300"><option value="">Encounter</option><option v-for="encounter in encounters" :key="encounter.id" :value="encounter.id">Encounter #{{ encounter.id }} · {{ encounter.status }}</option></select>
                    <input v-model="form.currency" class="rounded-md border-slate-300" maxlength="3" placeholder="Currency">
                    <PrimaryButton :disabled="form.processing">Create draft</PrimaryButton>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
