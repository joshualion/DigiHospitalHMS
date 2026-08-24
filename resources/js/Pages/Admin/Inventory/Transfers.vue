<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

defineProps({
    items: { type: Array, default: () => [] },
    batches: { type: Array, default: () => [] },
    locations: { type: Array, default: () => [] },
    transfers: { type: Array, default: () => [] },
    balances: { type: Array, default: () => [] },
});

const form = useForm({ inventory_item_id: '', inventory_batch_id: '', from_location_id: '', to_location_id: '', quantity: '', reason: '' });

function action(transfer, name) {
    const reason = name === 'cancel' ? window.prompt('Cancellation reason') : null;
    if (name === 'cancel' && !reason) return;
    router.patch(`/admin/inventory/transfers/${transfer.id}`, { action: name, reason }, { preserveScroll: true });
}
</script>

<template>
    <Head title="Inventory Transfers" />
    <AppLayout title="Inventory Transfers">
        <div class="mb-4 flex flex-wrap gap-2">
            <Link href="/admin/inventory/stock" class="rounded-md border px-3 py-2 text-sm font-bold">Stock</Link>
            <Link href="/admin/inventory/adjustments" class="rounded-md border px-3 py-2 text-sm font-bold">Adjustments</Link>
            <Link href="/admin/inventory/reports" class="rounded-md border px-3 py-2 text-sm font-bold">Reports</Link>
        </div>
        <div class="grid gap-6 xl:grid-cols-[1fr_380px]">
            <section class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="font-black">Transfer History</h2>
                <article v-for="transfer in transfers" :key="transfer.id" class="mt-4 border-t pt-4 text-sm">
                    <p class="font-bold">{{ transfer.item?.name }} - {{ transfer.quantity }} - {{ transfer.status }}</p>
                    <p class="text-slate-500">Batch {{ transfer.batch?.batch_number }} - {{ transfer.reason }}</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <button class="rounded-md border px-2 py-1 text-xs font-bold" type="button" @click="action(transfer, 'dispatch')">Dispatch</button>
                        <button class="rounded-md border px-2 py-1 text-xs font-bold" type="button" @click="action(transfer, 'receive')">Receive</button>
                        <button class="rounded-md border px-2 py-1 text-xs font-bold" type="button" @click="action(transfer, 'cancel')">Cancel</button>
                    </div>
                </article>
            </section>
            <form class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="form.post('/admin/inventory/transfers', { preserveScroll: true, onSuccess: () => form.reset() })">
                <h2 class="font-black">Request Transfer</h2>
                <div class="mt-4 grid gap-3">
                    <select v-model="form.inventory_item_id" class="rounded-md border-slate-300"><option value="">Item</option><option v-for="item in items" :key="item.id" :value="item.id">{{ item.name }}</option></select>
                    <select v-model="form.inventory_batch_id" class="rounded-md border-slate-300"><option value="">Batch</option><option v-for="batch in batches" :key="batch.id" :value="batch.id">{{ batch.batch_number }} - {{ batch.item?.name }}</option></select>
                    <select v-model="form.from_location_id" class="rounded-md border-slate-300"><option value="">From</option><option v-for="location in locations" :key="location.id" :value="location.id">{{ location.name }}</option></select>
                    <select v-model="form.to_location_id" class="rounded-md border-slate-300"><option value="">To</option><option v-for="location in locations" :key="location.id" :value="location.id">{{ location.name }}</option></select>
                    <TextInput id="transfer_qty" v-model="form.quantity" label="Quantity" type="number" />
                    <textarea v-model="form.reason" class="rounded-md border-slate-300" rows="2" placeholder="Reason"></textarea>
                    <PrimaryButton :disabled="form.processing">Request transfer</PrimaryButton>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
