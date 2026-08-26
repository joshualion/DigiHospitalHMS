<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { onBeforeUnmount, ref } from 'vue';
import ConfirmDialog from '@/Components/Admin/ConfirmDialog.vue';

defineProps({
    pages: { type: Array, required: true },
    media: { type: Object, required: true },
    stats: { type: Object, required: true },
    can_manage_media: { type: Boolean, default: false },
});

const mediaForm = useForm({
    title: '',
    alt_text: '',
    caption: '',
    credit: '',
    image: null,
});
const pendingPreview = ref('');
const pendingFileName = ref('');
const fileInput = ref(null);
const pendingDelete = ref(null);
const deleteForm = useForm({});

function revokePendingPreview() {
    if (pendingPreview.value) URL.revokeObjectURL(pendingPreview.value);
    pendingPreview.value = '';
    pendingFileName.value = '';
}

function clearPendingUpload() {
    revokePendingPreview();
    mediaForm.image = null;
    if (fileInput.value) fileInput.value.value = '';
}

function previewUpload(event) {
    const file = event.target.files?.[0] || null;
    revokePendingPreview();
    mediaForm.image = file;
    if (!file) return;
    pendingFileName.value = file.name;
    pendingPreview.value = URL.createObjectURL(file);
}

function uploadMedia() {
    mediaForm.post('/admin/public-website/media', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            mediaForm.reset();
            clearPendingUpload();
        },
    });
}

function deleteMedia(asset) {
    pendingDelete.value = asset;
}

function confirmDeleteMedia() {
    deleteForm.delete(`/admin/public-website/media/${pendingDelete.value.id}`, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            pendingDelete.value = null;
        },
    });
}

onBeforeUnmount(revokePendingPreview);
</script>

