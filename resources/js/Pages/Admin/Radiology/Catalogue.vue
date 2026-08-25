<script setup>
import ActionToolbar from '@/Components/Admin/ActionToolbar.vue';
import FormModal from '@/Components/Admin/FormModal.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    modalities: { type: Array, default: () => [] },
    studies: { type: Array, default: () => [] },
    facilities: { type: Array, default: () => [] },
    billableServices: { type: Array, default: () => [] },
});

const modality = useForm({ facility_id: '', code: '', name: '', description: '' });
const study = useForm({ radiology_modality_id: '', billable_service_id: '', code: '', name: '', description: '', preparation_acknowledgements: [], safety_screening_acknowledgements: [] });
const activeModal = ref(null);

function saveModality() {
    modality.post('/admin/radiology/modalities', { preserveScroll: true, onSuccess: () => { activeModal.value = null; modality.reset(); } });
}

function saveStudy() {
    study.post('/admin/radiology/studies', { preserveScroll: true, onSuccess: () => { activeModal.value = null; study.reset(); } });
}
</script>

<template>
    <Head title="Radiology Catalogue" />
    <AppLayout title="Radiology Catalogue">
        <PageHeader title="Radiology Catalogue" description="Radiology modalities and studies.">
            <template #actions>
                <ActionToolbar align="end">
                    <PrimaryButton type="button" @click="activeModal = 'modality'">Add Modality</PrimaryButton>
                    <PrimaryButton type="button" @click="activeModal = 'study'">Add Study</PrimaryButton>
                </ActionToolbar>
            </template>
        </PageHeader>

        <div class="grid gap-6">
            <section class="min-w-0 rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-lg font-black">Studies</h2>
                <div class="mt-5 divide-y divide-slate-100 dark:divide-slate-800">
                    <article v-for="item in studies" :key="item.id" class="min-w-0 py-3">
                        <p class="break-words font-bold">{{ item.code }} - {{ item.name }}</p>
                        <p class="break-words text-sm text-slate-500">{{ item.modality?.name || 'No modality' }} - {{ item.billable_service?.name || 'Not billable' }}</p>
                        <p v-if="item.description" class="mt-1 break-words text-sm text-slate-500">{{ item.description }}</p>
                    </article>
                    <p v-if="studies.length === 0" class="py-4 text-sm text-slate-500">No studies configured.</p>
                </div>
            </section>

            <section class="min-w-0 rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-lg font-black">Modalities</h2>
                <div class="mt-5 divide-y divide-slate-100 dark:divide-slate-800">
                    <article v-for="item in modalities" :key="item.id" class="min-w-0 py-3">
                        <p class="break-words font-bold">{{ item.code }} - {{ item.name }}</p>
                        <p class="break-words text-sm text-slate-500">{{ item.facility?.name || 'All facilities' }}</p>
                        <p v-if="item.description" class="mt-1 break-words text-sm text-slate-500">{{ item.description }}</p>
                    </article>
                    <p v-if="modalities.length === 0" class="py-4 text-sm text-slate-500">No modalities configured.</p>
                </div>
            </section>
        </div>

        <FormModal :show="activeModal === 'study'" title="Add Study" :form="study" submit-label="Create study" size="full" @close="activeModal = null" @submit="saveStudy">
            <div class="grid gap-3 md:grid-cols-2">
                <select v-model="study.radiology_modality_id" class="rounded-md border-slate-300">
                    <option value="">Modality</option>
                    <option v-for="item in modalities" :key="item.id" :value="item.id">{{ item.code }} - {{ item.name }}</option>
                </select>
                <select v-model="study.billable_service_id" class="rounded-md border-slate-300">
                    <option value="">Billable service</option>
                    <option v-for="service in billableServices" :key="service.id" :value="service.id">{{ service.code }} - {{ service.name }}</option>
                </select>
                <TextInput id="radiology_study_code" v-model="study.code" label="Study code" />
                <TextInput id="radiology_study_name" v-model="study.name" label="Study name" />
                <textarea v-model="study.description" class="rounded-md border-slate-300 md:col-span-2" rows="3" placeholder="Description, preparation notes and safety screening requirements configured by radiology professionals"></textarea>
            </div>
        </FormModal>

        <FormModal :show="activeModal === 'modality'" title="Add Modality" :form="modality" submit-label="Create modality" @close="activeModal = null" @submit="saveModality">
            <div class="grid gap-3">
                <select v-model="modality.facility_id" class="rounded-md border-slate-300">
                    <option value="">All facilities</option>
                    <option v-for="facility in facilities" :key="facility.id" :value="facility.id">{{ facility.name }}</option>
                </select>
                <TextInput id="radiology_modality_code" v-model="modality.code" label="Code" />
                <TextInput id="radiology_modality_name" v-model="modality.name" label="Name" />
                <textarea v-model="modality.description" class="rounded-md border-slate-300" rows="3" placeholder="Professional configuration notes"></textarea>
            </div>
        </FormModal>
    </AppLayout>
</template>
