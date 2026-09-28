<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import FormModal from '@/Components/Admin/FormModal.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    license: { type: Object, required: true },
    integrations: { type: Array, default: () => [] },
    backupEvents: { type: Array, default: () => [] },
    latestBackup: { type: Object, default: null },
    latestRestoreVerification: { type: Object, default: null },
});

const page = usePage();
const permissions = computed(() => page.props.auth.permissions || []);
const roles = computed(() => page.props.auth.roles || []);
const can = (permission) => roles.value.includes('superadmin') || permissions.value.includes(permission);

const activeModal = ref(null);
const target = ref(null);
const licenseForm = useForm({
    license_key: props.license.license_key || '',
    plan: props.license.plan || 'standard',
    status: props.license.status || 'trial',
    starts_on: props.license.starts_on || '',
    expires_on: props.license.expires_on || '',
    licensed_facilities: props.license.licensed_facilities || 1,
    notes: props.license.notes || '',
});
const integrationForm = useForm({ provider: '', status: 'disabled', endpoint: '', account_reference: '', notes: '' });
const backupForm = useForm({ status: 'success', source: 'manual', backup_reference: '', size_bytes: '', backup_completed_at: new Date().toISOString().slice(0,16), restore_verified_at: '', notes: '' });

function saveLicense() {
    licenseForm.patch('/admin/commercial/license', { preserveScroll: true });
}
function openIntegration(integration) {
    target.value = integration;
    integrationForm.reset();
    integrationForm.provider = integration.provider || 'not-configured';
    integrationForm.status = integration.status || 'disabled';
    activeModal.value = 'integration';
}
function saveIntegration() {
    integrationForm.patch(`/admin/commercial/integrations/${target.value.id}`, {
        preserveScroll: true,
        onSuccess: () => { activeModal.value = null; target.value = null; },
    });
}
function saveBackup() {
    backupForm.post('/admin/commercial/backups', {
        preserveScroll: true,
        onSuccess: () => { activeModal.value = null; backupForm.reset(); backupForm.status='success'; backupForm.source='manual'; backupForm.backup_completed_at=new Date().toISOString().slice(0,16); },
    });
}
function bytes(value) {
    if (!value) return '—';
    const units=['B','KB','MB','GB','TB']; let n=Number(value), i=0;
    while(n>=1024 && i<units.length-1){ n/=1024; i++; }
    return `${n.toFixed(i ? 1 : 0)} ${units[i]}`;
}
</script>

