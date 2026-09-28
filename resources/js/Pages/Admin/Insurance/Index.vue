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
    invoices: { type: Array, default: () => [] },
    claims: { type: Object, required: true },
    claimBatches: { type: Array, default: () => [] },
    receivablesAgeing: { type: Object, default: () => ({}) },
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
const claimForm = useForm({ patient_coverage_id: '', invoice_id: '', payer_pre_authorization_id: '', claim_number: '', service_date: '', submission_notes: '' });
const batchForm = useForm({ payer_organization_id: '', reference: '', currency: 'NGN', due_date: '', notes: '', claim_ids: [] });
const claimDecisionForm = useForm({ status: 'approved', payer_reference: '', approved_minor: '', decision_notes: '', rejection_reason: '' });
const claimPaymentForm = useForm({ amount_minor: '', payer_reference: '' });
const resubmitForm = useForm({ claim_number: '', submission_notes: '' });
const actionForm = useForm({});

const plansForCoverage = computed(() => props.organizations.find((entry) => Number(entry.id) === Number(coverageForm.payer_organization_id))?.plans || []);
const activeCoverages = computed(() => props.coverages.data.filter((entry) => entry.status === 'active'));
const draftClaims = computed(() => props.claims.data.filter((entry) => entry.status === 'draft' && !entry.payer_claim_batch_id));
const invoicesForClaim = computed(() => {
    const coverage = props.coverages.data.find((entry) => Number(entry.id) === Number(claimForm.patient_coverage_id));
    return coverage ? props.invoices.filter((invoice) => Number(invoice.patient_id) === Number(coverage.patient_id)) : props.invoices;
});
const batchEligibleClaims = computed(() => draftClaims.value.filter((claim) => !batchForm.payer_organization_id || Number(claim.payer_organization_id) === Number(batchForm.payer_organization_id)));

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

function openClaimDecision(record) {
    target.value = record;
    claimDecisionForm.reset();
    claimDecisionForm.status = 'approved';
    claimDecisionForm.approved_minor = record.claimed_minor;
    activeModal.value = 'claim-decision';
}

function openClaimPayment(record) {
    target.value = record;
    claimPaymentForm.reset();
    claimPaymentForm.amount_minor = Math.max(0, Number(record.approved_minor ?? record.claimed_minor) - Number(record.paid_minor || 0));
    claimPaymentForm.payer_reference = record.payer_reference || '';
    activeModal.value = 'claim-payment';
}

function openResubmit(record) {
    target.value = record;
    resubmitForm.reset();
    activeModal.value = 'claim-resubmit';
}

