<script setup>
import ActionToolbar from '@/Components/Admin/ActionToolbar.vue';
import ConfirmDialog from '@/Components/Admin/ConfirmDialog.vue';
import FormModal from '@/Components/Admin/FormModal.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    locations: { type: Array, default: () => [] },
    units: { type: Array, default: () => [] },
    items: { type: Array, default: () => [] },
    batches: { type: Array, default: () => [] },
    balances: { type: Array, default: () => [] },
    movements: { type: Array, default: () => [] },
});

const receive = useForm({ inventory_location_id: '', inventory_item_id: '', inventory_unit_id: '', batch_number: '', manufacture_date: '', expiry_date: '', supplier_reference: '', unit_cost_minor: '', currency: 'NGN', state: 'available', quantity: '', reason: 'Opening balance' });
const stateForm = useForm({ state: '', reason: '' });
const activeModal = ref(null);
const stateTarget = ref(null);

function saveReceive() {
    receive.post('/admin/inventory/batches/receive', { preserveScroll: true, onSuccess: () => { activeModal.value = null; receive.reset(); } });
}

function openState(batch, state) {
    stateTarget.value = batch;
    stateForm.defaults({ state, reason: '' });
    stateForm.reset();
    activeModal.value = 'state';
}

function saveState() {
    stateForm.patch(`/admin/inventory/batches/${stateTarget.value.id}/state`, { preserveScroll: true, onSuccess: () => { activeModal.value = null; stateForm.reset(); } });
}
</script>

<template>
    <Head title="Inventory Stock" />
    <AppLayout title="Inventory Stock">
        <PageHeader title="Inventory Stock" description="Batches, balances and stock ledger movements.">
            <template #actions>
                <ActionToolbar align="end">
                    <Link href="/admin/inventory/catalogue" class="rounded-md border px-3 py-2 text-sm font-bold">Catalogue</Link>
                    <Link href="/admin/inventory/transfers" class="rounded-md border px-3 py-2 text-sm font-bold">Transfers</Link>
                    <Link href="/admin/inventory/adjustments" class="rounded-md border px-3 py-2 text-sm font-bold">Adjustments</Link>
                    <Link href="/admin/inventory/reports" class="rounded-md border px-3 py-2 text-sm font-bold">Reports</Link>
                    <PrimaryButton type="button" @click="activeModal = 'receive'">Receive Batch</PrimaryButton>
                </ActionToolbar>
            </template>
        </PageHeader>

        <div class="grid gap-6">
            <section class="min-w-0 rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="font-black">Balances</h2>
                <div class="mt-4 divide-y divide-slate-100 dark:divide-slate-800">
                    <article v-for="balance in balances" :key="balance.id" class="min-w-0 py-3">
                        <p class="break-words font-bold">{{ balance.item?.name }} - {{ balance.quantity }} {{ balance.item?.base_unit?.code }}</p>
                        <p class="break-words text-sm text-slate-500">{{ balance.location?.name }} - batch {{ balance.batch?.batch_number }} - {{ balance.batch?.state }}</p>
                    </article>
                    <p v-if="balances.length === 0" class="py-4 text-sm text-slate-500">No balances recorded.</p>
                </div>
            </section>

            <section class="min-w-0 rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="font-black">Batch States</h2>
                <div class="mt-4 divide-y divide-slate-100 dark:divide-slate-800">
                    <article v-for="batch in batches" :key="batch.id" class="grid min-w-0 gap-3 py-3 md:grid-cols-[1fr_auto]">
                        <div class="min-w-0">
                            <p class="break-words font-bold">{{ batch.item?.name }} - {{ batch.batch_number }}</p>
                            <p class="break-words text-sm text-slate-500">{{ batch.state }} - expiry {{ batch.expiry_date || 'none' }}</p>
                        </div>
                        <ActionToolbar align="end">
                            <button class="rounded-md border px-2 py-1 text-xs font-bold" type="button" @click="openState(batch, 'available')">Available</button>
                            <button class="rounded-md border px-2 py-1 text-xs font-bold" type="button" @click="openState(batch, 'damaged')">Damaged</button>
                            <button class="rounded-md border px-2 py-1 text-xs font-bold" type="button" @click="openState(batch, 'recalled')">Recalled</button>
                        </ActionToolbar>
                    </article>
                </div>
            </section>

            <section class="min-w-0 rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="font-black">Movements</h2>
                <article v-for="movement in movements" :key="movement.id" class="mt-3 min-w-0 text-sm">
                    <p class="break-words font-bold">{{ movement.movement_type }} - {{ movement.item?.name }} - {{ movement.base_quantity }}</p>
                    <p class="break-words text-slate-500">{{ movement.reason }}</p>
                </article>
                <p v-if="movements.length === 0" class="mt-3 text-sm text-slate-500">No movements recorded.</p>
            </section>
        </div>

        <FormModal :show="activeModal === 'receive'" title="Receive Batch / Opening Balance" :form="receive" submit-label="Receive batch" size="full" @close="activeModal = null" @submit="saveReceive">
            <div class="grid gap-3 md:grid-cols-2">
                <select v-model="receive.inventory_location_id" class="rounded-md border-slate-300"><option value="">Location</option><option v-for="location in locations" :key="location.id" :value="location.id">{{ location.name }}</option></select>
                <select v-model="receive.inventory_item_id" class="rounded-md border-slate-300"><option value="">Item</option><option v-for="item in items" :key="item.id" :value="item.id">{{ item.sku }} - {{ item.name }}</option></select>
                <select v-model="receive.inventory_unit_id" class="rounded-md border-slate-300"><option value="">Unit</option><option v-for="unit in units" :key="unit.id" :value="unit.id">{{ unit.code }}</option></select>
                <TextInput id="batch_number" v-model="receive.batch_number" label="Batch / lot" />
                <TextInput id="quantity" v-model="receive.quantity" label="Quantity" type="number" />
                <TextInput id="manufacture_date" v-model="receive.manufacture_date" label="Manufacture date" type="date" />
                <TextInput id="expiry_date" v-model="receive.expiry_date" label="Expiry" type="date" />
                <TextInput id="supplier_reference" v-model="receive.supplier_reference" label="Supplier reference" />
                <TextInput id="unit_cost_minor" v-model="receive.unit_cost_minor" label="Unit cost minor" type="number" />
                <TextInput id="receive_currency" v-model="receive.currency" label="Currency" maxlength="3" />
                <select v-model="receive.state" class="rounded-md border-slate-300"><option value="available">Available</option><option value="quarantine">Quarantine</option></select>
                <textarea v-model="receive.reason" class="rounded-md border-slate-300 md:col-span-2" rows="2" placeholder="Reason"></textarea>
            </div>
        </FormModal>

        <ConfirmDialog :show="activeModal === 'state'" :title="`Mark batch ${stateForm.state}`" confirm-label="Save state" :form="stateForm" require-reason @close="activeModal = null" @confirm="saveState" />
    </AppLayout>
</template>
