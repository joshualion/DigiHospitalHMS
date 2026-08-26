<script setup>
import ConfirmDialog from '@/Components/Admin/ConfirmDialog.vue';
import MediaPicker from '@/Components/Admin/PublicWebsite/MediaPicker.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    page: { type: Object, required: true },
    preview_url: { type: String, required: true },
    media: { type: Array, required: true },
    item_types: { type: Array, required: true },
    can_manage_media: { type: Boolean, default: false },
    can_view_json: { type: Boolean, default: false },
});

const page = usePage();
const pageModel = ref(JSON.parse(JSON.stringify(props.page)));
const activePanel = ref('page');
const activeSectionKey = ref(pageModel.value.sections[0]?.key || 'hero');
const activeItemId = ref(pageModel.value.sections.flatMap((section) => section.items || [])[0]?.id || null);
const showDiagnostics = ref(false);
const uploadForm = useForm({ title: '', alt_text: '', caption: '', credit: '', image: null });
const confirmForm = useForm({});
const pendingConfirm = ref(null);
const accentOptions = [['calm', 'Calm'], ['healing', 'Healing'], ['alert', 'Alert'], ['blood', 'Blood'], ['seagrass', 'Seagrass']];
const itemLabels = { service: 'Services', department: 'Departments', clinician: 'Featured clinicians', testimonial: 'Testimonials', article: 'News/articles' };
const itemSections = { service: 'services', department: 'departments', clinician: 'doctors', testimonial: 'testimonials', article: 'news' };
ensurePageContent();

const sectionTabs = computed(() => [...pageModel.value.sections].sort((a, b) => Number(a.sort_order) - Number(b.sort_order)));
const activeSection = computed(() => pageModel.value.sections.find((section) => section.key === activeSectionKey.value));
const pageContent = computed(() => pageModel.value.draft_content || (pageModel.value.draft_content = {}));
const seo = computed(() => pageModel.value.seo || (pageModel.value.seo = {}));
const theme = computed(() => pageContent.value.theme || (pageContent.value.theme = { appearance: 'system', accent: 'calm', allowed_accents: ['calm', 'healing', 'alert', 'blood', 'seagrass'], show_switcher: true }));

function allItems() {
    return pageModel.value.sections.flatMap((section) => section.items || []);
}

function ensurePageContent() {
    pageModel.value.draft_content ||= {};
    pageModel.value.draft_content.utility ||= {};
    pageModel.value.draft_content.navigation ||= { items: [] };
    pageModel.value.draft_content.navigation.items ||= [];
    pageModel.value.draft_content.footer ||= {};
    pageModel.value.draft_content.footer.badges ||= [];
    pageModel.value.seo ||= {};
}

function sectionContent(section) {
    section.draft_content ||= {};
    return section.draft_content;
}

function itemsByType(type) {
    return allItems().filter((item) => item.type === type).sort((a, b) => Number(a.sort_order) - Number(b.sort_order));
}

function sectionForKey(key) {
    return pageModel.value.sections.find((section) => section.key === key) || null;
}

function statusLabel(record) {
    if (record.status === 'draft') return 'Unpublished';
    if (record.is_modified) return 'Modified';
    return 'Published';
}

function statusClass(record) {
    if (record.status === 'draft') return 'bg-amber-100 text-amber-800';
    if (record.is_modified) return 'bg-sky-100 text-sky-800';
    return 'bg-green-100 text-green-800';
}

function uploadMedia(afterUpload = null) {
    uploadForm.post('/admin/public-website/media', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            afterUpload?.(page.props.flash?.uploaded_media);
            uploadForm.reset();
        },
    });
}

function deleteMedia(asset) {
    pendingConfirm.value = {
        title: 'Delete Media',
        message: `Delete unavailable media '${asset.title}' from the media library?`,
        label: 'Delete',
        method: 'delete',
        url: `/admin/public-website/media/${asset.id}`,
    };
}

function savePage() {
    useForm({ title: pageModel.value.title, draft_content: pageModel.value.draft_content || {}, seo: pageModel.value.seo || {} })
        .patch(`/admin/public-website/pages/${pageModel.value.id}`, { preserveScroll: true });
}

function saveTheme() {
    useForm({ appearance: theme.value.appearance, accent: theme.value.accent, allowed_accents: theme.value.allowed_accents, show_switcher: theme.value.show_switcher })
        .patch(`/admin/public-website/pages/${pageModel.value.id}/theme`, { preserveScroll: true });
}

function saveSection(section) {
    useForm({ label: section.label, sort_order: section.sort_order, is_enabled: Boolean(section.is_enabled), draft_content: section.draft_content || {} })
        .patch(`/admin/public-website/sections/${section.id}`, { preserveScroll: true });
}

