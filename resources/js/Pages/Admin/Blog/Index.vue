<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
defineProps({ posts: { type: Array, required: true } });
</script>

<template>
  <AppLayout title="News & Blog">
    <div class="space-y-6">
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h2 class="text-2xl font-bold">Blog posts</h2>
          <p class="mt-1 text-sm text-slate-500">Create, edit, publish and moderate every hospital news article in one place.</p>
        </div>
        <Link href="/admin/news/create" class="rounded-lg bg-slate-950 px-4 py-3 text-sm font-bold text-white">+ Write new post</Link>
      </div>
      <div class="overflow-hidden rounded-xl border bg-white dark:border-slate-800 dark:bg-slate-900">
        <div v-for="post in posts" :key="post.id" class="flex flex-wrap items-center justify-between gap-4 border-b p-5 last:border-0 dark:border-slate-800">
          <div>
            <Link :href="`/admin/news/${post.id}/edit`" class="font-bold hover:underline">{{ post.title }}</Link>
            <div class="mt-2 text-xs text-slate-500">{{ post.slug }} · {{ post.status === 'published' ? 'Published' : 'Draft' }} <span v-if="post.published_at">· {{ post.published_at }}</span></div>
            <div v-if="post.comments" class="mt-2 text-xs font-semibold text-amber-700">{{ post.comments }} comments awaiting review</div>
          </div>
          <Link :href="`/admin/news/${post.id}/edit`" class="rounded-lg border px-4 py-2 text-sm font-semibold">Edit post & comments</Link>
        </div>
        <div v-if="posts.length === 0" class="p-10 text-center text-sm text-slate-500">No posts yet. Create a post to start the news blog.</div>
      </div>
      <p class="text-sm text-slate-500">Blog posts use the existing hospital website publishing system. Previous published articles remain available.</p>
    </div>
  </AppLayout>
</template>
