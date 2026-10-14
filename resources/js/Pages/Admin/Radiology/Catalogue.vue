<script setup>
import ActionToolbar from '@/Components/Admin/ActionToolbar.vue';
import ConfirmDialog from '@/Components/Admin/ConfirmDialog.vue';
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

const modality = useForm({ facility_id: '', code: '', name: '', description: '', is_active: true });
const study = useForm({ radiology_modality_id: '', billable_service_id: '', code: '', name: '', description: '', preparation_acknowledgements: [], safety_screening_acknowledgements: [], requires_professional_validation: true, is_active: true });
const activeModal = ref(null);
const editing = ref(null);
const deleteTarget = ref(null);
const deleteForm = useForm({});

function openEditor(type, item = null) {
    editing.value = item ? { type, item } : null;
    activeModal.value = type;

    if (type === 'modality') {
        modality.defaults(item ? {
            facility_id: item.facility_id || '',
            code: item.code,
            name: item.name,
            description: item.description || '',
            is_active: Boolean(item.is_active),
        } : { facility_id: '', code: '', name: '', description: '', is_active: true });
        modality.reset();
    }

    if (type === 'study') {
        study.defaults(item ? {
            radiology_modality_id: item.radiology_modality_id || '',
            billable_service_id: item.billable_service_id || '',
            code: item.code,
            name: item.name,
            description: item.description || '',
            preparation_acknowledgements: item.preparation_acknowledgements || [],
            safety_screening_acknowledgements: item.safety_screening_acknowledgements || [],
            requires_professional_validation: Boolean(item.requires_professional_validation),
            is_active: Boolean(item.is_active),
        } : {
            radiology_modality_id: '',
            billable_service_id: '',
            code: '',
            name: '',
            description: '',
            preparation_acknowledgements: [],
            safety_screening_acknowledgements: [],
            requires_professional_validation: true,
            is_active: true,
        });
        study.reset();
    }
}

function saveModality() {
    const item = editing.value?.type === 'modality' ? editing.value.item : null;
    const options = { preserveScroll: true, onSuccess: () => { activeModal.value = null; editing.value = null; modality.reset(); } };
    item ? modality.patch(`/admin/radiology/modalities/${item.id}`, options) : modality.post('/admin/radiology/modalities', options);
}

function saveStudy() {
    const item = editing.value?.type === 'study' ? editing.value.item : null;
    const options = { preserveScroll: true, onSuccess: () => { activeModal.value = null; editing.value = null; study.reset(); } };
    item ? study.patch(`/admin/radiology/studies/${item.id}`, options) : study.post('/admin/radiology/studies', options);
}

function openDelete(type, item) {
    deleteForm.clearErrors();
    deleteTarget.value = { type, item };
}

function deleteCatalogue() {
    if (!deleteTarget.value) return;
    const { type, item } = deleteTarget.value;
    const base = type === 'study' ? 'studies' : 'modalities';
    deleteForm.delete(`/admin/radiology/${base}/${item.id}`, { preserveScroll: true, onSuccess: () => { deleteTarget.value = null; } });
}
</script>

<template>
    <Head title="Radiology Catalogue" />
    <AppLayout title="Radiology Catalogue">
        <PageHeader title="Radiology Catalogue" description="Radiology modalities and studies.">
            <template #actions>
                <ActionToolbar align="end">
                    <PrimaryButton type="button" @click="openEditor('modality')">Add Modality</PrimaryButton>
                    <PrimaryButton type="button" @click="openEditor('study')">Add Study</PrimaryButton>
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
                        <p class="mt-1 text-xs font-semibold" :class="item.is_active ? 'text-emerald-700' : 'text-slate-500'">{{ item.is_active ? 'Active' : 'Inactive' }}</p>
                        <ActionToolbar class="mt-2"><button class="rounded border px-2 py-1 text-xs font-bold" type="button" @click="openEditor('study', item)">Edit</button><button class="rounded border border-rose-300 px-2 py-1 text-xs font-bold text-rose-700" type="button" @click="openDelete('study', item)">Delete</button></ActionToolbar>
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
                        <p class="mt-1 text-xs font-semibold" :class="item.is_active ? 'text-emerald-700' : 'text-slate-500'">{{ item.is_active ? 'Active' : 'Inactive' }}</p>
                        <ActionToolbar class="mt-2"><button class="rounded border px-2 py-1 text-xs font-bold" type="button" @click="openEditor('modality', item)">Edit</button><button class="rounded border border-rose-300 px-2 py-1 text-xs font-bold text-rose-700" type="button" @click="openDelete('modality', item)">Delete</button></ActionToolbar>
                    </article>
                    <p v-if="modalities.length === 0" class="py-4 text-sm text-slate-500">No modalities configured.</p>
                </div>
            </section>
        </div>

        <FormModal :show="activeModal === 'study'" :title="editing?.type === 'study' ? 'Edit Study' : 'Add Study'" :form="study" :submit-label="editing?.type === 'study' ? 'Save changes' : 'Create study'" size="full" @close="activeModal = null" @submit="saveStudy">
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
                <label class="flex items-center gap-2 text-sm"><input v-model="study.requires_professional_validation" type="checkbox"> Requires professional validation</label>
                <label class="flex items-center gap-2 text-sm"><input v-model="study.is_active" type="checkbox"> Active</label>
            </div>
        </FormModal>

        <FormModal :show="activeModal === 'modality'" :title="editing?.type === 'modality' ? 'Edit Modality' : 'Add Modality'" :form="modality" :submit-label="editing?.type === 'modality' ? 'Save changes' : 'Create modality'" @close="activeModal = null" @submit="saveModality">
            <div class="grid gap-3">
                <select v-model="modality.facility_id" class="rounded-md border-slate-300">
                    <option value="">All facilities</option>
                    <option v-for="facility in facilities" :key="facility.id" :value="facility.id">{{ facility.name }}</option>
                </select>
                <TextInput id="radiology_modality_code" v-model="modality.code" label="Code" />
                <TextInput id="radiology_modality_name" v-model="modality.name" label="Name" />
                <textarea v-model="modality.description" class="rounded-md border-slate-300" rows="3" placeholder="Professional configuration notes"></textarea>
                <label class="flex items-center gap-2 text-sm"><input v-model="modality.is_active" type="checkbox"> Active</label>
            </div>
        </FormModal>
        <ConfirmDialog :show="Boolean(deleteTarget)" :form="deleteForm" title="Delete radiology catalogue item" :message="deleteTarget ? `Delete ${deleteTarget.item.name}? Items with clinical history are protected and should be made inactive instead.` : ''" confirm-label="Delete" @close="deleteTarget = null" @confirm="deleteCatalogue" />
        <p v-if="deleteForm.errors.catalogue" class="mt-3 rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-800">{{ deleteForm.errors.catalogue }}</p>
    </AppLayout>
</template>
