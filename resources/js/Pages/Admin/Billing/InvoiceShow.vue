<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import PrimaryButton from '../../../Components/PrimaryButton.vue';
import TextInput from '../../../Components/TextInput.vue';

const props = defineProps({
    invoice: { type: Object, required: true },
    services: { type: Array, default: () => [] },
});

const serviceLine = useForm({ billable_service_id: '', quantity: 1, discount_minor: 0 });
const manualLine = useForm({ service_name: '', service_description: '', quantity: 1, unit_price_minor: '', discount_minor: 0, tax_rate_basis_points: 0, tax_exempt: false, manual_reason: '' });

function transition(action) {
    const reason = window.prompt(action === 'void' ? 'Void reason' : 'Cancel reason');
    if (!reason) return;
    router.patch(`/admin/billing/invoices/${props.invoice.id}/transition`, { action, reason }, { preserveScroll: true });
}
</script>

<template>
    <Head :title="invoice.invoice_number || 'Draft invoice'" />
    <AppLayout :title="invoice.invoice_number || 'Draft invoice'">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <Link href="/admin/billing/invoices" class="text-sm font-semibold text-red-800">Back to invoices</Link>
            <div class="flex flex-wrap gap-2">
                <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="router.post(`/admin/billing/invoices/${invoice.id}/issue`)">Issue</button>
                <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="transition('cancel')">Cancel</button>
                <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="transition('void')">Void</button>
                <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="router.post(`/admin/billing/invoices/${invoice.id}/replacement`)">Replacement draft</button>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[1fr_380px]">
            <section class="rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                <div class="grid gap-3 border-b border-slate-200 p-4 md:grid-cols-4 dark:border-slate-800">
                    <div><p class="text-xs text-slate-500">Patient</p><p class="font-bold">{{ invoice.patient?.full_name }}</p></div>
                    <div><p class="text-xs text-slate-500">Status</p><p class="font-bold">{{ invoice.status }}</p></div>
                    <div><p class="text-xs text-slate-500">Currency</p><p class="font-bold">{{ invoice.currency }}</p></div>
                    <div><p class="text-xs text-slate-500">Total</p><p class="font-bold">{{ invoice.total_minor }} minor units</p></div>
                </div>
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    <article v-for="line in invoice.lines" :key="line.id" class="grid gap-3 p-4 md:grid-cols-[1fr_160px]">
                        <div>
                            <p class="font-bold">{{ line.service_code || 'MANUAL' }} · {{ line.service_name }}</p>
                            <p class="text-sm text-slate-500">Qty {{ line.quantity }} · Unit {{ line.unit_price_minor }} · Discount {{ line.discount_minor }} · Tax {{ line.tax_minor }}</p>
                            <p v-if="line.manual_reason" class="mt-1 text-xs text-slate-500">Manual reason: {{ line.manual_reason }}</p>
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

            <aside class="space-y-6">
                <form class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="serviceLine.post(`/admin/billing/invoices/${invoice.id}/service-lines`, { preserveScroll: true, onSuccess: () => serviceLine.reset() })">
                    <h2 class="font-black">Service Line</h2>
                    <div class="mt-4 grid gap-3">
                        <select v-model="serviceLine.billable_service_id" class="rounded-md border-slate-300"><option value="">Service</option><option v-for="service in services" :key="service.id" :value="service.id">{{ service.code }} · {{ service.name }}</option></select>
                        <TextInput id="line_qty" v-model="serviceLine.quantity" label="Quantity" type="number" />
                        <TextInput id="line_discount" v-model="serviceLine.discount_minor" label="Discount minor units" type="number" />
                        <PrimaryButton :disabled="serviceLine.processing">Add service</PrimaryButton>
                    </div>
                </form>

                <form class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="manualLine.post(`/admin/billing/invoices/${invoice.id}/manual-lines`, { preserveScroll: true, onSuccess: () => manualLine.reset() })">
                    <h2 class="font-black">Manual Line</h2>
                    <div class="mt-4 grid gap-3">
                        <TextInput id="manual_name" v-model="manualLine.service_name" label="Name" />
                        <textarea v-model="manualLine.service_description" class="rounded-md border-slate-300" rows="2" placeholder="Description"></textarea>
                        <TextInput id="manual_qty" v-model="manualLine.quantity" label="Quantity" type="number" />
                        <TextInput id="manual_unit" v-model="manualLine.unit_price_minor" label="Unit price minor units" type="number" />
                        <TextInput id="manual_discount" v-model="manualLine.discount_minor" label="Discount minor units" type="number" />
                        <TextInput id="manual_tax" v-model="manualLine.tax_rate_basis_points" label="Tax basis points" type="number" />
                        <label class="flex items-center gap-2 text-sm"><input v-model="manualLine.tax_exempt" type="checkbox"> Tax exempt</label>
                        <textarea v-model="manualLine.manual_reason" class="rounded-md border-slate-300" rows="2" placeholder="Required reason"></textarea>
                        <PrimaryButton :disabled="manualLine.processing">Add manual line</PrimaryButton>
                    </div>
                </form>
            </aside>
        </div>
    </AppLayout>
</template>
