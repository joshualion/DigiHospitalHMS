<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import FormModal from '@/Components/Admin/FormModal.vue';
import ActionToolbar from '@/Components/Admin/ActionToolbar.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    templates: { type: Array, default: () => [] },
    deliveries: { type: Object, required: true },
    smsConfigured: { type: Boolean, default: false },
    mailDriver: { type: String, default: '' },
});

const page = usePage();
const permissions = computed(() => page.props.auth.permissions || []);
const roles = computed(() => page.props.auth.roles || []);
const can = (permission) => roles.value.includes('superadmin') || permissions.value.includes(permission);

const editing = ref(null);
const form = useForm({ name: '', subject: '', body: '', reminder_minutes_before: 1440, is_active: true });
const runForm = useForm({});

function openEdit(template) {
    editing.value = template;
    form.clearErrors();
    form.defaults({
        name: template.name,
        subject: template.subject || '',
        body: template.body,
        reminder_minutes_before: template.reminder_minutes_before,
        is_active: Boolean(template.is_active),
    });
    form.reset();
}

function save() {
    form.patch(`/admin/notifications/templates/${editing.value.id}`, {
        preserveScroll: true,
        onSuccess: () => { editing.value = null; },
    });
}

function runNow() {
    runForm.post('/admin/notifications/run', { preserveScroll: true });
}

function patientName(patient) {
    return patient ? [patient.first_name, patient.middle_name, patient.last_name].filter(Boolean).join(' ') : '—';
}

function masked(value) {
    if (!value) return '—';
    if (value.includes('@')) {
        const [name, domain] = value.split('@');
        return `${name.slice(0,2)}***@${domain}`;
    }
    return `${value.slice(0,4)}***${value.slice(-3)}`;
}
</script>

<template>
    <Head title="Notifications" />
    <AppLayout title="Notifications">
        <PageHeader title="Notifications & Reminders" description="Manage hospital notification templates and review appointment reminder delivery history.">
            <template #actions>
                <button v-if="can('notifications.send')" class="rounded-md border px-4 py-2 text-sm font-bold" type="button" :disabled="runForm.processing" @click="runNow">
                    {{ runForm.processing ? 'Running…' : 'Run due reminders now' }}
                </button>
            </template>
        </PageHeader>

        <section class="grid gap-4 lg:grid-cols-2">
            <article v-for="template in templates" :key="template.id" class="rounded-lg border p-5" style="border-color: var(--admin-border); background: var(--admin-surface);">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-black uppercase" style="color: var(--admin-text-muted);">{{ template.channel }}</p>
                        <h2 class="text-lg font-black">{{ template.name }}</h2>
                    </div>
                    <span class="rounded-full border px-2 py-1 text-xs font-bold">{{ template.is_active ? 'Active' : 'Disabled' }}</span>
                </div>
                <p class="mt-3 text-sm" style="color: var(--admin-text-muted);">Send {{ template.reminder_minutes_before }} minutes before the appointment.</p>
                <p v-if="template.channel === 'email'" class="mt-2 text-xs">Mail driver: <strong>{{ mailDriver }}</strong></p>
                <p v-else class="mt-2 text-xs">SMS webhook: <strong>{{ smsConfigured ? 'Configured' : 'Not configured' }}</strong></p>
                <div class="mt-4 rounded-md border p-3 text-sm whitespace-pre-line" style="border-color: var(--admin-border);">{{ template.body }}</div>
                <button v-if="can('notifications.manage')" class="mt-4 rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="openEdit(template)">Edit template</button>
            </article>
        </section>

        <section class="mt-6 overflow-hidden rounded-lg border" style="border-color: var(--admin-border); background: var(--admin-surface);">
            <div class="border-b p-4" style="border-color: var(--admin-border);">
                <h2 class="text-lg font-black">Delivery History</h2>
                <p class="text-sm" style="color: var(--admin-text-muted);">Recent reminder attempts, including skipped and failed deliveries.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead><tr><th class="p-3 text-left">Patient</th><th class="p-3 text-left">Channel</th><th class="p-3 text-left">Recipient</th><th class="p-3 text-left">Status</th><th class="p-3 text-left">Provider</th><th class="p-3 text-left">Sent</th><th class="p-3 text-left">Issue</th></tr></thead>
                    <tbody>
                        <tr v-for="delivery in deliveries.data" :key="delivery.id" class="border-t" style="border-color: var(--admin-border);">
                            <td class="p-3">{{ patientName(delivery.patient) }}<br><span class="text-xs">{{ delivery.patient?.hospital_number }}</span></td>
                            <td class="p-3">{{ delivery.channel }}</td>
                            <td class="p-3">{{ masked(delivery.recipient) }}</td>
                            <td class="p-3 font-bold">{{ delivery.status }}</td>
                            <td class="p-3">{{ delivery.provider || '—' }}</td>
                            <td class="p-3">{{ delivery.sent_at || '—' }}</td>
                            <td class="max-w-xs p-3 text-xs">{{ delivery.error_message || '—' }}</td>
                        </tr>
                        <tr v-if="!deliveries.data.length"><td colspan="7" class="p-5 text-center" style="color: var(--admin-text-muted);">No notification deliveries recorded yet.</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <FormModal :show="Boolean(editing)" :form="form" :title="editing ? `Edit ${editing.name}` : 'Edit template'" submit-label="Save template" @close="editing = null" @submit="save">
            <div class="grid gap-3">
                <label class="grid gap-1 text-sm font-semibold">Name<input v-model="form.name" class="rounded-md border p-2"></label>
                <label v-if="editing?.channel === 'email'" class="grid gap-1 text-sm font-semibold">Subject<input v-model="form.subject" class="rounded-md border p-2"></label>
                <label class="grid gap-1 text-sm font-semibold">Message<textarea v-model="form.body" class="min-h-40 rounded-md border p-2"></textarea></label>
                <label class="grid gap-1 text-sm font-semibold">Minutes before appointment<input v-model="form.reminder_minutes_before" type="number" min="30" max="10080" class="rounded-md border p-2"></label>
                <label class="flex items-center gap-2 text-sm font-semibold"><input v-model="form.is_active" type="checkbox"> Enable this reminder channel</label>
                <p class="text-xs" style="color: var(--admin-text-muted);">Available tokens: {patient_name}, {hospital_name}, {appointment_date}, {appointment_time}, {facility}, {department}</p>
            </div>
        </FormModal>
    </AppLayout>
</template>
