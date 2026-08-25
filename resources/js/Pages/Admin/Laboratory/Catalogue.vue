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
    tests: { type: Array, default: () => [] },
    profiles: { type: Array, default: () => [] },
    specimenTypes: { type: Array, default: () => [] },
    units: { type: Array, default: () => [] },
    billableServices: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
});

const specimen = useForm({ code: '', name: '', collection_notes: '' });
const unit = useForm({ code: '', name: '' });
const test = useForm({ code: '', name: '', department_id: '', default_specimen_type_id: '', billable_service_id: '', description: '', turnaround_time: '', requires_approval: true });
const component = useForm({ lab_test_id: '', code: '', name: '', lab_unit_id: '', result_type: 'numeric', sort_order: 1 });
const range = useForm({ lab_test_component_id: '', label: 'Default', low_value: '', high_value: '', critical_low_value: '', critical_high_value: '', qualitative_normal: '', display_text: '' });
const profile = useForm({ code: '', name: '', description: '', lab_test_ids: [] });
const activeModal = ref(null);

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
                    <PrimaryButton type="button" @click="activeModal = 'test'">Add Test</PrimaryButton>
                    <PrimaryButton type="button" @click="activeModal = 'component'">Add Component</PrimaryButton>
                    <PrimaryButton type="button" @click="activeModal = 'range'">Add Range</PrimaryButton>
                    <PrimaryButton type="button" @click="activeModal = 'specimen'">Add Specimen</PrimaryButton>
                    <PrimaryButton type="button" @click="activeModal = 'unit'">Add Unit</PrimaryButton>
                    <PrimaryButton type="button" @click="activeModal = 'profile'">Add Panel</PrimaryButton>
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
                        <p v-for="child in item.components" :key="child.id" class="break-words text-xs text-slate-500">{{ child.code }} - {{ child.name }} - {{ child.result_type }} {{ child.unit?.code || '' }}</p>
                    </article>
                    <p v-if="tests.length === 0" class="py-4 text-sm text-slate-500">No tests configured.</p>
                </div>
            </section>

            <section class="grid min-w-0 gap-6 lg:grid-cols-3">
                <div class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="font-black">Specimen Types</h2>
                    <p v-for="item in specimenTypes" :key="item.id" class="mt-3 break-words text-sm">{{ item.code }} - {{ item.name }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="font-black">Units</h2>
                    <p v-for="item in units" :key="item.id" class="mt-3 break-words text-sm">{{ item.code }} - {{ item.name }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="font-black">Panels / Profiles</h2>
                    <p v-for="item in profiles" :key="item.id" class="mt-3 break-words text-sm">{{ item.code }} - {{ item.name }}</p>
                </div>
            </section>
        </div>

        <FormModal :show="activeModal === 'test'" title="Add Test" :form="test" submit-label="Create test" size="full" @close="activeModal = null" @submit="save(test, '/admin/laboratory/tests')">
            <div class="grid gap-3 md:grid-cols-2">
                <TextInput id="lab_test_code" v-model="test.code" label="Code" />
                <TextInput id="lab_test_name" v-model="test.name" label="Name" />
                <select v-model="test.department_id" class="rounded-md border-slate-300"><option value="">Department</option><option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option></select>
                <select v-model="test.default_specimen_type_id" class="rounded-md border-slate-300"><option value="">Specimen type</option><option v-for="type in specimenTypes" :key="type.id" :value="type.id">{{ type.name }}</option></select>
                <select v-model="test.billable_service_id" class="rounded-md border-slate-300"><option value="">Billable service</option><option v-for="service in billableServices" :key="service.id" :value="service.id">{{ service.code }} - {{ service.name }}</option></select>
                <TextInput id="turnaround" v-model="test.turnaround_time" label="Turnaround time" />
                <textarea v-model="test.description" class="rounded-md border-slate-300 md:col-span-2" rows="2" placeholder="Description"></textarea>
                <label class="flex items-center gap-2 text-sm"><input v-model="test.requires_approval" type="checkbox"> Requires approval</label>
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

        <FormModal :show="activeModal === 'specimen'" title="Add Specimen Type" :form="specimen" submit-label="Create specimen" @close="activeModal = null" @submit="save(specimen, '/admin/laboratory/specimen-types')">
            <div class="grid gap-3">
                <TextInput id="specimen_code" v-model="specimen.code" label="Code" />
                <TextInput id="specimen_name" v-model="specimen.name" label="Name" />
                <textarea v-model="specimen.collection_notes" class="rounded-md border-slate-300" rows="2" placeholder="Collection notes"></textarea>
            </div>
        </FormModal>

        <FormModal :show="activeModal === 'unit'" title="Add Unit" :form="unit" submit-label="Create unit" @close="activeModal = null" @submit="save(unit, '/admin/laboratory/units')">
            <div class="grid gap-3">
                <TextInput id="unit_code" v-model="unit.code" label="Code" />
                <TextInput id="unit_name" v-model="unit.name" label="Name" />
            </div>
        </FormModal>

        <FormModal :show="activeModal === 'profile'" title="Add Panel / Profile" :form="profile" submit-label="Create panel" @close="activeModal = null" @submit="save(profile, '/admin/laboratory/profiles')">
            <div class="grid gap-3">
                <TextInput id="profile_code" v-model="profile.code" label="Code" />
                <TextInput id="profile_name" v-model="profile.name" label="Name" />
                <textarea v-model="profile.description" class="rounded-md border-slate-300" rows="2" placeholder="Description"></textarea>
                <select v-model="profile.lab_test_ids" class="rounded-md border-slate-300" multiple><option v-for="item in tests" :key="item.id" :value="item.id">{{ item.name }}</option></select>
            </div>
        </FormModal>
    </AppLayout>
</template>
