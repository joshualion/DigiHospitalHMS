<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    lowStock: { type: Array, default: () => [] },
    nearExpiry: { type: Array, default: () => [] },
    expired: { type: Array, default: () => [] },
    fefo: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="Inventory Reports" />
    <AppLayout title="Inventory Reports">
        <div class="mb-4 flex flex-wrap gap-2">
            <Link href="/admin/inventory/stock" class="rounded-md border px-3 py-2 text-sm font-bold">Stock</Link>
            <Link href="/admin/inventory/catalogue" class="rounded-md border px-3 py-2 text-sm font-bold">Catalogue</Link>
        </div>
        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="font-black">Low Stock</h2>
                <p v-for="row in lowStock" :key="row.item.id" class="mt-3 text-sm">{{ row.item.name }} - {{ row.quantity }} / reorder {{ row.item.reorder_level }}</p>
            </section>
            <section class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="font-black">Near Expiry</h2>
                <p v-for="batch in nearExpiry" :key="batch.id" class="mt-3 text-sm">{{ batch.item?.name }} - {{ batch.batch_number }} - {{ batch.expiry_date }}</p>
            </section>
            <section class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="font-black">Expired</h2>
                <p v-for="batch in expired" :key="batch.id" class="mt-3 text-sm">{{ batch.item?.name }} - {{ batch.batch_number }} - {{ batch.expiry_date }}</p>
            </section>
            <section class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="font-black">FEFO Suggestions</h2>
                <article v-for="row in fefo" :key="row.item.id" class="mt-3 text-sm">
                    <p class="font-bold">{{ row.item.name }}</p>
                    <p v-for="balance in row.batches" :key="balance.id" class="text-slate-500">{{ balance.batch?.batch_number }} - {{ balance.quantity }} - expires {{ balance.batch?.expiry_date || 'none' }}</p>
                </article>
            </section>
        </div>
    </AppLayout>
</template>
