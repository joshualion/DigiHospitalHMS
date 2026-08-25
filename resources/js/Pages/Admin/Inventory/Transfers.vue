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

defineProps({
    items: { type: Array, default: () => [] },
    batches: { type: Array, default: () => [] },
    locations: { type: Array, default: () => [] },
    transfers: { type: Array, default: () => [] },
    balances: { type: Array, default: () => [] },
});

const form = useForm({ inventory_item_id: '', inventory_batch_id: '', from_location_id: '', to_location_id: '', quantity: '', reason: '' });
const actionForm = useForm({ action: '', reason: '' });
const activeModal = ref(null);
const actionTarget = ref(null);

function saveTransfer() {
    form.post('/admin/inventory/transfers', { preserveScroll: true, onSuccess: () => { activeModal.value = null; form.reset(); } });
}

function openAction(transfer, name) {
    actionTarget.value = transfer;
    actionForm.defaults({ action: name, reason: '' });
    actionForm.reset();
    activeModal.value = 'action';
}

function saveAction() {
    actionForm.patch(`/admin/inventory/transfers/${actionTarget.value.id}`, { preserveScroll: true, onSuccess: () => { activeModal.value = null; actionForm.reset(); } });
}
</script>

<template>
    <Head title="Inventory Transfers" />
    <AppLayout title="Inventory Transfers">
        <PageHeader title="Inventory Transfers" description="Transfer requests, dispatch and receipt workflow.">
            <template #actions>
                <ActionToolbar align="end">
                    <Link href="/admin/inventory/stock" class="rounded-md border px-3 py-2 text-sm font-bold">Stock</Link>
                    <Link href="/admin/inventory/adjustments" class="rounded-md border px-3 py-2 text-sm font-bold">Adjustments</Link>
                    <Link href="/admin/inventory/reports" class="rounded-md border px-3 py-2 text-sm font-bold">Reports</Link>
                    <PrimaryButton type="button" @click="activeModal = 'transfer'">Request Transfer</PrimaryButton>
                </ActionToolbar>
            </template>
        </PageHeader>

        <section class="min-w-0 rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="font-black">Transfer History</h2>
            <div class="mt-4 divide-y divide-slate-100 dark:divide-slate-800">
                <article v-for="transfer in transfers" :key="transfer.id" class="grid min-w-0 gap-3 py-3 md:grid-cols-[1fr_auto]">
                    <div class="min-w-0">
                        <p class="break-words font-bold">{{ transfer.item?.name }} - {{ transfer.quantity }} - {{ transfer.status }}</p>
                        <p class="break-words text-sm text-slate-500">Batch {{ transfer.batch?.batch_number }} - {{ transfer.reason }}</p>
                    </div>
                    <ActionToolbar align="end">
                        <button class="rounded-md border px-2 py-1 text-xs font-bold" type="button" @click="openAction(transfer, 'dispatch')">Dispatch</button>
                        <button class="rounded-md border px-2 py-1 text-xs font-bold" type="button" @click="openAction(transfer, 'receive')">Receive</button>
                        <button class="rounded-md border px-2 py-1 text-xs font-bold" type="button" @click="openAction(transfer, 'cancel')">Cancel</button>
                    </ActionToolbar>
                </article>
                <p v-if="transfers.length === 0" class="py-4 text-sm text-slate-500">No transfers recorded.</p>
            </div>
        </section>

        <FormModal :show="activeModal === 'transfer'" title="Request Transfer" :form="form" submit-label="Request transfer" size="full" @close="activeModal = null" @submit="saveTransfer">
            <div class="grid gap-3 md:grid-cols-2">
                <select v-model="form.inventory_item_id" class="rounded-md border-slate-300"><option value="">Item</option><option v-for="item in items" :key="item.id" :value="item.id">{{ item.name }}</option></select>
                <select v-model="form.inventory_batch_id" class="rounded-md border-slate-300"><option value="">Batch</option><option v-for="batch in batches" :key="batch.id" :value="batch.id">{{ batch.batch_number }} - {{ batch.item?.name }}</option></select>
                <select v-model="form.from_location_id" class="rounded-md border-slate-300"><option value="">From</option><option v-for="location in locations" :key="location.id" :value="location.id">{{ location.name }}</option></select>
                <select v-model="form.to_location_id" class="rounded-md border-slate-300"><option value="">To</option><option v-for="location in locations" :key="location.id" :value="location.id">{{ location.name }}</option></select>
                <TextInput id="transfer_qty" v-model="form.quantity" label="Quantity" type="number" />
                <textarea v-model="form.reason" class="rounded-md border-slate-300" rows="2" placeholder="Reason"></textarea>
            </div>
        </FormModal>

        <ConfirmDialog :show="activeModal === 'action'" :title="`${actionForm.action} transfer`" confirm-label="Save action" :form="actionForm" :require-reason="actionForm.action === 'cancel'" @close="activeModal = null" @confirm="saveAction" />
    </AppLayout>
</template>
