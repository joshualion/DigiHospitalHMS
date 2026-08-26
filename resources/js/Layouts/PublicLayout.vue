<script setup>
import PublicBrandMark from '@/Components/Public/PublicBrandMark.vue';
import ThemeSwitcher from '@/Components/Public/ThemeSwitcher.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { LogIn, Menu, X } from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

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
const closeButton = ref(null);
const user = computed(() => page.props.auth.user);
const shell = computed(() => props.site?.shell || props.site || {});
const footer = computed(() => shell.value.footer || {});
const navigation = computed(() => shell.value.navigation || []);
const contact = computed(() => props.site?.contact || {});
const themeDefaults = computed(() => shell.value.theme || { appearance: 'system', accent: 'calm', allowedAccents: ['calm', 'healing', 'alert', 'blood', 'seagrass'], switcherVisible: true });
const hospitalName = computed(() => props.site.hospital?.display_name || 'Hospital');
const hospitalTagline = computed(() => props.site.hospital?.tagline || props.site.branding?.tagline || '');
const hospitalLogoPath = computed(() => props.site.hospital?.logo_url || props.site.hospital?.logo_path || '');
const footerCopyright = computed(() => (footer.value.copyright || `Copyright {year} ${hospitalName.value}. All rights reserved.`).replace('{year}', new Date().getFullYear()));
const footerBadges = computed(() => footer.value.badges || []);
const hasFooterSummary = computed(() => Boolean(footer.value.summary));
const hasFooterContact = computed(() => Boolean(contact.value.address || contact.value.phone || contact.value.email || contact.value.hours));

function isActive(href) {
    return href === '/' ? page.url === '/' : page.url.startsWith(href);
}

function closeMenu() {
    open.value = false;
}

function onKeydown(event) {
    if (event.key === 'Escape') closeMenu();
}

watch(open, async (value) => {
    document.body.style.overflow = value ? 'hidden' : '';
    if (value) {
        await nextTick();
        closeButton.value?.focus();
    }
});

onBeforeUnmount(() => {
    document.body.style.overflow = '';
});
</script>

