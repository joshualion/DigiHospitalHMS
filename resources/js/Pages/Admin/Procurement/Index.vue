<script setup>
import ActionToolbar from '@/Components/Admin/ActionToolbar.vue';
import ConfirmDialog from '@/Components/Admin/ConfirmDialog.vue';
import FormModal from '@/Components/Admin/FormModal.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    suppliers: { type: Array, default: () => [] },
    requisitions: { type: Array, default: () => [] },
    purchaseOrders: { type: Array, default: () => [] },
    reorderSuggestions: { type: Array, default: () => [] },
    facilities: { type: Array, default: () => [] },
    locations: { type: Array, default: () => [] },
    items: { type: Array, default: () => [] },
    units: { type: Array, default: () => [] },
    approvalLimits: { type: Array, default: () => [] },
});

const supplierForm = useForm({ code: '', name: '', status: 'active', contact_person: '', phone: '', email: '', address: '', payment_terms: '', lead_time_days: '', item_ids: [] });
const limitForm = useForm({ role_name: 'pharmacist', limit_minor: '1000000', currency: 'NGN' });
const requisitionForm = useForm({ facility_id: props.facilities[0]?.id || '', inventory_location_id: props.locations[0]?.id || '', currency: 'NGN', reason: '', lines: [{ inventory_item_id: '', inventory_unit_id: '', quantity: '', estimated_unit_cost_minor: '', discount_minor: 0, tax_minor: 0, notes: '' }] });
const requisitionActionForm = useForm({ action: '', reason: '', supplier_id: '' });
const receiptForm = useForm({ facility_id: '', inventory_location_id: props.locations[0]?.id || '', delivery_reference: '', lines: [] });
const receiptActionForm = useForm({ inventory_location_id: props.locations[0]?.id || '', quantity: '', reason: '' });
const activeModal = ref(null);
const actionTarget = ref(null);
const receiptMode = ref(null);
const receiptLineTarget = ref(null);

function blankLine() {
    return { inventory_item_id: '', inventory_unit_id: '', quantity: '', estimated_unit_cost_minor: '', discount_minor: 0, tax_minor: 0, notes: '' };
}

function addLine() {
    requisitionForm.lines.push(blankLine());
}

function removeLine(index) {
    if (requisitionForm.lines.length > 1) requisitionForm.lines.splice(index, 1);
}

function saveSupplier() {
    supplierForm.post('/admin/procurement/suppliers', { preserveScroll: true, onSuccess: () => { activeModal.value = null; supplierForm.reset(); } });
}

function saveLimit() {
    limitForm.post('/admin/procurement/approval-limits', { preserveScroll: true, onSuccess: () => { activeModal.value = null; } });
}

function saveRequisition() {
    requisitionForm.post('/admin/procurement/requisitions', { preserveScroll: true, onSuccess: () => { activeModal.value = null; requisitionForm.reset(); requisitionForm.lines = [blankLine()]; } });
}

function openRequisitionAction(requisition, actionName) {
    actionTarget.value = requisition;
    requisitionActionForm.defaults({ action: actionName, reason: '', supplier_id: actionName === 'convert' ? (props.suppliers.find((supplier) => supplier.status === 'active')?.id || '') : '' });
    requisitionActionForm.reset();
    activeModal.value = 'requisition-action';
}

function saveRequisitionAction() {
    requisitionActionForm.patch(`/admin/procurement/requisitions/${actionTarget.value.id}`, { preserveScroll: true, onSuccess: () => { activeModal.value = null; requisitionActionForm.reset(); } });
}

function openReceipt(po, full = false) {
    const line = po.lines?.[0];
    if (!line) return;
    const outstanding = Number(line.quantity) - Number(line.received_quantity);
    const qty = full ? outstanding : Math.max(1, Math.min(outstanding, Math.ceil(outstanding / 2)));
    actionTarget.value = po;
    receiptForm.defaults({
        facility_id: po.facility_id,
        inventory_location_id: props.locations[0]?.id || '',
        delivery_reference: `DEL-${Date.now()}`,
        lines: [{ purchase_order_line_id: line.id, batch_number: `BATCH-${Date.now()}`, expiry_date: '2027-12-31', received_quantity: qty, accepted_quantity: qty, rejected_quantity: 0, unit_cost_minor: line.unit_cost_minor, requires_clearance: true }],
    });
    receiptForm.reset();
    activeModal.value = 'receipt';
}

function saveReceipt() {
    receiptForm.post(`/admin/procurement/purchase-orders/${actionTarget.value.id}/receipts`, { preserveScroll: true, onSuccess: () => { activeModal.value = null; receiptForm.reset(); } });
}

