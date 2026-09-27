<script setup>
import ActionToolbar from '@/Components/Admin/ActionToolbar.vue';
import FormModal from '@/Components/Admin/FormModal.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    organizations: { type: Array, default: () => [] },
    patients: { type: Array, default: () => [] },
    coverages: { type: Object, required: true },
    preAuthorizations: { type: Array, default: () => [] },
    services: { type: Array, default: () => [] },
    facilities: { type: Array, default: () => [] },
});

const page = usePage();
const permissions = computed(() => page.props.auth.permissions || []);
const roles = computed(() => page.props.auth.roles || []);
const can = (permission) => roles.value.includes('superadmin') || permissions.value.includes(permission);
const activeModal = ref(null);
const target = ref(null);

const organizationForm = useForm({ type: 'hmo', code: '', name: '', contact_name: '', email: '', phone: '', address: '', credit_days: 30, status: 'active', notes: '' });
const planForm = useForm({ payer_organization_id: '', code: '', name: '', currency: 'NGN', requires_pre_authorization: false, status: 'active', effective_from: '', effective_to: '', notes: '' });
const tariffForm = useForm({ billable_service_id: '', facility_id: '', currency: 'NGN', amount_minor: '', effective_from: new Date().toISOString().slice(0, 10), effective_to: '', notes: '' });
const coverageForm = useForm({ patient_id: '', payer_organization_id: '', payer_plan_id: '', member_number: '', policy_number: '', principal_member_name: '', relationship_to_principal: '', employer_name: '', valid_from: '', valid_to: '', is_primary: true, status: 'active', notes: '' });
const authForm = useForm({ patient_coverage_id: '', billable_service_id: '', clinical_encounter_id: '', reference: '', requested_amount_minor: '', clinical_or_service_context: '', valid_until: '' });
const decisionForm = useForm({ status: 'approved', authorization_code: '', approved_amount_minor: '', decision_notes: '', valid_until: '' });

const plansForCoverage = computed(() => props.organizations.find((entry) => Number(entry.id) === Number(coverageForm.payer_organization_id))?.plans || []);
const activeCoverages = computed(() => props.coverages.data.filter((entry) => entry.status === 'active'));

function patientName(patient) {
    return [patient.first_name, patient.middle_name, patient.last_name].filter(Boolean).join(' ');
}

function money(minor, currency = 'NGN') {
    if (minor === null || minor === undefined || minor === '') return '—';
    return new Intl.NumberFormat('en-NG', { style: 'currency', currency }).format(Number(minor) / 100);
}

function submit(form, url, method = 'post') {
    const options = { preserveScroll: true, onSuccess: () => { activeModal.value = null; target.value = null; form.reset(); } };
    method === 'patch' ? form.patch(url, options) : form.post(url, options);
}

function openTariff(plan) {
    target.value = plan;
    tariffForm.reset();
    tariffForm.currency = plan.currency || 'NGN';
    tariffForm.effective_from = new Date().toISOString().slice(0, 10);
    activeModal.value = 'tariff';
}

function openDecision(record) {
    target.value = record;
    decisionForm.reset();
    decisionForm.status = 'approved';
    decisionForm.approved_amount_minor = record.requested_amount_minor || '';
    activeModal.value = 'decision';
}
</script>

