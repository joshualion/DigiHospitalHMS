<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    prescription: { type: Object, required: true },
    locations: { type: Array, default: () => [] },
    batches: { type: Array, default: () => [] },
    fefo: { type: Array, default: () => [] },
});

const review = useForm({ action: 'approved', prescription_item_id: '', reason: '', substituted_inventory_item_id: '', substitution_note: '' });
const amendment = useForm({ reason: '', content: '' });
const dispenseForms = Object.fromEntries(props.prescription.items.map((item) => [item.id, useForm({ inventory_location_id: props.locations[0]?.id || '', inventory_batch_id: '', quantity: '', instructions: '' })]));

function discontinue(status) {
    const reason = window.prompt('Reason');
    if (!reason) return;
    router.patch(`/admin/pharmacy/prescriptions/${props.prescription.id}/transition`, { status, reason }, { preserveScroll: true });
}

function returnDispense(dispense, action) {
    const reason = window.prompt('Reason');
    if (!reason) return;
    router.post(`/admin/pharmacy/dispenses/${dispense.id}/${action}`, { reason }, { preserveScroll: true });
}
</script>

<template>
    <Head :title="prescription.prescription_number" />
    <AppLayout :title="prescription.prescription_number">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <Link href="/admin/pharmacy/prescriptions" class="text-sm font-bold text-red-800">Back to worklist</Link>
            <div class="flex flex-wrap gap-2">
                <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="router.post(`/admin/pharmacy/prescriptions/${prescription.id}/sign`)">Sign</button>
                <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="router.post(`/admin/pharmacy/prescriptions/${prescription.id}/bill`)">Bill</button>
                <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="discontinue('discontinued')">Discontinue</button>
                <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="discontinue('cancelled')">Cancel</button>
            </div>
        </div>

        <section class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-950">
            <h2 class="font-black">Allergies And Alerts</h2>
            <p v-for="allergy in prescription.patient?.allergies || []" :key="`a-${allergy.id}`">{{ allergy.substance }} - {{ allergy.severity }}</p>
            <p v-for="alert in prescription.patient?.alerts || []" :key="`al-${alert.id}`">{{ alert.title }} - {{ alert.severity }}</p>
            <p v-if="(prescription.patient?.allergies || []).length === 0 && (prescription.patient?.alerts || []).length === 0">No active allergies or alerts recorded.</p>
        </section>

        <div class="grid gap-6 xl:grid-cols-[1fr_360px]">
            <section class="space-y-6">
                <section v-for="item in prescription.items" :key="item.id" class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-lg font-black">{{ item.medicine_name }}</h2>
                    <p class="text-sm text-slate-500">{{ item.dose }} - {{ item.frequency }} - {{ item.duration }} - outstanding {{ Number(item.quantity - item.dispensed_quantity).toFixed(4) }}</p>
                    <p class="mt-2 text-sm">{{ item.instructions }}</p>
                    <form class="mt-4 grid gap-3 md:grid-cols-4" @submit.prevent="dispenseForms[item.id].post(`/admin/pharmacy/prescription-items/${item.id}/dispense`, { preserveScroll: true })">
                        <select v-model="dispenseForms[item.id].inventory_location_id" class="rounded-md border-slate-300"><option v-for="location in locations" :key="location.id" :value="location.id">{{ location.name }}</option></select>
                        <select v-model="dispenseForms[item.id].inventory_batch_id" class="rounded-md border-slate-300"><option value="">Batch</option><option v-for="batch in batches.filter((batch) => batch.inventory_item_id === item.inventory_item_id)" :key="batch.id" :value="batch.id">{{ batch.batch_number }} - {{ batch.state }} - {{ batch.expiry_date || 'no expiry' }}</option></select>
                        <TextInput :id="`dispense_qty_${item.id}`" v-model="dispenseForms[item.id].quantity" label="Quantity" type="number" />
                        <PrimaryButton :disabled="dispenseForms[item.id].processing">Dispense</PrimaryButton>
                    </form>
                    <div class="mt-3 text-xs text-slate-500">
                        FEFO:
                        <span v-for="row in (fefo.find((entry) => entry.prescription_item_id === item.id)?.batches || [])" :key="row.id" class="mr-2">{{ row.batch?.batch_number }} ({{ row.quantity }})</span>
                    </div>
                </section>
                <section class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="font-black">Dispensing Events</h2>
                    <article v-for="dispense in prescription.dispenses" :key="dispense.id" class="mt-3 text-sm">
                        <p class="font-bold">{{ dispense.action }} - {{ dispense.quantity }} - {{ dispense.batch?.batch_number }}</p>
                        <button class="mr-2 rounded-md border px-2 py-1 text-xs font-bold" type="button" @click="returnDispense(dispense, 'return')">Return</button>
                        <button class="rounded-md border px-2 py-1 text-xs font-bold" type="button" @click="returnDispense(dispense, 'reverse')">Reverse</button>
                    </article>
                </section>
            </section>
            <aside class="space-y-6">
                <form class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="review.post(`/admin/pharmacy/prescriptions/${prescription.id}/reviews`, { preserveScroll: true, onSuccess: () => review.reset() })">
                    <h2 class="font-black">Pharmacist Review</h2>
                    <select v-model="review.action" class="mt-3 w-full rounded-md border-slate-300"><option value="approved">Approve</option><option value="clarification_requested">Request clarification</option><option value="rejected">Reject</option><option value="substitution_authorized">Authorize substitution</option></select>
                    <textarea v-model="review.reason" class="mt-3 w-full rounded-md border-slate-300" rows="3" placeholder="Reason"></textarea>
                    <PrimaryButton class="mt-3" :disabled="review.processing">Record review</PrimaryButton>
                    <p v-for="entry in prescription.reviews" :key="entry.id" class="mt-3 text-sm">{{ entry.action }} - {{ entry.reason }}</p>
                </form>
                <form class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="amendment.post(`/admin/pharmacy/prescriptions/${prescription.id}/amendments`, { preserveScroll: true, onSuccess: () => amendment.reset() })">
                    <h2 class="font-black">Append Amendment</h2>
                    <input v-model="amendment.reason" class="mt-3 w-full rounded-md border-slate-300 text-sm" placeholder="Reason">
                    <textarea v-model="amendment.content" class="mt-3 w-full rounded-md border-slate-300 text-sm" rows="3" placeholder="Amendment"></textarea>
                    <PrimaryButton class="mt-3" :disabled="amendment.processing">Add amendment</PrimaryButton>
                </form>
                <section v-if="prescription.invoice" class="rounded-lg border border-slate-200 bg-white p-5 text-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="font-black">Invoice</h2>
                    <p>{{ prescription.invoice.invoice_number || `Draft #${prescription.invoice.id}` }} - {{ prescription.invoice.total_minor }} minor units</p>
                </section>
            </aside>
        </div>
    </AppLayout>
</template>
