<script setup>
import { CalendarDays, Clock, MapPin, Phone } from '@lucide/vue';
import { Link } from '@inertiajs/vue3';

defineProps({ items: { type: Array, default: () => [] } });
const icons = { phone: Phone, clock: Clock, 'map-pin': MapPin, calendar: CalendarDays };
</script>
<template>
    <section v-if="items.length" class="relative z-10 -mt-28 px-4 sm:px-6 lg:px-8">
        <div class="public-container relative">
            <div class="pointer-events-none absolute left-1/2 top-0 h-10 w-10 -translate-x-1/2 -translate-y-5 rotate-45 rounded-md" style="background: var(--public-surface-elevated);"></div>
            <div class="relative grid overflow-hidden rounded-[2rem] border shadow-2xl sm:grid-cols-2 xl:grid-cols-4" style="background: var(--public-surface-elevated); border-color: var(--public-border); box-shadow: var(--public-shadow);">
                <article v-for="item in items" :key="item.heading" class="group border-b p-6 text-center transition hover:-translate-y-0.5 sm:border-r xl:border-b-0 lg:p-8" style="border-color: var(--public-border);">
                    <div class="mx-auto grid h-12 w-12 place-items-center rounded-2xl transition group-hover:scale-105" style="background: var(--public-accent-soft); color: var(--public-accent);">
                        <component :is="icons[item.icon] || CalendarDays" class="h-6 w-6" aria-hidden="true" />
                    </div>
                    <h2 class="mt-4 text-lg font-black" style="color: var(--public-text);">{{ item.heading }}</h2>
                    <p class="mt-2 text-sm leading-6" style="color: var(--public-text-secondary);">{{ item.text }}</p>
                    <Link
                        v-if="item.link_label && item.url"
                        :href="item.url"
                        class="public-focus mt-5 inline-flex min-h-[40px] items-center justify-center rounded-full border px-4 py-2 text-sm font-black shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md active:translate-y-0"
                        style="background: var(--public-accent-soft); border-color: var(--public-border); color: var(--public-link);"
                    >
                        {{ item.link_label }}
                    </Link>
                </article>
            </div>
        </div>
    </section>
</template>
