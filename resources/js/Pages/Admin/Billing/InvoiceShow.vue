<script setup>
import ActionToolbar from '@/Components/Admin/ActionToolbar.vue';
import ConfirmDialog from '@/Components/Admin/ConfirmDialog.vue';
import FormModal from '@/Components/Admin/FormModal.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    invoice: { type: Object, required: true },
    services: { type: Array, default: () => [] },
});

const serviceLine = useForm({ billable_service_id: '', quantity: 1, discount_minor: 0 });
const manualLine = useForm({ service_name: '', service_description: '', quantity: 1, unit_price_minor: '', discount_minor: 0, tax_rate_basis_points: 0, tax_exempt: false, manual_reason: '' });
const transitionForm = useForm({ action: '', reason: '' });
const activeModal = ref(null);
const transitionTarget = ref(null);

function saveServiceLine() {
    serviceLine.post(`/admin/billing/invoices/${props.invoice.id}/service-lines`, { preserveScroll: true, onSuccess: () => { activeModal.value = null; serviceLine.reset(); } });
}

function saveManualLine() {
    manualLine.post(`/admin/billing/invoices/${props.invoice.id}/manual-lines`, { preserveScroll: true, onSuccess: () => { activeModal.value = null; manualLine.reset(); } });
}

function openTransition(action) {
    transitionTarget.value = action;
    transitionForm.defaults({ action, reason: '' });
    transitionForm.reset();
}

function saveTransition() {
    transitionForm.patch(`/admin/billing/invoices/${props.invoice.id}/transition`, { preserveScroll: true, onSuccess: () => { transitionTarget.value = null; transitionForm.reset(); } });
}
</script>

<template>
    <Head :title="invoice.invoice_number || 'Draft invoice'" />
    <AppLayout :title="invoice.invoice_number || 'Draft invoice'">
        <PageHeader :title="invoice.invoice_number || 'Draft invoice'" description="Invoice details, line items and workflow controls.">
            <template #actions>
                <ActionToolbar align="end">
                    <Link href="/admin/billing/invoices" class="rounded-md border px-3 py-2 text-sm font-bold">Back</Link>
                    <PrimaryButton type="button" @click="activeModal = 'service'">Add Service Line</PrimaryButton>
                    <PrimaryButton type="button" @click="activeModal = 'manual'">Add Manual Line</PrimaryButton>
                    <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="router.post(`/admin/billing/invoices/${invoice.id}/issue`)">Issue</button>
                    <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="openTransition('cancel')">Cancel</button>
                    <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="openTransition('void')">Void</button>
                    <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="router.post(`/admin/billing/invoices/${invoice.id}/replacement`)">Replacement Draft</button>
                </ActionToolbar>
            </template>
        </PageHeader>

        <section class="min-w-0 rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="grid gap-3 border-b border-slate-200 p-4 md:grid-cols-4 dark:border-slate-800">
                <div><p class="text-xs text-slate-500">Patient</p><p class="break-words font-bold">{{ invoice.patient?.full_name }}</p></div>
                <div><p class="text-xs text-slate-500">Status</p><p class="font-bold">{{ invoice.status }}</p></div>
                <div><p class="text-xs text-slate-500">Currency</p><p class="font-bold">{{ invoice.currency }}</p></div>
                <div><p class="text-xs text-slate-500">Total</p><p class="font-bold">{{ invoice.total_minor }} minor units</p></div>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                <article v-for="line in invoice.lines" :key="line.id" class="grid min-w-0 gap-3 p-4 md:grid-cols-[1fr_160px]">
                    <div class="min-w-0">
                        <p class="break-words font-bold">{{ line.service_code || 'MANUAL' }} - {{ line.service_name }}</p>
                        <p class="break-words text-sm text-slate-500">Qty {{ line.quantity }} - Unit {{ line.unit_price_minor }} - Discount {{ line.discount_minor }} - Tax {{ line.tax_minor }}</p>
                        <p v-if="line.manual_reason" class="mt-1 break-words text-xs text-slate-500">Manual reason: {{ line.manual_reason }}</p>
                    </div>
                    <p class="font-black">{{ line.total_minor }}</p>
                </article>
                <p v-if="invoice.lines.length === 0" class="p-4 text-sm text-slate-500">No invoice lines yet.</p>
            </div>
            <div class="grid gap-3 border-t border-slate-200 p-4 text-sm md:grid-cols-4 dark:border-slate-800">
                <p>Subtotal: <strong>{{ invoice.subtotal_minor }}</strong></p>
                <p>Discount: <strong>{{ invoice.discount_minor }}</strong></p>
                <p>Tax: <strong>{{ invoice.tax_minor }}</strong></p>
                <p>Total: <strong>{{ invoice.total_minor }}</strong></p>
            </div>
        </section>

        <FormModal :show="activeModal === 'service'" title="Add Service Line" :form="serviceLine" submit-label="Add service" @close="activeModal = null" @submit="saveServiceLine">
            <div class="grid gap-3">
                <select v-model="serviceLine.billable_service_id" class="rounded-md border-slate-300"><option value="">Service</option><option v-for="service in services" :key="service.id" :value="service.id">{{ service.code }} - {{ service.name }}</option></select>
                <TextInput id="line_qty" v-model="serviceLine.quantity" label="Quantity" type="number" />
                <TextInput id="line_discount" v-model="serviceLine.discount_minor" label="Discount minor units" type="number" />
            </div>
        </FormModal>

        <FormModal :show="activeModal === 'manual'" title="Add Manual Line" :form="manualLine" submit-label="Add manual line" size="full" @close="activeModal = null" @submit="saveManualLine">
            <div class="grid gap-3 md:grid-cols-2">
                <TextInput id="manual_name" v-model="manualLine.service_name" label="Name" />
                <TextInput id="manual_qty" v-model="manualLine.quantity" label="Quantity" type="number" />
                <textarea v-model="manualLine.service_description" class="rounded-md border-slate-300 md:col-span-2" rows="2" placeholder="Description"></textarea>
                <TextInput id="manual_unit" v-model="manualLine.unit_price_minor" label="Unit price minor units" type="number" />
                <TextInput id="manual_discount" v-model="manualLine.discount_minor" label="Discount minor units" type="number" />
                <TextInput id="manual_tax" v-model="manualLine.tax_rate_basis_points" label="Tax basis points" type="number" />
                <label class="flex items-center gap-2 text-sm"><input v-model="manualLine.tax_exempt" type="checkbox"> Tax exempt</label>
                <textarea v-model="manualLine.manual_reason" class="rounded-md border-slate-300 md:col-span-2" rows="2" placeholder="Required reason"></textarea>
            </div>
        </FormModal>

        <ConfirmDialog :show="Boolean(transitionTarget)" :title="`${transitionTarget} invoice`" confirm-label="Save action" :form="transitionForm" require-reason @close="transitionTarget = null" @confirm="saveTransition" />
    </AppLayout>
</template>
