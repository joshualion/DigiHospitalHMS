<script setup>
import { Link } from '@inertiajs/vue3';
defineProps({ page: Object, eyebrow: String, title: String, summary: String });
</script>
<template>
    <section class="relative overflow-hidden px-4 py-20 text-center sm:px-6 lg:px-8" style="background: var(--public-footer); color: var(--public-footer-text);">
        <div class="absolute inset-0 opacity-30" style="background: radial-gradient(circle at 50% 0%, var(--public-accent-soft), transparent 45%);"></div>
        <div class="public-container relative">
            <Link href="/" class="public-focus inline-flex rounded-full border border-white/20 px-4 py-2 text-sm font-bold text-white/80">Home</Link>
            <p class="mt-8 text-sm font-black uppercase tracking-[0.18em]" style="color: var(--public-accent);">{{ eyebrow || page?.slug }}</p>
            <h1 class="mx-auto mt-4 max-w-4xl text-4xl font-black tracking-tight text-white sm:text-5xl lg:text-6xl">{{ title || page?.title }}</h1>
            <p v-if="summary" class="mx-auto mt-5 max-w-2xl text-lg leading-8 text-white/80">{{ summary }}</p>
        </div>
    </section>
</template>