function saveItem(item) {
    useForm({
        public_site_section_id: item.public_site_section_id,
        type: item.type,
        slug: item.slug,
        title: item.title,
        summary: item.summary,
        draft_content: item.draft_content || {},
        status: item.status || 'draft',
        is_enabled: Boolean(item.is_enabled),
        is_featured: Boolean(item.is_featured),
        sort_order: Number(item.sort_order || 0),
    }).patch(`/admin/public-website/items/${item.id}`, { preserveScroll: true });
}

function defaultItemContent(type) {
    return {
        service: { icon: 'stethoscope', description: '', cta_label: 'Learn more', cta_url: '/services' },
        department: { icon: 'building-2', public_title: '', summary: '' },
        clinician: { display_name: '', professional_title: '', specialty: '', bio: '', photo: '', alt: '' },
        testimonial: { display_name: '', context: '', text: '', rating: null, approved: false },
        article: { excerpt: '', body: '', image: '', alt: '', author: '', published_on: '' },
    }[type] || {};
}

function createItem(type) {
    const section = sectionForKey(itemSections[type]);
    useForm({
        public_site_section_id: section?.id || pageModel.value.sections[0]?.id || null,
        type,
        slug: '',
        title: `New ${type}`,
        summary: '',
        draft_content: defaultItemContent(type),
        status: 'draft',
        is_enabled: true,
        is_featured: type !== 'article',
        sort_order: itemsByType(type).length + 1,
    }).post(`/admin/public-website/pages/${pageModel.value.id}/items`, { preserveScroll: true });
}

function publishPage() {
    router.post(`/admin/public-website/pages/${pageModel.value.id}/publish`, {}, { preserveScroll: true });
}

function unpublishPage() {
    pendingConfirm.value = {
        title: 'Unpublish Page',
        message: 'Unpublish this page from the public website?',
        label: 'Unpublish',
        method: 'post',
        url: `/admin/public-website/pages/${pageModel.value.id}/unpublish`,
    };
}

function publishItem(item) {
    router.post(`/admin/public-website/items/${item.id}/publish`, {}, { preserveScroll: true });
}

function unpublishItem(item) {
    pendingConfirm.value = {
        title: 'Unpublish Item',
        message: `Unpublish '${item.title}' from the public website?`,
        label: 'Unpublish',
        method: 'post',
        url: `/admin/public-website/items/${item.id}/unpublish`,
    };
}

function removeItemFromDraft(item) {
    item.is_enabled = false;
    item.status = 'draft';
    saveItem(item);
}

function restoreRevision(revision) {
    pendingConfirm.value = {
        title: 'Restore Revision',
        message: `Restore revision ${revision.version} into draft?`,
        label: 'Restore',
        method: 'post',
        url: `/admin/public-website/revisions/${revision.id}/restore`,
    };
}

function closeConfirm() {
    pendingConfirm.value = null;
}

function submitConfirm() {
    const action = pendingConfirm.value;
    if (!action) return;

    const options = {
        preserveScroll: true,
        preserveState: true,
        onSuccess: closeConfirm,
    };

    if (action.method === 'delete') {
        confirmForm.delete(action.url, options);
    } else {
        confirmForm.post(action.url, options);
    }
}

function addArrayItem(array, value) {
    array.push(JSON.parse(JSON.stringify(value)));
}

function removeArrayItem(array, index) {
    array.splice(index, 1);
}

function moveArrayItem(array, index, direction) {
    const next = index + direction;
    if (next < 0 || next >= array.length) return;
    [array[index], array[next]] = [array[next], array[index]];
}

function moveRecord(records, record, direction) {
    const sorted = [...records].sort((a, b) => Number(a.sort_order) - Number(b.sort_order));
    const index = sorted.findIndex((entry) => entry.id === record.id);
    const next = index + direction;
    if (next < 0 || next >= sorted.length) return;
    [sorted[index].sort_order, sorted[next].sort_order] = [sorted[next].sort_order, sorted[index].sort_order];
    saveItem(record);
    saveItem(sorted[next]);
}

function toggleAccent(value) {
    if (theme.value.allowed_accents.includes(value)) {
        if (theme.value.allowed_accents.length > 1) theme.value.allowed_accents = theme.value.allowed_accents.filter((accent) => accent !== value);
    } else {
        theme.value.allowed_accents.push(value);
    }
    if (!theme.value.allowed_accents.includes(theme.value.accent)) theme.value.accent = theme.value.allowed_accents[0];
}
</script>

