<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

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
</script>

<template>
    <Head title="Laboratory Catalogue" />
    <AppLayout title="Laboratory Catalogue">
        <div class="grid gap-6 xl:grid-cols-[1fr_380px]">
            <section class="space-y-6">
                <section class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-lg font-black">Tests</h2>
                    <form class="mt-4 grid gap-3 md:grid-cols-2" @submit.prevent="test.post('/admin/laboratory/tests', { preserveScroll: true, onSuccess: () => test.reset() })">
                        <TextInput id="lab_test_code" v-model="test.code" label="Code" />
                        <TextInput id="lab_test_name" v-model="test.name" label="Name" />
                        <select v-model="test.default_specimen_type_id" class="rounded-md border-slate-300"><option value="">Specimen type</option><option v-for="type in specimenTypes" :key="type.id" :value="type.id">{{ type.name }}</option></select>
                        <select v-model="test.billable_service_id" class="rounded-md border-slate-300"><option value="">Billable service</option><option v-for="service in billableServices" :key="service.id" :value="service.id">{{ service.code }} - {{ service.name }}</option></select>
                        <textarea v-model="test.description" class="rounded-md border-slate-300 md:col-span-2" rows="2" placeholder="Description"></textarea>
                        <TextInput id="turnaround" v-model="test.turnaround_time" label="Turnaround time" />
                        <label class="flex items-center gap-2 text-sm"><input v-model="test.requires_approval" type="checkbox"> Requires approval</label>
                        <PrimaryButton :disabled="test.processing">Create test</PrimaryButton>
                    </form>
                    <div class="mt-5 divide-y divide-slate-100 dark:divide-slate-800">
                        <article v-for="item in tests" :key="item.id" class="py-3">
                            <p class="font-bold">{{ item.code }} - {{ item.name }}</p>
                            <p class="text-sm text-slate-500">{{ item.specimen_type?.name || 'No specimen' }} - {{ item.billable_service?.name || 'Not billable' }}</p>
                            <p v-for="child in item.components" :key="child.id" class="text-xs text-slate-500">{{ child.code }} - {{ child.name }} - {{ child.result_type }} {{ child.unit?.code || '' }}</p>
                        </article>
                    </div>
                </section>

                <section class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="font-black">Components And Reference Ranges</h2>
                    <form class="mt-4 grid gap-3 md:grid-cols-3" @submit.prevent="component.post(`/admin/laboratory/tests/${component.lab_test_id}/components`, { preserveScroll: true, onSuccess: () => component.reset() })">
                        <select v-model="component.lab_test_id" class="rounded-md border-slate-300"><option value="">Test</option><option v-for="item in tests" :key="item.id" :value="item.id">{{ item.name }}</option></select>
                        <TextInput id="component_code" v-model="component.code" label="Component code" />
                        <TextInput id="component_name" v-model="component.name" label="Component name" />
                        <select v-model="component.result_type" class="rounded-md border-slate-300"><option value="numeric">Numeric</option><option value="text">Text</option><option value="qualitative">Qualitative</option><option value="comment">Comment</option></select>
                        <select v-model="component.lab_unit_id" class="rounded-md border-slate-300"><option value="">Unit</option><option v-for="item in units" :key="item.id" :value="item.id">{{ item.code }}</option></select>
                        <PrimaryButton :disabled="component.processing">Add component</PrimaryButton>
                    </form>
                    <form class="mt-5 grid gap-3 md:grid-cols-4" @submit.prevent="range.post(`/admin/laboratory/components/${range.lab_test_component_id}/reference-ranges`, { preserveScroll: true, onSuccess: () => range.reset() })">
                        <select v-model="range.lab_test_component_id" class="rounded-md border-slate-300 md:col-span-2"><option value="">Component</option><template v-for="item in tests" :key="item.id"><option v-for="child in item.components" :key="child.id" :value="child.id">{{ item.name }} - {{ child.name }}</option></template></select>
                        <TextInput id="range_label" v-model="range.label" label="Label" />
                        <TextInput id="range_display" v-model="range.display_text" label="Display text" />
                        <TextInput id="range_low" v-model="range.low_value" label="Low" type="number" />
                        <TextInput id="range_high" v-model="range.high_value" label="High" type="number" />
                        <TextInput id="critical_low" v-model="range.critical_low_value" label="Critical low" type="number" />
                        <TextInput id="critical_high" v-model="range.critical_high_value" label="Critical high" type="number" />
                        <PrimaryButton :disabled="range.processing">Add range</PrimaryButton>
                    </form>
                </section>
            </section>

            <aside class="space-y-6">
                <form class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="specimen.post('/admin/laboratory/specimen-types', { preserveScroll: true, onSuccess: () => specimen.reset() })">
                    <h2 class="font-black">Specimen Type</h2>
                    <div class="mt-4 grid gap-3">
                        <TextInput id="specimen_code" v-model="specimen.code" label="Code" />
                        <TextInput id="specimen_name" v-model="specimen.name" label="Name" />
                        <textarea v-model="specimen.collection_notes" class="rounded-md border-slate-300" rows="2" placeholder="Collection notes"></textarea>
                        <PrimaryButton :disabled="specimen.processing">Create specimen</PrimaryButton>
                    </div>
                </form>
                <form class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="unit.post('/admin/laboratory/units', { preserveScroll: true, onSuccess: () => unit.reset() })">
                    <h2 class="font-black">Unit</h2>
                    <div class="mt-4 grid gap-3">
                        <TextInput id="unit_code" v-model="unit.code" label="Code" />
                        <TextInput id="unit_name" v-model="unit.name" label="Name" />
                        <PrimaryButton :disabled="unit.processing">Create unit</PrimaryButton>
                    </div>
                </form>
                <form class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="profile.post('/admin/laboratory/profiles', { preserveScroll: true, onSuccess: () => profile.reset() })">
                    <h2 class="font-black">Panel / Profile</h2>
                    <div class="mt-4 grid gap-3">
                        <TextInput id="profile_code" v-model="profile.code" label="Code" />
                        <TextInput id="profile_name" v-model="profile.name" label="Name" />
                        <select v-model="profile.lab_test_ids" class="rounded-md border-slate-300" multiple><option v-for="item in tests" :key="item.id" :value="item.id">{{ item.name }}</option></select>
                        <PrimaryButton :disabled="profile.processing">Create panel</PrimaryButton>
                    </div>
                </form>
            </aside>
        </div>
    </AppLayout>
</template>