<template>
    <Head title="Insurance & HMO" />
    <AppLayout title="Insurance & HMO">
        <PageHeader title="Insurance, HMO & Corporate Accounts" description="Manage payer organisations, plans, tariffs, patient coverage and pre-authorisation records.">
            <template #actions>
                <ActionToolbar align="end">
                    <PrimaryButton v-if="can('insurance.manage')" type="button" @click="activeModal = 'organization'">Add Payer</PrimaryButton>
                    <button v-if="can('insurance.manage')" class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="activeModal = 'plan'">Add Plan</button>
                    <button v-if="can('insurance.coverage.manage')" class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="activeModal = 'coverage'">Add Coverage</button>
                    <button v-if="can('insurance.preauthorizations.manage')" class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="activeModal = 'authorization'">Pre-authorisation</button>
                </ActionToolbar>
            </template>
        </PageHeader>

        <div class="space-y-6">
            <section class="rounded-lg border p-5" style="border-color: var(--admin-border); background: var(--admin-surface);">
                <h2 class="text-lg font-black">Payers & Plans</h2>
                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                    <article v-for="organization in organizations" :key="organization.id" class="rounded-md border p-4" style="border-color: var(--admin-border);">
                        <div class="flex items-start justify-between gap-3">
                            <div><p class="text-xs font-black uppercase" style="color: var(--admin-text-muted);">{{ organization.type }} · {{ organization.code }}</p><h3 class="text-lg font-black">{{ organization.name }}</h3></div>
                            <span class="rounded-full border px-2 py-1 text-xs font-bold">{{ organization.status }}</span>
                        </div>
                        <p class="mt-2 text-sm" style="color: var(--admin-text-muted);">Credit terms: {{ organization.credit_days }} days</p>
                        <div class="mt-4 grid gap-2">
                            <div v-for="plan in organization.plans" :key="plan.id" class="rounded-md border p-3 text-sm" style="border-color: var(--admin-border);">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div><strong>{{ plan.name }}</strong> <span style="color: var(--admin-text-muted);">({{ plan.code }})</span><p class="text-xs">{{ plan.requires_pre_authorization ? 'Pre-authorisation required by plan' : 'No plan-wide pre-authorisation requirement' }}</p></div>
                                    <button v-if="can('insurance.tariffs.manage')" class="rounded-md border px-2 py-1 text-xs font-bold" type="button" @click="openTariff(plan)">Add tariff</button>
                                </div>
                                <p class="mt-2 text-xs" style="color: var(--admin-text-muted);">{{ plan.tariffs?.length || 0 }} tariff record(s)</p>
                            </div>
                            <p v-if="!organization.plans?.length" class="text-sm" style="color: var(--admin-text-muted);">No plans configured.</p>
                        </div>
                    </article>
                    <p v-if="!organizations.length" class="text-sm" style="color: var(--admin-text-muted);">No HMO, insurer or corporate payer has been configured yet.</p>
                </div>
            </section>

            <section class="rounded-lg border p-5" style="border-color: var(--admin-border); background: var(--admin-surface);">
                <h2 class="text-lg font-black">Patient Coverage</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead><tr><th class="p-2 text-left">Patient</th><th class="p-2 text-left">Payer / Plan</th><th class="p-2 text-left">Member no.</th><th class="p-2 text-left">Validity</th><th class="p-2 text-left">Status</th></tr></thead>
                        <tbody>
                            <tr v-for="coverage in coverages.data" :key="coverage.id" class="border-t" style="border-color: var(--admin-border);">
                                <td class="p-2"><strong>{{ patientName(coverage.patient) }}</strong><br><span class="text-xs">{{ coverage.patient?.hospital_number }}</span></td>
                                <td class="p-2">{{ coverage.organization?.name }}<br><span class="text-xs">{{ coverage.plan?.name }}</span></td>
                                <td class="p-2">{{ coverage.member_number }}<span v-if="coverage.is_primary" class="ml-2 rounded-full border px-2 py-0.5 text-xs">Primary</span></td>
                                <td class="p-2">{{ coverage.valid_from || '—' }} → {{ coverage.valid_to || 'Open' }}</td>
                                <td class="p-2">{{ coverage.status }}</td>
                            </tr>
                            <tr v-if="!coverages.data.length"><td colspan="5" class="p-4 text-center" style="color: var(--admin-text-muted);">No patient coverage records yet.</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-lg border p-5" style="border-color: var(--admin-border); background: var(--admin-surface);">
                <h2 class="text-lg font-black">Pre-authorisations</h2>
                <div class="mt-4 grid gap-3">
                    <article v-for="record in preAuthorizations" :key="record.id" class="rounded-md border p-4 text-sm" style="border-color: var(--admin-border);">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div><p class="font-bold">{{ patientName(record.patient) }} · {{ record.coverage?.organization?.name }}</p><p style="color: var(--admin-text-muted);">{{ record.service?.name || 'General / unspecified service' }} · Requested {{ money(record.requested_amount_minor) }}</p></div>
                            <div class="text-right"><p class="font-black">{{ record.status }}</p><button v-if="record.status === 'requested' && can('insurance.preauthorizations.decide')" class="mt-2 rounded-md border px-3 py-1 font-bold" type="button" @click="openDecision(record)">Record decision</button></div>
                        </div>
                        <p v-if="record.authorization_code" class="mt-2"><strong>Authorization code:</strong> {{ record.authorization_code }}</p>
                    </article>
                    <p v-if="!preAuthorizations.length" class="text-sm" style="color: var(--admin-text-muted);">No pre-authorisation records yet.</p>
                </div>
            </section>
        </div>

        <FormModal :show="activeModal === 'organization'" title="Add Payer Organisation" :form="organizationForm" submit-label="Create payer" @close="activeModal = null" @submit="submit(organizationForm, '/admin/insurance/organizations')">
            <div class="grid gap-3 md:grid-cols-2">
                <label class="grid gap-1 text-sm font-semibold">Type<select v-model="organizationForm.type" class="rounded-md border"><option value="hmo">HMO</option><option value="insurer">Insurer</option><option value="corporate">Corporate account</option></select></label>
                <input v-model="organizationForm.code" class="rounded-md border p-2" placeholder="Code" required>
                <input v-model="organizationForm.name" class="rounded-md border p-2 md:col-span-2" placeholder="Organisation name" required>
                <input v-model="organizationForm.contact_name" class="rounded-md border p-2" placeholder="Contact name">
                <input v-model="organizationForm.email" type="email" class="rounded-md border p-2" placeholder="Email">
                <input v-model="organizationForm.phone" class="rounded-md border p-2" placeholder="Phone">
                <input v-model="organizationForm.credit_days" type="number" min="0" class="rounded-md border p-2" placeholder="Credit days">
                <textarea v-model="organizationForm.address" class="rounded-md border p-2 md:col-span-2" placeholder="Address"></textarea>
                <textarea v-model="organizationForm.notes" class="rounded-md border p-2 md:col-span-2" placeholder="Notes"></textarea>
            </div>
        </FormModal>

        <FormModal :show="activeModal === 'plan'" title="Add Payer Plan" :form="planForm" submit-label="Create plan" @close="activeModal = null" @submit="submit(planForm, '/admin/insurance/plans')">
            <div class="grid gap-3 md:grid-cols-2">
                <select v-model="planForm.payer_organization_id" class="rounded-md border p-2" required><option value="">Payer organisation</option><option v-for="organization in organizations" :key="organization.id" :value="organization.id">{{ organization.name }}</option></select>
                <input v-model="planForm.code" class="rounded-md border p-2" placeholder="Plan code" required>
                <input v-model="planForm.name" class="rounded-md border p-2" placeholder="Plan name" required>
                <input v-model="planForm.currency" class="rounded-md border p-2" maxlength="3" placeholder="Currency">
                <label class="flex items-center gap-2 text-sm font-semibold md:col-span-2"><input v-model="planForm.requires_pre_authorization" type="checkbox"> Plan generally requires pre-authorisation</label>
                <label class="grid gap-1 text-sm">Effective from<input v-model="planForm.effective_from" type="date" class="rounded-md border p-2"></label>
                <label class="grid gap-1 text-sm">Effective to<input v-model="planForm.effective_to" type="date" class="rounded-md border p-2"></label>
            </div>
        </FormModal>

        <FormModal :show="activeModal === 'tariff'" :title="target ? `Add Tariff · ${target.name}` : 'Add Tariff'" :form="tariffForm" submit-label="Add tariff" @close="activeModal = null" @submit="submit(tariffForm, `/admin/insurance/plans/${target.id}/tariffs`)">
            <div class="grid gap-3 md:grid-cols-2">
                <select v-model="tariffForm.billable_service_id" class="rounded-md border p-2" required><option value="">Service</option><option v-for="service in services" :key="service.id" :value="service.id">{{ service.code }} · {{ service.name }}</option></select>
                <select v-model="tariffForm.facility_id" class="rounded-md border p-2"><option value="">All facilities</option><option v-for="facility in facilities" :key="facility.id" :value="facility.id">{{ facility.name }}</option></select>
                <input v-model="tariffForm.currency" class="rounded-md border p-2" maxlength="3">
                <input v-model="tariffForm.amount_minor" type="number" min="0" class="rounded-md border p-2" placeholder="Amount in minor units" required>
                <input v-model="tariffForm.effective_from" type="date" class="rounded-md border p-2" required>
                <input v-model="tariffForm.effective_to" type="date" class="rounded-md border p-2">
                <textarea v-model="tariffForm.notes" class="rounded-md border p-2 md:col-span-2" placeholder="Notes"></textarea>
            </div>
        </FormModal>

        <FormModal :show="activeModal === 'coverage'" title="Add Patient Coverage" :form="coverageForm" submit-label="Add coverage" @close="activeModal = null" @submit="submit(coverageForm, '/admin/insurance/coverages')">
            <div class="grid gap-3 md:grid-cols-2">
                <select v-model="coverageForm.patient_id" class="rounded-md border p-2" required><option value="">Patient</option><option v-for="patient in patients" :key="patient.id" :value="patient.id">{{ patient.hospital_number }} · {{ patientName(patient) }}</option></select>
                <select v-model="coverageForm.payer_organization_id" class="rounded-md border p-2" required><option value="">Payer</option><option v-for="organization in organizations" :key="organization.id" :value="organization.id">{{ organization.name }}</option></select>
                <select v-model="coverageForm.payer_plan_id" class="rounded-md border p-2" required><option value="">Plan</option><option v-for="plan in plansForCoverage" :key="plan.id" :value="plan.id">{{ plan.name }}</option></select>
                <input v-model="coverageForm.member_number" class="rounded-md border p-2" placeholder="Member number" required>
                <input v-model="coverageForm.policy_number" class="rounded-md border p-2" placeholder="Policy number">
                <input v-model="coverageForm.employer_name" class="rounded-md border p-2" placeholder="Employer / sponsor">
                <input v-model="coverageForm.principal_member_name" class="rounded-md border p-2" placeholder="Principal member name">
                <input v-model="coverageForm.relationship_to_principal" class="rounded-md border p-2" placeholder="Relationship">
                <label class="grid gap-1 text-sm">Valid from<input v-model="coverageForm.valid_from" type="date" class="rounded-md border p-2"></label>
                <label class="grid gap-1 text-sm">Valid to<input v-model="coverageForm.valid_to" type="date" class="rounded-md border p-2"></label>
                <label class="flex items-center gap-2 text-sm font-semibold md:col-span-2"><input v-model="coverageForm.is_primary" type="checkbox"> Primary coverage for this patient</label>
            </div>
        </FormModal>

        <FormModal :show="activeModal === 'authorization'" title="Record Pre-authorisation Request" :form="authForm" submit-label="Record request" @close="activeModal = null" @submit="submit(authForm, '/admin/insurance/pre-authorizations')">
            <div class="grid gap-3 md:grid-cols-2">
                <select v-model="authForm.patient_coverage_id" class="rounded-md border p-2" required><option value="">Patient coverage</option><option v-for="coverage in activeCoverages" :key="coverage.id" :value="coverage.id">{{ coverage.patient?.hospital_number }} · {{ coverage.organization?.name }} · {{ coverage.member_number }}</option></select>
                <select v-model="authForm.billable_service_id" class="rounded-md border p-2"><option value="">General / unspecified service</option><option v-for="service in services" :key="service.id" :value="service.id">{{ service.name }}</option></select>
                <input v-model="authForm.reference" class="rounded-md border p-2" placeholder="External reference">
                <input v-model="authForm.requested_amount_minor" type="number" min="0" class="rounded-md border p-2" placeholder="Requested amount (minor units)">
                <textarea v-model="authForm.clinical_or_service_context" class="min-h-24 rounded-md border p-2 md:col-span-2" placeholder="Service / clinical context supplied to payer"></textarea>
            </div>
        </FormModal>

        <FormModal :show="activeModal === 'decision'" title="Record Pre-authorisation Decision" :form="decisionForm" submit-label="Save decision" @close="activeModal = null" @submit="submit(decisionForm, `/admin/insurance/pre-authorizations/${target.id}/decision`, 'patch')">
            <div class="grid gap-3 md:grid-cols-2">
                <label class="grid gap-1 text-sm font-semibold">Decision<select v-model="decisionForm.status" class="rounded-md border p-2"><option value="approved">Approved</option><option value="declined">Declined</option></select></label>
                <input v-model="decisionForm.authorization_code" class="rounded-md border p-2" placeholder="Authorization code">
                <input v-model="decisionForm.approved_amount_minor" type="number" min="0" class="rounded-md border p-2" placeholder="Approved amount (minor units)">
                <input v-model="decisionForm.valid_until" type="date" class="rounded-md border p-2">
                <textarea v-model="decisionForm.decision_notes" class="min-h-24 rounded-md border p-2 md:col-span-2" placeholder="Payer decision notes" required></textarea>
            </div>
        </FormModal>
    </AppLayout>
</template>
