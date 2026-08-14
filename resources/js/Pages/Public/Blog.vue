<script setup>
import { Head } from '@inertiajs/vue3';
import PublicLayout from '../../Layouts/PublicLayout.vue';
</script>

<template>
    <Head title="Blog" />
    <PublicLayout>
        <section class="mx-auto max-w-5xl px-4 py-16">
            <h1 class="text-3xl font-bold">Blog</h1>
            <p class="mt-4 text-slate-700 dark:text-slate-300">Blog and news content is static during Phase 0. The CMS is deferred and should not be treated as implemented.</p>
        </section>
    </PublicLayout>
</template>
