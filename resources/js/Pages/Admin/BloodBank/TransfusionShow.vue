<script setup>
import ActionToolbar from '@/Components/Admin/ActionToolbar.vue';
import FormModal from '@/Components/Admin/FormModal.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({ issue: { type: Object, required: true } });
const page = usePage();
const permissions = computed(() => page.props.auth.permissions || []);
const roles = computed(() => page.props.auth.roles || []);
const can = (permission) => roles.value.includes('superadmin') || permissions.value.includes(permission);
const episode = computed(() => props.issue.transfusion_episode || null);
const activeModal = ref(null);
const selectedReaction = ref(null);

const startForm = useForm({
    started_at: '',
    destination: props.issue.destination || '',
    patient_identifier_checked: props.issue.request?.patient?.hospital_number || '',
    component_identifier_checked: props.issue.component?.component_number || '',
    identity_check_status: 'matched',
    notes: '',
});
const observationForm = useForm({ observed_at: '', temperature_c: '', pulse_bpm: '', respiratory_rate: '', systolic_bp: '', diastolic_bp: '', spo2_percent: '', notes: '' });
const completeForm = useForm({ completed_at: '', notes: '' });
const stopForm = useForm({ stopped_at: '', stop_reason: '', notes: '' });
const reactionForm = useForm({ occurred_at: '', reported_severity: 'unspecified', observed_signs: '', immediate_actions: '', clinician_notified_at: '', blood_bank_notified_at: '' });
const resolveForm = useForm({ resolution_notes: '' });

function submit(form, url) {
    form.post(url, {
        preserveScroll: true,
        onSuccess: () => {
            activeModal.value = null;
            selectedReaction.value = null;
            form.reset();
        },
    });
}

function resolveReaction(reaction) {
    selectedReaction.value = reaction;
    resolveForm.reset();
    activeModal.value = 'resolve';
}
</script>

