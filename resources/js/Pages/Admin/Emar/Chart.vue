<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    chart: { type: Object, required: true },
    doses: { type: Array, default: () => [] },
});

const now = () => new Date().toISOString().slice(0, 16);
const form = useForm({
    outcome: 'administered',
    actual_at: now(),
    quantity_administered: 1,
    prescription_dispense_id: '',
    confirmation: { patient: true, medication: true, dose: true, route: true, timing: true },
    reason: '',
    prn_indication: '',
    prn_response: '',
});
const amendment = useForm({ reason: '', content: '' });

function fullName(patient) {
    return [patient?.first_name, patient?.middle_name, patient?.last_name].filter(Boolean).join(' ');
}

function administer(dose) {
    form.prescription_dispense_id = dose.prescription_item?.dispenses?.find((dispense) => dispense.action === 'dispense')?.id || '';
    form.post(`/admin/emar/schedules/${dose.id}/administer`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`eMAR - ${fullName(chart.patient)}`" />
    <AppLayout :title="`eMAR - ${fullName(chart.patient)}`">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <Link href="/admin/emar" class="text-sm font-semibold text-red-800">Back to eMAR worklist</Link>
            <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="router.post(`/admin/emar/charts/${chart.id}/sync`, {}, { preserveScroll: true })">Refresh schedule</button>
        </div>

        <div class="mb-4 grid gap-3 md:grid-cols-3">
            <section class="rounded-lg border border-slate-200 bg-white p-4 text-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="font-black">{{ chart.admission?.admission_number }}</p>
                <p class="text-slate-500">{{ chart.admission?.ward?.name }} - {{ chart.admission?.bed?.label }}</p>
            </section>
            <section class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-950">
                <p class="font-black">Allergies</p>
                <p v-for="item in chart.patient?.allergies || []" :key="item.id">{{ item.substance }} - {{ item.severity }}</p>
                <p v-if="!chart.patient?.allergies?.length">None recorded</p>
            </section>
            <section class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                <p class="font-black">Alerts / Warnings</p>
                <p v-for="item in chart.patient?.alerts || []" :key="item.id">{{ item.title }} - {{ item.severity }}</p>
                <p v-if="!chart.patient?.alerts?.length">No patient alerts recorded</p>
            </section>
        </div>

        <div class="grid gap-6 xl:grid-cols-[1fr_380px]">
            <section class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="font-black">Medication Schedule</h2>
                <div class="mt-4 divide-y divide-slate-100 dark:divide-slate-800">
                    <article v-for="dose in doses" :key="dose.id" class="grid gap-3 py-4 lg:grid-cols-[1fr_auto]">
                        <div>
                            <p class="font-bold">{{ dose.medicine_name }} - {{ dose.dose }} - {{ dose.route || 'Route not specified' }}</p>
                            <p class="text-sm text-slate-500">{{ dose.order_type }} - {{ dose.scheduled_at || 'PRN' }} - {{ dose.due_state }} - {{ dose.status }}</p>
                            <p v-if="dose.prn_instructions" class="text-xs text-slate-500">PRN: {{ dose.prn_instructions }}</p>
                            <p v-if="dose.prescription_item?.prescription?.status === 'discontinued'" class="text-xs font-bold text-red-700">Prescription discontinued</p>
                        </div>
                        <PrimaryButton v-if="!dose.administration" type="button" @click="administer(dose)">Record</PrimaryButton>
                        <span v-else class="text-sm font-bold text-emerald-700">{{ dose.administration.outcome }}</span>
                    </article>
                </div>
            </section>

            <aside class="space-y-6">
                <form class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="font-black">Administration Details</h2>
                    <div class="mt-4 grid gap-3">
                        <select v-model="form.outcome" class="rounded-md border-slate-300">
                            <option value="administered">Administered</option>
                            <option value="omitted">Omitted</option>
                            <option value="refused">Refused</option>
                            <option value="held">Held</option>
                            <option value="unavailable">Unavailable</option>
                            <option value="delayed">Delayed</option>
                            <option value="not-given">Not given</option>
                        </select>
                        <TextInput id="emar_actual_at" v-model="form.actual_at" label="Actual time" type="datetime-local" />
                        <TextInput id="emar_quantity" v-model="form.quantity_administered" label="Quantity administered" type="number" step="0.0001" />
                        <textarea v-model="form.reason" class="rounded-md border-slate-300" rows="2" placeholder="Reason for non-administered or delayed outcome"></textarea>
                        <textarea v-model="form.prn_indication" class="rounded-md border-slate-300" rows="2" placeholder="PRN indication"></textarea>
                        <textarea v-model="form.prn_response" class="rounded-md border-slate-300" rows="2" placeholder="PRN response/effect"></textarea>
                        <label v-for="(_, key) in form.confirmation" :key="key" class="text-sm"><input v-model="form.confirmation[key]" type="checkbox"> Confirm {{ key }}</label>
                    </div>
                </form>

                <section class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="font-black">Medication History</h2>
                    <div class="mt-4 divide-y divide-slate-100 text-sm dark:divide-slate-800">
                        <article v-for="admin in chart.emar_administrations" :key="admin.id" class="py-3">
                            <p class="font-bold">{{ admin.medicine_name }} - {{ admin.outcome }}</p>
                            <p class="text-slate-500">{{ admin.actual_at }} - Batch {{ admin.batch?.batch_number || 'not consumed' }}</p>
                            <div class="mt-2 grid gap-2">
                                <input v-model="amendment.reason" class="rounded-md border-slate-300 text-xs" placeholder="Correction reason">
                                <input v-model="amendment.content" class="rounded-md border-slate-300 text-xs" placeholder="Correction note">
                                <button class="rounded-md border px-2 py-1 text-xs font-bold" type="button" @click="amendment.post(`/admin/emar/administrations/${admin.id}/amendments`, { preserveScroll: true })">Add correction</button>
                            </div>
                            <p v-for="item in admin.amendments" :key="item.id" class="mt-2 text-xs text-slate-500">Correction: {{ item.reason }} - {{ item.content }}</p>
                        </article>
                    </div>
                </section>
            </aside>
        </div>
    </AppLayout>
</template>
