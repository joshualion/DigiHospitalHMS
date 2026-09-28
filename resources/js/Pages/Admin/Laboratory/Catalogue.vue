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
    tests: { type: Array, default: () => [] },
    profiles: { type: Array, default: () => [] },
    specimenTypes: { type: Array, default: () => [] },
    units: { type: Array, default: () => [] },
    billableServices: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
});

const specimen = useForm({ code: '', name: '', collection_notes: '', is_active: true });
const unit = useForm({ code: '', name: '', is_active: true });
const test = useForm({ code: '', name: '', department_id: '', default_specimen_type_id: '', billable_service_id: '', description: '', turnaround_time: '', requires_approval: true, is_active: true });
const component = useForm({ lab_test_id: '', code: '', name: '', lab_unit_id: '', result_type: 'numeric', sort_order: 1 });
const range = useForm({ lab_test_component_id: '', label: 'Default', low_value: '', high_value: '', critical_low_value: '', critical_high_value: '', qualitative_normal: '', display_text: '' });
const profile = useForm({ code: '', name: '', description: '', lab_test_ids: [], is_active: true });
const activeModal = ref(null);
const editing = ref(null);
const deleteTarget = ref(null);
const deleteForm = useForm({});

function openEditor(type, item = null) {
    editing.value = item ? { type, item } : null;
    activeModal.value = type;

    if (type === 'test') {
        test.defaults(item ? {
            code: item.code, name: item.name, department_id: item.department_id || '',
            default_specimen_type_id: item.default_specimen_type_id || '', billable_service_id: item.billable_service_id || '',
            description: item.description || '', turnaround_time: item.turnaround_time || '',
            requires_approval: Boolean(item.requires_approval), is_active: Boolean(item.is_active),
        } : { code: '', name: '', department_id: '', default_specimen_type_id: '', billable_service_id: '', description: '', turnaround_time: '', requires_approval: true, is_active: true });
        test.reset();
    }
    if (type === 'specimen') {
        specimen.defaults(item ? { code: item.code, name: item.name, collection_notes: item.collection_notes || '', is_active: Boolean(item.is_active) } : { code: '', name: '', collection_notes: '', is_active: true });
        specimen.reset();
    }
    if (type === 'unit') {
        unit.defaults(item ? { code: item.code, name: item.name, is_active: Boolean(item.is_active) } : { code: '', name: '', is_active: true });
        unit.reset();
    }
    if (type === 'profile') {
        profile.defaults(item ? { code: item.code, name: item.name, description: item.description || '', lab_test_ids: (item.tests || []).map((entry) => entry.id), is_active: Boolean(item.is_active) } : { code: '', name: '', description: '', lab_test_ids: [], is_active: true });
        profile.reset();
    }
}

function saveCatalogue(type, form, baseUrl) {
    const item = editing.value?.type === type ? editing.value.item : null;
    const options = { preserveScroll: true, onSuccess: () => { activeModal.value = null; editing.value = null; form.reset(); } };
    item ? form.patch(`${baseUrl}/${item.id}`, options) : form.post(baseUrl, options);
}

function openDelete(type, item) {
    deleteForm.clearErrors();
    deleteTarget.value = { type, item };
}

function deleteCatalogue() {
    if (!deleteTarget.value) return;
    const { type, item } = deleteTarget.value;
    const base = { test: 'tests', specimen: 'specimen-types', unit: 'units', profile: 'profiles' }[type];
    deleteForm.delete(`/admin/laboratory/${base}/${item.id}`, { preserveScroll: true, onSuccess: () => { deleteTarget.value = null; } });
}

function save(form, url, reset = () => form.reset()) {
    form.post(url, { preserveScroll: true, onSuccess: () => { activeModal.value = null; reset(); } });
}
</script>

