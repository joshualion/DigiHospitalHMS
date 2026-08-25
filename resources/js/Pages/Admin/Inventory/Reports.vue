<script setup>
import ActionToolbar from '@/Components/Admin/ActionToolbar.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
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
        <PageHeader title="Inventory Reports" description="Low stock, expiry, reorder and FEFO reporting.">
            <template #actions>
                <ActionToolbar align="end">
                    <Link href="/admin/inventory/stock" class="rounded-md border px-3 py-2 text-sm font-bold">Stock</Link>
                    <Link href="/admin/inventory/catalogue" class="rounded-md border px-3 py-2 text-sm font-bold">Catalogue</Link>
                </ActionToolbar>
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="min-w-0 rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="font-black">Low Stock</h2>
                <p v-for="row in lowStock" :key="row.item.id" class="mt-3 break-words text-sm">{{ row.item.name }} - {{ row.quantity }} / reorder {{ row.item.reorder_level }}</p>
                <p v-if="lowStock.length === 0" class="mt-3 text-sm text-slate-500">No low stock items.</p>
            </section>
            <section class="min-w-0 rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="font-black">Near Expiry</h2>
                <p v-for="batch in nearExpiry" :key="batch.id" class="mt-3 break-words text-sm">{{ batch.item?.name }} - {{ batch.batch_number }} - {{ batch.expiry_date }}</p>
                <p v-if="nearExpiry.length === 0" class="mt-3 text-sm text-slate-500">No near-expiry batches.</p>
            </section>
            <section class="min-w-0 rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="font-black">Expired</h2>
                <p v-for="batch in expired" :key="batch.id" class="mt-3 break-words text-sm">{{ batch.item?.name }} - {{ batch.batch_number }} - {{ batch.expiry_date }}</p>
                <p v-if="expired.length === 0" class="mt-3 text-sm text-slate-500">No expired batches.</p>
            </section>
            <section class="min-w-0 rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="font-black">FEFO Suggestions</h2>
                <article v-for="row in fefo" :key="row.item.id" class="mt-3 min-w-0 text-sm">
                    <p class="break-words font-bold">{{ row.item.name }}</p>
                    <p v-for="balance in row.batches" :key="balance.id" class="break-words text-slate-500">{{ balance.batch?.batch_number }} - {{ balance.quantity }} - expires {{ balance.batch?.expiry_date || 'none' }}</p>
                </article>
                <p v-if="fefo.length === 0" class="mt-3 text-sm text-slate-500">No FEFO suggestions.</p>
            </section>
        </div>
    </AppLayout>
</template>
