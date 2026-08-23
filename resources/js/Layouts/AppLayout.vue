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
const permissions = computed(() => page.props.auth.permissions || []);
const hospital = computed(() => page.props.auth.hospital);
const facilities = computed(() => page.props.auth.facilities || []);
const defaultFacility = computed(() => facilities.value.find((facility) => facility.is_default) || facilities.value[0]);
const isSuperadmin = computed(() => roles.value.includes('superadmin'));
const isAdmin = computed(() => ['admin', 'hospital-admin', 'superadmin'].some((role) => roles.value.includes(role)));

function can(permission) {
    return isSuperadmin.value || permissions.value.includes(permission);
}

const navItems = computed(() => [
    { label: 'Dashboard', href: '/dashboard', show: true },
    { label: 'Admin', href: '/admin/dashboard', show: isAdmin.value && can('hospital.view') },
    { label: 'Hospital', href: '/admin/hospital', show: can('hospital.view') },
    { label: 'Facilities', href: '/admin/facilities', show: can('facilities.view') },
    { label: 'Departments', href: '/admin/departments', show: can('departments.view') },
    { label: 'Staff', href: '/admin/staff', show: can('staff.view') },
    { label: 'Patients', href: '/admin/patients', show: can('patients.view') },
    { label: 'Roles', href: '/admin/roles', show: can('roles.view') },
    { label: 'Settings', href: '/admin/settings', show: can('settings.manage') },
    { label: 'Numbering', href: '/admin/numbering', show: can('numbering.manage') },
    { label: 'Audit Logs', href: '/admin/audit-logs', show: can('audit.view') },
    { label: 'Public Website', href: '/admin/public-website', show: can('website.view') },
    { label: 'Profile', href: '/profile', show: true },
]);

function logout() {
    router.post('/logout');
}
</script>

<template>
    <div class="min-h-screen bg-slate-100 dark:bg-slate-950">
        <aside class="fixed inset-y-0 left-0 hidden w-64 border-r border-slate-200 bg-white p-5 lg:block dark:border-slate-800 dark:bg-slate-900">
            <Link href="/dashboard" class="text-xl font-bold text-red-800 dark:text-red-300">HMS</Link>
            <nav class="mt-8 space-y-1 text-sm">
                <Link v-for="item in navItems.filter((entry) => entry.show)" :key="item.href" :href="item.href" class="block rounded-md px-3 py-2 hover:bg-slate-100 dark:hover:bg-slate-800">{{ item.label }}</Link>
            </nav>
        </aside>

        <div class="lg:pl-64">
            <header class="border-b border-slate-200 bg-white px-4 py-4 dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between gap-4">
                    <button class="lg:hidden" type="button" @click="open = !open">Menu</button>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-500">Workspace</p>
                        <h1 class="text-xl font-semibold">{{ title }}</h1>
                        <p v-if="hospital || defaultFacility" class="mt-1 text-xs text-slate-500">
                            <span v-if="hospital">{{ hospital.display_name }}</span>
                            <span v-if="hospital && defaultFacility"> · </span>
                            <span v-if="defaultFacility">{{ defaultFacility.name }}</span>
                        </p>
                    </div>
                    <div class="flex items-center gap-3 text-sm">
                        <span class="hidden sm:inline">{{ user?.full_name }}</span>
                        <button class="rounded-md border border-slate-300 px-3 py-2 dark:border-slate-700" type="button" @click="logout">Logout</button>
                    </div>
                </div>
                <nav v-if="open" class="mt-4 space-y-1 text-sm lg:hidden">
                    <Link v-for="item in navItems.filter((entry) => entry.show)" :key="item.href" :href="item.href" class="block py-2">{{ item.label }}</Link>
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
