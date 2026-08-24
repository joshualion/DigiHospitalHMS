<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

defineProps({
    items: { type: Array, default: () => [] },
    batches: { type: Array, default: () => [] },
    locations: { type: Array, default: () => [] },
    adjustments: { type: Array, default: () => [] },
});

const form = useForm({ inventory_location_id: '', inventory_item_id: '', inventory_batch_id: '', quantity_delta: '', reason: '' });
</script>

<template>
    <Head title="Inventory Adjustments" />
    <AppLayout title="Inventory Adjustments">
        <div class="mb-4 flex flex-wrap gap-2">
            <Link href="/admin/inventory/stock" class="rounded-md border px-3 py-2 text-sm font-bold">Stock</Link>
            <Link href="/admin/inventory/transfers" class="rounded-md border px-3 py-2 text-sm font-bold">Transfers</Link>
            <Link href="/admin/inventory/reports" class="rounded-md border px-3 py-2 text-sm font-bold">Reports</Link>
        </div>
        <div class="grid gap-6 xl:grid-cols-[1fr_380px]">
            <section class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="font-black">Adjustment Requests</h2>
                <article v-for="adjustment in adjustments" :key="adjustment.id" class="mt-4 border-t pt-4 text-sm">
                    <p class="font-bold">{{ adjustment.item?.name }} - {{ adjustment.quantity_delta }} - {{ adjustment.status }}</p>
                    <p class="text-slate-500">{{ adjustment.reason }}</p>
                    <button class="mt-2 rounded-md border px-2 py-1 text-xs font-bold" type="button" @click="router.patch(`/admin/inventory/adjustments/${adjustment.id}/approve`, {}, { preserveScroll: true })">Approve</button>
                </article>
            </section>
            <form class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="form.post('/admin/inventory/adjustments', { preserveScroll: true, onSuccess: () => form.reset() })">
                <h2 class="font-black">Request Adjustment</h2>
                <div class="mt-4 grid gap-3">
                    <select v-model="form.inventory_location_id" class="rounded-md border-slate-300"><option value="">Location</option><option v-for="location in locations" :key="location.id" :value="location.id">{{ location.name }}</option></select>
                    <select v-model="form.inventory_item_id" class="rounded-md border-slate-300"><option value="">Item</option><option v-for="item in items" :key="item.id" :value="item.id">{{ item.name }}</option></select>
                    <select v-model="form.inventory_batch_id" class="rounded-md border-slate-300"><option value="">Batch</option><option v-for="batch in batches" :key="batch.id" :value="batch.id">{{ batch.batch_number }} - {{ batch.item?.name }}</option></select>
                    <TextInput id="quantity_delta" v-model="form.quantity_delta" label="Quantity delta" type="number" />
                    <textarea v-model="form.reason" class="rounded-md border-slate-300" rows="2" placeholder="Reason"></textarea>
                    <PrimaryButton :disabled="form.processing">Request adjustment</PrimaryButton>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
