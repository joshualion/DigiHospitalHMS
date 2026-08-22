<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    media: { type: Array, default: () => [] },
    label: { type: String, default: 'Image' },
    altValue: { type: String, default: '' },
    canUpload: { type: Boolean, default: false },
    uploadForm: { type: Object, default: null },
});

const emit = defineEmits(['update:modelValue', 'update:altValue', 'upload']);
const open = ref(false);

const selected = computed(() => props.media.find((asset) => asset.url === props.modelValue || asset.path === props.modelValue));

function choose(asset) {
    emit('update:modelValue', asset.url);
    if (!props.altValue) emit('update:altValue', asset.alt_text || asset.title || '');
    open.value = false;
}
</script>

<template>
    <div class="rounded-md border border-slate-200 p-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-bold text-slate-950">{{ label }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ selected ? selected.title : modelValue || 'No media selected' }}</p>
            </div>
            <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold" type="button" @click="open = !open">
                {{ open ? 'Close library' : 'Choose media' }}
            </button>
        </div>

        <img v-if="modelValue" :src="modelValue" :alt="altValue || label" class="mt-3 h-36 w-full rounded-md object-cover">

        <label class="mt-3 block text-sm font-semibold">Alternative text
            <input :value="altValue" class="mt-1 w-full rounded-md border-slate-300" type="text" @input="emit('update:altValue', $event.target.value)">
        </label>

        <div v-if="open" class="mt-4 grid gap-3">
            <div class="grid max-h-80 gap-3 overflow-auto sm:grid-cols-2 lg:grid-cols-3">
                <button v-for="asset in media" :key="asset.id" class="rounded-md border border-slate-200 p-2 text-left hover:border-teal-600" type="button" @click="choose(asset)">
                    <img :src="asset.url" :alt="asset.alt_text" class="h-24 w-full rounded object-cover">
                    <span class="mt-2 block text-xs font-bold">{{ asset.title }}</span>
                    <span class="block text-xs text-slate-500">{{ asset.alt_text }}</span>
                </button>
                <p v-if="media.length === 0" class="text-sm text-slate-600">No uploaded media yet.</p>
            </div>

            <form v-if="canUpload && uploadForm" class="grid gap-3 rounded-md bg-slate-50 p-3" @submit.prevent="emit('upload')">
                <p class="text-sm font-bold">Upload new media</p>
                <input v-model="uploadForm.title" class="rounded-md border-slate-300 text-sm" type="text" placeholder="Title">
                <input v-model="uploadForm.alt_text" class="rounded-md border-slate-300 text-sm" type="text" placeholder="Alternative text">
                <input class="text-sm" type="file" accept="image/jpeg,image/png,image/webp" @input="uploadForm.image = $event.target.files[0]">
                <button class="rounded-md bg-teal-700 px-3 py-2 text-sm font-bold text-white disabled:opacity-60" type="submit" :disabled="uploadForm.processing">Upload</button>
                <p v-if="Object.keys(uploadForm.errors || {}).length" class="text-sm text-rose-700">Check the upload fields and try again.</p>
            </form>
        </div>
    </div>
</template>