function openReceiptAction(po, mode) {
    const receiptLines = po.goods_receipts?.flatMap((receipt) => receipt.lines || []) || [];
    const receiptLine = mode === 'reverse' ? (receiptLines[1] || receiptLines[0]) : receiptLines[0];
    if (!receiptLine) return;
    receiptLineTarget.value = receiptLine;
    receiptMode.value = mode;
    receiptActionForm.defaults({ inventory_location_id: props.locations[0]?.id || '', quantity: receiptLine.accepted_quantity, reason: mode === 'return' ? 'Admin supplier return' : 'Admin receipt reversal' });
    receiptActionForm.reset();
    activeModal.value = 'receipt-action';
}

function saveReceiptAction() {
    const url = receiptMode.value === 'return' ? `/admin/procurement/receipt-lines/${receiptLineTarget.value.id}/return` : `/admin/procurement/receipt-lines/${receiptLineTarget.value.id}/reverse`;
    receiptActionForm.post(url, { preserveScroll: true, onSuccess: () => { activeModal.value = null; receiptActionForm.reset(); } });
}
</script>

<template>
    <Head title="Procurement" />
    <AppLayout title="Procurement">
        <PageHeader title="Procurement" description="Suppliers, requisitions, purchase orders and goods receipts.">
            <template #actions>
                <ActionToolbar align="end">
                    <PrimaryButton type="button" @click="activeModal = 'supplier'">Add Supplier</PrimaryButton>
                    <PrimaryButton type="button" @click="activeModal = 'limit'">Add Approval Limit</PrimaryButton>
                    <PrimaryButton type="button" @click="activeModal = 'requisition'">Draft Requisition</PrimaryButton>
                </ActionToolbar>
            </template>
        </PageHeader>

        <div class="grid gap-6">
            <section class="min-w-0 rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="font-black">Purchase Requisitions</h2>
                    <span class="text-sm text-slate-500">{{ requisitions.length }} requests</span>
                </div>
                <div class="mt-4 divide-y divide-slate-100 dark:divide-slate-800">
                    <article v-for="req in requisitions" :key="req.id" class="grid min-w-0 gap-3 py-3 lg:grid-cols-[1fr_auto]">
                        <div class="min-w-0">
                            <p class="break-words font-bold">REQ #{{ req.id }} - {{ req.status }}</p>
                            <p class="break-words text-sm text-slate-500">{{ req.location?.name }} - {{ req.total_minor }} {{ req.currency }} minor units</p>
                            <p class="break-words text-xs text-slate-500">{{ req.lines?.map((line) => `${line.item?.name} x ${line.quantity}`).join(', ') }}</p>
                        </div>
                        <ActionToolbar align="end">
                            <button class="rounded-md border px-3 py-2 text-xs font-bold" type="button" @click="openRequisitionAction(req, 'submit')">Submit</button>
                            <button class="rounded-md border px-3 py-2 text-xs font-bold" type="button" @click="openRequisitionAction(req, 'approve')">Approve</button>
                            <button class="rounded-md border px-3 py-2 text-xs font-bold" type="button" @click="openRequisitionAction(req, 'reject')">Reject</button>
                            <button class="rounded-md border px-3 py-2 text-xs font-bold" type="button" @click="openRequisitionAction(req, 'convert')">Convert PO</button>
                        </ActionToolbar>
                    </article>
                </div>
            </section>

            <section class="min-w-0 rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="font-black">Purchase Orders And Goods Receipts</h2>
                <div class="mt-4 divide-y divide-slate-100 dark:divide-slate-800">
                    <article v-for="po in purchaseOrders" :key="po.id" class="grid min-w-0 gap-3 py-3 lg:grid-cols-[1fr_auto]">
                        <div class="min-w-0">
                            <p class="break-words font-bold">{{ po.purchase_order_number }} - {{ po.status }}</p>
                            <p class="break-words text-sm text-slate-500">{{ po.supplier?.name }} - {{ po.total_minor }} {{ po.currency }} minor units</p>
                            <p class="break-words text-xs text-slate-500">{{ po.lines?.map((line) => `${line.item?.name} ordered ${line.quantity}, received ${line.received_quantity}`).join(', ') }}</p>
                        </div>
                        <ActionToolbar align="end">
                            <button class="rounded-md border px-3 py-2 text-xs font-bold" type="button" @click="openReceipt(po, false)">Partial receipt</button>
                            <button class="rounded-md border px-3 py-2 text-xs font-bold" type="button" @click="openReceipt(po, true)">Full receipt</button>
                            <button class="rounded-md border px-3 py-2 text-xs font-bold" type="button" @click="openReceiptAction(po, 'return')">Supplier return</button>
                            <button class="rounded-md border px-3 py-2 text-xs font-bold" type="button" @click="openReceiptAction(po, 'reverse')">Reverse receipt</button>
                        </ActionToolbar>
                    </article>
                </div>
            </section>

            <section class="grid gap-6 lg:grid-cols-3">
                <div class="min-w-0 rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="font-black">Suppliers</h2>
                    <p v-for="supplier in suppliers" :key="supplier.id" class="mt-3 break-words text-sm">{{ supplier.code }} - {{ supplier.name }} - {{ supplier.status }}</p>
                </div>
                <div class="min-w-0 rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="font-black">Approval Limits</h2>
                    <p v-for="limit in approvalLimits" :key="limit.id" class="mt-3 break-words text-sm">{{ limit.role_name }} - {{ limit.currency }} {{ limit.limit_minor }}</p>
                </div>
                <div class="min-w-0 rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="font-black">Reorder Suggestions</h2>
                    <p v-for="row in reorderSuggestions" :key="row.item.id" class="mt-3 break-words text-sm">{{ row.item.name }} - on hand {{ row.on_hand }}, on order {{ row.on_order }}, suggested {{ row.suggested_quantity }}</p>
                </div>
            </section>
        </div>

        <FormModal :show="activeModal === 'supplier'" title="Add Supplier" :form="supplierForm" submit-label="Save supplier" size="full" @close="activeModal = null" @submit="saveSupplier">
            <div class="grid gap-3 md:grid-cols-2">
                <TextInput id="supplier_code" v-model="supplierForm.code" label="Code" />
                <TextInput id="supplier_name" v-model="supplierForm.name" label="Name" />
                <select v-model="supplierForm.status" class="rounded-md border-slate-300"><option value="active">Active</option><option value="inactive">Inactive</option></select>
                <TextInput id="supplier_contact_person" v-model="supplierForm.contact_person" label="Contact person" />
                <TextInput id="supplier_phone" v-model="supplierForm.phone" label="Phone" />
                <TextInput id="supplier_email" v-model="supplierForm.email" label="Email" type="email" />
                <textarea v-model="supplierForm.address" class="rounded-md border-slate-300 md:col-span-2" rows="2" placeholder="Address"></textarea>
                <TextInput id="supplier_payment_terms" v-model="supplierForm.payment_terms" label="Payment terms" />
                <TextInput id="supplier_lead_time_days" v-model="supplierForm.lead_time_days" label="Lead time days" type="number" />
                <select v-model="supplierForm.item_ids" class="rounded-md border-slate-300 md:col-span-2" multiple><option v-for="item in items" :key="item.id" :value="item.id">{{ item.name }}</option></select>
            </div>
        </FormModal>

        <FormModal :show="activeModal === 'limit'" title="Approval Limit" :form="limitForm" submit-label="Save limit" @close="activeModal = null" @submit="saveLimit">
            <div class="grid gap-3">
                <TextInput id="approval_role_name" v-model="limitForm.role_name" label="Role" />
                <TextInput id="approval_limit_minor" v-model="limitForm.limit_minor" label="Limit minor units" type="number" />
                <TextInput id="approval_currency" v-model="limitForm.currency" label="Currency" />
            </div>
        </FormModal>

        <FormModal :show="activeModal === 'requisition'" title="Draft Requisition" :form="requisitionForm" submit-label="Create requisition" size="full" @close="activeModal = null" @submit="saveRequisition">
            <div class="grid gap-3 md:grid-cols-2">
                <select v-model="requisitionForm.facility_id" class="rounded-md border-slate-300"><option v-for="facility in facilities" :key="facility.id" :value="facility.id">{{ facility.name }}</option></select>
                <select v-model="requisitionForm.inventory_location_id" class="rounded-md border-slate-300"><option v-for="location in locations" :key="location.id" :value="location.id">{{ location.name }}</option></select>
                <TextInput id="requisition_currency" v-model="requisitionForm.currency" label="Currency" />
                <textarea v-model="requisitionForm.reason" class="rounded-md border-slate-300 md:col-span-2" rows="2" placeholder="Reason"></textarea>
            </div>
            <div class="mt-4 grid gap-3">
                <div v-for="(line, index) in requisitionForm.lines" :key="index" class="grid gap-2 rounded-md border border-slate-200 p-3 md:grid-cols-2">
                    <select v-model="line.inventory_item_id" class="rounded-md border-slate-300"><option value="">Item</option><option v-for="item in items" :key="item.id" :value="item.id">{{ item.name }}</option></select>
                    <select v-model="line.inventory_unit_id" class="rounded-md border-slate-300"><option value="">Unit</option><option v-for="unit in units" :key="unit.id" :value="unit.id">{{ unit.code }}</option></select>
                    <TextInput :id="`req_qty_${index}`" v-model="line.quantity" label="Quantity" type="number" />
                    <TextInput :id="`req_unit_cost_${index}`" v-model="line.estimated_unit_cost_minor" label="Unit cost minor" type="number" />
                    <TextInput :id="`req_discount_${index}`" v-model="line.discount_minor" label="Discount minor" type="number" />
                    <TextInput :id="`req_tax_${index}`" v-model="line.tax_minor" label="Tax minor" type="number" />
                    <textarea v-model="line.notes" class="rounded-md border-slate-300 md:col-span-2" rows="2" placeholder="Notes"></textarea>
                    <button class="w-fit rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="removeLine(index)">Remove line</button>
                </div>
                <button class="w-fit rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="addLine">Add line</button>
            </div>
        </FormModal>

        <FormModal :show="activeModal === 'requisition-action'" :title="`${requisitionActionForm.action} requisition`" :form="requisitionActionForm" submit-label="Save action" @close="activeModal = null" @submit="saveRequisitionAction">
            <div class="grid gap-3">
                <select v-if="requisitionActionForm.action === 'convert'" v-model="requisitionActionForm.supplier_id" class="rounded-md border-slate-300"><option value="">Supplier</option><option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">{{ supplier.name }}</option></select>
                <textarea v-if="requisitionActionForm.action === 'reject'" v-model="requisitionActionForm.reason" class="rounded-md border-slate-300" rows="3" placeholder="Reason"></textarea>
                <p class="text-sm text-slate-500">Confirm this requisition workflow action.</p>
            </div>
        </FormModal>

        <FormModal :show="activeModal === 'receipt'" title="Goods Receipt" :form="receiptForm" submit-label="Record receipt" size="full" @close="activeModal = null" @submit="saveReceipt">
            <div class="grid gap-3 md:grid-cols-2">
                <select v-model="receiptForm.facility_id" class="rounded-md border-slate-300"><option v-for="facility in facilities" :key="facility.id" :value="facility.id">{{ facility.name }}</option></select>
                <select v-model="receiptForm.inventory_location_id" class="rounded-md border-slate-300"><option v-for="location in locations" :key="location.id" :value="location.id">{{ location.name }}</option></select>
                <TextInput id="delivery_reference" v-model="receiptForm.delivery_reference" label="Delivery reference" />
            </div>
            <div v-for="(line, index) in receiptForm.lines" :key="index" class="mt-4 grid gap-3 rounded-md border border-slate-200 p-3 md:grid-cols-2">
                <TextInput :id="`receipt_batch_${index}`" v-model="line.batch_number" label="Batch number" />
                <TextInput :id="`receipt_expiry_${index}`" v-model="line.expiry_date" label="Expiry date" type="date" />
                <TextInput :id="`receipt_received_${index}`" v-model="line.received_quantity" label="Received quantity" type="number" />
                <TextInput :id="`receipt_accepted_${index}`" v-model="line.accepted_quantity" label="Accepted quantity" type="number" />
                <TextInput :id="`receipt_rejected_${index}`" v-model="line.rejected_quantity" label="Rejected quantity" type="number" />
                <TextInput :id="`receipt_cost_${index}`" v-model="line.unit_cost_minor" label="Unit cost minor" type="number" />
                <label class="flex items-center gap-2 text-sm"><input v-model="line.requires_clearance" type="checkbox"> Requires clearance</label>
            </div>
        </FormModal>

        <FormModal :show="activeModal === 'receipt-action'" :title="`${receiptMode} receipt line`" :form="receiptActionForm" submit-label="Save action" @close="activeModal = null" @submit="saveReceiptAction">
            <div class="grid gap-3">
                <select v-if="receiptMode === 'return'" v-model="receiptActionForm.inventory_location_id" class="rounded-md border-slate-300"><option v-for="location in locations" :key="location.id" :value="location.id">{{ location.name }}</option></select>
                <TextInput v-if="receiptMode === 'return'" id="receipt_return_quantity" v-model="receiptActionForm.quantity" label="Quantity" type="number" />
                <textarea v-model="receiptActionForm.reason" class="rounded-md border-slate-300" rows="3" placeholder="Reason"></textarea>
            </div>
        </FormModal>
    </AppLayout>
</template>
