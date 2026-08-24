<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    locations: { type: Array, default: () => [] },
    units: { type: Array, default: () => [] },
    items: { type: Array, default: () => [] },
    batches: { type: Array, default: () => [] },
    balances: { type: Array, default: () => [] },
    movements: { type: Array, default: () => [] },
});

const receive = useForm({ inventory_location_id: '', inventory_item_id: '', inventory_unit_id: '', batch_number: '', manufacture_date: '', expiry_date: '', supplier_reference: '', unit_cost_minor: '', currency: 'NGN', state: 'available', quantity: '', reason: 'Opening balance' });

function stateBatch(batch, state) {
    const reason = window.prompt('Reason');
    if (!reason) return;
    router.patch(`/admin/inventory/batches/${batch.id}/state`, { state, reason }, { preserveScroll: true });
}
</script>

<template>
    <Head title="Inventory Stock" />
    <AppLayout title="Inventory Stock">
        <div class="mb-4 flex flex-wrap gap-2">
            <Link href="/admin/inventory/catalogue" class="rounded-md border px-3 py-2 text-sm font-bold">Catalogue</Link>
            <Link href="/admin/inventory/transfers" class="rounded-md border px-3 py-2 text-sm font-bold">Transfers</Link>
            <Link href="/admin/inventory/adjustments" class="rounded-md border px-3 py-2 text-sm font-bold">Adjustments</Link>
            <Link href="/admin/inventory/reports" class="rounded-md border px-3 py-2 text-sm font-bold">Reports</Link>
        </div>
        <div class="grid gap-6 xl:grid-cols-[1fr_380px]">
            <section class="space-y-6">
                <section class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="font-black">Balances</h2>
                    <div class="mt-4 divide-y divide-slate-100 dark:divide-slate-800">
                        <article v-for="balance in balances" :key="balance.id" class="py-3">
                            <p class="font-bold">{{ balance.item?.name }} - {{ balance.quantity }} {{ balance.item?.base_unit?.code }}</p>
                            <p class="text-sm text-slate-500">{{ balance.location?.name }} - batch {{ balance.batch?.batch_number }} - {{ balance.batch?.state }}</p>
                        </article>
                    </div>
                </section>
                <section class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="font-black">Movements</h2>
                    <article v-for="movement in movements" :key="movement.id" class="mt-3 text-sm">
                        <p class="font-bold">{{ movement.movement_type }} - {{ movement.item?.name }} - {{ movement.base_quantity }}</p>
                        <p class="text-slate-500">{{ movement.reason }}</p>
                    </article>
                </section>
            </section>
            <aside class="space-y-6">
                <form class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="receive.post('/admin/inventory/batches/receive', { preserveScroll: true, onSuccess: () => receive.reset() })">
                    <h2 class="font-black">Batch Receipt / Opening Balance</h2>
                    <div class="mt-4 grid gap-3">
                        <select v-model="receive.inventory_location_id" class="rounded-md border-slate-300"><option value="">Location</option><option v-for="location in locations" :key="location.id" :value="location.id">{{ location.name }}</option></select>
                        <select v-model="receive.inventory_item_id" class="rounded-md border-slate-300"><option value="">Item</option><option v-for="item in items" :key="item.id" :value="item.id">{{ item.sku }} - {{ item.name }}</option></select>
                        <select v-model="receive.inventory_unit_id" class="rounded-md border-slate-300"><option value="">Unit</option><option v-for="unit in units" :key="unit.id" :value="unit.id">{{ unit.code }}</option></select>
                        <TextInput id="batch_number" v-model="receive.batch_number" label="Batch / lot" />
                        <TextInput id="quantity" v-model="receive.quantity" label="Quantity" type="number" />
                        <TextInput id="expiry_date" v-model="receive.expiry_date" label="Expiry" type="date" />
                        <TextInput id="unit_cost_minor" v-model="receive.unit_cost_minor" label="Unit cost minor" type="number" />
                        <select v-model="receive.state" class="rounded-md border-slate-300"><option value="available">Available</option><option value="quarantine">Quarantine</option></select>
                        <textarea v-model="receive.reason" class="rounded-md border-slate-300" rows="2" placeholder="Reason"></textarea>
                        <PrimaryButton :disabled="receive.processing">Receive batch</PrimaryButton>
                    </div>
                </form>
                <section class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="font-black">Batch States</h2>
                    <article v-for="batch in batches" :key="batch.id" class="mt-3 text-sm">
                        <p class="font-bold">{{ batch.item?.name }} - {{ batch.batch_number }}</p>
                        <p class="text-slate-500">{{ batch.state }} - expiry {{ batch.expiry_date || 'none' }}</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button class="rounded-md border px-2 py-1 text-xs font-bold" type="button" @click="stateBatch(batch, 'available')">Available</button>
                            <button class="rounded-md border px-2 py-1 text-xs font-bold" type="button" @click="stateBatch(batch, 'damaged')">Damaged</button>
                            <button class="rounded-md border px-2 py-1 text-xs font-bold" type="button" @click="stateBatch(batch, 'recalled')">Recalled</button>
                        </div>
                    </article>
                </section>
            </aside>
        </div>
    </AppLayout>
</template>
