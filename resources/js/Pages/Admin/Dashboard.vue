<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
</script>

<template>
    <Head title="Admin Dashboard" />
    <AppLayout title="Admin Dashboard">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <Link href="/admin/hospital" class="rounded-lg border border-slate-200 bg-white p-5 hover:border-red-300 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="font-semibold">Hospital Profile</h2>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Legal identity, contact details, status and defaults.</p>
            </Link>
            <Link href="/admin/facilities" class="rounded-lg border border-slate-200 bg-white p-5 hover:border-red-300 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="font-semibold">Facilities</h2>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Branches, primary facility, and active status.</p>
            </Link>
            <Link href="/admin/staff" class="rounded-lg border border-slate-200 bg-white p-5 hover:border-red-300 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="font-semibold">Staff And Access</h2>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Staff profiles, roles, and facility memberships.</p>
            </Link>
        </div>
    </AppLayout>
</template>
