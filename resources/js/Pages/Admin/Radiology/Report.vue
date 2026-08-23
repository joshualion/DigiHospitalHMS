<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    radiologyRequest: { type: Object, required: true },
});
</script>

<template>
    <Head :title="`Radiology Report ${radiologyRequest.request_number}`" />
    <AppLayout :title="`Radiology Report ${radiologyRequest.request_number}`">
        <div class="mb-4 flex justify-between gap-3 print:hidden">
            <Link :href="`/admin/radiology/requests/${radiologyRequest.id}`" class="text-sm font-bold text-red-800">Back to request</Link>
            <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="window.print()">Print report</button>
        </div>

        <section class="mx-auto max-w-4xl rounded-lg border border-slate-200 bg-white p-6 text-slate-950 print:border-0">
            <div class="border-b border-slate-200 pb-4">
                <p class="text-xs font-black uppercase text-slate-500">Approved Radiology Report</p>
                <h1 class="text-2xl font-black">{{ radiologyRequest.request_number }}</h1>
                <p class="text-sm text-slate-500">Accession {{ radiologyRequest.accession_number }} - {{ radiologyRequest.status }}</p>
            </div>

            <div class="grid gap-4 py-4 md:grid-cols-3">
                <div>
                    <p class="text-xs font-black uppercase text-slate-500">Patient</p>
                    <p class="font-bold">{{ radiologyRequest.patient?.full_name }}</p>
                    <p class="text-sm text-slate-500">{{ radiologyRequest.patient?.hospital_number }}</p>
                </div>
                <div>
                    <p class="text-xs font-black uppercase text-slate-500">Ordered</p>
                    <p class="font-bold">{{ radiologyRequest.ordered_at }}</p>
                </div>
                <div>
                    <p class="text-xs font-black uppercase text-slate-500">Released</p>
                    <p class="font-bold">{{ radiologyRequest.released_at || radiologyRequest.approved_at }}</p>
                </div>
            </div>

            <div class="border-t border-slate-200 py-4">
                <h2 class="font-black">Studies</h2>
                <p v-for="item in radiologyRequest.studies" :key="item.id" class="mt-1 text-sm">{{ item.study_code }} - {{ item.study_name }}</p>
            </div>

            <div class="space-y-5 border-t border-slate-200 pt-4">
                <article>
                    <h2 class="font-black">Findings</h2>
                    <p class="mt-2 whitespace-pre-line text-sm">{{ radiologyRequest.report?.findings }}</p>
                </article>
                <article>
                    <h2 class="font-black">Impression</h2>
                    <p class="mt-2 whitespace-pre-line text-sm">{{ radiologyRequest.report?.impression }}</p>
                </article>
                <article v-if="radiologyRequest.report?.recommendations">
                    <h2 class="font-black">Recommendations</h2>
                    <p class="mt-2 whitespace-pre-line text-sm">{{ radiologyRequest.report?.recommendations }}</p>
                </article>
            </div>

            <div v-if="radiologyRequest.report?.has_critical_finding" class="mt-6 border-t border-slate-200 pt-4">
                <h2 class="font-black text-red-700">Critical Finding</h2>
                <p class="mt-2 text-sm">{{ radiologyRequest.report?.critical_finding_notes }}</p>
            </div>

            <div v-if="radiologyRequest.report?.amendments?.length" class="mt-6 border-t border-slate-200 pt-4">
                <h2 class="font-black">Amendments</h2>
                <p v-for="item in radiologyRequest.report.amendments" :key="item.id" class="mt-2 text-sm"><strong>{{ item.reason }}</strong> - {{ item.content }}</p>
            </div>
        </section>
    </AppLayout>
</template>
