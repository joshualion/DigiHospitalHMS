<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';

defineProps({
    admissions: { type: Array, default: () => [] },
    tasks: { type: Array, default: () => [] },
});

function fullName(patient) {
    return [patient?.first_name, patient?.middle_name, patient?.last_name].filter(Boolean).join(' ');
}

function openChart(admission) {
    if (admission.chart) {
        router.get(`/admin/inpatient/charts/${admission.chart.id}`);
        return;
    }
    router.post(`/admin/inpatient/admissions/${admission.id}/chart`);
}
</script>

<template>
    <Head title="Ward Charts" />
    <AppLayout title="Ward Charts">
        <div class="grid gap-6 xl:grid-cols-[1fr_360px]">
            <section class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="font-black">Active Admissions</h2>
                <div class="mt-4 divide-y divide-slate-100 dark:divide-slate-800">
                    <article v-for="admission in admissions" :key="admission.id" class="grid gap-3 py-3 md:grid-cols-[1fr_auto]">
                        <div>
                            <p class="font-bold">{{ admission.admission_number }} - {{ fullName(admission.patient) }}</p>
                            <p class="text-sm text-slate-500">{{ admission.ward?.name || 'Ward pending' }} - {{ admission.bed?.label || 'Bed pending' }}</p>
                            <p v-if="admission.patient?.allergies?.length" class="mt-1 text-xs font-bold text-red-700">Allergies: {{ admission.patient.allergies.map((item) => item.substance).join(', ') }}</p>
                            <p v-if="admission.patient?.alerts?.length" class="text-xs font-bold text-amber-700">Alerts: {{ admission.patient.alerts.map((item) => item.title).join(', ') }}</p>
                        </div>
                        <PrimaryButton type="button" @click="openChart(admission)">{{ admission.chart ? 'Open chart' : 'Create chart' }}</PrimaryButton>
                    </article>
                </div>
            </section>

            <aside class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="font-black">Order Worklist</h2>
                <div class="mt-4 divide-y divide-slate-100 text-sm dark:divide-slate-800">
                    <p v-for="task in tasks" :key="task.id" class="py-2">
                        <strong>{{ task.order_type }}</strong> - {{ task.status }}<br>
                        <span class="text-slate-500">{{ task.chart?.patient?.hospital_number }} {{ fullName(task.chart?.patient) }}</span>
                    </p>
                </div>
            </aside>
        </div>
    </AppLayout>
</template>
