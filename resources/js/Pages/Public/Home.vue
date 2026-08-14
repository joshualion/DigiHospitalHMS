<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '../../Layouts/PublicLayout.vue';
</script>

<template>
    <Head title="Home" />
    <PublicLayout>
        <section class="bg-slate-900 px-4 py-20 text-white">
            <div class="mx-auto grid max-w-7xl gap-10 lg:grid-cols-[1.1fr_0.9fr] lg:items-center">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-red-300">Hospital Management Solution</p>
                    <h1 class="mt-4 text-4xl font-bold leading-tight sm:text-5xl">Reliable foundation for hospital operations.</h1>
                    <p class="mt-5 max-w-2xl text-lg text-slate-200">This Phase 0 interface is a marketing and application shell. Clinical, appointment, billing, pharmacy, laboratory, inventory, admission, and blood-bank workflows are not implemented yet.</p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <Link href="/about" class="rounded-md bg-red-700 px-5 py-3 font-semibold text-white">Learn more</Link>
                        <Link href="/login" class="rounded-md border border-white/40 px-5 py-3 font-semibold text-white">Staff login</Link>
                    </div>
                </div>
                <div class="rounded-lg border border-white/15 bg-white/10 p-6">
                    <h2 class="text-xl font-semibold">Current status</h2>
                    <ul class="mt-4 space-y-3 text-sm text-slate-200">
                        <li>Authentication and admin shell foundation.</li>
                        <li>Public pages are informational only.</li>
                        <li>Hospital-domain workflows begin after Phase 0 approval.</li>
                    </ul>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
