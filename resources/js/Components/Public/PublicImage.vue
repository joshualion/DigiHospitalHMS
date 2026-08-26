<script setup>
import { ImageOff } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    src: { type: String, default: '' },
    alt: { type: String, default: '' },
    width: { type: [String, Number], default: null },
    height: { type: [String, Number], default: null },
    loading: { type: String, default: 'lazy' },
    fetchpriority: { type: String, default: null },
    sizes: { type: String, default: null },
    imgClass: { type: String, default: 'h-full w-full object-cover' },
    fallbackClass: { type: String, default: '' },
    showFallbackLabel: { type: Boolean, default: true },
});

const failed = ref(false);

const source = computed(() => (props.src || '').trim());
const hasImage = computed(() => Boolean(source.value) && !failed.value);
const fallbackLabel = computed(() => props.alt || 'Image unavailable');
const srcset = computed(() => {
    if (!source.value || !source.value.startsWith('/frontend/images/slider/')) return null;

    const match = source.value.match(/^\/frontend\/images\/slider\/(.+)\.(jpe?g|png|webp)$/i);
    if (!match) return null;

    const [, name] = match;
    const maxWidths = { 1: 1280, 2: 768, 222: 1280, 3: 1280, slide1: 1280 };
    if (!maxWidths[name]) return null;

    const maxWidth = maxWidths[name] || 1280;

    return [480, 768, 1280]
        .filter((size) => size <= maxWidth)
        .map((size) => `/frontend/images/slider/responsive/${name}-${size}.webp ${size}w`)
        .join(', ');
});

watch(source, () => {
    failed.value = false;
});
</script>

<template>
    <img
        v-if="hasImage"
        :src="source"
        :srcset="srcset || undefined"
        :sizes="sizes || undefined"
        :alt="alt"
        :class="imgClass"
        :width="width || undefined"
        :height="height || undefined"
        :loading="loading"
        :fetchpriority="fetchpriority || undefined"
        decoding="async"
        @error="failed = true"
    >
    <div
        v-else
        class="grid min-h-full place-items-center overflow-hidden"
        :class="fallbackClass"
        role="img"
        :aria-label="fallbackLabel"
        style="background: linear-gradient(135deg, var(--public-accent-soft), var(--public-surface-elevated)); color: var(--public-text-secondary);"
    >
        <div class="grid gap-3 p-6 text-center">
            <span class="mx-auto grid h-12 w-12 place-items-center rounded-2xl" style="background: var(--public-surface); color: var(--public-accent);">
                <ImageOff class="h-6 w-6" aria-hidden="true" />
            </span>
            <span v-if="showFallbackLabel" class="text-sm font-bold">{{ fallbackLabel }}</span>
        </div>
    </div>
</template>
