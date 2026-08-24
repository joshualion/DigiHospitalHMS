<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    prescriptions: { type: Object, required: true },
    facilities: { type: Array, default: () => [] },
    patients: { type: Array, default: () => [] },
    encounters: { type: Array, default: () => [] },
    items: { type: Array, default: () => [] },
    units: { type: Array, default: () => [] },
});

const form = useForm({
    facility_id: props.facilities[0]?.id || '',
    patient_id: '',
    clinical_encounter_id: '',
    clinical_note: '',
    items: [{ inventory_item_id: '', inventory_unit_id: '', dose: '', route: '', frequency: '', duration: '', quantity: '', instructions: '', indication: '', is_prn: false, prn_instructions: '' }],
});

function addItem() {
    form.items.push({ inventory_item_id: '', inventory_unit_id: '', dose: '', route: '', frequency: '', duration: '', quantity: '', instructions: '', indication: '', is_prn: false, prn_instructions: '' });
}
</script>

<template>
    <Head title="Prescriptions" />
    <AppLayout title="Prescriptions">
        <div class="grid gap-6 xl:grid-cols-[1fr_420px]">
            <section class="rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 p-4 dark:border-slate-800">
                    <h2 class="font-black">Pharmacist Worklist</h2>
                </div>
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    <Link v-for="rx in prescriptions.data" :key="rx.id" class="grid gap-3 p-4 md:grid-cols-[1fr_auto]" :href="`/admin/pharmacy/prescriptions/${rx.id}`">
                        <div>
                            <p class="font-bold">{{ rx.prescription_number }} - {{ rx.patient?.full_name }}</p>
                            <p class="text-sm text-slate-500">{{ rx.items.map((item) => item.medicine_name).join(', ') }}</p>
                        </div>
                        <span class="h-fit rounded-full bg-slate-100 px-2 py-1 text-xs font-bold text-slate-700">{{ rx.status }}</span>
                    </Link>
                </div>
            </section>
            <form class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="form.post('/admin/pharmacy/prescriptions')">
                <h2 class="font-black">Draft Prescription</h2>
                <div class="mt-4 grid gap-3">
                    <select v-model="form.patient_id" class="rounded-md border-slate-300"><option value="">Patient</option><option v-for="patient in patients" :key="patient.id" :value="patient.id">{{ patient.hospital_number }} - {{ patient.full_name }}</option></select>
                    <select v-model="form.facility_id" class="rounded-md border-slate-300"><option v-for="facility in facilities" :key="facility.id" :value="facility.id">{{ facility.name }}</option></select>
                    <select v-model="form.clinical_encounter_id" class="rounded-md border-slate-300"><option value="">Encounter</option><option v-for="encounter in encounters" :key="encounter.id" :value="encounter.id">Encounter #{{ encounter.id }} - {{ encounter.status }}</option></select>
                    <textarea v-model="form.clinical_note" class="rounded-md border-slate-300" rows="2" placeholder="Clinical note"></textarea>
                    <div v-for="(row, index) in form.items" :key="index" class="grid gap-2 rounded-md border border-slate-200 p-3">
                        <select v-model="row.inventory_item_id" class="rounded-md border-slate-300"><option value="">Medicine</option><option v-for="item in items" :key="item.id" :value="item.id">{{ item.name }}</option></select>
                        <select v-model="row.inventory_unit_id" class="rounded-md border-slate-300"><option value="">Unit</option><option v-for="unit in units" :key="unit.id" :value="unit.id">{{ unit.code }}</option></select>
                        <TextInput :id="`dose_${index}`" v-model="row.dose" label="Dose" />
                        <TextInput :id="`qty_${index}`" v-model="row.quantity" label="Quantity" type="number" />
                        <TextInput :id="`freq_${index}`" v-model="row.frequency" label="Frequency" />
                        <TextInput :id="`duration_${index}`" v-model="row.duration" label="Duration" />
                        <textarea v-model="row.instructions" class="rounded-md border-slate-300" rows="2" placeholder="Instructions"></textarea>
                        <label class="text-sm"><input v-model="row.is_prn" type="checkbox"> PRN</label>
                    </div>
                    <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="addItem">Add medicine</button>
                    <PrimaryButton :disabled="form.processing">Create draft</PrimaryButton>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
