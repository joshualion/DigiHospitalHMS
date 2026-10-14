<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
  post: { type: Object, default: null },
  media: { type: Array, default: () => [] },
  revisions: { type: Array, default: () => [] },
  comments: { type: Array, default: () => [] },
  can_publish: { type: Boolean, default: false },
});
const page = usePage();
const form = useForm({
  title: props.post?.title || '', slug: props.post?.slug || '', summary: props.post?.summary || '',
  subtitle: props.post?.content?.subtitle || '', body: props.post?.content?.body || '',
  author: props.post?.content?.author || '', category: props.post?.content?.category || '',
  tags: props.post?.content?.tags || '', seo_title: props.post?.content?.seo_title || '',
  seo_description: props.post?.content?.seo_description || '',
  cover: props.post?.content?.image || '', cover_alt: props.post?.content?.alt || '',
  thumbnail: props.post?.content?.thumbnail || '', thumbnail_alt: props.post?.content?.thumbnail_alt || '',
  comments_enabled: props.post?.content?.comments_enabled === true,
  is_featured: props.post?.is_featured || false,
});
const upload = useForm({ title: '', alt_text: '', caption: '', credit: '', image: null });
const uploadTarget = ref('cover');
const availableMedia = computed(() => props.media.map((asset) => ({ ...asset, reference: `/public-site/media/${asset.id}` })));
const preview = computed(() => props.post?.status === 'published' ? `/news/${props.post?.slug}` : null);

function save() {
  if (props.post) form.patch(`/admin/news/${props.post.id}`, { preserveScroll: true });
  else form.post('/admin/news');
}
function publish() {
  if (!props.post || form.isDirty) return;
  router.post(`/admin/news/${props.post.id}/publish`, {}, { preserveScroll: true });
}
function unpublish() {
  if (!props.post || !window.confirm('Unpublish this post?')) return;
  router.post(`/admin/news/${props.post.id}/unpublish`, {}, { preserveScroll: true });
}
function restore(revision) {
  if (!window.confirm(`Restore revision ${revision.version} into the draft editor?`)) return;
  router.post(`/admin/news/${props.post.id}/revisions/${revision.id}/restore`, {}, { preserveScroll: true });
}
function moderate(comment, status) {
  router.patch(`/admin/news/comments/${comment.id}`, { status, note: '' }, { preserveScroll: true });
}
function uploadImage() {
  upload.post('/admin/public-website/media', {
    forceFormData: true, preserveScroll: true,
    onSuccess: () => {
      const media = page.props.flash?.uploaded_media;
      if (media?.id) form[uploadTarget.value] = `/public-site/media/${media.id}`;
      upload.reset();
    },
  });
}
</script>

