<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    page: { type: Object, required: true },
    preview_url: { type: String, required: true },
    media: { type: Array, required: true },
    item_types: { type: Array, required: true },
});

const activeSectionId = ref(props.page.sections[0]?.id || null);
const activeItemId = ref(props.page.sections.flatMap((section) => section.items || [])[0]?.id || null);
const pageForm = useForm({
    title: props.page.title,
    draft_content: props.page.draft_content || {},
    seo: props.page.seo || {},
});

const newItem = useForm({
    public_site_section_id: props.page.sections[0]?.id || null,
    type: 'service',
    slug: '',
    title: '',
    summary: '',
    draft_content: {},
    status: 'draft',
    is_enabled: true,
    is_featured: false,
    sort_order: 100,
});

const activeSection = computed(() => props.page.sections.find((section) => section.id === activeSectionId.value));
const activeItem = computed(() => props.page.sections.flatMap((section) => section.items || []).find((item) => item.id === activeItemId.value));
const sectionForm = computed(() => {
    const section = activeSection.value;
    if (!section) return null;
    return useForm({
        label: section.label,
        sort_order: section.sort_order,
        is_enabled: section.is_enabled,
        draft_content: section.draft_content || {},
    });
});
const itemForm = computed(() => {
    const item = activeItem.value;
    if (!item) return null;
    return useForm({
        public_site_section_id: item.public_site_section_id,
        type: item.type,
        slug: item.slug,
        title: item.title,
        summary: item.summary,
        draft_content: item.draft_content || {},
        status: item.status,
        is_enabled: item.is_enabled,
        is_featured: item.is_featured,
        sort_order: item.sort_order,
    });
});

function savePage() {
    pageForm.patch(`/admin/public-website/pages/${props.page.id}`, { preserveScroll: true });
}

function publishPage() {
    router.post(`/admin/public-website/pages/${props.page.id}/publish`, {}, { preserveScroll: true });
}

function unpublishPage() {
    if (window.confirm('Unpublish this page from the public website?')) {
        router.post(`/admin/public-website/pages/${props.page.id}/unpublish`, {}, { preserveScroll: true });
    }
}

function saveSection(form, section) {
    form.patch(`/admin/public-website/sections/${section.id}`, { preserveScroll: true });
}

function saveItem(form, item) {
    form.patch(`/admin/public-website/items/${item.id}`, { preserveScroll: true });
}

function publishItem(item) {
    router.post(`/admin/public-website/items/${item.id}/publish`, {}, { preserveScroll: true });
}

function createItem() {
    newItem.post(`/admin/public-website/pages/${props.page.id}/items`, {
        preserveScroll: true,
        onSuccess: () => newItem.reset(),
    });
}

function restoreRevision(revision) {
    if (window.confirm(`Restore revision ${revision.version} into draft?`)) {
        router.post(`/admin/public-website/revisions/${revision.id}/restore`, {}, { preserveScroll: true });
    }
}

function pretty(value) {
    return JSON.stringify(value ?? {}, null, 2);
}

function parseInto(target, field, event) {
    try {
        target[field] = JSON.parse(event.target.value || '{}');
        event.target.setCustomValidity('');
    } catch (error) {
        event.target.setCustomValidity('Enter valid JSON.');
    }
}
</script>

