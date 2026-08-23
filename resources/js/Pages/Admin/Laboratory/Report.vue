<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    labRequest: { type: Object, required: true },
});
</script>

<template>
    <Head :title="`Lab Report ${labRequest.request_number}`" />
    <AppLayout :title="`Lab Report ${labRequest.request_number}`">
        <div class="mb-4 flex justify-between gap-3 print:hidden">
            <Link :href="`/admin/laboratory/requests/${labRequest.id}`" class="text-sm font-bold text-red-800">Back to request</Link>
            <button class="rounded-md border px-3 py-2 text-sm font-bold" type="button" @click="window.print()">Print report</button>
        </div>
        <section class="mx-auto max-w-4xl rounded-lg border border-slate-200 bg-white p-6 text-slate-950 print:border-0">
            <div class="border-b border-slate-200 pb-4">
                <p class="text-xs font-black uppercase text-slate-500">Approved Laboratory Report</p>
                <h1 class="text-2xl font-black">{{ labRequest.request_number }}</h1>
                <p class="text-sm text-slate-500">Accession {{ labRequest.accession_number }} - {{ labRequest.status }}</p>
            </div>
            <div class="grid gap-4 py-4 md:grid-cols-3">
                <div><p class="text-xs font-black uppercase text-slate-500">Patient</p><p class="font-bold">{{ labRequest.patient?.full_name }}</p><p class="text-sm text-slate-500">{{ labRequest.patient?.hospital_number }}</p></div>
                <div><p class="text-xs font-black uppercase text-slate-500">Ordered</p><p class="font-bold">{{ labRequest.ordered_at }}</p></div>
                <div><p class="text-xs font-black uppercase text-slate-500">Released</p><p class="font-bold">{{ labRequest.released_at || labRequest.approved_at }}</p></div>
            </div>
            <div class="border-t border-slate-200 pt-4">
                <article v-for="result in labRequest.results" :key="result.id" class="grid gap-3 border-b border-slate-100 py-3 md:grid-cols-[1fr_180px_140px]">
                    <div>
                        <p class="font-bold">{{ result.component_name }}</p>
                        <p class="text-xs text-slate-500">{{ result.reference_range_snapshot?.display_text || '' }}</p>
                    </div>
                    <p>{{ result.numeric_value ?? result.qualitative_value ?? result.text_value ?? result.comment }} {{ result.component?.unit?.code || '' }}</p>
                    <p class="font-bold">{{ result.flag }} <span v-if="result.is_critical">Critical</span></p>
                </article>
            </div>
            <div v-if="labRequest.amendments.length" class="mt-6 border-t border-slate-200 pt-4">
                <h2 class="font-black">Amendments</h2>
                <p v-for="item in labRequest.amendments" :key="item.id" class="mt-2 text-sm"><strong>{{ item.reason }}</strong> - {{ item.content }}</p>
            </div>
        </section>
    </AppLayout>
</template>
