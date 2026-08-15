<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    site: {
        type: Object,
        default: () => ({
            hospital: { display_name: 'HMS' },
            navigation: [
                { label: 'Home', href: '/' },
                { label: 'About', href: '/about' },
                { label: 'Services', href: '/services' },
                { label: 'Doctors', href: '/doctors' },
                { label: 'Contact', href: '/contact' },
            ],
        }),
    },
    preview: { type: Boolean, default: false },
});

const page = usePage();
const open = ref(false);
const user = computed(() => page.props.auth.user);
const shell = computed(() => props.site?.shell || props.site || {});
const utility = computed(() => shell.value.utility || {});
const footer = computed(() => shell.value.footer || {});
const navigation = computed(() => shell.value.navigation || []);
const socialLinks = computed(() => utility.value.social_links || []);
const contact = computed(() => props.site?.contact || {});

function isActive(href) {
    return href === '/' ? page.url === '/' : page.url.startsWith(href);
}
</script>

<template>
    <div class="min-h-screen bg-white text-slate-950 antialiased">
        <div v-if="preview" class="bg-amber-100 px-4 py-2 text-center text-sm font-semibold text-amber-950">
            Preview mode. Draft content is visible only to authorized users.
        </div>

        <div v-if="utility.visible !== false" class="border-b border-slate-200 bg-slate-950 text-white">
            <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-2 text-xs sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
                <div class="flex flex-wrap gap-x-5 gap-y-1">
                    <span v-if="utility.phone">Phone: {{ utility.phone }}</span>
                    <span v-if="utility.emergency_phone" class="font-semibold text-rose-200">Emergency: {{ utility.emergency_phone }}</span>
                    <span v-if="utility.email">{{ utility.email }}</span>
                    <span v-if="utility.hours">{{ utility.hours }}</span>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a v-for="link in socialLinks" :key="link.url" :href="link.url" class="hover:text-cyan-200" rel="noreferrer" target="_blank">{{ link.label }}</a>
                    <span v-if="utility.location">{{ utility.location }}</span>
                </div>
            </div>
        </div>

        <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 shadow-sm backdrop-blur">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                <Link href="/" class="flex items-center gap-3">
                    <span class="grid h-11 w-11 place-items-center rounded-md bg-teal-700 text-lg font-black text-white">H</span>
                    <span>
                        <span class="block text-base font-bold leading-tight text-slate-950">{{ site.hospital?.display_name || 'Hospital' }}</span>
                        <span class="block text-xs font-medium uppercase tracking-wide text-teal-700">Care and hospital services</span>
                    </span>
                </Link>

                <nav class="hidden items-center gap-1 text-sm font-semibold lg:flex">
                    <Link
                        v-for="item in navigation"
                        :key="item.href"
                        :href="item.href"
                        class="rounded-md px-3 py-2 transition hover:bg-slate-100 hover:text-teal-800"
                        :class="isActive(item.href) ? 'bg-teal-50 text-teal-800' : 'text-slate-700'"
                    >
                        {{ item.label }}
                    </Link>
                </nav>

                <div class="hidden items-center gap-3 lg:flex">
                    <Link href="/appointment" class="rounded-md bg-rose-700 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-rose-800">Appointment Info</Link>
                    <Link :href="user ? '/dashboard' : '/login'" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-800 hover:border-teal-700 hover:text-teal-800">
                        {{ user ? 'Dashboard' : 'Login' }}
                    </Link>
                </div>

                <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold lg:hidden" type="button" @click="open = !open">
                    Menu
                </button>
            </div>

            <nav v-if="open" class="border-t border-slate-200 bg-white px-4 py-3 text-sm font-semibold lg:hidden">
                <Link v-for="item in navigation" :key="item.href" :href="item.href" class="block rounded-md px-3 py-2" @click="open = false">{{ item.label }}</Link>
                <Link href="/appointment" class="mt-2 block rounded-md bg-rose-700 px-3 py-2 text-white" @click="open = false">Appointment Info</Link>
                <Link :href="user ? '/dashboard' : '/login'" class="block rounded-md px-3 py-2" @click="open = false">{{ user ? 'Dashboard' : 'Login' }}</Link>
            </nav>
        </header>

        <main>
            <slot />
        </main>

        <footer class="bg-slate-950 text-white">
            <div class="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 md:grid-cols-[1.5fr_1fr_1fr] lg:px-8">
                <div>
                    <div class="flex items-center gap-3">
                        <span class="grid h-11 w-11 place-items-center rounded-md bg-teal-600 text-lg font-black">H</span>
                        <span class="text-lg font-bold">{{ site.hospital?.display_name || 'Hospital' }}</span>
                    </div>
                    <p class="mt-4 max-w-md text-sm leading-6 text-slate-300">
                        {{ footer.summary || 'A configurable public hospital website managed from the administration area.' }}
                    </p>
                </div>
                <div>
                    <h2 class="text-sm font-bold uppercase tracking-wide text-cyan-200">Explore</h2>
                    <div class="mt-4 grid gap-2 text-sm text-slate-300">
                        <Link v-for="item in navigation" :key="`footer-${item.href}`" :href="item.href" class="hover:text-white">{{ item.label }}</Link>
                    </div>
                </div>
                <div>
                    <h2 class="text-sm font-bold uppercase tracking-wide text-cyan-200">Contact</h2>
                    <div class="mt-4 space-y-2 text-sm text-slate-300">
                        <p v-if="contact.address">{{ contact.address }}</p>
                        <p v-if="contact.phone">{{ contact.phone }}</p>
                        <p v-if="contact.email">{{ contact.email }}</p>
                        <p v-if="contact.hours">{{ contact.hours }}</p>
                    </div>
                </div>
            </div>
            <div class="border-t border-white/10 px-4 py-5 text-center text-xs text-slate-400">
                {{ footer.copyright || `Copyright ${new Date().getFullYear()} ${site.hospital?.display_name || 'Hospital'}. All rights reserved.` }}
            </div>
        </footer>
    </div>
</template>
