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
    prescription: { type: Object, required: true },
    locations: { type: Array, default: () => [] },
    batches: { type: Array, default: () => [] },
    fefo: { type: Array, default: () => [] },
});

const review = useForm({ action: 'approved', prescription_item_id: '', reason: '', substituted_inventory_item_id: '', substitution_note: '' });
const amendment = useForm({ reason: '', content: '' });
const transitionForm = useForm({ status: '', reason: '' });
const dispenseForm = useForm({ inventory_location_id: props.locations[0]?.id || '', inventory_batch_id: '', quantity: '', instructions: '' });
const dispenseActionForm = useForm({ reason: '' });
const activeModal = ref(null);
const selectedItem = ref(null);
const selectedDispense = ref(null);
const dispenseAction = ref(null);

function openDiscontinue(status) {
    transitionForm.defaults({ status, reason: '' });
    transitionForm.reset();
    activeModal.value = 'transition';
}

function saveTransition() {
    transitionForm.patch(`/admin/pharmacy/prescriptions/${props.prescription.id}/transition`, { preserveScroll: true, onSuccess: () => { activeModal.value = null; transitionForm.reset(); } });
}

function openDispense(item) {
    selectedItem.value = item;
    dispenseForm.defaults({ inventory_location_id: props.locations[0]?.id || '', inventory_batch_id: '', quantity: '', instructions: '' });
    dispenseForm.reset();
    activeModal.value = 'dispense';
}

function saveDispense() {
    dispenseForm.post(`/admin/pharmacy/prescription-items/${selectedItem.value.id}/dispense`, { preserveScroll: true, onSuccess: () => { activeModal.value = null; dispenseForm.reset(); } });
}

function saveReview() {
    review.post(`/admin/pharmacy/prescriptions/${props.prescription.id}/reviews`, { preserveScroll: true, onSuccess: () => { activeModal.value = null; review.reset(); } });
}

function saveAmendment() {
    amendment.post(`/admin/pharmacy/prescriptions/${props.prescription.id}/amendments`, { preserveScroll: true, onSuccess: () => { activeModal.value = null; amendment.reset(); } });
}

function openDispenseAction(dispense, action) {
    selectedDispense.value = dispense;
    dispenseAction.value = action;
    dispenseActionForm.defaults({ reason: '' });
    dispenseActionForm.reset();
    activeModal.value = 'dispense-action';
}

function saveDispenseAction() {
    dispenseActionForm.post(`/admin/pharmacy/dispenses/${selectedDispense.value.id}/${dispenseAction.value}`, { preserveScroll: true, onSuccess: () => { activeModal.value = null; dispenseActionForm.reset(); } });
}
</script>

