<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import PrimaryButton from '../../../Components/PrimaryButton.vue';
import TextInput from '../../../Components/TextInput.vue';

const props = defineProps({
    encounter: { type: Object, required: true },
    timeline: { type: Array, default: () => [] },
});

const vitals = useForm({
    temperature: '',
    temperature_unit: 'C',
    pulse: '',
    respiratory_rate: '',
    blood_pressure_systolic: '',
    blood_pressure_diastolic: '',
    oxygen_saturation: '',
    weight_kg: '',
    height_cm: '',
    pain_score: '',
    measured_at: new Date().toISOString().slice(0, 16),
    notes: '',
});
const assessment = useForm({
    presenting_complaint: props.encounter.presenting_complaint || '',
    history_presenting_complaint: props.encounter.history_presenting_complaint || '',
    medical_history: props.encounter.medical_history || '',
    surgical_history: props.encounter.surgical_history || '',
    medication_history: props.encounter.medication_history || '',
    family_history: props.encounter.family_history || '',
    social_history: props.encounter.social_history || '',
    examination_findings: props.encounter.examination_findings || '',
    treatment_plan: props.encounter.treatment_plan || '',
    follow_up_instructions: props.encounter.follow_up_instructions || '',
    follow_up_date: props.encounter.follow_up_date || '',
    referral_recommendation: props.encounter.referral_recommendation || '',
});
const diagnosis = useForm({ description: '', coding_system: '', code: '', status: 'provisional' });
const amendment = useForm({ reason: '', content: '' });