<template>
  <AppLayout :title="post ? 'Edit blog post' : 'New blog post'">
    <div class="mx-auto max-w-6xl space-y-6">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
          <Link href="/admin/news" class="text-sm font-semibold text-slate-500 hover:underline">← All blog posts</Link>
          <h2 class="mt-2 text-2xl font-bold">{{ post ? 'Edit post' : 'Write a new post' }}</h2>
          <p class="mt-1 text-sm text-slate-500">Your draft is separate from the published article. Save changes before publishing.</p>
        </div>
        <div v-if="post" class="flex flex-wrap gap-2">
          <a v-if="preview" :href="preview" target="_blank" rel="noopener" class="rounded-lg border px-3 py-2 text-sm font-semibold">View published post ↗</a>
          <button v-if="post.status === 'published'" type="button" class="rounded-lg border px-3 py-2 text-sm font-semibold" @click="unpublish">Unpublish</button>
          <button v-if="can_publish" type="button" :disabled="form.isDirty" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-bold text-white disabled:opacity-50" @click="publish">Publish saved draft</button>
        </div>
      </div>

      <form class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_19rem]" @submit.prevent="save">
        <div class="space-y-6">
          <section class="space-y-4 rounded-xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <h3 class="text-lg font-bold">Article content</h3>
            <label class="block text-sm font-semibold">Post title<input v-model="form.title" maxlength="255" required class="mt-1 w-full rounded-lg border-slate-300" placeholder="Write a clear headline" /></label>
            <label class="block text-sm font-semibold">Subtitle<input v-model="form.subtitle" maxlength="300" class="mt-1 w-full rounded-lg border-slate-300" placeholder="Optional standfirst beneath the title" /></label>
            <label class="block text-sm font-semibold">Short excerpt<textarea v-model="form.summary" maxlength="1000" rows="3" class="mt-1 w-full rounded-lg border-slate-300" placeholder="Shown on the news listing and shared links" /></label>
            <label class="block text-sm font-semibold">Post body<textarea v-model="form.body" required rows="18" class="mt-1 w-full rounded-lg border-slate-300 font-sans leading-relaxed" placeholder="Write the full article. Separate paragraphs with blank lines." /></label>
            <p class="text-xs text-slate-500">Content is saved safely as text. Paragraph breaks are preserved on the public site.</p>
          </section>
          <section class="space-y-4 rounded-xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <h3 class="text-lg font-bold">Search engine optimization</h3>
            <label class="block text-sm font-semibold">Search title<input v-model="form.seo_title" maxlength="255" class="mt-1 w-full rounded-lg border-slate-300" placeholder="Defaults to post title" /></label>
            <label class="block text-sm font-semibold">Meta description<textarea v-model="form.seo_description" rows="3" maxlength="320" class="mt-1 w-full rounded-lg border-slate-300" placeholder="Concise search-result description" /></label>
            <label class="block text-sm font-semibold">Permalink / URL slug<input v-model="form.slug" maxlength="255" class="mt-1 w-full rounded-lg border-slate-300" placeholder="Generated automatically from title when blank" /></label>
          </section>
          <section class="space-y-4 rounded-xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <h3 class="text-lg font-bold">Images</h3>
            <p class="text-sm text-slate-500">Upload using the media library, then select a cover or thumbnail. An omitted thumbnail automatically uses the cover image.</p>
            <label class="block text-sm font-semibold">Cover image
              <select v-model="form.cover" class="mt-1 w-full rounded-lg border-slate-300"><option value="">No cover image</option><option v-for="asset in availableMedia" :key="asset.id" :value="asset.reference">{{ asset.title || 'Image #' + asset.id }}</option></select>
            </label>
            <label class="block text-sm font-semibold">Cover image alternative text<input v-model="form.cover_alt" class="mt-1 w-full rounded-lg border-slate-300" placeholder="Describe the image for screen readers" /></label>
            <label class="block text-sm font-semibold">Listing thumbnail
              <select v-model="form.thumbnail" class="mt-1 w-full rounded-lg border-slate-300"><option value="">Use cover image</option><option v-for="asset in availableMedia" :key="asset.id" :value="asset.reference">{{ asset.title || 'Image #' + asset.id }}</option></select>
            </label>
            <label class="block text-sm font-semibold">Thumbnail alternative text<input v-model="form.thumbnail_alt" class="mt-1 w-full rounded-lg border-slate-300" /></label>
            <div class="grid gap-3 rounded-lg border border-dashed p-4">
              <h4 class="font-semibold">Upload an image</h4>
              <input v-model="upload.title" class="w-full rounded-lg border-slate-300 text-sm" placeholder="Image title" />
              <input v-model="upload.alt_text" class="w-full rounded-lg border-slate-300 text-sm" placeholder="Alternative text" />
              <input type="file" accept="image/jpeg,image/png,image/webp" @change="upload.image = $event.target.files?.[0] || null" />
              <select v-model="uploadTarget" class="rounded-lg border-slate-300 text-sm"><option value="cover">Use as cover</option><option value="thumbnail">Use as thumbnail</option></select>
              <button type="button" :disabled="!upload.image || upload.processing" class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-bold text-white disabled:opacity-50" @click="uploadImage">Upload to media library</button>
              <p v-if="upload.errors.image" class="text-xs text-red-700">{{ upload.errors.image }}</p>
              <p class="text-xs text-slate-500">New uploads appear in the library after refreshing this page.</p>
            </div>
          </section>
        </div>
        <div class="space-y-6">
          <section class="space-y-4 rounded-xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <h3 class="font-bold">Publishing settings</h3>
            <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">{{ post?.status === 'published' ? 'Published' : 'Draft' }}</span>
            <label class="block text-sm font-semibold">Author<input v-model="form.author" class="mt-1 w-full rounded-lg border-slate-300" placeholder="Hospital editorial team" /></label>
            <label class="block text-sm font-semibold">Category<input v-model="form.category" class="mt-1 w-full rounded-lg border-slate-300" placeholder="Hospital news, health education…" /></label>
            <label class="block text-sm font-semibold">Tags<input v-model="form.tags" class="mt-1 w-full rounded-lg border-slate-300" placeholder="Comma-separated keywords" /></label>
            <label class="flex items-center gap-3 text-sm font-semibold"><input v-model="form.is_featured" type="checkbox" /> Feature this post</label>
            <label class="flex items-center gap-3 text-sm font-semibold"><input v-model="form.comments_enabled" type="checkbox" /> Accept public comments (review required)</label>
            <button type="submit" :disabled="form.processing" class="w-full rounded-lg bg-slate-950 px-5 py-3 font-bold text-white disabled:opacity-50">Save draft</button>
            <div v-if="Object.keys(form.errors).length" class="text-sm text-red-700"><p v-for="(error, key) in form.errors" :key="key">{{ error }}</p></div>
          </section>
        </div>
      </form>

      <section v-if="post" class="space-y-4 rounded-xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
        <h3 class="text-lg font-bold">Comment moderation</h3>
        <p class="text-sm text-slate-500">Comments remain hidden until approved. You can change their review status at any time; changes are written to the audit log.</p>
        <div v-for="comment in comments" :key="comment.id" class="space-y-3 border-b py-4 last:border-0">
          <div class="flex flex-wrap items-center justify-between gap-2"><strong>{{ comment.name }}</strong><span class="text-xs font-bold">{{ comment.status }}</span></div>
          <p class="whitespace-pre-line text-sm">{{ comment.body }}</p>
          <div class="flex flex-wrap gap-2">
            <button v-for="status in ['approved','rejected','pending']" :key="status" type="button" class="rounded-lg border px-3 py-2 text-xs font-semibold" @click="moderate(comment, status)">{{ status === 'approved' ? 'Approve' : status === 'rejected' ? 'Reject / hide' : 'Reset to pending' }}</button>
          </div>
        </div>
        <p v-if="!comments.length" class="text-sm text-slate-500">No comments yet.</p>
      </section>

      <section v-if="post" class="rounded-xl border bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
        <h3 class="font-bold">Post publishing history</h3>
        <div v-for="revision in revisions" :key="revision.id" class="flex flex-wrap items-center justify-between gap-3 border-b py-3 last:border-0">
          <p class="text-sm">Version {{ revision.version }} · {{ revision.action }}</p>
          <button type="button" class="rounded-lg border px-3 py-2 text-xs font-semibold" @click="restore(revision)">Restore to draft</button>
        </div>
        <p v-if="!revisions.length" class="mt-3 text-sm text-slate-500">No published revisions yet.</p>
      </section>
    </div>
  </AppLayout>
</template>
