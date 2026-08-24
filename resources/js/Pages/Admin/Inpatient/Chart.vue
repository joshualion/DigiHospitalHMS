<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    chart: { type: Object, required: true },
    timeline: { type: Array, default: () => [] },
});

const now = () => new Date().toISOString().slice(0, 16);
const progress = useForm({ note_type: 'soap', subjective: '', objective: '', assessment: '', plan: '', narrative: '' });
const amendment = useForm({ reason: '', content: '' });
const nursing = useForm({ shift: 'day', note: '' });
const observation = useForm({ temperature: '', temperature_unit: 'C', pulse: '', respiratory_rate: '', blood_pressure_systolic: '', blood_pressure_diastolic: '', oxygen_saturation: '', pain_score: '', glucose: '', glucose_unit: 'mmol/L', consciousness_notes: '', observed_at: now() });
const intake = useForm({ direction: 'intake', measurement_type: 'oral fluids', quantity: '', unit: 'ml', notes: '', measured_at: now() });
const carePlan = useForm({ problem: '', goal: '', intervention: '', evaluation: '', status: 'active' });
const diagnosis = useForm({ description: '', coding_system: '', code: '', status: 'provisional' });
const order = useForm({ order_type: 'nursing_care', instruction: '', status: 'active' });
const handover = useForm({ from_shift: 'day', to_shift: 'night', summary: '' });
const discharge = useForm({
    admission_summary: props.chart.discharge_summary?.admission_summary || props.chart.admission?.reason || '',
    diagnosis_summary: props.chart.discharge_summary?.diagnosis_summary || props.chart.diagnoses?.map((item) => item.description).join('\n') || '',
    results_summary: props.chart.discharge_summary?.results_summary || '',
    clinical_course: props.chart.discharge_summary?.clinical_course || '',
    discharge_plan: props.chart.discharge_summary?.discharge_plan || '',
});

function fullName(patient) {
    return [patient?.first_name, patient?.middle_name, patient?.last_name].filter(Boolean).join(' ');
}