function transition(action) {
    const reason = action === 'cancel' ? window.prompt('Reason') : null;
    if (action === 'cancel' && !reason) return;
    router.patch(`/admin/encounters/${props.encounter.id}/transition`, { action, reason }, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Encounter · ${encounter.patient?.full_name}`" />
    <AppLayout :title="encounter.patient?.full_name || 'Encounter'">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <Link href="/admin/clinical/worklist" class="text-sm font-semibold text-red-800">Back to worklist</Link>
            <div class="flex flex-wrap gap-2">
                <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="transition('pause')">Pause</button>
                <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="transition('resume')">Resume</button>
                <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="transition('cancel')">Cancel</button>
                <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="transition('sign')">Sign</button>
            </div>
        </div>

        <div class="mb-4 grid gap-3 md:grid-cols-2">
            <div v-if="encounter.patient?.allergies?.length" class="rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-950">
                <p class="font-black">Allergies</p>
                <p v-for="allergy in encounter.patient.allergies" :key="allergy.id">{{ allergy.substance }} · {{ allergy.severity }} · {{ allergy.status }}</p>
            </div>
            <div v-if="encounter.patient?.alerts?.length" class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950">
                <p class="font-black">Important Alerts</p>
                <p v-for="alert in encounter.patient.alerts" :key="alert.id">{{ alert.title }} · {{ alert.severity }} · {{ alert.status }}</p>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[1fr_380px]">
            <section class="space-y-6">
                <form class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="assessment.patch(`/admin/encounters/${encounter.id}/assessment`, { preserveScroll: true })">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="text-lg font-black">Clinical Assessment</h2>
                        <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-bold text-slate-700">{{ encounter.status }}</span>
                    </div>
                    <div class="mt-4 grid gap-4">
                        <label class="grid gap-1 text-sm">Presenting complaint<textarea v-model="assessment.presenting_complaint" class="rounded-md border-slate-300" rows="2"></textarea></label>
                        <label class="grid gap-1 text-sm">History<textarea v-model="assessment.history_presenting_complaint" class="rounded-md border-slate-300" rows="3"></textarea></label>
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="grid gap-1 text-sm">Medical history<textarea v-model="assessment.medical_history" class="rounded-md border-slate-300" rows="2"></textarea></label>
                            <label class="grid gap-1 text-sm">Surgical history<textarea v-model="assessment.surgical_history" class="rounded-md border-slate-300" rows="2"></textarea></label>
                            <label class="grid gap-1 text-sm">Medication history<textarea v-model="assessment.medication_history" class="rounded-md border-slate-300" rows="2"></textarea></label>
                            <label class="grid gap-1 text-sm">Family history<textarea v-model="assessment.family_history" class="rounded-md border-slate-300" rows="2"></textarea></label>
                            <label class="grid gap-1 text-sm">Social history<textarea v-model="assessment.social_history" class="rounded-md border-slate-300" rows="2"></textarea></label>
                            <label class="grid gap-1 text-sm">Examination findings<textarea v-model="assessment.examination_findings" class="rounded-md border-slate-300" rows="2"></textarea></label>
                        </div>
                        <label class="grid gap-1 text-sm">Treatment / management plan<textarea v-model="assessment.treatment_plan" class="rounded-md border-slate-300" rows="3"></textarea></label>
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="grid gap-1 text-sm">Follow-up instructions<textarea v-model="assessment.follow_up_instructions" class="rounded-md border-slate-300" rows="2"></textarea></label>
                            <TextInput id="follow_up_date" v-model="assessment.follow_up_date" label="Follow-up date" type="date" />
                        </div>
                        <label class="grid gap-1 text-sm">Referral recommendation<textarea v-model="assessment.referral_recommendation" class="rounded-md border-slate-300" rows="2"></textarea></label>
                    </div>
                    <PrimaryButton class="mt-4" :disabled="assessment.processing">Save assessment</PrimaryButton>
                </form>

                <section class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="font-black">Diagnoses</h2>
                    <form class="mt-4 grid gap-3 md:grid-cols-4" @submit.prevent="diagnosis.post(`/admin/encounters/${encounter.id}/diagnoses`, { preserveScroll: true, onSuccess: () => diagnosis.reset() })">
                        <input v-model="diagnosis.description" class="rounded-md border-slate-300 md:col-span-4" placeholder="Diagnosis description">
                        <input v-model="diagnosis.coding_system" class="rounded-md border-slate-300" placeholder="Coding system">
                        <input v-model="diagnosis.code" class="rounded-md border-slate-300" placeholder="Code">
                        <select v-model="diagnosis.status" class="rounded-md border-slate-300"><option value="provisional">Provisional</option><option value="confirmed">Confirmed</option></select>
                        <PrimaryButton :disabled="diagnosis.processing">Add diagnosis</PrimaryButton>
                    </form>
                    <div class="mt-4 divide-y divide-slate-100 text-sm dark:divide-slate-800">
                        <p v-for="item in encounter.diagnoses" :key="item.id" class="py-2"><strong>{{ item.description }}</strong> · {{ item.status }} <span v-if="item.code">· {{ item.coding_system }} {{ item.code }}</span></p>
                    </div>
                </section>
            </section>

            <aside class="space-y-6">
                <form class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="vitals.post(`/admin/encounters/${encounter.id}/vitals`, { preserveScroll: true, onSuccess: () => vitals.reset() })">
                    <h2 class="font-black">Vitals</h2>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <TextInput id="temp" v-model="vitals.temperature" label="Temperature" type="number" step="0.1" />
                        <label class="grid gap-1 text-sm">Unit<select v-model="vitals.temperature_unit" class="rounded-md border-slate-300"><option value="C">C</option><option value="F">F</option></select></label>
                        <TextInput id="pulse" v-model="vitals.pulse" label="Pulse" type="number" />
                        <TextInput id="resp" v-model="vitals.respiratory_rate" label="Respiratory rate" type="number" />
                        <TextInput id="bp_sys" v-model="vitals.blood_pressure_systolic" label="BP systolic" type="number" />
                        <TextInput id="bp_dia" v-model="vitals.blood_pressure_diastolic" label="BP diastolic" type="number" />
                        <TextInput id="spo2" v-model="vitals.oxygen_saturation" label="Oxygen saturation" type="number" />
                        <TextInput id="pain" v-model="vitals.pain_score" label="Pain score" type="number" />
                        <TextInput id="weight" v-model="vitals.weight_kg" label="Weight kg" type="number" step="0.1" />
                        <TextInput id="height" v-model="vitals.height_cm" label="Height cm" type="number" step="0.1" />
                        <TextInput id="measured" v-model="vitals.measured_at" label="Measured at" type="datetime-local" />
                        <label class="grid gap-1 text-sm sm:col-span-2">Notes<textarea v-model="vitals.notes" class="rounded-md border-slate-300" rows="2"></textarea></label>
                    </div>
                    <PrimaryButton class="mt-4" :disabled="vitals.processing">Record vitals</PrimaryButton>
                    <div class="mt-4 divide-y divide-slate-100 text-sm dark:divide-slate-800">
                        <p v-for="item in encounter.vitals" :key="item.id" class="py-2">{{ item.measured_at }} · T {{ item.temperature || '-' }}{{ item.temperature_unit }} · BP {{ item.blood_pressure_systolic || '-' }}/{{ item.blood_pressure_diastolic || '-' }} · BMI {{ item.bmi || '-' }}</p>
                    </div>
                </form>

                <form class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="amendment.post(`/admin/encounters/${encounter.id}/amendments`, { preserveScroll: true, onSuccess: () => amendment.reset() })">
                    <h2 class="font-black">Signed Encounter Amendment</h2>
                    <input v-model="amendment.reason" class="mt-3 w-full rounded-md border-slate-300 text-sm" placeholder="Reason">
                    <textarea v-model="amendment.content" class="mt-3 w-full rounded-md border-slate-300 text-sm" rows="3" placeholder="Amendment"></textarea>
                    <PrimaryButton class="mt-3" :disabled="amendment.processing">Add amendment</PrimaryButton>
                    <div class="mt-4 divide-y divide-slate-100 text-sm dark:divide-slate-800">
                        <p v-for="item in encounter.amendments" :key="item.id" class="py-2"><strong>{{ item.reason }}</strong> · {{ item.content }}</p>
                    </div>
                </form>

                <section class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="font-black">Clinical Timeline</h2>
                    <div class="mt-3 divide-y divide-slate-100 text-sm dark:divide-slate-800">
                        <p v-for="item in timeline" :key="`${item.type}-${item.label}-${item.occurred_at}`" class="py-2">{{ item.type }} · {{ item.label }} · {{ item.occurred_at }}</p>
                    </div>
                </section>
            </aside>
        </div>
    </AppLayout>
</template>
