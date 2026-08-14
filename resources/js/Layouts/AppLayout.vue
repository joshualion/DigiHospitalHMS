<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineProps({
    title: {
        type: String,
        default: 'Dashboard',
    },
});

const page = usePage();
const open = ref(false);
const user = computed(() => page.props.auth.user);
const roles = computed(() => page.props.auth.roles || []);
const isAdmin = computed(() => roles.value.includes('admin') || roles.value.includes('superadmin'));

function logout() {
    router.post('/logout');
}
</script>

<template>
    <div class="min-h-screen bg-slate-100 dark:bg-slate-950">
        <aside class="fixed inset-y-0 left-0 hidden w-64 border-r border-slate-200 bg-white p-5 lg:block dark:border-slate-800 dark:bg-slate-900">
            <Link href="/dashboard" class="text-xl font-bold text-red-800 dark:text-red-300">HMS</Link>
            <nav class="mt-8 space-y-1 text-sm">
                <Link href="/dashboard" class="block rounded-md px-3 py-2 hover:bg-slate-100 dark:hover:bg-slate-800">Dashboard</Link>
                <Link v-if="isAdmin" href="/admin/dashboard" class="block rounded-md px-3 py-2 hover:bg-slate-100 dark:hover:bg-slate-800">Admin</Link>
                <Link v-if="isAdmin" href="/admin/users" class="block rounded-md px-3 py-2 hover:bg-slate-100 dark:hover:bg-slate-800">Users</Link>
                <Link v-if="isAdmin" href="/admin/roles" class="block rounded-md px-3 py-2 hover:bg-slate-100 dark:hover:bg-slate-800">Roles</Link>
                <Link v-if="isAdmin" href="/admin/pages" class="block rounded-md px-3 py-2 hover:bg-slate-100 dark:hover:bg-slate-800">CMS Pages</Link>
                <Link href="/profile" class="block rounded-md px-3 py-2 hover:bg-slate-100 dark:hover:bg-slate-800">Profile</Link>
            </nav>
        </aside>

        <div class="lg:pl-64">
            <header class="border-b border-slate-200 bg-white px-4 py-4 dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between gap-4">
                    <button class="lg:hidden" type="button" @click="open = !open">Menu</button>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-500">Workspace</p>
                        <h1 class="text-xl font-semibold">{{ title }}</h1>
                    </div>
                    <div class="flex items-center gap-3 text-sm">
                        <span class="hidden sm:inline">{{ user?.full_name }}</span>
                        <button class="rounded-md border border-slate-300 px-3 py-2 dark:border-slate-700" type="button" @click="logout">Logout</button>
                    </div>
                </div>
                <nav v-if="open" class="mt-4 space-y-1 text-sm lg:hidden">
                    <Link href="/dashboard" class="block py-2">Dashboard</Link>
                    <Link v-if="isAdmin" href="/admin/dashboard" class="block py-2">Admin</Link>
                    <Link href="/profile" class="block py-2">Profile</Link>
                </nav>
            </header>

            <div v-if="$page.props.flash.status || $page.props.flash.success" class="mx-auto max-w-6xl px-4 pt-4">
                <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ $page.props.flash.success || $page.props.flash.status }}
                </div>
            </div>

            <main class="mx-auto max-w-6xl px-4 py-6">
                <slot />
            </main>
        </div>
    </div>
</template>
