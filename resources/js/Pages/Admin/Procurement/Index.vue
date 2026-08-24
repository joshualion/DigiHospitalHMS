<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';

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
const requisitionForm = useForm({
    facility_id: props.facilities[0]?.id || '',
    inventory_location_id: props.locations[0]?.id || '',
    currency: 'NGN',
    reason: '',
    lines: [{ inventory_item_id: '', inventory_unit_id: '', quantity: '', estimated_unit_cost_minor: '', discount_minor: 0, tax_minor: 0, notes: '' }],
});

function addLine() {
    requisitionForm.lines.push({ inventory_item_id: '', inventory_unit_id: '', quantity: '', estimated_unit_cost_minor: '', discount_minor: 0, tax_minor: 0, notes: '' });
}

function action(requisition, actionName) {
    const payload = { action: actionName };
    if (actionName === 'reject') payload.reason = window.prompt('Reason') || '';
    if (actionName === 'convert') payload.supplier_id = props.suppliers.find((supplier) => supplier.status === 'active')?.id || '';
    router.patch(`/admin/procurement/requisitions/${requisition.id}`, payload, { preserveScroll: true });
}

function receive(po, full = false) {
    const line = po.lines?.[0];
    if (!line) return;
    const outstanding = Number(line.quantity) - Number(line.received_quantity);
    const qty = full ? outstanding : Math.max(1, Math.min(outstanding, Math.ceil(outstanding / 2)));
    router.post(`/admin/procurement/purchase-orders/${po.id}/receipts`, {
        facility_id: po.facility_id,
        inventory_location_id: props.locations[0]?.id || '',
        delivery_reference: `DEL-${Date.now()}`,
        lines: [{
            purchase_order_line_id: line.id,
            batch_number: `BATCH-${Date.now()}`,
            expiry_date: '2027-12-31',
            received_quantity: qty,
            accepted_quantity: qty,
            rejected_quantity: 0,
            unit_cost_minor: line.unit_cost_minor,
            requires_clearance: true,
        }],
    }, { preserveScroll: true });
}

function returnReceiptLine(po, mode) {
    const receiptLines = po.goods_receipts?.flatMap((receipt) => receipt.lines || []) || [];
    const receiptLine = mode === 'reverse' ? (receiptLines[1] || receiptLines[0]) : receiptLines[0];
    if (!receiptLine) return;
    if (mode === 'return') {
        router.post(`/admin/procurement/receipt-lines/${receiptLine.id}/return`, { inventory_location_id: props.locations[0]?.id || '', quantity: receiptLine.accepted_quantity, reason: 'Admin supplier return' }, { preserveScroll: true });
        return;
    }
    router.post(`/admin/procurement/receipt-lines/${receiptLine.id}/reverse`, { reason: 'Admin receipt reversal' }, { preserveScroll: true });
}
</script>