<template>
    <AppLayout title="Transfusion Administration">
        <PageHeader title="Transfusion Administration" description="Bedside identity checks, administration record, observations and reaction documentation. This workflow records clinical actions; it does not make treatment decisions.">
            <template #actions>
                <ActionToolbar align="end">
                    <Link class="rounded-md border px-3 py-2 text-sm font-bold" style="border-color: var(--admin-border);" :href="`/admin/blood-bank/requests/${issue.blood_request_id}`">Back to request</Link>
                    <PrimaryButton v-if="!episode && can('blood-transfusion.administer')" type="button" @click="activeModal = 'start'">Start transfusion</PrimaryButton>
                    <template v-else-if="episode?.status === 'in_progress'">
                        <button v-if="can('blood-transfusion.observe')" class="rounded-md border px-3 py-2 text-sm font-bold" style="border-color: var(--admin-border);" type="button" @click="activeModal = 'observe'">Record observation</button>
                        <button v-if="can('blood-transfusion.reactions.manage')" class="rounded-md border border-amber-400 px-3 py-2 text-sm font-bold text-amber-700" type="button" @click="activeModal = 'reaction'">Report reaction</button>
                        <button v-if="can('blood-transfusion.administer')" class="rounded-md border px-3 py-2 text-sm font-bold" style="border-color: var(--admin-border);" type="button" @click="activeModal = 'stop'">Stop</button>
                        <PrimaryButton v-if="can('blood-transfusion.administer')" type="button" @click="activeModal = 'complete'">Complete</PrimaryButton>
                    </template>
                </ActionToolbar>
            </template>
        </PageHeader>

        <div class="space-y-5 overflow-x-hidden">
            <section class="grid gap-3 md:grid-cols-4">
                <div class="rounded-md border p-3" style="border-color: var(--admin-border); background: var(--admin-surface);"><p class="text-xs font-black uppercase">Patient</p><p class="font-bold">{{ issue.request?.patient?.full_name }}</p><p class="text-sm">{{ issue.request?.patient?.hospital_number }}</p></div>
                <div class="rounded-md border p-3" style="border-color: var(--admin-border); background: var(--admin-surface);"><p class="text-xs font-black uppercase">Issue</p><p class="font-bold">{{ issue.issue_number }}</p></div>
                <div class="rounded-md border p-3" style="border-color: var(--admin-border); background: var(--admin-surface);"><p class="text-xs font-black uppercase">Component</p><p class="font-bold">{{ issue.component?.component_number }}</p><p class="text-sm">{{ issue.component?.type?.name }}</p></div>
                <div class="rounded-md border p-3" style="border-color: var(--admin-border); background: var(--admin-surface);"><p class="text-xs font-black uppercase">Transfusion</p><p class="font-bold">{{ episode?.status || 'Not started' }}</p></div>
            </section>

            <section v-if="episode" class="rounded-md border p-5" style="border-color: var(--admin-border); background: var(--admin-surface);">
                <h2 class="font-black">Bedside identity record</h2>
                <div class="mt-3 grid gap-3 text-sm md:grid-cols-2">
                    <p><strong>Patient identifier checked:</strong> {{ episode.patient_identifier_checked }}</p>
                    <p><strong>Component identifier checked:</strong> {{ episode.component_identifier_checked }}</p>
                    <p><strong>Identity status:</strong> {{ episode.identity_check_status }}</p>
                    <p><strong>Started:</strong> {{ episode.started_at }}</p>
                    <p v-if="episode.completed_at"><strong>Completed:</strong> {{ episode.completed_at }}</p>
                    <p v-if="episode.stopped_at"><strong>Stopped:</strong> {{ episode.stopped_at }}</p>
                    <p v-if="episode.stop_reason" class="md:col-span-2"><strong>Stop reason:</strong> {{ episode.stop_reason }}</p>
                </div>
            </section>

            <section v-if="episode" class="rounded-md border p-5" style="border-color: var(--admin-border); background: var(--admin-surface);">
                <div class="flex items-center justify-between gap-3"><h2 class="font-black">Observations</h2><button v-if="episode.status === 'in_progress' && can('blood-transfusion.observe')" class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="activeModal = 'observe'">Add</button></div>
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead><tr><th class="p-2 text-left">Time</th><th class="p-2">Temp °C</th><th class="p-2">Pulse</th><th class="p-2">Resp</th><th class="p-2">BP</th><th class="p-2">SpO₂</th><th class="p-2 text-left">Notes</th></tr></thead>
                        <tbody>
                            <tr v-for="observation in episode.observations || []" :key="observation.id" class="border-t" style="border-color: var(--admin-border);">
                                <td class="p-2">{{ observation.observed_at }}</td><td class="p-2 text-center">{{ observation.temperature_c || '—' }}</td><td class="p-2 text-center">{{ observation.pulse_bpm || '—' }}</td><td class="p-2 text-center">{{ observation.respiratory_rate || '—' }}</td><td class="p-2 text-center">{{ observation.systolic_bp && observation.diastolic_bp ? `${observation.systolic_bp}/${observation.diastolic_bp}` : '—' }}</td><td class="p-2 text-center">{{ observation.spo2_percent || '—' }}</td><td class="p-2">{{ observation.notes || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="!(episode.observations || []).length" class="py-4 text-sm" style="color: var(--admin-text-muted);">No observations recorded yet.</p>
                </div>
            </section>

            <section v-if="episode" class="rounded-md border p-5" style="border-color: var(--admin-border); background: var(--admin-surface);">
                <div class="flex items-center justify-between gap-3"><h2 class="font-black">Reaction reports</h2><button v-if="episode.status === 'in_progress' && can('blood-transfusion.reactions.manage')" class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="activeModal = 'reaction'">Report reaction</button></div>
                <article v-for="reaction in episode.reactions || []" :key="reaction.id" class="mt-3 rounded-md border p-4 text-sm" style="border-color: var(--admin-border);">
                    <div class="flex flex-wrap justify-between gap-3"><p class="font-bold">{{ reaction.reported_severity || 'Unspecified' }} · {{ reaction.status }}</p><button v-if="reaction.status === 'open' && can('blood-transfusion.reactions.manage')" class="rounded-md border px-3 py-1 font-bold" type="button" @click="resolveReaction(reaction)">Resolve record</button></div>
                    <p class="mt-2"><strong>Observed:</strong> {{ reaction.observed_signs }}</p>
                    <p v-if="reaction.immediate_actions" class="mt-2"><strong>Actions documented:</strong> {{ reaction.immediate_actions }}</p>
                    <p v-if="reaction.resolution_notes" class="mt-2"><strong>Resolution:</strong> {{ reaction.resolution_notes }}</p>
                </article>
                <p v-if="!(episode.reactions || []).length" class="mt-3 text-sm" style="color: var(--admin-text-muted);">No transfusion reactions recorded.</p>
            </section>
        </div>

        <FormModal :show="activeModal === 'start'" title="Start Transfusion" description="Confirm the patient and issued component at the bedside. No compatibility decision is calculated here." :form="startForm" submit-label="Start transfusion" @close="activeModal = null" @submit="submit(startForm, `/admin/blood-bank/issues/${issue.id}/transfusion`)">
            <div class="grid gap-3">
                <input v-model="startForm.started_at" type="datetime-local" class="rounded-md border p-2" placeholder="Start time">
                <input v-model="startForm.destination" class="rounded-md border p-2" placeholder="Ward / destination">
                <input v-model="startForm.patient_identifier_checked" class="rounded-md border p-2" placeholder="Patient identifier checked" required>
                <input v-model="startForm.component_identifier_checked" class="rounded-md border p-2" placeholder="Component identifier checked" required>
                <select v-model="startForm.identity_check_status" class="rounded-md border p-2"><option value="matched">Identifiers matched</option></select>
                <textarea v-model="startForm.notes" class="min-h-24 rounded-md border p-2" placeholder="Notes"></textarea>
            </div>
        </FormModal>

        <FormModal :show="activeModal === 'observe'" title="Record Transfusion Observation" :form="observationForm" submit-label="Record" @close="activeModal = null" @submit="submit(observationForm, `/admin/blood-bank/transfusions/${episode.id}/observations`)">
            <div class="grid gap-3 md:grid-cols-2">
                <input v-model="observationForm.observed_at" type="datetime-local" class="rounded-md border p-2">
                <input v-model="observationForm.temperature_c" type="number" step="0.1" class="rounded-md border p-2" placeholder="Temperature °C">
                <input v-model="observationForm.pulse_bpm" type="number" class="rounded-md border p-2" placeholder="Pulse bpm">
                <input v-model="observationForm.respiratory_rate" type="number" class="rounded-md border p-2" placeholder="Respiratory rate">
                <input v-model="observationForm.systolic_bp" type="number" class="rounded-md border p-2" placeholder="Systolic BP">
                <input v-model="observationForm.diastolic_bp" type="number" class="rounded-md border p-2" placeholder="Diastolic BP">
                <input v-model="observationForm.spo2_percent" type="number" class="rounded-md border p-2" placeholder="SpO₂ %">
                <textarea v-model="observationForm.notes" class="min-h-20 rounded-md border p-2 md:col-span-2" placeholder="Observation notes"></textarea>
            </div>
        </FormModal>

        <FormModal :show="activeModal === 'complete'" title="Complete Transfusion" :form="completeForm" submit-label="Complete" @close="activeModal = null" @submit="submit(completeForm, `/admin/blood-bank/transfusions/${episode.id}/complete`)"><div class="grid gap-3"><input v-model="completeForm.completed_at" type="datetime-local" class="rounded-md border p-2"><textarea v-model="completeForm.notes" class="min-h-24 rounded-md border p-2" placeholder="Completion notes"></textarea></div></FormModal>

        <FormModal :show="activeModal === 'stop'" title="Stop Transfusion" :form="stopForm" submit-label="Stop" @close="activeModal = null" @submit="submit(stopForm, `/admin/blood-bank/transfusions/${episode.id}/stop`)"><div class="grid gap-3"><input v-model="stopForm.stopped_at" type="datetime-local" class="rounded-md border p-2"><textarea v-model="stopForm.stop_reason" class="min-h-24 rounded-md border p-2" placeholder="Documented reason" required></textarea><textarea v-model="stopForm.notes" class="min-h-20 rounded-md border p-2" placeholder="Notes"></textarea></div></FormModal>

        <FormModal :show="activeModal === 'reaction'" title="Report Transfusion Reaction" description="Record observed facts and actions. This screen does not diagnose the reaction or recommend treatment." :form="reactionForm" submit-label="Record reaction" @close="activeModal = null" @submit="submit(reactionForm, `/admin/blood-bank/transfusions/${episode.id}/reactions`)">
            <div class="grid gap-3 md:grid-cols-2">
                <input v-model="reactionForm.occurred_at" type="datetime-local" class="rounded-md border p-2">
                <select v-model="reactionForm.reported_severity" class="rounded-md border p-2"><option value="unspecified">Unspecified</option><option value="mild">Mild (reported)</option><option value="moderate">Moderate (reported)</option><option value="severe">Severe (reported)</option></select>
                <textarea v-model="reactionForm.observed_signs" class="min-h-28 rounded-md border p-2 md:col-span-2" placeholder="Observed signs / symptoms" required></textarea>
                <textarea v-model="reactionForm.immediate_actions" class="min-h-28 rounded-md border p-2 md:col-span-2" placeholder="Actions taken by authorized staff"></textarea>
                <label class="grid gap-1 text-sm font-semibold">Clinician notified<input v-model="reactionForm.clinician_notified_at" type="datetime-local" class="rounded-md border p-2"></label>
                <label class="grid gap-1 text-sm font-semibold">Blood bank notified<input v-model="reactionForm.blood_bank_notified_at" type="datetime-local" class="rounded-md border p-2"></label>
            </div>
        </FormModal>

        <FormModal :show="activeModal === 'resolve'" title="Resolve Reaction Record" :form="resolveForm" submit-label="Resolve record" @close="activeModal = null" @submit="submit(resolveForm, `/admin/blood-bank/transfusion-reactions/${selectedReaction.id}/resolve`)"><textarea v-model="resolveForm.resolution_notes" class="min-h-32 w-full rounded-md border p-2" placeholder="Document follow-up and resolution" required></textarea></FormModal>
    </AppLayout>
</template>
