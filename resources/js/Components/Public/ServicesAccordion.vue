<script setup>
import { ChevronDown, Stethoscope, Siren, HeartPulse } from '@lucide/vue';
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({ services: { type: Array, default: () => [] } });
const openSlug = ref(props.services[0]?.slug || null);
const columns = computed(() => [props.services.filter((_, index) => index % 2 === 0), props.services.filter((_, index) => index % 2 === 1)]);
const iconMap = { stethoscope: Stethoscope, siren: Siren, care: HeartPulse };
function toggle(service) { openSlug.value = openSlug.value === service.slug ? null : service.slug; }
function panelId(service) { return `service-panel-${service.slug}`; }
function buttonId(service) { return `service-button-${service.slug}`; }
function serviceContent(service) { return service.content || {}; }
</script>

<template>
    <div v-if="services.length" class="mx-auto mt-12 grid max-w-6xl gap-4 lg:grid-cols-2">
        <div v-for="(column, columnIndex) in columns" :key="columnIndex" class="grid content-start gap-4">
            <article v-for="service in column" :key="`${service.source || service.type}-${service.id}-${service.slug}`" class="public-card overflow-hidden rounded-3xl transition hover:-translate-y-0.5">
                <h3>
                    <button :id="buttonId(service)" type="button" class="public-focus flex w-full items-center gap-4 p-5 text-left" :aria-expanded="openSlug === service.slug" :aria-controls="panelId(service)" @click="toggle(service)">
                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl" style="background: var(--public-accent-soft); color: var(--public-accent);">
                            <component :is="iconMap[serviceContent(service).icon] || HeartPulse" class="h-6 w-6" aria-hidden="true" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-lg font-black" style="color: var(--public-text);">{{ service.title }}</span>
                            <span class="mt-1 block text-sm" style="color: var(--public-text-secondary);">{{ service.summary }}</span>
                        </span>
                        <ChevronDown class="h-5 w-5 shrink-0 transition" :class="openSlug === service.slug ? 'rotate-180' : ''" aria-hidden="true" />
                    </button>
                </h3>
                <div v-show="openSlug === service.slug" :id="panelId(service)" class="px-5 pb-5" role="region" :aria-labelledby="buttonId(service)">
                    <div class="rounded-2xl p-5" style="background: var(--public-accent-soft); color: var(--public-text);">
                        <p class="text-sm leading-7">{{ serviceContent(service).description || service.summary }}</p>
                        <Link v-if="serviceContent(service).cta_label && serviceContent(service).cta_url" :href="serviceContent(service).cta_url" class="public-focus public-link mt-4 inline-flex text-sm font-black">{{ serviceContent(service).cta_label }}</Link>
                    </div>
                </div>
            </article>
        </div>
    </div>
    <div v-else class="public-card mx-auto mt-10 max-w-2xl rounded-3xl p-8 text-center">
        <p class="font-bold" style="color: var(--public-text-secondary);">No published services are available yet.</p>
    </div>
</template>