<template>
    <AppLayout :title="`Public Website: ${pageModel.title}`">
        <div class="space-y-6">
            <section class="rounded-md border border-slate-200 bg-white p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">/{{ pageModel.slug === 'home' ? '' : pageModel.slug }}</p>
                        <h2 class="mt-1 text-2xl font-black">{{ pageModel.title }}</h2>
                        <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                            <span class="rounded-full px-2 py-1 text-xs font-bold" :class="statusClass(pageModel)">{{ statusLabel(pageModel) }}</span>
                            <span class="text-slate-600">Published version {{ pageModel.published_version }}</span>
                            <span class="text-slate-600">Last published {{ pageModel.published_at || 'never' }}</span>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a :href="preview_url" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold" target="_blank">Preview draft</a>
                        <button class="rounded-md bg-teal-700 px-3 py-2 text-sm font-bold text-white" type="button" @click="publishPage">Publish page</button>
                        <button class="rounded-md border border-rose-300 px-3 py-2 text-sm font-bold text-rose-700" type="button" @click="unpublishPage">Unpublish</button>
                    </div>
                </div>
            </section>

            <nav class="flex flex-wrap gap-2 rounded-md border border-slate-200 bg-white p-3" aria-label="Editor areas">
                <button v-for="[key, label] in [['page', 'Branding & SEO'], ['sections', 'Sections'], ['items', 'Content items'], ['history', 'Publishing history']]" :key="key" class="rounded-md px-3 py-2 text-sm font-bold" :class="activePanel === key ? 'bg-slate-950 text-white' : 'border border-slate-200 text-slate-700'" type="button" @click="activePanel = key">{{ label }}</button>
                <button v-if="can_view_json" class="rounded-md border border-slate-200 px-3 py-2 text-sm font-bold text-slate-700" type="button" @click="showDiagnostics = !showDiagnostics">Diagnostics</button>
            </nav>

            <section v-if="activePanel === 'page'" class="grid gap-6 lg:grid-cols-[1fr_0.9fr]">
                <form class="space-y-5 rounded-md border border-slate-200 bg-white p-5" @submit.prevent="savePage">
                    <h3 class="text-lg font-bold">Branding, Header and Footer</h3>
                    <label class="block text-sm font-semibold">Page title
                        <input v-model="pageModel.title" class="mt-1 w-full rounded-md border-slate-300" type="text" required>
                    </label>
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="block text-sm font-semibold">Top-bar phone
                            <input v-model="pageContent.utility.phone" class="mt-1 w-full rounded-md border-slate-300" type="text">
                        </label>
                        <label class="block text-sm font-semibold">Emergency phone
                            <input v-model="pageContent.utility.emergency_phone" class="mt-1 w-full rounded-md border-slate-300" type="text">
                        </label>
                        <label class="block text-sm font-semibold">Public email
                            <input v-model="pageContent.utility.email" class="mt-1 w-full rounded-md border-slate-300" type="email">
                        </label>
                        <label class="block text-sm font-semibold">Opening hours
                            <input v-model="pageContent.utility.hours" class="mt-1 w-full rounded-md border-slate-300" type="text">
                        </label>
                    </div>
                    <label class="flex items-center gap-2 text-sm font-semibold"><input v-model="pageContent.utility.visible" type="checkbox"> Show top information bar</label>

                    <div class="rounded-md border border-slate-200 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <h4 class="font-bold">Navigation</h4>
                            <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold" type="button" @click="addArrayItem(pageContent.navigation.items, { label: 'New link', url: '/' })">Add link</button>
                        </div>
                        <div class="mt-3 space-y-3">
                            <div v-for="(link, index) in pageContent.navigation.items" :key="index" class="grid gap-3 rounded-md bg-slate-50 p-3 md:grid-cols-[1fr_1fr_auto]">
                                <input v-model="link.label" class="rounded-md border-slate-300 text-sm" type="text" aria-label="Navigation label">
                                <input v-model="link.url" class="rounded-md border-slate-300 text-sm" type="text" aria-label="Navigation URL">
                                <div class="flex gap-2">
                                    <button class="rounded-md border px-2 text-sm" type="button" aria-label="Move up" @click="moveArrayItem(pageContent.navigation.items, index, -1)">Up</button>
                                    <button class="rounded-md border px-2 text-sm" type="button" aria-label="Move down" @click="moveArrayItem(pageContent.navigation.items, index, 1)">Down</button>
                                    <button class="rounded-md border border-rose-300 px-2 text-sm text-rose-700" type="button" @click="removeArrayItem(pageContent.navigation.items, index)">Remove</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-md border border-slate-200 p-4">
                        <h4 class="font-bold">Footer</h4>
                        <label class="mt-3 block text-sm font-semibold">Footer summary
                            <textarea v-model="pageContent.footer.summary" class="mt-1 w-full rounded-md border-slate-300" rows="3"></textarea>
                        </label>
                        <label class="mt-3 block text-sm font-semibold">Copyright
                            <input v-model="pageContent.footer.copyright" class="mt-1 w-full rounded-md border-slate-300" type="text">
                        </label>
                        <div class="mt-3">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-semibold">Footer badges</p>
                                <button class="rounded-md border px-2 py-1 text-sm" type="button" @click="addArrayItem(pageContent.footer.badges, 'New badge')">Add badge</button>
                            </div>
                            <div class="mt-2 space-y-2">
                                <div v-for="(badge, index) in pageContent.footer.badges" :key="index" class="flex gap-2">
                                    <input v-model="pageContent.footer.badges[index]" class="min-w-0 flex-1 rounded-md border-slate-300 text-sm" type="text">
                                    <button class="rounded-md border border-rose-300 px-2 text-sm text-rose-700" type="button" @click="removeArrayItem(pageContent.footer.badges, index)">Remove</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button class="rounded-md bg-slate-950 px-4 py-2 text-sm font-bold text-white" type="submit">Save branding, navigation and footer draft</button>
                </form>

                <div class="space-y-6">
                    <form class="space-y-4 rounded-md border border-slate-200 bg-white p-5" @submit.prevent="savePage">
                        <h3 class="text-lg font-bold">SEO</h3>
                        <label class="block text-sm font-semibold">SEO title
                            <input v-model="seo.title" class="mt-1 w-full rounded-md border-slate-300" type="text">
                        </label>
                        <label class="block text-sm font-semibold">Meta description
                            <textarea v-model="seo.description" class="mt-1 w-full rounded-md border-slate-300" rows="3"></textarea>
                        </label>
                        <label class="block text-sm font-semibold">Canonical URL
                            <input v-model="seo.canonical_url" class="mt-1 w-full rounded-md border-slate-300" type="text">
                        </label>
                        <MediaPicker v-model="seo.image" v-model:alt-value="seo.image_alt" :media="media" label="Social sharing image" :can-upload="can_manage_media" :upload-form="uploadForm" @upload="uploadMedia" @delete="deleteMedia" />
                        <button class="rounded-md bg-slate-950 px-4 py-2 text-sm font-bold text-white" type="submit">Save SEO draft</button>
                    </form>

                    <form class="space-y-4 rounded-md border border-slate-200 bg-white p-5" @submit.prevent="saveTheme">
                        <h3 class="text-lg font-bold">Theme Defaults</h3>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="text-sm font-semibold">Appearance
                                <select v-model="theme.appearance" class="mt-1 w-full rounded-md border-slate-300">
                                    <option value="system">System</option>
                                    <option value="light">Light</option>
                                    <option value="dark">Dark</option>
                                </select>
                            </label>
                            <label class="text-sm font-semibold">Default accent
                                <select v-model="theme.accent" class="mt-1 w-full rounded-md border-slate-300">
                                    <option v-for="[value, label] in accentOptions" :key="value" :value="value" :disabled="!theme.allowed_accents.includes(value)">{{ label }}</option>
                                </select>
                            </label>
                        </div>
                        <label class="flex items-center gap-2 text-sm font-semibold"><input v-model="theme.show_switcher" type="checkbox"> Show visitor theme switcher</label>
                        <div>
                            <p class="text-sm font-semibold">Allowed accents</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <button v-for="[value, label] in accentOptions" :key="value" type="button" class="rounded-md border px-3 py-2 text-sm font-semibold" :class="theme.allowed_accents.includes(value) ? 'border-teal-700 bg-teal-50 text-teal-800' : 'border-slate-300'" @click="toggleAccent(value)">{{ label }}</button>
                            </div>
                        </div>
                        <button class="rounded-md bg-slate-950 px-4 py-2 text-sm font-bold text-white" type="submit">Save theme defaults</button>
                    </form>
                </div>
            </section>

            <section v-if="activePanel === 'sections'" class="grid gap-6 lg:grid-cols-[18rem_1fr]">
                <div class="rounded-md border border-slate-200 bg-white p-3">
                    <button v-for="section in sectionTabs" :key="section.id" class="mb-2 block w-full rounded-md border p-3 text-left text-sm" :class="activeSectionKey === section.key ? 'border-teal-700 bg-teal-50' : 'border-slate-200'" type="button" @click="activeSectionKey = section.key">
                        <span class="font-bold">{{ section.label }}</span>
                        <span class="mt-1 block text-xs" :class="statusClass(section)">{{ section.is_enabled ? statusLabel(section) : 'Draft disabled' }}</span>
                    </button>
                </div>

                <form v-if="activeSection" class="space-y-5 rounded-md border border-slate-200 bg-white p-5" @submit.prevent="saveSection(activeSection)">
                    <div class="grid gap-4 md:grid-cols-[1fr_8rem_auto]">
                        <label class="text-sm font-semibold">Section label
                            <input v-model="activeSection.label" class="mt-1 w-full rounded-md border-slate-300" type="text" required>
                        </label>
                        <label class="text-sm font-semibold">Order
                            <input v-model="activeSection.sort_order" class="mt-1 w-full rounded-md border-slate-300" type="number" min="0">
                        </label>
                        <label class="flex items-center gap-2 pt-7 text-sm font-semibold"><input v-model="activeSection.is_enabled" type="checkbox"> Enabled</label>
                    </div>

                    <template v-if="activeSection.key === 'hero'">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-bold">Hero slides</h3>
                            <button class="rounded-md border px-3 py-2 text-sm font-semibold" type="button" @click="addArrayItem((sectionContent(activeSection).slides ||= []), { label: '', headline: '', text: '', image: '', alt: '', primary_label: '', primary_url: '', secondary_label: '', secondary_url: '', overlay: 55, active: true })">Add slide</button>
                        </div>
                        <article v-for="(slide, index) in sectionContent(activeSection).slides" :key="index" class="space-y-3 rounded-md border border-slate-200 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h4 class="font-bold">Slide {{ index + 1 }}</h4>
                                <div class="flex gap-2">
                                    <button class="rounded-md border px-2 text-sm" type="button" @click="moveArrayItem(sectionContent(activeSection).slides, index, -1)">Up</button>
                                    <button class="rounded-md border px-2 text-sm" type="button" @click="moveArrayItem(sectionContent(activeSection).slides, index, 1)">Down</button>
                                    <button class="rounded-md border border-rose-300 px-2 text-sm text-rose-700" type="button" @click="removeArrayItem(sectionContent(activeSection).slides, index)">Remove</button>
                                </div>
                            </div>
                            <div class="grid gap-3 md:grid-cols-2">
                                <input v-model="slide.label" class="rounded-md border-slate-300 text-sm" type="text" placeholder="Label">
                                <input v-model="slide.headline" class="rounded-md border-slate-300 text-sm" type="text" placeholder="Headline">
                                <textarea v-model="slide.text" class="rounded-md border-slate-300 text-sm md:col-span-2" rows="3" placeholder="Supporting text"></textarea>
                                <input v-model="slide.primary_label" class="rounded-md border-slate-300 text-sm" type="text" placeholder="Primary button label">
                                <input v-model="slide.primary_url" class="rounded-md border-slate-300 text-sm" type="text" placeholder="Primary button URL">
                                <input v-model="slide.secondary_label" class="rounded-md border-slate-300 text-sm" type="text" placeholder="Secondary button label">
                                <input v-model="slide.secondary_url" class="rounded-md border-slate-300 text-sm" type="text" placeholder="Secondary button URL">
                            </div>
                            <MediaPicker v-model="slide.image" v-model:alt-value="slide.alt" :media="media" label="Slide image" :can-upload="can_manage_media" :upload-form="uploadForm" @upload="uploadMedia" @delete="deleteMedia" />
                            <label class="flex items-center gap-2 text-sm font-semibold"><input v-model="slide.active" type="checkbox"> Slide enabled</label>
                        </article>
                    </template>

                    <template v-else-if="activeSection.key === 'info_banner'">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-bold">Information Banner / Opening Hours</h3>
                            <button class="rounded-md border px-3 py-2 text-sm font-semibold" type="button" @click="addArrayItem((sectionContent(activeSection).items ||= []), { icon: 'info', heading: '', text: '', link_label: '', url: '' })">Add banner item</button>
                        </div>
                        <div v-for="(item, index) in sectionContent(activeSection).items" :key="index" class="grid gap-3 rounded-md border border-slate-200 p-4 md:grid-cols-2">
                            <input v-model="item.icon" class="rounded-md border-slate-300 text-sm" type="text" placeholder="Icon name">
                            <input v-model="item.heading" class="rounded-md border-slate-300 text-sm" type="text" placeholder="Heading">
                            <input v-model="item.text" class="rounded-md border-slate-300 text-sm md:col-span-2" type="text" placeholder="Text">
                            <input v-model="item.link_label" class="rounded-md border-slate-300 text-sm" type="text" placeholder="Link label">
                            <input v-model="item.url" class="rounded-md border-slate-300 text-sm" type="text" placeholder="URL">
                            <div class="flex gap-2 md:col-span-2">
                                <button class="rounded-md border px-2 text-sm" type="button" @click="moveArrayItem(sectionContent(activeSection).items, index, -1)">Up</button>
                                <button class="rounded-md border px-2 text-sm" type="button" @click="moveArrayItem(sectionContent(activeSection).items, index, 1)">Down</button>
                                <button class="rounded-md border border-rose-300 px-2 text-sm text-rose-700" type="button" @click="removeArrayItem(sectionContent(activeSection).items, index)">Remove</button>
                            </div>
                        </div>
                    </template>

                    <template v-else-if="activeSection.key === 'about'">
                        <h3 class="text-lg font-bold">About Section</h3>
                        <div class="grid gap-3 md:grid-cols-2">
                            <input v-model="sectionContent(activeSection).label" class="rounded-md border-slate-300 text-sm" type="text" placeholder="Kicker">
                            <input v-model="sectionContent(activeSection).heading" class="rounded-md border-slate-300 text-sm" type="text" placeholder="Heading">
                            <textarea v-model="sectionContent(activeSection).description" class="rounded-md border-slate-300 text-sm md:col-span-2" rows="4" placeholder="Description"></textarea>
                            <input v-model="sectionContent(activeSection).cta_label" class="rounded-md border-slate-300 text-sm" type="text" placeholder="CTA label">
                            <input v-model="sectionContent(activeSection).cta_url" class="rounded-md border-slate-300 text-sm" type="text" placeholder="CTA URL">
                        </div>
                        <MediaPicker v-model="sectionContent(activeSection).image" v-model:alt-value="sectionContent(activeSection).image_alt" :media="media" label="About image" :can-upload="can_manage_media" :upload-form="uploadForm" @upload="uploadMedia" @delete="deleteMedia" />
                    </template>

                    <template v-else-if="activeSection.key === 'why_choose_us'">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-bold">Trust / Benefit Items</h3>
                            <button class="rounded-md border px-3 py-2 text-sm font-semibold" type="button" @click="addArrayItem((sectionContent(activeSection).items ||= []), { icon: 'shield-check', heading: '', text: '' })">Add benefit</button>
                        </div>
                        <div class="grid gap-3 md:grid-cols-2">
                            <input v-model="sectionContent(activeSection).label" class="rounded-md border-slate-300 text-sm" type="text" placeholder="Kicker">
                            <input v-model="sectionContent(activeSection).heading" class="rounded-md border-slate-300 text-sm" type="text" placeholder="Heading">
                            <textarea v-model="sectionContent(activeSection).description" class="rounded-md border-slate-300 text-sm md:col-span-2" rows="2" placeholder="Description"></textarea>
                        </div>
                        <div v-for="(item, index) in sectionContent(activeSection).items" :key="index" class="grid gap-3 rounded-md border border-slate-200 p-4 md:grid-cols-2">
                            <input v-model="item.icon" class="rounded-md border-slate-300 text-sm" type="text" placeholder="Icon">
                            <input v-model="item.heading" class="rounded-md border-slate-300 text-sm" type="text" placeholder="Heading">
                            <textarea v-model="item.text" class="rounded-md border-slate-300 text-sm md:col-span-2" rows="2" placeholder="Text"></textarea>
                            <div class="flex gap-2 md:col-span-2">
                                <button class="rounded-md border px-2 text-sm" type="button" @click="moveArrayItem(sectionContent(activeSection).items, index, -1)">Up</button>
                                <button class="rounded-md border px-2 text-sm" type="button" @click="moveArrayItem(sectionContent(activeSection).items, index, 1)">Down</button>
                                <button class="rounded-md border border-rose-300 px-2 text-sm text-rose-700" type="button" @click="removeArrayItem(sectionContent(activeSection).items, index)">Remove</button>
                            </div>
                        </div>
                    </template>

                    <template v-else>
                        <h3 class="text-lg font-bold">{{ activeSection.label }}</h3>
                        <div class="grid gap-3 md:grid-cols-2">
                            <input v-model="sectionContent(activeSection).heading" class="rounded-md border-slate-300 text-sm" type="text" placeholder="Heading">
                            <input v-model="sectionContent(activeSection).description" class="rounded-md border-slate-300 text-sm" type="text" placeholder="Description">
                            <input v-if="activeSection.key === 'appointment_cta'" v-model="sectionContent(activeSection).button_label" class="rounded-md border-slate-300 text-sm" type="text" placeholder="Button label">
                            <input v-if="activeSection.key === 'appointment_cta'" v-model="sectionContent(activeSection).button_url" class="rounded-md border-slate-300 text-sm" type="text" placeholder="Button URL">
                            <textarea v-if="activeSection.key === 'appointment_cta'" v-model="sectionContent(activeSection).text" class="rounded-md border-slate-300 text-sm md:col-span-2" rows="3" placeholder="CTA text"></textarea>
                            <input v-if="activeSection.key === 'contact'" v-model="sectionContent(activeSection).address" class="rounded-md border-slate-300 text-sm" type="text" placeholder="Address">
                            <input v-if="activeSection.key === 'contact'" v-model="sectionContent(activeSection).phone" class="rounded-md border-slate-300 text-sm" type="text" placeholder="Phone">
                            <input v-if="activeSection.key === 'contact'" v-model="sectionContent(activeSection).email" class="rounded-md border-slate-300 text-sm" type="email" placeholder="Email">
                            <input v-if="activeSection.key === 'contact'" v-model="sectionContent(activeSection).hours" class="rounded-md border-slate-300 text-sm" type="text" placeholder="Hours">
                        </div>
                    </template>

                    <button class="rounded-md bg-slate-950 px-4 py-2 text-sm font-bold text-white" type="submit">Save section draft</button>
                </form>
            </section>

            <section v-if="activePanel === 'items'" class="space-y-6">
                <div v-for="type in item_types" :key="type" class="rounded-md border border-slate-200 bg-white p-5">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h3 class="text-lg font-bold">{{ itemLabels[type] || type }}</h3>
                        <button class="rounded-md border px-3 py-2 text-sm font-semibold" type="button" @click="createItem(type)">Add {{ type }}</button>
                    </div>
                    <div class="mt-4 grid gap-4">
                        <article v-for="item in itemsByType(type)" :key="item.id" class="rounded-md border border-slate-200 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <button class="text-left" type="button" @click="activeItemId = item.id">
                                    <span class="block font-bold">{{ item.title }}</span>
                                    <span class="mt-1 inline-flex rounded-full px-2 py-1 text-xs font-bold" :class="statusClass(item)">{{ item.is_enabled ? statusLabel(item) : 'Draft disabled' }}</span>
                                </button>
                                <div class="flex flex-wrap gap-2">
                                    <button class="rounded-md border px-2 py-1 text-sm" type="button" @click="moveRecord(itemsByType(type), item, -1)">Up</button>
                                    <button class="rounded-md border px-2 py-1 text-sm" type="button" @click="moveRecord(itemsByType(type), item, 1)">Down</button>
                                    <button class="rounded-md bg-teal-700 px-2 py-1 text-sm font-bold text-white" type="button" @click="publishItem(item)">Publish</button>
                                    <button class="rounded-md border border-rose-300 px-2 py-1 text-sm font-bold text-rose-700" type="button" @click="unpublishItem(item)">Unpublish</button>
                                    <button class="rounded-md border border-rose-300 px-2 py-1 text-sm text-rose-700" type="button" @click="removeItemFromDraft(item)">Remove from draft</button>
                                </div>
                            </div>

                            <form v-if="activeItemId === item.id" class="mt-4 grid gap-4" @submit.prevent="saveItem(item)">
                                <div class="grid gap-3 md:grid-cols-2">
                                    <input v-model="item.title" class="rounded-md border-slate-300 text-sm" type="text" placeholder="Title" required>
                                    <input v-model="item.slug" class="rounded-md border-slate-300 text-sm" type="text" placeholder="Slug">
                                    <textarea v-model="item.summary" class="rounded-md border-slate-300 text-sm md:col-span-2" rows="2" placeholder="Summary"></textarea>
                                    <label class="flex items-center gap-2 text-sm font-semibold"><input v-model="item.is_enabled" type="checkbox"> Enabled</label>
                                    <label class="flex items-center gap-2 text-sm font-semibold"><input v-model="item.is_featured" type="checkbox"> Featured</label>
                                </div>

                                <div v-if="type === 'service'" class="grid gap-3 md:grid-cols-2">
                                    <input v-model="item.draft_content.icon" class="rounded-md border-slate-300 text-sm" type="text" placeholder="Icon">
                                    <input v-model="item.draft_content.cta_label" class="rounded-md border-slate-300 text-sm" type="text" placeholder="CTA label">
                                    <input v-model="item.draft_content.cta_url" class="rounded-md border-slate-300 text-sm" type="text" placeholder="CTA URL">
                                    <textarea v-model="item.draft_content.description" class="rounded-md border-slate-300 text-sm md:col-span-2" rows="3" placeholder="Description"></textarea>
                                </div>

                                <div v-if="type === 'department'" class="grid gap-3 md:grid-cols-2">
                                    <input v-model="item.draft_content.icon" class="rounded-md border-slate-300 text-sm" type="text" placeholder="Icon">
                                    <input v-model="item.draft_content.public_title" class="rounded-md border-slate-300 text-sm" type="text" placeholder="Public title">
                                    <textarea v-model="item.draft_content.summary" class="rounded-md border-slate-300 text-sm md:col-span-2" rows="3" placeholder="Public summary"></textarea>
                                </div>

                                <div v-if="type === 'clinician'" class="grid gap-3">
                                    <div class="grid gap-3 md:grid-cols-2">
                                        <input v-model="item.draft_content.display_name" class="rounded-md border-slate-300 text-sm" type="text" placeholder="Display name">
                                        <input v-model="item.draft_content.professional_title" class="rounded-md border-slate-300 text-sm" type="text" placeholder="Professional title">
                                        <input v-model="item.draft_content.specialty" class="rounded-md border-slate-300 text-sm" type="text" placeholder="Specialty">
                                        <textarea v-model="item.draft_content.bio" class="rounded-md border-slate-300 text-sm md:col-span-2" rows="4" placeholder="Biography"></textarea>
                                    </div>
                                    <MediaPicker v-model="item.draft_content.photo" v-model:alt-value="item.draft_content.alt" :media="media" label="Clinician photo" :can-upload="can_manage_media" :upload-form="uploadForm" @upload="uploadMedia" @delete="deleteMedia" />
                                </div>

                                <div v-if="type === 'testimonial'" class="grid gap-3 md:grid-cols-2">
                                    <input v-model="item.draft_content.display_name" class="rounded-md border-slate-300 text-sm" type="text" placeholder="Display name">
                                    <input v-model="item.draft_content.context" class="rounded-md border-slate-300 text-sm" type="text" placeholder="Context">
                                    <textarea v-model="item.draft_content.text" class="rounded-md border-slate-300 text-sm md:col-span-2" rows="3" placeholder="Testimonial text"></textarea>
                                    <label class="flex items-center gap-2 text-sm font-semibold"><input v-model="item.draft_content.approved" type="checkbox"> Approved for publication</label>
                                </div>

                                <div v-if="type === 'article'" class="grid gap-3">
                                    <div class="grid gap-3 md:grid-cols-2">
                                        <input v-model="item.draft_content.author" class="rounded-md border-slate-300 text-sm" type="text" placeholder="Author">
                                        <input v-model="item.draft_content.published_on" class="rounded-md border-slate-300 text-sm" type="date">
                                        <textarea v-model="item.draft_content.excerpt" class="rounded-md border-slate-300 text-sm md:col-span-2" rows="2" placeholder="Excerpt"></textarea>
                                        <textarea v-model="item.draft_content.body" class="rounded-md border-slate-300 text-sm md:col-span-2" rows="6" placeholder="Article body"></textarea>
                                    </div>
                                    <MediaPicker v-model="item.draft_content.image" v-model:alt-value="item.draft_content.alt" :media="media" label="Article image" :can-upload="can_manage_media" :upload-form="uploadForm" @upload="uploadMedia" @delete="deleteMedia" />
                                </div>

                                <button class="rounded-md bg-slate-950 px-4 py-2 text-sm font-bold text-white" type="submit">Save item draft</button>
                            </form>
                        </article>
                    </div>
                </div>
            </section>

            <section v-if="activePanel === 'history'" class="rounded-md border border-slate-200 bg-white p-5">
                <h3 class="font-bold">Publishing History</h3>
                <div class="mt-4 divide-y divide-slate-200">
                    <div v-for="revision in pageModel.revisions" :key="revision.id" class="flex flex-col gap-3 py-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p class="font-semibold">Version {{ revision.version }} - {{ revision.action }}</p>
                            <p class="text-sm text-slate-600">{{ revision.created_at }} - {{ revision.creator?.firstname }} {{ revision.creator?.lastname }}</p>
                        </div>
                        <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold" type="button" @click="restoreRevision(revision)">Restore to draft</button>
                    </div>
                    <p v-if="pageModel.revisions.length === 0" class="py-3 text-sm text-slate-600">No revisions recorded yet.</p>
                </div>
            </section>

            <section v-if="can_view_json && showDiagnostics" class="rounded-md border border-slate-200 bg-white p-5">
                <h3 class="font-bold">Read-only diagnostics</h3>
                <pre class="mt-4 max-h-96 overflow-auto rounded-md bg-slate-950 p-4 text-xs text-slate-100">{{ JSON.stringify(pageModel, null, 2) }}</pre>
            </section>
        </div>

        <ConfirmDialog
            :show="Boolean(pendingConfirm)"
            :title="pendingConfirm?.title || 'Confirm Action'"
            :message="pendingConfirm?.message || ''"
            :form="confirmForm"
            :confirm-label="pendingConfirm?.label || 'Confirm'"
            @close="closeConfirm"
            @confirm="submitConfirm"
        />
    </AppLayout>
</template>
