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
    adjustments: { type: Array, default: () => [] },
});

const form = useForm({ inventory_location_id: '', inventory_item_id: '', inventory_batch_id: '', quantity_delta: '', reason: '' });
const approveForm = useForm({});
const activeModal = ref(null);
const approveTarget = ref(null);

function saveAdjustment() {
    form.post('/admin/inventory/adjustments', { preserveScroll: true, onSuccess: () => { activeModal.value = null; form.reset(); } });
}

function approveAdjustment() {
    approveForm.patch(`/admin/inventory/adjustments/${approveTarget.value.id}/approve`, { preserveScroll: true, onSuccess: () => { activeModal.value = null; approveTarget.value = null; } });
}
</script>

<template>
    <Head title="Inventory Adjustments" />
    <AppLayout title="Inventory Adjustments">
        <PageHeader title="Inventory Adjustments" description="Stock adjustment requests and approvals.">
            <template #actions>
                <ActionToolbar align="end">
                    <Link href="/admin/inventory/stock" class="rounded-md border px-3 py-2 text-sm font-bold">Stock</Link>
                    <Link href="/admin/inventory/transfers" class="rounded-md border px-3 py-2 text-sm font-bold">Transfers</Link>
                    <Link href="/admin/inventory/reports" class="rounded-md border px-3 py-2 text-sm font-bold">Reports</Link>
                    <PrimaryButton type="button" @click="activeModal = 'adjustment'">Request Adjustment</PrimaryButton>
                </ActionToolbar>
            </template>
        </PageHeader>

        <section class="min-w-0 rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="font-black">Adjustment Requests</h2>
            <div class="mt-4 divide-y divide-slate-100 dark:divide-slate-800">
                <article v-for="adjustment in adjustments" :key="adjustment.id" class="grid min-w-0 gap-3 py-3 md:grid-cols-[1fr_auto]">
                    <div class="min-w-0">
                        <p class="break-words font-bold">{{ adjustment.item?.name }} - {{ adjustment.quantity_delta }} - {{ adjustment.status }}</p>
                        <p class="break-words text-sm text-slate-500">{{ adjustment.reason }}</p>
                    </div>
                    <button class="rounded-md border px-2 py-1 text-xs font-bold" type="button" @click="approveTarget = adjustment; activeModal = 'approve'">Approve</button>
                </article>
                <p v-if="adjustments.length === 0" class="py-4 text-sm text-slate-500">No adjustments recorded.</p>
            </div>
        </section>

        <FormModal :show="activeModal === 'adjustment'" title="Request Adjustment" :form="form" submit-label="Request adjustment" @close="activeModal = null" @submit="saveAdjustment">
            <div class="grid gap-3">
                <select v-model="form.inventory_location_id" class="rounded-md border-slate-300"><option value="">Location</option><option v-for="location in locations" :key="location.id" :value="location.id">{{ location.name }}</option></select>
                <select v-model="form.inventory_item_id" class="rounded-md border-slate-300"><option value="">Item</option><option v-for="item in items" :key="item.id" :value="item.id">{{ item.name }}</option></select>
                <select v-model="form.inventory_batch_id" class="rounded-md border-slate-300"><option value="">Batch</option><option v-for="batch in batches" :key="batch.id" :value="batch.id">{{ batch.batch_number }} - {{ batch.item?.name }}</option></select>
                <TextInput id="quantity_delta" v-model="form.quantity_delta" label="Quantity delta" type="number" />
                <textarea v-model="form.reason" class="rounded-md border-slate-300" rows="2" placeholder="Reason"></textarea>
            </div>
        </FormModal>

        <ConfirmDialog :show="activeModal === 'approve'" title="Approve adjustment" message="Approve this stock adjustment request?" confirm-label="Approve" :form="approveForm" @close="activeModal = null" @confirm="approveAdjustment" />
    </AppLayout>
</template>
