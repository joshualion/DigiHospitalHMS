<script setup>
import { HeartPulse } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps({
    name: { type: String, default: 'Hospital' },
    tagline: { type: String, default: '' },
    logoPath: { type: String, default: '' },
    context: { type: String, default: 'header' },
});

const resolvedTagline = computed(() => props.tagline || '');
const isFooter = computed(() => props.context === 'footer');
const contextClass = computed(() => (isFooter.value ? 'public-brand-footer' : 'public-brand-header'));
const logoSrc = computed(() => {
    if (!props.logoPath) return '';
    if (/^https?:\/\//.test(props.logoPath) || props.logoPath.startsWith('/')) return props.logoPath;
    return `/${props.logoPath.replace(/^\/+/, '')}`;
});
</script>

<template>
    <span class="public-brand" :class="contextClass">
        <span v-if="logoSrc" class="public-brand-icon-shell overflow-hidden border" style="border-color: var(--public-border); background: var(--public-surface-elevated);">
            <img :src="logoSrc" :alt="`${name} logo`" class="h-full w-full object-cover">
        </span>
        <span v-else class="public-brand-icon-shell" aria-hidden="true">
            <span class="public-brand-icon-core"></span>
            <HeartPulse class="public-brand-icon" />
        </span>
        <span class="public-brand-copy">
            <span class="public-brand-wordmark">{{ name }}</span>
            <span v-if="resolvedTagline" class="public-brand-tagline-row">
                <span class="public-brand-tagline-mark" aria-hidden="true"></span>
                <span class="public-brand-tagline">{{ resolvedTagline }}</span>
            </span>
        </span>
    </span>
</template>
