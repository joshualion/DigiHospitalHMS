<script setup>
import ThemeSwitcher from '@/Components/Public/ThemeSwitcher.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Activity, Bed, Building2, ClipboardList, FileClock, FlaskConical, Gauge, Menu, PackageSearch, PanelLeftClose, PanelLeftOpen, Pill, ScanLine, Settings, ShieldCheck, ShoppingCart, Stethoscope, UserCog, UsersRound, WalletCards, X } from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';

defineProps({
    title: {
        type: String,
        default: 'Dashboard',
    },
});

const STORAGE_KEY = 'admin-sidebar-collapsed';
const page = usePage();
const mobileOpen = ref(false);
const collapsed = ref(false);
const user = computed(() => page.props.auth.user);
const roles = computed(() => page.props.auth.roles || []);
const permissions = computed(() => page.props.auth.permissions || []);
const hospital = computed(() => page.props.auth.hospital);
const facilities = computed(() => page.props.auth.facilities || []);
const defaultFacility = computed(() => facilities.value.find((facility) => facility.is_default) || facilities.value[0]);
const isSuperadmin = computed(() => roles.value.includes('superadmin'));
const isAdmin = computed(() => ['admin', 'hospital-admin', 'superadmin'].some((role) => roles.value.includes(role)));
const sidebarCompact = computed(() => collapsed.value && !mobileOpen.value);
const themeDefaults = { appearance: 'system', accent: 'calm', allowedAccents: ['calm', 'healing', 'alert', 'blood', 'seagrass'], switcherVisible: true };

function can(permission) {
    return isSuperadmin.value || permissions.value.includes(permission);
}

function isActive(href) {
    return href === '/dashboard' ? page.url === href : page.url.startsWith(href);
}

const navItems = computed(() => [
    { label: 'Dashboard', href: '/dashboard', icon: Gauge, show: true },
    { label: 'Admin', href: '/admin/dashboard', icon: Activity, show: isAdmin.value && can('hospital.view') },
    { label: 'Hospital', href: '/admin/hospital', icon: Building2, show: can('hospital.view') },
    { label: 'Facilities', href: '/admin/facilities', icon: Building2, show: can('facilities.view') },
    { label: 'Departments', href: '/admin/departments', icon: ClipboardList, show: can('departments.view') },
    { label: 'Staff', href: '/admin/staff', icon: UserCog, show: can('staff.view') },
    { label: 'Patients', href: '/admin/patients', icon: UsersRound, show: can('patients.view') },
    { label: 'Appointments', href: '/admin/appointments', icon: ClipboardList, show: can('appointments.view') },
    { label: 'Queues', href: '/admin/queues', icon: Activity, show: can('queues.view') },
    { label: 'Clinical', href: '/admin/clinical/worklist', icon: Stethoscope, show: can('encounters.view') },
    { label: 'Admissions', href: '/admin/admissions', icon: Bed, show: can('admissions.view') },
    { label: 'Billing', href: '/admin/billing/invoices', icon: FileClock, show: can('invoices.view') },
    { label: 'Payments', href: '/admin/payments/workbench', icon: WalletCards, show: can('payments.view') },
    { label: 'Laboratory', href: '/admin/laboratory/requests', icon: FlaskConical, show: can('lab.requests.view') },
    { label: 'Radiology', href: '/admin/radiology/requests', icon: ScanLine, show: can('radiology.requests.view') },
    { label: 'Inventory', href: '/admin/inventory/stock', icon: PackageSearch, show: can('inventory.view') },
    { label: 'Procurement', href: '/admin/procurement', icon: ShoppingCart, show: can('procurement.view') },
    { label: 'Pharmacy', href: '/admin/pharmacy/prescriptions', icon: Pill, show: can('prescriptions.view') },
    { label: 'Roles', href: '/admin/roles', icon: ShieldCheck, show: can('roles.view') },
    { label: 'Settings', href: '/admin/settings', icon: Settings, show: can('settings.manage') },
    { label: 'Numbering', href: '/admin/numbering', icon: FileClock, show: can('numbering.manage') },
    { label: 'Audit Logs', href: '/admin/audit-logs', icon: FileClock, show: can('audit.view') },
    { label: 'Public Website', href: '/admin/public-website', icon: Stethoscope, show: can('website.view') },
    { label: 'Profile', href: '/profile', icon: UserCog, show: true },
]);

function logout() {
    router.post('/logout');
}

function toggleSidebar() {
    collapsed.value = !collapsed.value;
}

onMounted(() => {
    collapsed.value = window.localStorage.getItem(STORAGE_KEY) === 'true';
});

watch(collapsed, (value) => {
    window.localStorage.setItem(STORAGE_KEY, value ? 'true' : 'false');
});
</script>

