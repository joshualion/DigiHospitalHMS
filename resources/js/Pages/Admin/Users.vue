<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
</script>

<template>
    <Head title="Users" />
    <AppLayout title="Users">
        <div class="rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-lg font-semibold">User administration deferred</h2>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">The route is protected and boot-safe. Staff onboarding and role assignment screens belong to Phase 1.</p>
        </div>
    </AppLayout>
</template>
