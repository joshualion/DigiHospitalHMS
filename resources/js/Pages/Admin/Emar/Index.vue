<script setup>
import PageHeader from '@/Components/Admin/PageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';

defineProps({ charts: { type: Array, default: () => [] }, doses: { type: Array, default: () => [] } });
function fullName(patient) { return [patient?.first_name, patient?.middle_name, patient?.last_name].filter(Boolean).join(' '); }
</script>

<template>
    <Head title="eMAR" />
    <AppLayout title="Medication Administration">
        <PageHeader title="Medication Administration" description="Ward medication charts and due medication worklist." />
        <div class="grid gap-6">
            <section class="min-w-0 rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="font-black">Ward Medication Charts</h2>
                <div class="mt-4 divide-y divide-slate-100 dark:divide-slate-800">
                    <article v-for="chart in charts" :key="chart.id" class="grid min-w-0 gap-3 py-3 md:grid-cols-[1fr_auto]"><div class="min-w-0"><p class="break-words font-bold">{{ chart.admission?.admission_number }} - {{ fullName(chart.patient) }}</p><p class="break-words text-sm text-slate-500">{{ chart.admission?.ward?.name }} - {{ chart.admission?.bed?.label }}</p><p v-if="chart.patient?.allergies?.length" class="break-words text-xs font-bold text-red-700">Allergies: {{ chart.patient.allergies.map((item) => item.substance).join(', ') }}</p><p v-if="chart.patient?.alerts?.length" class="break-words text-xs font-bold text-amber-700">Alerts: {{ chart.patient.alerts.map((item) => item.title).join(', ') }}</p></div><PrimaryButton type="button" @click="router.get(`/admin/emar/charts/${chart.id}`)">Open eMAR</PrimaryButton></article>
                    <p v-if="charts.length === 0" class="py-4 text-sm text-slate-500">No medication charts.</p>
                </div>
            </section>
            <section class="min-w-0 rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900"><h2 class="font-black">Due Medication</h2><div class="mt-4 divide-y divide-slate-100 text-sm dark:divide-slate-800"><p v-for="dose in doses" :key="dose.id" class="break-words py-2"><strong>{{ dose.medicine_name }}</strong> - {{ dose.due_state }}<br><span class="text-slate-500">{{ dose.scheduled_at || 'PRN' }} - {{ dose.dose }} {{ dose.route || '' }}</span></p><p v-if="doses.length === 0" class="py-4 text-sm text-slate-500">No due medication.</p></div></section>
        </div>
    </AppLayout>
</template>
