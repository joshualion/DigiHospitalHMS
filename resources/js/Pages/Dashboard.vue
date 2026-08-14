<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
</script>

<template>
    <Head title="Dashboard" />
    <AppLayout title="Dashboard">
        <div class="rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-lg font-semibold">Application foundation</h2>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Hospital workspaces will be added after Phase 0. No clinical, appointment, billing, pharmacy, laboratory, inventory, admission, or blood-bank workflows are active.</p>
        </div>
    </AppLayout>
</template>
