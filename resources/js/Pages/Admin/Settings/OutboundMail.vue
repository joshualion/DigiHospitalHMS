<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
const props = defineProps({ settings: { type: Object, default: null } });
const s = props.settings || {};
const form = useForm({
    enabled: Boolean(s.enabled), provider: 'custom', host: s.host || '',
    port: s.port || 587, encryption: s.encryption || 'tls', username: s.username || '',
    password: '', from_address: s.from_address || '', from_name: s.from_name || ''
});
const testForm = useForm({ recipient: '' });

function save() { form.patch('/admin/settings/outbound-mail', { preserveScroll: true, onSuccess: () => { form.password = ''; } }); }
function sendTest() { testForm.post('/admin/settings/outbound-mail/test', { preserveScroll: true }); }
</script>

<template>
    <Head title="SMTP Settings" />
    <AppLayout title="Outgoing email settings">
        <div class="mx-auto max-w-3xl space-y-6">
            <div class="rounded-lg border bg-white p-6 dark:bg-slate-900">
                <h1 class="text-xl font-bold">Outgoing email settings</h1>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">For dependable hospital notifications, use an official mailbox on your organisation's own domain (for example, noreply@yourdomain.com or support@yourdomain.com). Configure it with your preferred SMTP provider. Turn the override off to use existing server email settings. Credentials are encrypted.</p>
                <form class="mt-5 grid gap-4" @submit.prevent="save">
                    <label class="flex items-center gap-2"><input v-model="form.enabled" type="checkbox"> Use these SMTP settings for hospital emails</label>
                    <p class="rounded bg-teal-50 p-3 text-sm text-teal-950">Recommended: provision an official hospital-domain email address before enabling automated notifications. Verify SPF, DKIM and DMARC with your email provider. Branded email improves identity, but does not guarantee inbox delivery.</p>
                    <label class="grid gap-1">SMTP host<input v-model="form.host" class="rounded border" required></label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="grid gap-1">Port<input v-model.number="form.port" type="number" min="1" max="65535" class="rounded border" required></label>
                        <label class="grid gap-1">Security<select v-model="form.encryption" class="rounded border"><option value="tls">STARTTLS (587)</option><option value="ssl">SSL/TLS (465)</option></select></label>
                    </div>
                    <label class="grid gap-1">SMTP username<input v-model="form.username" class="rounded border" autocomplete="off" required></label>
                    <label class="grid gap-1">SMTP mailbox password<input v-model="form.password" type="password" class="rounded border" autocomplete="new-password" :placeholder="s.has_password ? 'Leave blank to keep existing secret' : 'Enter password'"></label>
                    <label class="grid gap-1">From email<input v-model="form.from_address" type="email" class="rounded border" required></label>
                    <label class="grid gap-1">From name<input v-model="form.from_name" class="rounded border" required></label>
                    <p v-if="Object.keys(form.errors).length" class="text-sm text-red-700">{{ Object.values(form.errors).join(' ') }}</p>
                    <button :disabled="form.processing" class="rounded bg-teal-800 px-4 py-2 font-semibold text-white">Save email settings</button>
                </form>
            </div>
            <form class="rounded-lg border bg-white p-6 dark:bg-slate-900" @submit.prevent="sendTest">
                <h2 class="font-bold">Send a test email</h2>
                <p class="mt-1 text-xs text-slate-500">Save your settings first. A successful send request does not guarantee inbox delivery.</p>
                <label class="mt-3 grid gap-1">Recipient<input v-model="testForm.recipient" type="email" class="rounded border" required></label>
                <p v-if="testForm.errors.recipient" class="text-sm text-red-700">{{ testForm.errors.recipient }}</p>
                <button :disabled="testForm.processing" class="mt-3 rounded border border-teal-800 px-4 py-2 text-teal-800">Send test email</button>
            </form>
        </div>
    </AppLayout>
</template>