<template>
    <Head title="Commercial Administration" />
    <AppLayout title="Commercial Administration">
        <PageHeader title="Commercial & Integration Administration" description="Track installation licensing, provider configuration status and backup/restore evidence without exposing provider secrets in the browser.">
            <template #actions>
                <button v-if="can('backup-monitor.manage')" class="rounded-md border px-4 py-2 text-sm font-bold" type="button" @click="activeModal='backup'">Record backup status</button>
            </template>
        </PageHeader>

        <section class="grid gap-5 xl:grid-cols-2">
            <article class="rounded-lg border p-5" style="border-color: var(--admin-border); background: var(--admin-surface);">
                <h2 class="text-lg font-black">Installation Licence</h2>
                <p class="mt-1 text-sm" style="color: var(--admin-text-muted);">Administrative licence metadata for this hospital installation.</p>
                <form class="mt-4 grid gap-3 sm:grid-cols-2" @submit.prevent="saveLicense">
                    <label class="grid gap-1 text-sm font-semibold sm:col-span-2">Licence key<input v-model="licenseForm.license_key" class="rounded-md border p-2" placeholder="Optional licence/reference key"></label>
                    <label class="grid gap-1 text-sm font-semibold">Plan<input v-model="licenseForm.plan" class="rounded-md border p-2"></label>
                    <label class="grid gap-1 text-sm font-semibold">Status<select v-model="licenseForm.status" class="rounded-md border p-2"><option value="trial">Trial</option><option value="active">Active</option><option value="suspended">Suspended</option><option value="expired">Expired</option></select></label>
                    <label class="grid gap-1 text-sm font-semibold">Starts<input v-model="licenseForm.starts_on" type="date" class="rounded-md border p-2"></label>
                    <label class="grid gap-1 text-sm font-semibold">Expires<input v-model="licenseForm.expires_on" type="date" class="rounded-md border p-2"></label>
                    <label class="grid gap-1 text-sm font-semibold">Licensed facilities<input v-model="licenseForm.licensed_facilities" type="number" min="1" class="rounded-md border p-2"></label>
                    <label class="grid gap-1 text-sm font-semibold sm:col-span-2">Notes<textarea v-model="licenseForm.notes" class="rounded-md border p-2"></textarea></label>
                    <button v-if="can('commercial-admin.manage')" class="rounded-md border px-4 py-2 font-bold sm:col-span-2" type="submit">Save licence</button>
                </form>
            </article>

            <article class="rounded-lg border p-5" style="border-color: var(--admin-border); background: var(--admin-surface);">
                <h2 class="text-lg font-black">Backup & Restore Monitor</h2>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-md border p-4" style="border-color: var(--admin-border);">
                        <p class="text-xs font-black uppercase" style="color: var(--admin-text-muted);">Latest successful backup</p>
                        <p class="mt-2 font-black">{{ latestBackup?.backup_completed_at || 'Not recorded' }}</p>
                        <p class="text-xs">{{ bytes(latestBackup?.size_bytes) }} · {{ latestBackup?.source || '—' }}</p>
                    </div>
                    <div class="rounded-md border p-4" style="border-color: var(--admin-border);">
                        <p class="text-xs font-black uppercase" style="color: var(--admin-text-muted);">Latest restore verification</p>
                        <p class="mt-2 font-black">{{ latestRestoreVerification?.restore_verified_at || 'Not verified' }}</p>
                        <p class="text-xs">{{ latestRestoreVerification?.backup_reference || '—' }}</p>
                    </div>
                </div>
                <p class="mt-4 text-xs" style="color: var(--admin-text-muted);">This records evidence of backups and restore drills; it does not itself create or store database backups.</p>
            </article>
        </section>

        <section class="mt-6 rounded-lg border p-5" style="border-color: var(--admin-border); background: var(--admin-surface);">
            <h2 class="text-lg font-black">Integration Status</h2>
            <p class="mt-1 text-sm" style="color: var(--admin-text-muted);">Payment, accounting and SMS integration metadata. Credentials remain in server environment configuration.</p>
            <div class="mt-4 grid gap-4 lg:grid-cols-3">
                <article v-for="integration in integrations" :key="integration.id" class="rounded-md border p-4" style="border-color: var(--admin-border);">
                    <p class="text-xs font-black uppercase" style="color: var(--admin-text-muted);">{{ integration.type }}</p>
                    <h3 class="mt-1 font-black">{{ integration.provider }}</h3>
                    <p class="mt-2 text-sm">Status: <strong>{{ integration.status }}</strong></p>
                    <p class="text-sm">Configuration metadata: {{ integration.configured ? 'Present' : 'Not configured' }}</p>
                    <p class="mt-2 text-xs">{{ integration.last_check_message || 'No connectivity check recorded.' }}</p>
                    <button v-if="can('integrations.manage')" class="mt-3 rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="openIntegration(integration)">Configure metadata</button>
                </article>
            </div>
        </section>

        <section class="mt-6 overflow-hidden rounded-lg border" style="border-color: var(--admin-border); background: var(--admin-surface);">
            <div class="border-b p-4" style="border-color: var(--admin-border);"><h2 class="text-lg font-black">Backup Monitoring History</h2></div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead><tr><th class="p-3 text-left">Completed</th><th class="p-3 text-left">Status</th><th class="p-3 text-left">Source</th><th class="p-3 text-left">Reference</th><th class="p-3 text-left">Size</th><th class="p-3 text-left">Restore verified</th><th class="p-3 text-left">Notes</th></tr></thead>
                    <tbody>
                        <tr v-for="event in backupEvents" :key="event.id" class="border-t" style="border-color: var(--admin-border);">
                            <td class="p-3">{{ event.backup_completed_at }}</td><td class="p-3 font-bold">{{ event.status }}</td><td class="p-3">{{ event.source }}</td><td class="p-3">{{ event.backup_reference || '—' }}</td><td class="p-3">{{ bytes(event.size_bytes) }}</td><td class="p-3">{{ event.restore_verified_at || '—' }}</td><td class="p-3">{{ event.notes || '—' }}</td>
                        </tr>
                        <tr v-if="!backupEvents.length"><td colspan="7" class="p-5 text-center" style="color: var(--admin-text-muted);">No backup monitoring events recorded yet.</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <FormModal :show="activeModal==='integration'" :form="integrationForm" :title="target ? `Configure ${target.type} integration` : 'Configure integration'" submit-label="Save integration" @close="activeModal=null" @submit="saveIntegration">
            <div class="grid gap-3">
                <label class="grid gap-1 text-sm font-semibold">Provider<input v-model="integrationForm.provider" class="rounded-md border p-2" placeholder="e.g. Paystack, Sage, custom API"></label>
                <label class="grid gap-1 text-sm font-semibold">Status<select v-model="integrationForm.status" class="rounded-md border p-2"><option value="disabled">Disabled</option><option value="configured">Configured</option><option value="active">Active</option><option value="error">Error</option></select></label>
                <label class="grid gap-1 text-sm font-semibold">Endpoint / base URL<input v-model="integrationForm.endpoint" type="url" class="rounded-md border p-2"></label>
                <label class="grid gap-1 text-sm font-semibold">Account/reference<input v-model="integrationForm.account_reference" class="rounded-md border p-2"></label>
                <label class="grid gap-1 text-sm font-semibold">Notes<textarea v-model="integrationForm.notes" class="rounded-md border p-2"></textarea></label>
                <p class="text-xs" style="color: var(--admin-text-muted);">API keys, passwords and tokens must be configured on the server and are intentionally not accepted here.</p>
            </div>
        </FormModal>

        <FormModal :show="activeModal==='backup'" :form="backupForm" title="Record Backup / Restore Status" submit-label="Record event" @close="activeModal=null" @submit="saveBackup">
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="grid gap-1 text-sm font-semibold">Status<select v-model="backupForm.status" class="rounded-md border p-2"><option value="success">Success</option><option value="warning">Warning</option><option value="failed">Failed</option></select></label>
                <label class="grid gap-1 text-sm font-semibold">Source<input v-model="backupForm.source" class="rounded-md border p-2" placeholder="cron, hosting panel, manual"></label>
                <label class="grid gap-1 text-sm font-semibold">Backup reference<input v-model="backupForm.backup_reference" class="rounded-md border p-2"></label>
                <label class="grid gap-1 text-sm font-semibold">Size in bytes<input v-model="backupForm.size_bytes" type="number" min="0" class="rounded-md border p-2"></label>
                <label class="grid gap-1 text-sm font-semibold">Backup completed<input v-model="backupForm.backup_completed_at" type="datetime-local" class="rounded-md border p-2" required></label>
                <label class="grid gap-1 text-sm font-semibold">Restore verified<input v-model="backupForm.restore_verified_at" type="datetime-local" class="rounded-md border p-2"></label>
                <label class="grid gap-1 text-sm font-semibold sm:col-span-2">Notes<textarea v-model="backupForm.notes" class="rounded-md border p-2"></textarea></label>
            </div>
        </FormModal>
    </AppLayout>
</template>
