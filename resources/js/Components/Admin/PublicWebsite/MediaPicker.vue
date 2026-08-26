<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    media: { type: Array, default: () => [] },
    label: { type: String, default: 'Image' },
    altValue: { type: String, default: '' },
    canUpload: { type: Boolean, default: false },
    uploadForm: { type: Object, default: null },
});

const emit = defineEmits(['update:modelValue', 'update:altValue', 'upload', 'delete']);
const open = ref(false);
const pendingPreview = ref('');
const pendingFileName = ref('');
const fileInput = ref(null);
const brokenImages = ref({});

const selected = computed(() => props.media.find((asset) => asset.url === props.modelValue || asset.path === props.modelValue));

function choose(asset) {
    emit('update:modelValue', asset.url);
    if (!props.altValue) emit('update:altValue', asset.alt_text || asset.title || '');
    open.value = false;
}

function clearSelectedImage() {
    emit('update:modelValue', '');
    emit('update:altValue', '');
}

function revokePendingPreview() {
    if (pendingPreview.value) URL.revokeObjectURL(pendingPreview.value);
    pendingPreview.value = '';
    pendingFileName.value = '';
}

function clearPendingUpload() {
    revokePendingPreview();
    if (props.uploadForm) props.uploadForm.image = null;
    if (fileInput.value) fileInput.value.value = '';
}

function previewUpload(event) {
    const file = event.target.files?.[0] || null;
    revokePendingPreview();
    if (props.uploadForm) props.uploadForm.image = file;
    if (!file) return;
    pendingFileName.value = file.name;
    pendingPreview.value = URL.createObjectURL(file);
}

function uploadedHere(asset) {
    if (asset?.url) choose(asset);
    clearPendingUpload();
}

function markBroken(src) {
    if (src) brokenImages.value[src] = true;
}

onBeforeUnmount(revokePendingPreview);
</script>

<template>
    <div class="rounded-md border border-slate-200 p-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-bold text-slate-950">{{ label }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ selected ? selected.title : modelValue || 'No media selected' }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold" type="button" @click="open = !open">
                    {{ open ? 'Close library' : 'Choose media' }}
                </button>
                <button v-if="modelValue" class="rounded-md border border-rose-300 px-3 py-2 text-sm font-semibold text-rose-700" type="button" @click="clearSelectedImage">
                    Remove image
                </button>
            </div>
        </div>

        <div v-if="modelValue" class="mt-3 overflow-hidden rounded-md border border-slate-200">
            <img v-if="!brokenImages[modelValue]" :src="modelValue" :alt="altValue || label" class="h-36 w-full object-cover" @error="markBroken(modelValue)">
            <div v-else class="grid gap-3 bg-slate-50 px-3 py-8 text-center text-sm text-rose-700">
                <span>Image unavailable. Choose another image or remove this one.</span>
                <div class="flex flex-wrap justify-center gap-2">
                    <button class="rounded-md border border-rose-300 px-3 py-2 text-xs font-bold text-rose-700" type="button" @click="clearSelectedImage">Remove image from field</button>
                    <button v-if="selected && canUpload" class="rounded-md border border-rose-300 px-3 py-2 text-xs font-bold text-rose-700 disabled:cursor-not-allowed disabled:opacity-50" type="button" :disabled="selected.usage_count > 0" :title="selected.usage_count > 0 ? 'This media is still used. Remove it from fields and save first.' : 'Delete unavailable media from library'" @click="emit('delete', selected)">
                        Delete unavailable
                    </button>
                </div>
            </div>
        </div>
        <p v-else class="mt-3 rounded-md border border-dashed border-slate-300 px-3 py-6 text-center text-sm text-slate-500">No image selected for this field.</p>

        <label class="mt-3 block text-sm font-semibold">Alternative text
            <input :value="altValue" class="mt-1 w-full rounded-md border-slate-300" type="text" @input="emit('update:altValue', $event.target.value)">
        </label>

        <div v-if="open" class="mt-4 grid gap-3">
            <div class="grid max-h-80 gap-3 overflow-auto sm:grid-cols-2 lg:grid-cols-3">
                <article v-for="asset in media" :key="asset.id" class="rounded-md border border-slate-200 p-2">
                    <button class="block w-full text-left" type="button" @click="choose(asset)">
                        <img v-if="!brokenImages[asset.url]" :src="asset.url" :alt="asset.alt_text" class="h-24 w-full rounded object-cover" @error="markBroken(asset.url)">
                        <span v-else class="grid h-24 w-full place-items-center rounded bg-slate-50 px-2 text-center text-xs text-rose-700">Image unavailable</span>
                        <span class="mt-2 block text-xs font-bold">{{ asset.title }}</span>
                        <span class="block text-xs text-slate-500">{{ asset.alt_text }}</span>
                    </button>
                    <button v-if="brokenImages[asset.url] && canUpload" class="mt-2 w-full rounded-md border border-rose-300 px-2 py-1 text-xs font-bold text-rose-700 disabled:cursor-not-allowed disabled:opacity-50" type="button" :disabled="asset.usage_count > 0" :title="asset.usage_count > 0 ? 'This media is still used. Remove it from fields and save first.' : 'Delete unavailable media from library'" @click="emit('delete', asset)">
                        Delete unavailable
                    </button>
                </article>
                <p v-if="media.length === 0" class="text-sm text-slate-600">No uploaded media yet.</p>
            </div>

            <form v-if="canUpload && uploadForm" class="grid gap-3 rounded-md bg-slate-50 p-3" @submit.prevent="emit('upload', uploadedHere)">
                <p class="text-sm font-bold">Upload new media</p>
                <input v-model="uploadForm.title" class="rounded-md border-slate-300 text-sm" type="text" placeholder="Title">
                <input v-model="uploadForm.alt_text" class="rounded-md border-slate-300 text-sm" type="text" placeholder="Alternative text">
                <input ref="fileInput" class="text-sm" type="file" accept="image/jpeg,image/png,image/webp" @change="previewUpload">
                <div v-if="pendingPreview" class="rounded-md border border-slate-200 bg-white p-2">
                    <img :src="pendingPreview" :alt="pendingFileName" class="h-40 w-full rounded object-cover">
                    <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                        <p class="min-w-0 truncate text-xs text-slate-600">{{ pendingFileName }}</p>
                        <button class="rounded-md border border-rose-300 px-2 py-1 text-xs font-bold text-rose-700" type="button" @click="clearPendingUpload">Remove selected file</button>
                    </div>
                </div>
                <button class="rounded-md bg-teal-700 px-3 py-2 text-sm font-bold text-white disabled:opacity-60" type="submit" :disabled="uploadForm.processing">Upload</button>
                <p v-if="Object.keys(uploadForm.errors || {}).length" class="text-sm text-rose-700">Check the upload fields and try again.</p>
            </form>
        </div>
    </div>
</template>