<template>
    <Head title="Laboratory Catalogue" />
    <AppLayout title="Laboratory Catalogue">
        <PageHeader title="Laboratory Catalogue" description="Laboratory tests, specimen types, result components and profiles.">
            <template #actions>
                <ActionToolbar align="end">
                    <PrimaryButton type="button" @click="openEditor('test')">Add Test</PrimaryButton>
                    <PrimaryButton type="button" @click="activeModal = 'component'">Add Component</PrimaryButton>
                    <PrimaryButton type="button" @click="activeModal = 'range'">Add Range</PrimaryButton>
                    <PrimaryButton type="button" @click="openEditor('specimen')">Add Specimen</PrimaryButton>
                    <PrimaryButton type="button" @click="openEditor('unit')">Add Unit</PrimaryButton>
                    <PrimaryButton type="button" @click="openEditor('profile')">Add Panel</PrimaryButton>
                </ActionToolbar>
            </template>
        </PageHeader>

        <div class="grid gap-6">
            <section class="min-w-0 rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-lg font-black">Tests</h2>
                <div class="mt-5 divide-y divide-slate-100 dark:divide-slate-800">
                    <article v-for="item in tests" :key="item.id" class="min-w-0 py-3">
                        <p class="break-words font-bold">{{ item.code }} - {{ item.name }}</p>
                        <p class="break-words text-sm text-slate-500">{{ item.specimen_type?.name || 'No specimen' }} - {{ item.billable_service?.name || 'Not billable' }}</p>
                        <p class="mt-1 text-xs font-semibold" :class="item.is_active ? 'text-emerald-700' : 'text-slate-500'">{{ item.is_active ? 'Active' : 'Inactive' }}</p>
                        <p v-for="child in item.components" :key="child.id" class="break-words text-xs text-slate-500">{{ child.code }} - {{ child.name }} - {{ child.result_type }} {{ child.unit?.code || '' }}</p>
                        <ActionToolbar class="mt-2"><button class="rounded border px-2 py-1 text-xs font-bold" type="button" @click="openEditor('test', item)">Edit</button><button class="rounded border border-rose-300 px-2 py-1 text-xs font-bold text-rose-700" type="button" @click="openDelete('test', item)">Delete</button></ActionToolbar>
                    </article>
                    <p v-if="tests.length === 0" class="py-4 text-sm text-slate-500">No tests configured.</p>
                </div>
            </section>

            <section class="grid min-w-0 gap-6 lg:grid-cols-3">
                <div class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="font-black">Specimen Types</h2>
                    <div v-for="item in specimenTypes" :key="item.id" class="mt-3 rounded border p-2 text-sm"><p>{{ item.code }} - {{ item.name }} <span class="text-xs text-slate-500">· {{ item.is_active ? 'Active' : 'Inactive' }}</span></p><ActionToolbar class="mt-2"><button class="rounded border px-2 py-1 text-xs font-bold" type="button" @click="openEditor('specimen', item)">Edit</button><button class="rounded border border-rose-300 px-2 py-1 text-xs font-bold text-rose-700" type="button" @click="openDelete('specimen', item)">Delete</button></ActionToolbar></div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="font-black">Units</h2>
                    <div v-for="item in units" :key="item.id" class="mt-3 rounded border p-2 text-sm"><p>{{ item.code }} - {{ item.name }} <span class="text-xs text-slate-500">· {{ item.is_active ? 'Active' : 'Inactive' }}</span></p><ActionToolbar class="mt-2"><button class="rounded border px-2 py-1 text-xs font-bold" type="button" @click="openEditor('unit', item)">Edit</button><button class="rounded border border-rose-300 px-2 py-1 text-xs font-bold text-rose-700" type="button" @click="openDelete('unit', item)">Delete</button></ActionToolbar></div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="font-black">Panels / Profiles</h2>
                    <div v-for="item in profiles" :key="item.id" class="mt-3 rounded border p-2 text-sm"><p>{{ item.code }} - {{ item.name }} <span class="text-xs text-slate-500">· {{ item.is_active ? 'Active' : 'Inactive' }}</span></p><ActionToolbar class="mt-2"><button class="rounded border px-2 py-1 text-xs font-bold" type="button" @click="openEditor('profile', item)">Edit</button><button class="rounded border border-rose-300 px-2 py-1 text-xs font-bold text-rose-700" type="button" @click="openDelete('profile', item)">Delete</button></ActionToolbar></div>
                </div>
            </section>
        </div>

        <FormModal :show="activeModal === 'test'" :title="editing?.type === 'test' ? 'Edit Test' : 'Add Test'" :form="test" :submit-label="editing?.type === 'test' ? 'Save changes' : 'Create test'" size="full" @close="activeModal = null" @submit="saveCatalogue('test', test, '/admin/laboratory/tests')">
            <div class="grid gap-3 md:grid-cols-2">
                <TextInput id="lab_test_code" v-model="test.code" label="Code" />
                <TextInput id="lab_test_name" v-model="test.name" label="Name" />
                <select v-model="test.department_id" class="rounded-md border-slate-300"><option value="">Department</option><option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option></select>
                <select v-model="test.default_specimen_type_id" class="rounded-md border-slate-300"><option value="">Specimen type</option><option v-for="type in specimenTypes" :key="type.id" :value="type.id">{{ type.name }}</option></select>
                <select v-model="test.billable_service_id" class="rounded-md border-slate-300"><option value="">Billable service</option><option v-for="service in billableServices" :key="service.id" :value="service.id">{{ service.code }} - {{ service.name }}</option></select>
                <TextInput id="turnaround" v-model="test.turnaround_time" label="Turnaround time" />
                <textarea v-model="test.description" class="rounded-md border-slate-300 md:col-span-2" rows="2" placeholder="Description"></textarea>
                <label class="flex items-center gap-2 text-sm"><input v-model="test.requires_approval" type="checkbox"> Requires approval</label><label class="flex items-center gap-2 text-sm"><input v-model="test.is_active" type="checkbox"> Active</label>
            </div>
        </FormModal>

        <FormModal :show="activeModal === 'component'" title="Add Component" :form="component" submit-label="Add component" @close="activeModal = null" @submit="save(component, `/admin/laboratory/tests/${component.lab_test_id}/components`)">
            <div class="grid gap-3">
                <select v-model="component.lab_test_id" class="rounded-md border-slate-300"><option value="">Test</option><option v-for="item in tests" :key="item.id" :value="item.id">{{ item.name }}</option></select>
                <TextInput id="component_code" v-model="component.code" label="Component code" />
                <TextInput id="component_name" v-model="component.name" label="Component name" />
                <select v-model="component.result_type" class="rounded-md border-slate-300"><option value="numeric">Numeric</option><option value="text">Text</option><option value="qualitative">Qualitative</option><option value="comment">Comment</option></select>
                <select v-model="component.lab_unit_id" class="rounded-md border-slate-300"><option value="">Unit</option><option v-for="item in units" :key="item.id" :value="item.id">{{ item.code }}</option></select>
            </div>
        </FormModal>

        <FormModal :show="activeModal === 'range'" title="Add Reference Range" :form="range" submit-label="Add range" size="full" @close="activeModal = null" @submit="save(range, `/admin/laboratory/components/${range.lab_test_component_id}/reference-ranges`)">
            <div class="grid gap-3 md:grid-cols-2">
                <select v-model="range.lab_test_component_id" class="rounded-md border-slate-300 md:col-span-2"><option value="">Component</option><template v-for="item in tests" :key="item.id"><option v-for="child in item.components" :key="child.id" :value="child.id">{{ item.name }} - {{ child.name }}</option></template></select>
                <TextInput id="range_label" v-model="range.label" label="Label" />
                <TextInput id="range_display" v-model="range.display_text" label="Display text" />
                <TextInput id="range_low" v-model="range.low_value" label="Low" type="number" />
                <TextInput id="range_high" v-model="range.high_value" label="High" type="number" />
                <TextInput id="critical_low" v-model="range.critical_low_value" label="Critical low" type="number" />
                <TextInput id="critical_high" v-model="range.critical_high_value" label="Critical high" type="number" />
            </div>
        </FormModal>

        <FormModal :show="activeModal === 'specimen'" :title="editing?.type === 'specimen' ? 'Edit Specimen Type' : 'Add Specimen Type'" :form="specimen" :submit-label="editing?.type === 'specimen' ? 'Save changes' : 'Create specimen'" @close="activeModal = null" @submit="saveCatalogue('specimen', specimen, '/admin/laboratory/specimen-types')">
            <div class="grid gap-3">
                <TextInput id="specimen_code" v-model="specimen.code" label="Code" />
                <TextInput id="specimen_name" v-model="specimen.name" label="Name" />
                <textarea v-model="specimen.collection_notes" class="rounded-md border-slate-300" rows="2" placeholder="Collection notes"></textarea><label class="flex items-center gap-2 text-sm"><input v-model="specimen.is_active" type="checkbox"> Active</label>
            </div>
        </FormModal>

        <FormModal :show="activeModal === 'unit'" :title="editing?.type === 'unit' ? 'Edit Unit' : 'Add Unit'" :form="unit" :submit-label="editing?.type === 'unit' ? 'Save changes' : 'Create unit'" @close="activeModal = null" @submit="saveCatalogue('unit', unit, '/admin/laboratory/units')">
            <div class="grid gap-3">
                <TextInput id="unit_code" v-model="unit.code" label="Code" />
                <TextInput id="unit_name" v-model="unit.name" label="Name" /><label class="flex items-center gap-2 text-sm"><input v-model="unit.is_active" type="checkbox"> Active</label>
            </div>
        </FormModal>

        <FormModal :show="activeModal === 'profile'" :title="editing?.type === 'profile' ? 'Edit Panel / Profile' : 'Add Panel / Profile'" :form="profile" :submit-label="editing?.type === 'profile' ? 'Save changes' : 'Create panel'" @close="activeModal = null" @submit="saveCatalogue('profile', profile, '/admin/laboratory/profiles')">
            <div class="grid gap-3">
                <TextInput id="profile_code" v-model="profile.code" label="Code" />
                <TextInput id="profile_name" v-model="profile.name" label="Name" />
                <textarea v-model="profile.description" class="rounded-md border-slate-300" rows="2" placeholder="Description"></textarea>
                <select v-model="profile.lab_test_ids" class="rounded-md border-slate-300" multiple><option v-for="item in tests" :key="item.id" :value="item.id">{{ item.name }}</option></select><label class="flex items-center gap-2 text-sm"><input v-model="profile.is_active" type="checkbox"> Active</label>
            </div>
        </FormModal>
        <ConfirmDialog :show="Boolean(deleteTarget)" :form="deleteForm" title="Delete laboratory catalogue item" :message="deleteTarget ? `Delete ${deleteTarget.item.name}? Items with clinical history are protected and should be made inactive instead.` : ''" confirm-label="Delete" @close="deleteTarget = null" @confirm="deleteCatalogue" />
        <p v-if="deleteForm.errors.catalogue" class="mt-3 rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-800">{{ deleteForm.errors.catalogue }}</p>
    </AppLayout>
</template>