<template>
    <Head title="Procurement" />
    <AppLayout title="Procurement">
        <div class="grid gap-6 xl:grid-cols-[1fr_380px]">
            <section class="space-y-6">
                <section class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="font-black">Purchase Requisitions</h2>
                        <span class="text-sm text-slate-500">{{ requisitions.length }} requests</span>
                    </div>
                    <div class="mt-4 divide-y divide-slate-100 dark:divide-slate-800">
                        <article v-for="req in requisitions" :key="req.id" class="grid gap-3 py-3 lg:grid-cols-[1fr_auto]">
                            <div>
                                <p class="font-bold">REQ #{{ req.id }} - {{ req.status }}</p>
                                <p class="text-sm text-slate-500">{{ req.location?.name }} - {{ req.total_minor }} {{ req.currency }} minor units</p>
                                <p class="text-xs text-slate-500">{{ req.lines?.map((line) => `${line.item?.name} x ${line.quantity}`).join(', ') }}</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button class="rounded-md border px-3 py-2 text-xs font-bold" type="button" @click="action(req, 'submit')">Submit</button>
                                <button class="rounded-md border px-3 py-2 text-xs font-bold" type="button" @click="action(req, 'approve')">Approve</button>
                                <button class="rounded-md border px-3 py-2 text-xs font-bold" type="button" @click="action(req, 'reject')">Reject</button>
                                <button class="rounded-md border px-3 py-2 text-xs font-bold" type="button" @click="action(req, 'convert')">Convert PO</button>
                            </div>
                        </article>
                    </div>
                </section>

                <section class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="font-black">Purchase Orders And Goods Receipts</h2>
                    <div class="mt-4 divide-y divide-slate-100 dark:divide-slate-800">
                        <article v-for="po in purchaseOrders" :key="po.id" class="grid gap-3 py-3 lg:grid-cols-[1fr_auto]">
                            <div>
                                <p class="font-bold">{{ po.purchase_order_number }} - {{ po.status }}</p>
                                <p class="text-sm text-slate-500">{{ po.supplier?.name }} - {{ po.total_minor }} {{ po.currency }} minor units</p>
                                <p class="text-xs text-slate-500">{{ po.lines?.map((line) => `${line.item?.name} ordered ${line.quantity}, received ${line.received_quantity}`).join(', ') }}</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button class="rounded-md border px-3 py-2 text-xs font-bold" type="button" @click="receive(po, false)">Partial receipt</button>
                                <button class="rounded-md border px-3 py-2 text-xs font-bold" type="button" @click="receive(po, true)">Full receipt</button>
                                <button class="rounded-md border px-3 py-2 text-xs font-bold" type="button" @click="returnReceiptLine(po, 'return')">Supplier return</button>
                                <button class="rounded-md border px-3 py-2 text-xs font-bold" type="button" @click="returnReceiptLine(po, 'reverse')">Reverse receipt</button>
                            </div>
                        </article>
                    </div>
                </section>

                <section class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="font-black">Reorder Suggestions</h2>
                    <div class="mt-3 grid gap-2 md:grid-cols-2">
                        <p v-for="row in reorderSuggestions" :key="row.item.id" class="rounded-md border border-slate-200 p-3 text-sm dark:border-slate-800">
                            <span class="font-bold">{{ row.item.name }}</span><br>
                            On hand {{ row.on_hand }}, on order {{ row.on_order }}, suggested {{ row.suggested_quantity }}
                        </p>
                    </div>
                </section>
            </section>

            <aside class="space-y-6">
                <form class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="supplierForm.post('/admin/procurement/suppliers', { preserveScroll: true, onSuccess: () => supplierForm.reset() })">
                    <h2 class="font-black">Supplier</h2>
                    <div class="mt-4 grid gap-3">
                        <TextInput id="supplier_code" v-model="supplierForm.code" label="Code" />
                        <TextInput id="supplier_name" v-model="supplierForm.name" label="Name" />
                        <TextInput id="supplier_contact_person" v-model="supplierForm.contact_person" label="Contact person" />
                        <TextInput id="supplier_phone" v-model="supplierForm.phone" label="Phone" />
                        <TextInput id="supplier_email" v-model="supplierForm.email" label="Email" type="email" />
                        <textarea v-model="supplierForm.address" class="rounded-md border-slate-300" rows="2" placeholder="Address"></textarea>
                        <TextInput id="supplier_payment_terms" v-model="supplierForm.payment_terms" label="Payment terms" />
                        <TextInput id="supplier_lead_time_days" v-model="supplierForm.lead_time_days" label="Lead time days" type="number" />
                        <select v-model="supplierForm.item_ids" class="rounded-md border-slate-300" multiple><option v-for="item in items" :key="item.id" :value="item.id">{{ item.name }}</option></select>
                        <PrimaryButton :disabled="supplierForm.processing">Save supplier</PrimaryButton>
                    </div>
                </form>

                <form class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="limitForm.post('/admin/procurement/approval-limits', { preserveScroll: true })">
                    <h2 class="font-black">Approval Limit</h2>
                    <div class="mt-4 grid gap-3">
                        <TextInput id="approval_role_name" v-model="limitForm.role_name" label="Role" />
                        <TextInput id="approval_limit_minor" v-model="limitForm.limit_minor" label="Limit minor units" type="number" />
                        <TextInput id="approval_currency" v-model="limitForm.currency" label="Currency" />
                        <PrimaryButton :disabled="limitForm.processing">Save limit</PrimaryButton>
                    </div>
                </form>

                <form class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="requisitionForm.post('/admin/procurement/requisitions', { preserveScroll: true })">
                    <h2 class="font-black">Draft Requisition</h2>
                    <div class="mt-4 grid gap-3">
                        <select v-model="requisitionForm.inventory_location_id" class="rounded-md border-slate-300"><option v-for="location in locations" :key="location.id" :value="location.id">{{ location.name }}</option></select>
                        <textarea v-model="requisitionForm.reason" class="rounded-md border-slate-300" rows="2" placeholder="Reason"></textarea>
                        <div v-for="(line, index) in requisitionForm.lines" :key="index" class="grid gap-2 rounded-md border border-slate-200 p-3">
                            <select v-model="line.inventory_item_id" class="rounded-md border-slate-300"><option value="">Item</option><option v-for="item in items" :key="item.id" :value="item.id">{{ item.name }}</option></select>
                            <select v-model="line.inventory_unit_id" class="rounded-md border-slate-300"><option value="">Unit</option><option v-for="unit in units" :key="unit.id" :value="unit.id">{{ unit.code }}</option></select>
                            <TextInput :id="`req_qty_${index}`" v-model="line.quantity" label="Quantity" type="number" />
                            <TextInput :id="`req_unit_cost_${index}`" v-model="line.estimated_unit_cost_minor" label="Unit cost minor" type="number" />
                            <TextInput :id="`req_tax_${index}`" v-model="line.tax_minor" label="Tax minor" type="number" />
                        </div>
                        <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="addLine">Add line</button>
                        <PrimaryButton :disabled="requisitionForm.processing">Create requisition</PrimaryButton>
                    </div>
                </form>
            </aside>
        </div>
    </AppLayout>
</template>