<template>
    <AppLayout :title="`Public Website: ${page.title}`">
        <div class="space-y-6">
            <section class="rounded-md border border-slate-200 bg-white p-5">
                <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">/{{ page.slug === 'home' ? '' : page.slug }}</p>
                        <h2 class="mt-1 text-2xl font-black">{{ page.title }}</h2>
                        <p class="mt-2 text-sm text-slate-600">Status: {{ page.status }} · published version {{ page.version }} · last published {{ page.published_at || 'never' }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a :href="preview_url" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold" target="_blank">Preview draft</a>
                        <button class="rounded-md bg-teal-700 px-3 py-2 text-sm font-bold text-white" type="button" @click="publishPage">Publish page</button>
                        <button class="rounded-md border border-rose-300 px-3 py-2 text-sm font-bold text-rose-700" type="button" @click="unpublishPage">Unpublish</button>
                    </div>
                </div>
            </section>

            <section class="grid gap-6 lg:grid-cols-[0.85fr_1.15fr]">
                <form class="rounded-md border border-slate-200 bg-white p-5" @submit.prevent="savePage">
                    <h3 class="font-bold">Page Draft and SEO</h3>
                    <div class="mt-4 grid gap-4">
                        <label class="text-sm font-semibold">Title
                            <input v-model="pageForm.title" class="mt-1 w-full rounded-md border-slate-300" type="text">
                        </label>
                        <label class="text-sm font-semibold">Draft content JSON
                            <textarea class="mt-1 w-full rounded-md border-slate-300 font-mono text-xs" rows="8" :value="pretty(pageForm.draft_content)" @input="parseInto(pageForm, 'draft_content', $event)"></textarea>
                        </label>
                        <label class="text-sm font-semibold">SEO JSON
                            <textarea class="mt-1 w-full rounded-md border-slate-300 font-mono text-xs" rows="6" :value="pretty(pageForm.seo)" @input="parseInto(pageForm, 'seo', $event)"></textarea>
                        </label>
                        <button class="rounded-md bg-slate-950 px-4 py-2 text-sm font-bold text-white" type="submit">Save draft</button>
                    </div>
                </form>

                <div class="rounded-md border border-slate-200 bg-white p-5">
                    <h3 class="font-bold">Sections</h3>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <button v-for="section in page.sections" :key="section.id" class="rounded-md border px-3 py-2 text-sm font-semibold" :class="activeSectionId === section.id ? 'border-teal-700 bg-teal-50 text-teal-800' : 'border-slate-300'" type="button" @click="activeSectionId = section.id">
                            {{ section.label }}
                        </button>
                    </div>
                    <form v-if="activeSection && sectionForm" class="mt-5 grid gap-4" @submit.prevent="saveSection(sectionForm, activeSection)">
                        <label class="text-sm font-semibold">Label
                            <input v-model="sectionForm.label" class="mt-1 w-full rounded-md border-slate-300" type="text">
                        </label>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="text-sm font-semibold">Order
                                <input v-model="sectionForm.sort_order" class="mt-1 w-full rounded-md border-slate-300" type="number" min="0">
                            </label>
                            <label class="flex items-center gap-2 pt-7 text-sm font-semibold">
                                <input v-model="sectionForm.is_enabled" type="checkbox"> Enabled
                            </label>
                        </div>
                        <label class="text-sm font-semibold">Draft content JSON
                            <textarea class="mt-1 w-full rounded-md border-slate-300 font-mono text-xs" rows="10" :value="pretty(sectionForm.draft_content)" @input="parseInto(sectionForm, 'draft_content', $event)"></textarea>
                        </label>
                        <button class="rounded-md bg-slate-950 px-4 py-2 text-sm font-bold text-white" type="submit">Save section draft</button>
                    </form>
                </div>
            </section>

            <section class="grid gap-6 lg:grid-cols-[0.8fr_1.2fr]">
                <div class="rounded-md border border-slate-200 bg-white p-5">
                    <h3 class="font-bold">Content Items</h3>
                    <div class="mt-4 max-h-[520px] space-y-2 overflow-auto">
                        <button v-for="item in page.sections.flatMap((section) => section.items || [])" :key="item.id" class="block w-full rounded-md border p-3 text-left text-sm" :class="activeItemId === item.id ? 'border-teal-700 bg-teal-50' : 'border-slate-200'" type="button" @click="activeItemId = item.id">
                            <span class="font-bold">{{ item.title }}</span>
                            <span class="mt-1 block text-xs text-slate-500">{{ item.type }} · {{ item.status }} · order {{ item.sort_order }}</span>
                        </button>
                    </div>
                </div>

                <form v-if="activeItem && itemForm" class="rounded-md border border-slate-200 bg-white p-5" @submit.prevent="saveItem(itemForm, activeItem)">
                    <div class="flex items-start justify-between gap-3">
                        <h3 class="font-bold">Edit Item</h3>
                        <button class="rounded-md bg-teal-700 px-3 py-2 text-sm font-bold text-white" type="button" @click="publishItem(activeItem)">Publish item</button>
                    </div>
                    <div class="mt-4 grid gap-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="text-sm font-semibold">Title
                                <input v-model="itemForm.title" class="mt-1 w-full rounded-md border-slate-300" type="text">
                            </label>
                            <label class="text-sm font-semibold">Slug
                                <input v-model="itemForm.slug" class="mt-1 w-full rounded-md border-slate-300" type="text">
                            </label>
                        </div>
                        <label class="text-sm font-semibold">Summary
                            <textarea v-model="itemForm.summary" class="mt-1 w-full rounded-md border-slate-300" rows="3"></textarea>
                        </label>
                        <div class="grid gap-4 sm:grid-cols-3">
                            <label class="text-sm font-semibold">Order
                                <input v-model="itemForm.sort_order" class="mt-1 w-full rounded-md border-slate-300" type="number" min="0">
                            </label>
                            <label class="flex items-center gap-2 pt-7 text-sm font-semibold">
                                <input v-model="itemForm.is_enabled" type="checkbox"> Enabled
                            </label>
                            <label class="flex items-center gap-2 pt-7 text-sm font-semibold">
                                <input v-model="itemForm.is_featured" type="checkbox"> Featured
                            </label>
                        </div>
                        <label class="text-sm font-semibold">Draft content JSON
                            <textarea class="mt-1 w-full rounded-md border-slate-300 font-mono text-xs" rows="10" :value="pretty(itemForm.draft_content)" @input="parseInto(itemForm, 'draft_content', $event)"></textarea>
                        </label>
                        <button class="rounded-md bg-slate-950 px-4 py-2 text-sm font-bold text-white" type="submit">Save item draft</button>
                    </div>
                </form>
            </section>

            <form class="rounded-md border border-slate-200 bg-white p-5" @submit.prevent="createItem">
                <h3 class="font-bold">Create Draft Item</h3>
                <div class="mt-4 grid gap-4 md:grid-cols-3">
                    <label class="text-sm font-semibold">Section
                        <select v-model="newItem.public_site_section_id" class="mt-1 w-full rounded-md border-slate-300">
                            <option v-for="section in page.sections" :key="section.id" :value="section.id">{{ section.label }}</option>
                        </select>
                    </label>
                    <label class="text-sm font-semibold">Type
                        <select v-model="newItem.type" class="mt-1 w-full rounded-md border-slate-300">
                            <option v-for="type in item_types" :key="type" :value="type">{{ type }}</option>
                        </select>
                    </label>
                    <label class="text-sm font-semibold">Title
                        <input v-model="newItem.title" class="mt-1 w-full rounded-md border-slate-300" type="text">
                    </label>
                    <label class="text-sm font-semibold md:col-span-3">Summary
                        <textarea v-model="newItem.summary" class="mt-1 w-full rounded-md border-slate-300" rows="2"></textarea>
                    </label>
                    <label class="text-sm font-semibold md:col-span-3">Draft content JSON
                        <textarea class="mt-1 w-full rounded-md border-slate-300 font-mono text-xs" rows="5" :value="pretty(newItem.draft_content)" @input="parseInto(newItem, 'draft_content', $event)"></textarea>
                    </label>
                </div>
                <button class="mt-4 rounded-md bg-slate-950 px-4 py-2 text-sm font-bold text-white" type="submit">Create item</button>
            </form>

            <section class="rounded-md border border-slate-200 bg-white p-5">
                <h3 class="font-bold">Publishing History</h3>
                <div class="mt-4 divide-y divide-slate-200">
                    <div v-for="revision in page.revisions" :key="revision.id" class="flex flex-col gap-3 py-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p class="font-semibold">Version {{ revision.version }} · {{ revision.event }}</p>
                            <p class="text-sm text-slate-600">{{ revision.created_at }} · {{ revision.creator?.firstname }} {{ revision.creator?.lastname }}</p>
                        </div>
                        <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold" type="button" @click="restoreRevision(revision)">Restore to draft</button>
                    </div>
                    <p v-if="page.revisions.length === 0" class="py-3 text-sm text-slate-600">No revisions recorded yet.</p>
                </div>
            </section>

            <section class="rounded-md border border-slate-200 bg-white p-5">
                <h3 class="font-bold">Available Media Paths</h3>
                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <code v-for="asset in media" :key="asset.id" class="block rounded-md bg-slate-100 p-3 text-xs">{{ asset.url }} · {{ asset.alt_text }}</code>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
