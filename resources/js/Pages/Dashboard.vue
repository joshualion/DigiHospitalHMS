<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';

const props = defineProps({
    dashboard: { type: Object, required: true },
});

const maxRegistration = computed(() => Math.max(1, ...(props.dashboard.trends.find((trend) => trend.name === 'Registrations')?.values || [0])));
const registrations = computed(() => props.dashboard.trends.find((trend) => trend.name === 'Registrations'));
</script>

<template>
    <Head title="Hospital Dashboard" />
    <AppLayout title="Hospital Dashboard">
        <div class="space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-bold">Hospital overview</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Live operational figures, restricted to your hospital and access permissions.</p>
                </div>
                <Link href="/profile" class="rounded-lg border px-4 py-2 text-sm font-semibold hover:bg-slate-100 dark:hover:bg-slate-800">My profile</Link>
            </div>

            <div v-if="!dashboard.hasHospital" class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                No hospital is assigned to this account. Contact your administrator to configure your workspace.
            </div>

            <div v-if="dashboard.cards.length" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <Link v-for="card in dashboard.cards" :key="card.label" :href="card.href" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-red-300 hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ card.label }}</div>
                    <div class="mt-3 break-words text-3xl font-extrabold tabular-nums">{{ card.value }}</div>
                    <div class="mt-4 text-xs font-medium text-red-600 dark:text-red-400">Open module →</div>
                </Link>
            </div>
            <div v-else-if="dashboard.hasHospital" class="rounded-xl border p-5 text-sm text-slate-500 dark:border-slate-800">
                Your account has no operational metric permissions. Use the navigation to access your assigned work.
            </div>

            <section v-if="registrations" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div>
                    <h3 class="text-lg font-bold">Patient registrations</h3>
                    <p class="text-sm text-slate-500">Last seven calendar days · hospital-wide</p>
                </div>
                <div class="mt-6 grid h-48 grid-cols-7 items-end gap-2 sm:gap-4" role="img" aria-label="Patient registrations over the last seven days">
                    <div v-for="(value, index) in registrations.values" :key="index" class="flex h-full min-w-0 flex-col items-center justify-end gap-2">
                        <span class="text-xs font-semibold tabular-nums">{{ value }}</span>
                        <div class="w-full max-w-16 rounded-t-md bg-red-600/85" :style="{ height: Math.max(3, value / maxRegistration * 130) + 'px' }"></div>
                        <span class="text-xs text-slate-500">{{ dashboard.days[index] }}</span>
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h3 class="font-bold">Workspace guidance</h3>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">Figures use saved operational records, not demo values. Available modules and summary metrics depend on your assigned role. Refresh the page for the latest snapshot.</p>
            </section>
        </div>
    </AppLayout>
</template>