<template>
    <AppLayout title="Public Website">
        <div class="space-y-6">
            <section class="grid gap-4 md:grid-cols-4">
                <div class="rounded-md border border-slate-200 bg-white p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Published pages</p>
                    <p class="mt-2 text-3xl font-black">{{ stats.published_pages }}</p>
                </div>
                <div class="rounded-md border border-slate-200 bg-white p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Draft pages</p>
                    <p class="mt-2 text-3xl font-black">{{ stats.draft_pages }}</p>
                </div>
                <div class="rounded-md border border-slate-200 bg-white p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Media</p>
                    <p class="mt-2 text-3xl font-black">{{ stats.media_count }}</p>
                </div>
                <div class="rounded-md border border-slate-200 bg-white p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Revisions</p>
                    <p class="mt-2 text-3xl font-black">{{ stats.revision_count }}</p>
                </div>
            </section>

            <section class="rounded-md border border-slate-200 bg-white">
                <div class="border-b border-slate-200 p-5">
                    <h2 class="text-lg font-bold">Website Pages</h2>
                    <p class="mt-1 text-sm text-slate-600">Draft edits remain private until an authorized publisher publishes the page.</p>
                </div>
                <div class="divide-y divide-slate-200">
                    <div v-for="page in pages" :key="page.id" class="flex flex-col gap-3 p-5 md:flex-row md:items-center md:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-bold text-slate-950">{{ page.title }}</h3>
                                <span class="rounded-full px-2 py-1 text-xs font-semibold" :class="page.status === 'published' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'">{{ page.status }}</span>
                            </div>
                            <p class="mt-1 text-sm text-slate-600">/{{ page.slug === 'home' ? '' : page.slug }} · {{ page.sections_count }} sections · version {{ page.published_version }}</p>
                            <p class="mt-1 text-xs text-slate-500">Last published: {{ page.published_at || 'Not published' }}</p>
                            <div v-if="page.launch_warnings?.length" class="mt-3 rounded-md border border-amber-200 bg-amber-50 p-3 text-xs font-semibold text-amber-900">
                                <p>Launch content required:</p>
                                <ul class="mt-1 list-disc space-y-1 pl-4">
                                    <li v-for="warning in page.launch_warnings" :key="warning">{{ warning }}</li>
                                </ul>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <a :href="page.slug === 'home' ? '/' : `/${page.slug}`" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold" target="_blank">View</a>
                            <Link :href="`/admin/public-website/pages/${page.id}`" class="rounded-md bg-slate-950 px-3 py-2 text-sm font-semibold text-white">Manage</Link>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid gap-6 lg:grid-cols-[1fr_1.2fr]">
                <form class="rounded-md border border-slate-200 bg-white p-5" @submit.prevent="uploadMedia">
                    <h2 class="text-lg font-bold">Upload Media</h2>
                    <div class="mt-4 grid gap-4">
                        <label class="text-sm font-semibold">Title
                            <input v-model="mediaForm.title" class="mt-1 w-full rounded-md border-slate-300" type="text">
                        </label>
                        <label class="text-sm font-semibold">Alternative text
                            <input v-model="mediaForm.alt_text" class="mt-1 w-full rounded-md border-slate-300" type="text">
                        </label>
                        <label class="text-sm font-semibold">Caption
                            <textarea v-model="mediaForm.caption" class="mt-1 w-full rounded-md border-slate-300" rows="2"></textarea>
                        </label>
                        <label class="text-sm font-semibold">Credit/source
                            <input v-model="mediaForm.credit" class="mt-1 w-full rounded-md border-slate-300" type="text">
                        </label>
                        <label class="text-sm font-semibold">Image
                            <input ref="fileInput" class="mt-1 block w-full text-sm" type="file" accept="image/jpeg,image/png,image/webp" @change="previewUpload">
                        </label>
                        <div v-if="pendingPreview" class="rounded-md border border-slate-200 bg-slate-50 p-3">
                            <img :src="pendingPreview" :alt="pendingFileName" class="h-56 w-full rounded-md object-cover">
                            <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                                <p class="min-w-0 truncate text-sm text-slate-600">{{ pendingFileName }}</p>
                                <button class="rounded-md border border-rose-300 px-3 py-2 text-xs font-bold text-rose-700" type="button" @click="clearPendingUpload">Remove selected file</button>
                            </div>
                        </div>
                        <button class="rounded-md bg-teal-700 px-4 py-2 text-sm font-bold text-white disabled:opacity-60" type="submit" :disabled="mediaForm.processing">Upload</button>
                    </div>
                    <p v-if="Object.keys(mediaForm.errors).length" class="mt-3 text-sm text-rose-700">Check the upload fields and try again.</p>
                </form>

                <div class="rounded-md border border-slate-200 bg-white p-5">
                    <h2 class="text-lg font-bold">Media Library</h2>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <article v-for="asset in media.data" :key="asset.id" class="rounded-md border border-slate-200 p-3">
                            <img :src="asset.url" :alt="asset.alt_text" class="h-32 w-full rounded-md object-cover" loading="lazy">
                            <p class="mt-3 text-sm font-bold">{{ asset.title }}</p>
                            <p class="text-xs text-slate-500">{{ asset.mime_type }} · {{ asset.width }}x{{ asset.height }}</p>
                            <p class="mt-1 text-xs text-slate-500">Used {{ asset.usage_count }} time(s)</p>
                            <button v-if="can_manage_media" class="mt-3 rounded-md border border-rose-300 px-3 py-2 text-xs font-bold text-rose-700 disabled:cursor-not-allowed disabled:opacity-50" type="button" :disabled="asset.usage_count > 0" @click="deleteMedia(asset)">Delete</button>
                        </article>
                        <p v-if="media.data.length === 0" class="text-sm text-slate-600">No media has been uploaded yet.</p>
                    </div>
                </div>
            </section>
        </div>

        <ConfirmDialog
            :show="Boolean(pendingDelete)"
            title="Delete Media"
            :message="pendingDelete ? `Delete '${pendingDelete.title}' from the media library?` : ''"
            :form="deleteForm"
            confirm-label="Delete"
            @close="pendingDelete = null"
            @confirm="confirmDeleteMedia"
        />
    </AppLayout>
</template>