<template>
    <div class="admin-theme min-h-screen antialiased">
        <div v-if="mobileOpen" class="fixed inset-0 z-40 bg-slate-950/60 backdrop-blur-sm lg:hidden" @click="mobileOpen = false"></div>

        <aside
            class="fixed inset-y-0 left-0 z-50 flex flex-col border-r p-3 transition-all duration-200 lg:translate-x-0"
            :class="[sidebarCompact ? 'w-[5.25rem]' : 'w-72', mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0']"
            style="background: var(--admin-sidebar); border-color: var(--admin-sidebar-border); color: var(--admin-text);"
        >
            <div class="flex items-center justify-between gap-2 px-2 py-2">
                <Link href="/dashboard" class="flex min-w-0 items-center gap-3 rounded-md">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl font-black" style="background: var(--public-accent); color: var(--public-accent-foreground);">H</span>
                    <span v-if="!sidebarCompact" class="min-w-0">
                        <span class="block truncate text-lg font-black">HMS</span>
                        <span class="block truncate text-xs font-semibold" style="color: var(--admin-text-muted);">{{ hospital?.display_name || 'Hospital workspace' }}</span>
                    </span>
                </Link>
                <button class="grid h-9 w-9 place-items-center rounded-md border lg:hidden" style="border-color: var(--admin-border);" type="button" aria-label="Close sidebar" @click="mobileOpen = false">
                    <X class="h-4 w-4" />
                </button>
            </div>

            <button class="mt-3 hidden h-10 items-center justify-center gap-2 rounded-md border text-sm font-bold lg:flex" style="border-color: var(--admin-border); color: var(--admin-text-muted);" type="button" :aria-label="collapsed ? 'Expand sidebar' : 'Collapse sidebar'" @click="toggleSidebar">
                <component :is="collapsed ? PanelLeftOpen : PanelLeftClose" class="h-4 w-4" />
                <span v-if="!sidebarCompact">Collapse</span>
            </button>

            <nav class="mt-5 flex-1 space-y-1 text-sm font-bold" aria-label="Admin navigation">
                <Link
                    v-for="item in navItems.filter((entry) => entry.show)"
                    :key="item.href"
                    :href="item.href"
                    class="admin-nav-link rounded-md px-3 py-2"
                    :class="{ 'admin-nav-link-active': isActive(item.href), 'justify-center': sidebarCompact }"
                    :title="sidebarCompact ? item.label : null"
                    @click="mobileOpen = false"
                >
                    <component :is="item.icon" class="h-4 w-4 shrink-0" />
                    <span v-if="!sidebarCompact" class="truncate">{{ item.label }}</span>
                </Link>
            </nav>

            <div class="border-t pt-4" style="border-color: var(--admin-border);">
                <div class="flex items-center gap-2" :class="sidebarCompact ? 'justify-center' : 'justify-between'">
                    <ThemeSwitcher :defaults="themeDefaults" />
                    <button v-if="!sidebarCompact" class="rounded-md border px-3 py-2 text-sm font-bold" style="border-color: var(--admin-border);" type="button" @click="logout">Logout</button>
                </div>
            </div>
        </aside>

        <div class="transition-all duration-200" :class="collapsed ? 'lg:pl-[5.25rem]' : 'lg:pl-72'">
            <header class="sticky top-0 z-30 border-b backdrop-blur-xl" style="background: color-mix(in srgb, var(--public-header) 86%, transparent); border-color: var(--admin-border);">
                <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4">
                    <div class="flex min-w-0 items-center gap-3">
                        <button class="grid h-10 w-10 place-items-center rounded-md border" style="border-color: var(--admin-border);" type="button" aria-label="Open sidebar" @click="mobileOpen = true">
                            <Menu class="h-5 w-5" />
                        </button>
                        <div class="min-w-0">
                            <p class="text-xs font-black uppercase tracking-wide" style="color: var(--public-accent);">Workspace</p>
                            <h1 class="truncate text-xl font-black">{{ title }}</h1>
                            <p v-if="hospital || defaultFacility" class="mt-1 truncate text-xs" style="color: var(--admin-text-muted);">
                                <span v-if="hospital">{{ hospital.display_name }}</span>
                                <span v-if="hospital && defaultFacility"> · </span>
                                <span v-if="defaultFacility">{{ defaultFacility.name }}</span>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 text-sm">
                        <ThemeSwitcher :defaults="themeDefaults" />
                        <span class="hidden font-semibold sm:inline">{{ user?.full_name }}</span>
                        <button class="hidden rounded-md border px-3 py-2 font-bold sm:inline-flex" style="border-color: var(--admin-border);" type="button" @click="logout">Logout</button>
                    </div>
                </div>
            </header>

            <div v-if="$page.props.flash.status || $page.props.flash.success" class="mx-auto max-w-7xl px-4 pt-4">
                <div class="rounded-md border px-4 py-3 text-sm font-semibold" style="background: var(--public-accent-soft); border-color: var(--public-accent); color: var(--admin-text);">
                    {{ $page.props.flash.success || $page.props.flash.status }}
                </div>
            </div>

            <main class="mx-auto max-w-7xl px-4 py-6">
                <slot />
            </main>
        </div>
    </div>
</template>