function statusClass(status) {
    if (['approved','paid','closed'].includes(status)) return 'bg-emerald-100 text-emerald-800';
    if (['rejected'].includes(status)) return 'bg-rose-100 text-rose-800';
    if (['submitted','partially_approved','partially_paid'].includes(status)) return 'bg-amber-100 text-amber-800';
    return 'bg-slate-100 text-slate-700';
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
                    <button v-if="can('insurance.claims.manage')" class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="activeModal = 'claim'">New Claim</button>
                    <button v-if="can('insurance.claims.manage')" class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="activeModal = 'batch'">Create Batch</button>
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

            <section class="rounded-lg border p-5" style="border-color: var(--admin-border); background: var(--admin-surface);">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-black">Claims Workbench</h2>
                        <p class="text-sm" style="color: var(--admin-text-muted);">Create, submit, decide, resubmit and reconcile payer claims against issued invoices.</p>
                    </div>
                </div>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead><tr><th class="p-2 text-left">Claim</th><th class="p-2 text-left">Patient / Payer</th><th class="p-2 text-left">Invoice</th><th class="p-2 text-left">Amounts</th><th class="p-2 text-left">Status</th><th class="p-2 text-left">Actions</th></tr></thead>
                        <tbody>
                            <tr v-for="claim in claims.data" :key="claim.id" class="border-t" style="border-color: var(--admin-border);">
                                <td class="p-2"><strong>{{ claim.claim_number }}</strong><br><span class="text-xs">{{ claim.batch?.reference || 'Unbatched' }}</span></td>
                                <td class="p-2">{{ patientName(claim.patient) }}<br><span class="text-xs">{{ claim.organization?.name }} · {{ claim.plan?.name }}</span></td>
                                <td class="p-2">{{ claim.invoice?.invoice_number || '—' }}</td>
                                <td class="p-2">Claimed {{ money(claim.claimed_minor, claim.currency) }}<br><span class="text-xs">Approved {{ money(claim.approved_minor, claim.currency) }} · Paid {{ money(claim.paid_minor, claim.currency) }}</span></td>
                                <td class="p-2"><span class="rounded-full px-2 py-1 text-xs font-bold" :class="statusClass(claim.status)">{{ claim.status.replaceAll('_',' ') }}</span></td>
                                <td class="p-2">
                                    <ActionToolbar>
                                        <button v-if="['draft','resubmitted'].includes(claim.status) && can('insurance.claims.manage')" class="rounded-md border px-2 py-1 text-xs font-bold" type="button" @click="submit(actionForm, `/admin/insurance/claims/${claim.id}/submit`, 'patch')">Submit</button>
                                        <button v-if="claim.status === 'submitted' && can('insurance.claims.decide')" class="rounded-md border px-2 py-1 text-xs font-bold" type="button" @click="openClaimDecision(claim)">Decision</button>
                                        <button v-if="claim.status === 'rejected' && can('insurance.claims.manage')" class="rounded-md border px-2 py-1 text-xs font-bold" type="button" @click="openResubmit(claim)">Resubmit</button>
                                        <button v-if="['approved','partially_approved','partially_paid'].includes(claim.status) && can('insurance.claims.manage')" class="rounded-md border px-2 py-1 text-xs font-bold" type="button" @click="openClaimPayment(claim)">Record payment</button>
                                    </ActionToolbar>
                                </td>
                            </tr>
                            <tr v-if="!claims.data.length"><td colspan="6" class="p-4 text-center" style="color: var(--admin-text-muted);">No payer claims recorded yet.</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-lg border p-5" style="border-color: var(--admin-border); background: var(--admin-surface);">
                <h2 class="text-lg font-black">Receivables Ageing</h2>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <div class="rounded-md border p-4" style="border-color: var(--admin-border);"><p class="text-xs font-bold uppercase">Current</p><p class="mt-1 text-lg font-black">{{ money(receivablesAgeing.current || 0) }}</p></div>
                    <div class="rounded-md border p-4" style="border-color: var(--admin-border);"><p class="text-xs font-bold uppercase">1–30 days</p><p class="mt-1 text-lg font-black">{{ money(receivablesAgeing['1_30'] || 0) }}</p></div>
                    <div class="rounded-md border p-4" style="border-color: var(--admin-border);"><p class="text-xs font-bold uppercase">31–60 days</p><p class="mt-1 text-lg font-black">{{ money(receivablesAgeing['31_60'] || 0) }}</p></div>
                    <div class="rounded-md border p-4" style="border-color: var(--admin-border);"><p class="text-xs font-bold uppercase">61–90 days</p><p class="mt-1 text-lg font-black">{{ money(receivablesAgeing['61_90'] || 0) }}</p></div>
                    <div class="rounded-md border p-4" style="border-color: var(--admin-border);"><p class="text-xs font-bold uppercase">90+ days</p><p class="mt-1 text-lg font-black">{{ money(receivablesAgeing['90_plus'] || 0) }}</p></div>
                </div>
                <div v-if="claimBatches.length" class="mt-5">
                    <h3 class="font-black">Recent claim batches</h3>
                    <div class="mt-2 grid gap-2">
                        <div v-for="batch in claimBatches" :key="batch.id" class="flex flex-wrap items-center justify-between gap-2 rounded-md border p-3 text-sm" style="border-color: var(--admin-border);">
                            <div><strong>{{ batch.reference }}</strong> · {{ batch.organization?.name }} · {{ batch.claims?.length || 0 }} claim(s)</div>
                            <div class="flex items-center gap-2">{{ money(batch.claimed_minor, batch.currency) }} · {{ batch.status }} <button v-if="batch.status === 'draft' && can('insurance.claims.manage')" class="rounded-md border px-2 py-1 text-xs font-bold" type="button" @click="submit(actionForm, `/admin/insurance/claim-batches/${batch.id}/submit`, 'patch')">Submit batch</button></div>
                        </div>
                    </div>
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

        <FormModal :show="activeModal === 'claim'" title="Create Payer Claim" :form="claimForm" submit-label="Create claim" @close="activeModal = null" @submit="submit(claimForm, '/admin/insurance/claims')">
            <div class="grid gap-3 md:grid-cols-2">
                <select v-model="claimForm.patient_coverage_id" class="rounded-md border p-2" required><option value="">Patient coverage</option><option v-for="coverage in activeCoverages" :key="coverage.id" :value="coverage.id">{{ coverage.patient?.hospital_number }} · {{ coverage.organization?.name }} · {{ coverage.member_number }}</option></select>
                <select v-model="claimForm.invoice_id" class="rounded-md border p-2" required><option value="">Issued invoice</option><option v-for="invoice in invoicesForClaim" :key="invoice.id" :value="invoice.id">{{ invoice.invoice_number }} · {{ patientName(invoice.patient) }} · {{ money(invoice.balance_minor, invoice.currency) }}</option></select>
                <input v-model="claimForm.claim_number" class="rounded-md border p-2" placeholder="Claim number / internal reference" required>
                <input v-model="claimForm.service_date" type="date" class="rounded-md border p-2">
                <textarea v-model="claimForm.submission_notes" class="rounded-md border p-2 md:col-span-2" placeholder="Claim preparation notes"></textarea>
            </div>
        </FormModal>

        <FormModal :show="activeModal === 'batch'" title="Create Claim Batch" :form="batchForm" submit-label="Create batch" @close="activeModal = null" @submit="submit(batchForm, '/admin/insurance/claim-batches')">
            <div class="grid gap-3 md:grid-cols-2">
                <select v-model="batchForm.payer_organization_id" class="rounded-md border p-2" required><option value="">Payer organisation</option><option v-for="organization in organizations" :key="organization.id" :value="organization.id">{{ organization.name }}</option></select>
                <input v-model="batchForm.reference" class="rounded-md border p-2" placeholder="Batch reference" required>
                <input v-model="batchForm.currency" class="rounded-md border p-2" maxlength="3" required>
                <input v-model="batchForm.due_date" type="date" class="rounded-md border p-2">
                <div class="md:col-span-2">
                    <p class="text-sm font-bold">Draft claims</p>
                    <div class="mt-2 grid gap-2">
                        <label v-for="claim in batchEligibleClaims" :key="claim.id" class="flex items-center gap-2 rounded-md border p-2 text-sm"><input v-model="batchForm.claim_ids" :value="claim.id" type="checkbox"> {{ claim.claim_number }} · {{ claim.organization?.name }} · {{ money(claim.claimed_minor, claim.currency) }}</label>
                    </div>
                </div>
                <textarea v-model="batchForm.notes" class="rounded-md border p-2 md:col-span-2" placeholder="Batch notes"></textarea>
            </div>
        </FormModal>

        <FormModal :show="activeModal === 'claim-decision'" title="Record Claim Decision" :form="claimDecisionForm" submit-label="Save decision" @close="activeModal = null" @submit="submit(claimDecisionForm, `/admin/insurance/claims/${target.id}/decision`, 'patch')">
            <div class="grid gap-3 md:grid-cols-2">
                <select v-model="claimDecisionForm.status" class="rounded-md border p-2"><option value="approved">Approved</option><option value="partially_approved">Partially approved</option><option value="rejected">Rejected</option></select>
                <input v-model="claimDecisionForm.payer_reference" class="rounded-md border p-2" placeholder="Payer reference">
                <input v-model="claimDecisionForm.approved_minor" type="number" min="0" class="rounded-md border p-2" placeholder="Approved amount (minor units)">
                <textarea v-model="claimDecisionForm.decision_notes" class="rounded-md border p-2 md:col-span-2" placeholder="Decision notes" required></textarea>
                <textarea v-if="claimDecisionForm.status === 'rejected'" v-model="claimDecisionForm.rejection_reason" class="rounded-md border p-2 md:col-span-2" placeholder="Rejection reason" required></textarea>
            </div>
        </FormModal>

        <FormModal :show="activeModal === 'claim-payment'" title="Record Payer Payment" :form="claimPaymentForm" submit-label="Record payment" @close="activeModal = null" @submit="submit(claimPaymentForm, `/admin/insurance/claims/${target.id}/payments`)">
            <div class="grid gap-3 md:grid-cols-2">
                <input v-model="claimPaymentForm.amount_minor" type="number" min="1" class="rounded-md border p-2" placeholder="Amount received (minor units)" required>
                <input v-model="claimPaymentForm.payer_reference" class="rounded-md border p-2" placeholder="Payer payment reference">
            </div>
        </FormModal>

        <FormModal :show="activeModal === 'claim-resubmit'" title="Resubmit Rejected Claim" :form="resubmitForm" submit-label="Create resubmission" @close="activeModal = null" @submit="submit(resubmitForm, `/admin/insurance/claims/${target.id}/resubmit`)">
            <div class="grid gap-3">
                <input v-model="resubmitForm.claim_number" class="rounded-md border p-2" placeholder="New claim number" required>
                <textarea v-model="resubmitForm.submission_notes" class="rounded-md border p-2" placeholder="What was corrected for resubmission?" required></textarea>
            </div>
        </FormModal>

    </AppLayout>
</template>
