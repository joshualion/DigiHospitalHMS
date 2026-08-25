<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({ issue: Object });
</script>

<template>
    <AppLayout :title="issue.issue_number">
        <section class="mx-auto max-w-3xl rounded-md border bg-white p-8 text-slate-950 print:border-0" style="border-color: var(--admin-border);">
            <div class="flex items-start justify-between gap-4 border-b pb-4">
                <div>
                    <h1 class="text-2xl font-black">Blood Component Issue</h1>
                    <p class="mt-1 text-sm">{{ issue.issue_number }}</p>
                </div>
                <button class="rounded-md border px-3 py-2 text-sm font-bold print:hidden" type="button" @click="window.print()">Print</button>
            </div>
            <dl class="mt-6 grid gap-4 sm:grid-cols-2">
                <div><dt class="text-xs font-black uppercase text-slate-500">Request</dt><dd class="font-bold">{{ issue.request?.request_number }}</dd></div>
                <div><dt class="text-xs font-black uppercase text-slate-500">Patient</dt><dd class="font-bold">{{ issue.request?.patient?.full_name }} - {{ issue.request?.patient?.hospital_number }}</dd></div>
                <div><dt class="text-xs font-black uppercase text-slate-500">Component</dt><dd class="font-bold">{{ issue.component?.component_number }} - {{ issue.component?.type?.name }}</dd></div>
                <div><dt class="text-xs font-black uppercase text-slate-500">Batch/Unit</dt><dd class="font-bold">{{ issue.component?.component_number }}</dd></div>
                <div><dt class="text-xs font-black uppercase text-slate-500">Issued At</dt><dd class="font-bold">{{ issue.issued_at }}</dd></div>
                <div><dt class="text-xs font-black uppercase text-slate-500">Destination</dt><dd class="font-bold">{{ issue.destination }}</dd></div>
                <div><dt class="text-xs font-black uppercase text-slate-500">Receiver</dt><dd class="font-bold">{{ issue.received_by_name }} {{ issue.receiver_role ? `(${issue.receiver_role})` : '' }}</dd></div>
                <div><dt class="text-xs font-black uppercase text-slate-500">Status</dt><dd class="font-bold">{{ issue.status }}</dd></div>
            </dl>
        </section>
    </AppLayout>
</template>