<template>
    <div class="public-theme min-h-screen antialiased" @keydown="onKeydown">
        <a href="#main-content" class="public-focus sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[80] focus:rounded-full focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-black focus:text-slate-950">Skip to content</a>

        <div v-if="preview" class="px-4 py-2 text-center text-sm font-bold" style="background: var(--public-accent-soft); color: var(--public-text);">
            Preview mode. Draft content is visible only to authorized users.
        </div>

        <header class="sticky top-0 z-50 border-b backdrop-blur-xl" style="background: var(--public-header); border-color: var(--public-border);">
            <div class="public-container flex min-h-[84px] items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                <Link href="/" class="public-focus flex min-w-0 flex-1 rounded-2xl lg:max-w-[30rem] xl:max-w-[36rem]">
                    <PublicBrandMark :name="hospitalName" :tagline="hospitalTagline" :logo-path="hospitalLogoPath" />
                </Link>

                <nav class="hidden items-center justify-center gap-1 text-sm font-bold lg:flex" aria-label="Primary navigation">
                    <Link v-for="item in navigation" :key="item.href" :href="item.href" class="public-focus rounded-full px-4 py-2 transition" :style="isActive(item.href) ? 'background: var(--public-accent-soft); color: var(--public-accent);' : 'color: var(--public-text-secondary);'">
                        {{ item.label }}
                    </Link>
                </nav>

                <div class="hidden items-center gap-3 lg:flex">
                    <ThemeSwitcher :defaults="themeDefaults" />
                    <Link :href="user ? '/dashboard' : '/login'" class="public-focus btn-public-secondary"><LogIn class="h-4 w-4" aria-hidden="true" />{{ user ? 'Dashboard' : 'Login' }}</Link>
                </div>

                <div class="flex items-center gap-2 lg:hidden">
                    <ThemeSwitcher :defaults="themeDefaults" />
                    <button class="public-focus grid h-11 w-11 place-items-center rounded-full border" style="border-color: var(--public-border); color: var(--public-text);" type="button" aria-label="Open menu" :aria-expanded="open" @click="open = true">
                        <Menu class="h-5 w-5" aria-hidden="true" />
                    </button>
                </div>
            </div>
        </header>

        <div v-if="open" class="fixed inset-0 z-[70] bg-black/50 lg:hidden" @click="closeMenu"></div>
        <aside v-if="open" class="fixed inset-y-0 right-0 z-[80] flex w-full max-w-sm flex-col border-l p-5 shadow-2xl lg:hidden" style="background: var(--public-surface-elevated); border-color: var(--public-border); color: var(--public-text);" aria-label="Mobile navigation">
            <div class="flex items-center justify-between gap-3">
                <span class="text-lg font-black">Menu</span>
                <button ref="closeButton" class="public-focus grid h-11 w-11 place-items-center rounded-full border" style="border-color: var(--public-border);" type="button" aria-label="Close menu" @click="closeMenu"><X class="h-5 w-5" aria-hidden="true" /></button>
            </div>
            <nav class="mt-8 grid gap-2 text-base font-bold">
                <Link v-for="item in navigation" :key="item.href" :href="item.href" class="public-focus rounded-2xl px-4 py-3" :style="isActive(item.href) ? 'background: var(--public-accent-soft); color: var(--public-accent);' : ''" @click="closeMenu">{{ item.label }}</Link>
                <Link :href="user ? '/dashboard' : '/login'" class="public-focus btn-public-secondary" @click="closeMenu">{{ user ? 'Dashboard' : 'Login' }}</Link>
            </nav>
            <div class="mt-8 border-t pt-6" style="border-color: var(--public-border);">
                <p class="mb-3 text-sm font-black">Theme</p>
                <ThemeSwitcher :defaults="themeDefaults" />
            </div>
        </aside>

        <main id="main-content">
            <slot />
        </main>

        <footer class="relative overflow-hidden" style="background: var(--public-footer); color: var(--public-footer-text);">
            <div class="absolute inset-0 opacity-20" style="background: radial-gradient(circle at 20% 0%, var(--public-accent), transparent 30%);"></div>
                <div class="public-container relative grid gap-10 px-4 py-16 sm:px-6 md:grid-cols-[1.4fr_0.8fr_1fr] lg:px-8">
                <div>
                    <div class="max-w-md">
                        <PublicBrandMark :name="hospitalName" :tagline="hospitalTagline" :logo-path="hospitalLogoPath" context="footer" />
                    </div>
                    <p v-if="hasFooterSummary" class="mt-5 max-w-md text-sm leading-7 text-white/72">{{ footer.summary }}</p>
                    <div v-if="footerBadges.length" class="mt-6 flex flex-wrap gap-2 text-xs font-bold uppercase tracking-wide text-white/55">
                        <span v-for="badge in footerBadges" :key="badge">{{ badge }}</span>
                    </div>
                </div>
                <div v-if="hasFooterContact">
                    <h2 class="text-sm font-black uppercase tracking-wide" style="color: var(--public-accent);">Explore</h2>
                    <div class="mt-4 grid gap-2 text-sm text-white/72">
                        <Link v-for="item in navigation" :key="`footer-${item.href}`" :href="item.href" class="public-focus hover:text-white">{{ item.label }}</Link>
                    </div>
                </div>
                <div>
                    <h2 class="text-sm font-black uppercase tracking-wide" style="color: var(--public-accent);">Contact</h2>
                    <div class="mt-4 space-y-3 text-sm leading-6 text-white/72">
                        <p v-if="contact.address">{{ contact.address }}</p>
                        <p v-if="contact.phone">{{ contact.phone }}</p>
                        <p v-if="contact.email">{{ contact.email }}</p>
                        <p v-if="contact.hours">{{ contact.hours }}</p>
                    </div>
                </div>
            </div>
            <div class="relative border-t px-4 py-5 text-center text-xs text-white/50" style="border-color: rgba(255,255,255,0.1);">
                {{ footerCopyright }}
            </div>
        </footer>
    </div>
</template>