<template>
    <Head :title="prescription.prescription_number" />
    <AppLayout :title="prescription.prescription_number">
        <PageHeader :title="prescription.prescription_number" description="Prescription review, dispensing and audit-preserving corrections.">
            <template #actions>
                <ActionToolbar align="end">
                    <Link href="/admin/pharmacy/prescriptions" class="rounded-md border px-3 py-2 text-sm font-bold">Back</Link>
                    <PrimaryButton type="button" @click="activeModal = 'review'">Pharmacist Review</PrimaryButton>
                    <PrimaryButton type="button" @click="activeModal = 'amendment'">Add Amendment</PrimaryButton>
                    <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="router.post(`/admin/pharmacy/prescriptions/${prescription.id}/sign`)" >Sign</button>
                    <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="router.post(`/admin/pharmacy/prescriptions/${prescription.id}/bill`)" >Bill</button>
                    <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="openDiscontinue('discontinued')">Discontinue</button>
                    <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="openDiscontinue('cancelled')">Cancel</button>
                </ActionToolbar>
            </template>
        </PageHeader>

        <section class="mb-6 min-w-0 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-950">
            <h2 class="font-black">Allergies And Alerts</h2>
            <p v-for="allergy in prescription.patient?.allergies || []" :key="`a-${allergy.id}`" class="break-words">{{ allergy.substance }} - {{ allergy.severity }}</p>
            <p v-for="alert in prescription.patient?.alerts || []" :key="`al-${alert.id}`" class="break-words">{{ alert.title }} - {{ alert.severity }}</p>
            <p v-if="(prescription.patient?.allergies || []).length === 0 && (prescription.patient?.alerts || []).length === 0">No active allergies or alerts recorded.</p>
        </section>

        <div class="grid gap-6">
            <section class="grid gap-6">
                <article v-for="item in prescription.items" :key="item.id" class="min-w-0 rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="break-words text-lg font-black">{{ item.medicine_name }}</h2>
                            <p class="break-words text-sm text-slate-500">{{ item.dose }} - {{ item.frequency }} - {{ item.duration }} - outstanding {{ Number(item.quantity - item.dispensed_quantity).toFixed(4) }}</p>
                            <p class="mt-2 break-words text-sm">{{ item.instructions }}</p>
                        </div>
                        <PrimaryButton type="button" @click="openDispense(item)">Dispense</PrimaryButton>
                    </div>
                    <div class="mt-3 text-xs text-slate-500">
                        FEFO:
                        <span v-for="row in (fefo.find((entry) => entry.prescription_item_id === item.id)?.batches || [])" :key="row.id" class="mr-2">{{ row.batch?.batch_number }} ({{ row.quantity }})</span>
                    </div>
                </article>
            </section>

            <section class="min-w-0 rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="font-black">Dispensing Events</h2>
                <article v-for="dispense in prescription.dispenses" :key="dispense.id" class="grid min-w-0 gap-3 border-t py-3 text-sm md:grid-cols-[1fr_auto]">
                    <div class="min-w-0">
                        <p class="break-words font-bold">{{ dispense.action }} - {{ dispense.quantity }} - {{ dispense.batch?.batch_number }}</p>
                    </div>
                    <ActionToolbar align="end">
                        <button class="rounded-md border px-2 py-1 text-xs font-bold" type="button" @click="openDispenseAction(dispense, 'return')">Return</button>
                        <button class="rounded-md border px-2 py-1 text-xs font-bold" type="button" @click="openDispenseAction(dispense, 'reverse')">Reverse</button>
                    </ActionToolbar>
                </article>
                <p v-if="prescription.dispenses.length === 0" class="mt-3 text-sm text-slate-500">No dispensing events.</p>
            </section>

            <section class="grid gap-6 lg:grid-cols-2">
                <div class="min-w-0 rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="font-black">Pharmacist Reviews</h2>
                    <p v-for="entry in prescription.reviews" :key="entry.id" class="mt-3 break-words text-sm">{{ entry.action }} - {{ entry.reason }}</p>
                </div>
                <div v-if="prescription.invoice" class="min-w-0 rounded-lg border border-slate-200 bg-white p-5 text-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="font-black">Invoice</h2>
                    <p class="break-words">{{ prescription.invoice.invoice_number || `Draft #${prescription.invoice.id}` }} - {{ prescription.invoice.total_minor }} minor units</p>
                </div>
            </section>
        </div>

        <FormModal :show="activeModal === 'dispense'" :title="`Dispense ${selectedItem?.medicine_name || ''}`" :form="dispenseForm" submit-label="Dispense" @close="activeModal = null" @submit="saveDispense">
            <div class="grid gap-3">
                <select v-model="dispenseForm.inventory_location_id" class="rounded-md border-slate-300"><option v-for="location in locations" :key="location.id" :value="location.id">{{ location.name }}</option></select>
                <select v-model="dispenseForm.inventory_batch_id" class="rounded-md border-slate-300"><option value="">Batch</option><option v-for="batch in batches.filter((batch) => batch.inventory_item_id === selectedItem?.inventory_item_id)" :key="batch.id" :value="batch.id">{{ batch.batch_number }} - {{ batch.state }} - {{ batch.expiry_date || 'no expiry' }}</option></select>
                <TextInput id="dispense_qty" v-model="dispenseForm.quantity" label="Quantity" type="number" />
                <textarea v-model="dispenseForm.instructions" class="rounded-md border-slate-300" rows="2" placeholder="Instructions"></textarea>
            </div>
        </FormModal>

        <FormModal :show="activeModal === 'review'" title="Pharmacist Review" :form="review" submit-label="Record review" @close="activeModal = null" @submit="saveReview">
            <div class="grid gap-3">
                <select v-model="review.action" class="w-full rounded-md border-slate-300"><option value="approved">Approve</option><option value="clarification_requested">Request clarification</option><option value="rejected">Reject</option><option value="substitution_authorized">Authorize substitution</option></select>
                <select v-model="review.prescription_item_id" class="w-full rounded-md border-slate-300"><option value="">Whole prescription</option><option v-for="item in prescription.items" :key="item.id" :value="item.id">{{ item.medicine_name }}</option></select>
                <textarea v-model="review.reason" class="w-full rounded-md border-slate-300" rows="3" placeholder="Reason"></textarea>
                <select v-model="review.substituted_inventory_item_id" class="w-full rounded-md border-slate-300"><option value="">Substituted item</option><option v-for="batch in batches" :key="batch.item?.id" :value="batch.item?.id">{{ batch.item?.name }}</option></select>
                <textarea v-model="review.substitution_note" class="w-full rounded-md border-slate-300" rows="2" placeholder="Substitution note"></textarea>
            </div>
        </FormModal>

        <FormModal :show="activeModal === 'amendment'" title="Append Amendment" :form="amendment" submit-label="Add amendment" @close="activeModal = null" @submit="saveAmendment">
            <div class="grid gap-3">
                <input v-model="amendment.reason" class="w-full rounded-md border-slate-300 text-sm" placeholder="Reason">
                <textarea v-model="amendment.content" class="w-full rounded-md border-slate-300 text-sm" rows="3" placeholder="Amendment"></textarea>
            </div>
        </FormModal>

        <ConfirmDialog :show="activeModal === 'transition'" :title="`${transitionForm.status} prescription`" confirm-label="Save action" :form="transitionForm" require-reason @close="activeModal = null" @confirm="saveTransition" />
        <ConfirmDialog :show="activeModal === 'dispense-action'" :title="`${dispenseAction} dispense`" confirm-label="Save action" :form="dispenseActionForm" require-reason @close="activeModal = null" @confirm="saveDispenseAction" />
    </AppLayout>
</template>
