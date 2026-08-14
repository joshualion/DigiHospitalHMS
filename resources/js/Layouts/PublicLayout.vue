<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const page = usePage();
const open = ref(false);
const user = computed(() => page.props.auth.user);

const links = [
    ['Home', '/'],
    ['About', '/about'],
    ['Doctors', '/doctor'],
    ['Appointment', '/appointment'],
    ['Blog', '/blog'],
    ['Contact', '/contact'],
    ['Policies', '/policies'],
];
</script>

<template>
    <div class="min-h-screen bg-white text-slate-900 dark:bg-slate-950 dark:text-slate-100">
        <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur dark:border-slate-800 dark:bg-slate-950/95">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                <Link href="/" class="text-xl font-bold text-red-800 dark:text-red-300">HMS</Link>
                <nav class="hidden items-center gap-5 text-sm font-medium md:flex">
                    <Link v-for="[label, href] in links" :key="href" :href="href" class="hover:text-red-800">{{ label }}</Link>
                    <Link v-if="user" href="/dashboard" class="rounded-md border border-slate-300 px-3 py-2">Dashboard</Link>
                    <Link v-else href="/login" class="rounded-md bg-red-800 px-3 py-2 text-white">Login</Link>
                </nav>
                <button class="md:hidden" type="button" @click="open = !open">Menu</button>
            </div>
            <nav v-if="open" class="border-t border-slate-200 px-4 py-3 md:hidden dark:border-slate-800">
                <Link v-for="[label, href] in links" :key="href" :href="href" class="block py-2" @click="open = false">{{ label }}</Link>
                <Link :href="user ? '/dashboard' : '/login'" class="block py-2" @click="open = false">{{ user ? 'Dashboard' : 'Login' }}</Link>
            </nav>
        </header>

        <main>
            <slot />
        </main>

        <footer class="border-t border-slate-200 bg-slate-50 px-4 py-8 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300">
            <div class="mx-auto flex max-w-7xl flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <p>Hospital Management Solution foundation.</p>
                <p>Mon - Fri, 8:00 AM - 6:00 PM</p>
            </div>
        </footer>
    </div>
</template>
