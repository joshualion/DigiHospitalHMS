<script setup>
import ActionToolbar from '@/Components/Admin/ActionToolbar.vue';
import ConfirmDialog from '@/Components/Admin/ConfirmDialog.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({ admissions: { type: Array, default: () => [] }, tasks: { type: Array, default: () => [] } });
const chartForm = useForm({});
const chartTarget = ref(null);
function fullName(patient) { return [patient?.first_name, patient?.middle_name, patient?.last_name].filter(Boolean).join(' '); }
function openChart(admission) { if (admission.chart) { router.get(`/admin/inpatient/charts/${admission.chart.id}`); return; } chartTarget.value = admission; }
function createChart() { chartForm.post(`/admin/inpatient/admissions/${chartTarget.value.id}/chart`, { preserveScroll: true, onSuccess: () => { chartTarget.value = null; } }); }
</script>

<template>
    <Head title="Ward Charts" />
    <AppLayout title="Ward Charts">
        <PageHeader title="Ward Charts" description="Active inpatient admissions and order worklist." />
        <div class="grid gap-6">
            <section class="min-w-0 rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="font-black">Active Admissions</h2>
                <div class="mt-4 divide-y divide-slate-100 dark:divide-slate-800">
                    <article v-for="admission in admissions" :key="admission.id" class="grid min-w-0 gap-3 py-3 md:grid-cols-[1fr_auto]">
                        <div class="min-w-0">
                            <p class="break-words font-bold">{{ admission.admission_number }} - {{ fullName(admission.patient) }}</p>
                            <p class="break-words text-sm text-slate-500">{{ admission.ward?.name || 'Ward pending' }} - {{ admission.bed?.label || 'Bed pending' }}</p>
                            <p v-if="admission.patient?.allergies?.length" class="mt-1 break-words text-xs font-bold text-red-700">Allergies: {{ admission.patient.allergies.map((item) => item.substance).join(', ') }}</p>
                            <p v-if="admission.patient?.alerts?.length" class="break-words text-xs font-bold text-amber-700">Alerts: {{ admission.patient.alerts.map((item) => item.title).join(', ') }}</p>
                        </div>
                        <PrimaryButton type="button" @click="openChart(admission)">{{ admission.chart ? 'Open chart' : 'Create chart' }}</PrimaryButton>
                    </article>
                    <p v-if="admissions.length === 0" class="py-4 text-sm text-slate-500">No active admissions.</p>
                </div>
            </section>
            <section class="min-w-0 rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="font-black">Order Worklist</h2>
                <div class="mt-4 divide-y divide-slate-100 text-sm dark:divide-slate-800">
                    <p v-for="task in tasks" :key="task.id" class="break-words py-2"><strong>{{ task.order_type }}</strong> - {{ task.status }}<br><span class="text-slate-500">{{ task.chart?.patient?.hospital_number }} {{ fullName(task.chart?.patient) }}</span></p>
                    <p v-if="tasks.length === 0" class="py-4 text-sm text-slate-500">No active order tasks.</p>
                </div>
            </section>
        </div>
        <ConfirmDialog :show="Boolean(chartTarget)" title="Create inpatient chart" message="Create a ward chart for this admission?" confirm-label="Create chart" :form="chartForm" @close="chartTarget = null" @confirm="createChart" />
    </AppLayout>
</template>