function transitionOrder(item, action) {
    const reason = ['cancel', 'discontinue'].includes(action) ? window.prompt('Reason') : null;
    router.patch(`/admin/inpatient/orders/${item.id}`, { action, reason }, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Ward Chart - ${fullName(chart.patient)}`" />
    <AppLayout :title="fullName(chart.patient) || 'Ward Chart'">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <Link href="/admin/inpatient" class="text-sm font-semibold text-red-800">Back to ward charts</Link>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">{{ chart.status }}</span>
        </div>

        <div class="mb-4 grid gap-3 md:grid-cols-3">
            <section class="rounded-lg border border-slate-200 bg-white p-4 text-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="font-black">{{ chart.admission?.admission_number }}</p>
                <p class="text-slate-500">{{ chart.ward?.name || chart.admission?.ward?.name }} - {{ chart.bed?.label || chart.admission?.bed?.label }}</p>
            </section>
            <section class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-950">
                <p class="font-black">Allergies</p>
                <p v-for="item in chart.patient?.allergies || []" :key="item.id">{{ item.substance }} - {{ item.severity }}</p>
                <p v-if="!chart.patient?.allergies?.length">None recorded</p>
            </section>
            <section class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                <p class="font-black">Alerts</p>
                <p v-for="item in chart.patient?.alerts || []" :key="item.id">{{ item.title }} - {{ item.severity }}</p>
                <p v-if="!chart.patient?.alerts?.length">None recorded</p>
            </section>
        </div>

        <div class="grid gap-6 xl:grid-cols-[1fr_420px]">
            <section class="space-y-6">
                <form class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="progress.post(`/admin/inpatient/charts/${chart.id}/progress-notes`, { preserveScroll: true, onSuccess: () => progress.reset() })">
                    <h2 class="font-black">Clinician Progress Note</h2>
                    <div class="mt-4 grid gap-3">
                        <select v-model="progress.note_type" class="rounded-md border-slate-300"><option value="soap">SOAP</option><option value="ward_round">Ward round</option><option value="review">Review</option><option value="procedure_note">Procedure note</option><option value="other">Other</option></select>
                        <textarea v-model="progress.subjective" class="rounded-md border-slate-300" rows="2" placeholder="Subjective"></textarea>
                        <textarea v-model="progress.objective" class="rounded-md border-slate-300" rows="2" placeholder="Objective"></textarea>
                        <textarea v-model="progress.assessment" class="rounded-md border-slate-300" rows="2" placeholder="Assessment"></textarea>
                        <textarea v-model="progress.plan" class="rounded-md border-slate-300" rows="2" placeholder="Plan"></textarea>
                        <textarea v-model="progress.narrative" class="rounded-md border-slate-300" rows="2" placeholder="Additional narrative"></textarea>
                        <PrimaryButton :disabled="progress.processing">Save note</PrimaryButton>
                    </div>
                    <div class="mt-4 divide-y divide-slate-100 text-sm dark:divide-slate-800">
                        <article v-for="note in chart.progress_notes" :key="note.id" class="py-3">
                            <p class="font-bold">{{ note.note_type }} - {{ note.status }}</p>
                            <p>{{ note.assessment || note.narrative || note.subjective }}</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <button v-if="note.status === 'draft'" class="rounded-md border px-2 py-1 text-xs font-bold" type="button" @click="router.post(`/admin/inpatient/progress-notes/${note.id}/sign`, {}, { preserveScroll: true })">Sign</button>
                                <button v-if="note.status === 'signed'" class="rounded-md border px-2 py-1 text-xs font-bold" type="button" @click="amendment.post(`/admin/inpatient/progress-notes/${note.id}/amendments`, { preserveScroll: true })">Amend</button>
                            </div>
                            <p v-for="amend in note.amendments" :key="amend.id" class="mt-2 text-xs text-slate-500">Amendment: {{ amend.reason }} - {{ amend.content }}</p>
                        </article>
                    </div>
                    <div class="mt-3 grid gap-2 md:grid-cols-2">
                        <input v-model="amendment.reason" class="rounded-md border-slate-300 text-sm" placeholder="Amendment reason">
                        <input v-model="amendment.content" class="rounded-md border-slate-300 text-sm" placeholder="Amendment content">
                    </div>
                </form>

                <section class="grid gap-6 lg:grid-cols-2">
                    <form class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="nursing.post(`/admin/inpatient/charts/${chart.id}/nursing-notes`, { preserveScroll: true, onSuccess: () => nursing.reset() })">
                        <h2 class="font-black">Nursing Note</h2>
                        <select v-model="nursing.shift" class="mt-3 w-full rounded-md border-slate-300"><option value="day">Day</option><option value="night">Night</option><option value="evening">Evening</option></select>
                        <textarea v-model="nursing.note" class="mt-3 w-full rounded-md border-slate-300" rows="4" placeholder="Nursing note"></textarea>
                        <PrimaryButton class="mt-3" :disabled="nursing.processing">Record note</PrimaryButton>
                    </form>

                    <form class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="handover.post(`/admin/inpatient/charts/${chart.id}/handovers`, { preserveScroll: true, onSuccess: () => handover.reset() })">
                        <h2 class="font-black">Shift Handover</h2>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <TextInput id="from_shift" v-model="handover.from_shift" label="From shift" />
                            <TextInput id="to_shift" v-model="handover.to_shift" label="To shift" />
                        </div>
                        <textarea v-model="handover.summary" class="mt-3 w-full rounded-md border-slate-300" rows="4" placeholder="Handover summary"></textarea>
                        <PrimaryButton class="mt-3" :disabled="handover.processing">Sign handover</PrimaryButton>
                    </form>
                </section>

                <form class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="discharge.post(`/admin/inpatient/charts/${chart.id}/discharge-summary`, { preserveScroll: true })">
                    <h2 class="font-black">Discharge Summary</h2>
                    <div class="mt-4 grid gap-3">
                        <textarea v-model="discharge.admission_summary" class="rounded-md border-slate-300" rows="2" placeholder="Admission summary"></textarea>
                        <textarea v-model="discharge.diagnosis_summary" class="rounded-md border-slate-300" rows="2" placeholder="Diagnosis summary"></textarea>
                        <textarea v-model="discharge.results_summary" class="rounded-md border-slate-300" rows="2" placeholder="Results summary"></textarea>
                        <textarea v-model="discharge.clinical_course" class="rounded-md border-slate-300" rows="2" placeholder="Clinical course"></textarea>
                        <textarea v-model="discharge.discharge_plan" class="rounded-md border-slate-300" rows="2" placeholder="Discharge plan"></textarea>
                        <div class="flex flex-wrap gap-2">
                            <PrimaryButton :disabled="discharge.processing">Draft summary</PrimaryButton>
                            <button v-if="chart.discharge_summary?.status === 'draft'" class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="router.post(`/admin/inpatient/discharge-summaries/${chart.discharge_summary.id}/sign`, {}, { preserveScroll: true })">Sign summary</button>
                        </div>
                    </div>
                </form>
            </section>

            <aside class="space-y-6">
                <form class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="observation.post(`/admin/inpatient/charts/${chart.id}/observations`, { preserveScroll: true, onSuccess: () => observation.reset() })">
                    <h2 class="font-black">Observation Chart</h2>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <TextInput id="obs_temp" v-model="observation.temperature" label="Temperature" type="number" step="0.1" />
                        <select v-model="observation.temperature_unit" class="rounded-md border-slate-300"><option value="C">C</option><option value="F">F</option></select>
                        <TextInput id="obs_pulse" v-model="observation.pulse" label="Pulse" type="number" />
                        <TextInput id="obs_resp" v-model="observation.respiratory_rate" label="Respiration" type="number" />
                        <TextInput id="obs_bp_s" v-model="observation.blood_pressure_systolic" label="BP systolic" type="number" />
                        <TextInput id="obs_bp_d" v-model="observation.blood_pressure_diastolic" label="BP diastolic" type="number" />
                        <TextInput id="obs_spo2" v-model="observation.oxygen_saturation" label="Oxygen" type="number" />
                        <TextInput id="obs_pain" v-model="observation.pain_score" label="Pain" type="number" />
                        <TextInput id="obs_glucose" v-model="observation.glucose" label="Glucose" type="number" step="0.1" />
                        <TextInput id="obs_time" v-model="observation.observed_at" label="Observed at" type="datetime-local" />
                    </div>
                    <textarea v-model="observation.consciousness_notes" class="mt-3 w-full rounded-md border-slate-300" rows="2" placeholder="Consciousness notes"></textarea>
                    <PrimaryButton class="mt-3" :disabled="observation.processing">Record observation</PrimaryButton>
                </form>

                <form class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="intake.post(`/admin/inpatient/charts/${chart.id}/intake-output`, { preserveScroll: true, onSuccess: () => intake.reset() })">
                    <h2 class="font-black">Intake / Output</h2>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <select v-model="intake.direction" class="rounded-md border-slate-300"><option value="intake">Intake</option><option value="output">Output</option></select>
                        <TextInput id="io_type" v-model="intake.measurement_type" label="Type" />
                        <TextInput id="io_qty" v-model="intake.quantity" label="Quantity" type="number" step="0.01" />
                        <TextInput id="io_unit" v-model="intake.unit" label="Unit" />
                        <TextInput id="io_time" v-model="intake.measured_at" label="Measured at" type="datetime-local" />
                    </div>
                    <textarea v-model="intake.notes" class="mt-3 w-full rounded-md border-slate-300" rows="2" placeholder="Notes"></textarea>
                    <PrimaryButton class="mt-3" :disabled="intake.processing">Record intake/output</PrimaryButton>
                </form>

                <form class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="carePlan.post(`/admin/inpatient/charts/${chart.id}/care-plans`, { preserveScroll: true, onSuccess: () => carePlan.reset() })">
                    <h2 class="font-black">Care Plan</h2>
                    <textarea v-model="carePlan.problem" class="mt-3 w-full rounded-md border-slate-300" rows="2" placeholder="Problem"></textarea>
                    <textarea v-model="carePlan.goal" class="mt-3 w-full rounded-md border-slate-300" rows="2" placeholder="Goal"></textarea>
                    <textarea v-model="carePlan.intervention" class="mt-3 w-full rounded-md border-slate-300" rows="2" placeholder="Intervention"></textarea>
                    <textarea v-model="carePlan.evaluation" class="mt-3 w-full rounded-md border-slate-300" rows="2" placeholder="Evaluation"></textarea>
                    <PrimaryButton class="mt-3" :disabled="carePlan.processing">Record care plan</PrimaryButton>
                </form>

                <form class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="order.post(`/admin/inpatient/charts/${chart.id}/orders`, { preserveScroll: true, onSuccess: () => order.reset() })">
                    <h2 class="font-black">Clinician Order</h2>
                    <select v-model="order.order_type" class="mt-3 w-full rounded-md border-slate-300"><option value="nursing_care">Nursing care</option><option value="monitoring">Monitoring</option><option value="diet">Diet</option><option value="activity">Activity</option><option value="investigation">Investigation</option></select>
                    <textarea v-model="order.instruction" class="mt-3 w-full rounded-md border-slate-300" rows="3" placeholder="Order instruction"></textarea>
                    <select v-model="order.status" class="mt-3 w-full rounded-md border-slate-300"><option value="draft">Draft</option><option value="active">Active</option></select>
                    <PrimaryButton class="mt-3" :disabled="order.processing">Record order</PrimaryButton>
                    <div class="mt-4 divide-y divide-slate-100 text-sm dark:divide-slate-800">
                        <p v-for="item in chart.orders" :key="item.id" class="py-2">
                            <strong>{{ item.order_type }}</strong> - {{ item.status }}<br>{{ item.instruction }}
                            <span class="mt-2 flex flex-wrap gap-2">
                                <button class="rounded-md border px-2 py-1 text-xs font-bold" type="button" @click="transitionOrder(item, 'activate')">Activate</button>
                                <button class="rounded-md border px-2 py-1 text-xs font-bold" type="button" @click="transitionOrder(item, 'acknowledge')">Acknowledge</button>
                                <button class="rounded-md border px-2 py-1 text-xs font-bold" type="button" @click="transitionOrder(item, 'complete')">Complete</button>
                            </span>
                        </p>
                    </div>
                </form>

                <section class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="font-black">Timeline</h2>
                    <p v-for="item in timeline" :key="`${item.type}-${item.label}-${item.occurred_at}`" class="mt-2 text-sm">{{ item.type }} - {{ item.label }} - {{ item.occurred_at }}</p>
                </section>
            </aside>
        </div>
    </AppLayout>
</template>
