<script setup>
import ActionToolbar from '@/Components/Admin/ActionToolbar.vue';
import FormModal from '@/Components/Admin/FormModal.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    departments: { type: Object, required: true },
    facilities: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const page = usePage();
const permissions = computed(() => page.props.auth.permissions || []);
const roles = computed(() => page.props.auth.roles || []);
const can = (permission) => roles.value.includes('superadmin') || permissions.value.includes(permission);
const search = useForm({ search: props.filters.search || '', status: props.filters.status || '' });
const blankDepartment = () => ({ facility_id: '', name: '', code: '', description: '', category: 'administrative', status: 'active', display_order: 0, public_is_visible: false, public_is_featured: false, public_slug: '', public_name: '', public_description: '', public_icon: '', public_image_path: '', public_display_order: 0 });
const form = useForm(blankDepartment());
const showForm = ref(false);
const editing = ref(null);

function filter() {
    router.get('/admin/departments', search.data(), { preserveState: true, replace: true });
}

function openCreate() {
    form.clearErrors();
    form.defaults(blankDepartment());
    form.reset();
    editing.value = null;
    showForm.value = true;
}

function openEdit(department) {
    form.clearErrors();
    form.defaults({ ...blankDepartment(), ...department, facility_id: department.facility_id || '' });
    form.reset();
    editing.value = department;
    showForm.value = true;
}

function saveDepartment() {
    const options = { preserveScroll: true, onSuccess: () => { showForm.value = false; editing.value = null; form.defaults(blankDepartment()); form.reset(); } };
    editing.value ? form.patch(`/admin/departments/${editing.value.id}`, options) : form.post('/admin/departments', options);
}
</script>

<template>
    <Head title="Departments" />
    <AppLayout title="Departments">
        <PageHeader title="Departments" description="Maintain hospital-wide and facility-specific department records.">
            <template #actions>
                <ActionToolbar align="end">
                    <PrimaryButton v-if="can('departments.manage')" type="button" @click="openCreate">Add Department</PrimaryButton>
                </ActionToolbar>
            </template>
        </PageHeader>

        <section class="min-w-0 rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="grid gap-3 border-b border-slate-200 p-4 md:grid-cols-[1fr_180px] dark:border-slate-800">
                <input v-model="search.search" class="min-w-0 rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900" placeholder="Search departments" @change="filter">
                <select v-model="search.status" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900" @change="filter"><option value="">All</option><option value="active">Active</option><option value="inactive">Inactive</option></select>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500 dark:bg-slate-950">
                        <tr><th class="p-4">Department</th><th class="p-4">Facility</th><th class="p-4">Public</th><th class="p-4">Status</th><th class="p-4">Order</th><th class="p-4">Actions</th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="department in departments.data" :key="department.id" class="border-b border-slate-100 dark:border-slate-800">
                            <td class="p-4"><strong>{{ department.name }}</strong><br><span class="text-slate-500">{{ department.code }} - {{ department.category }}</span></td>
                            <td class="p-4">{{ department.facility?.name || 'Hospital-wide' }}</td>
                            <td class="p-4"><span class="rounded-full px-2 py-1 text-xs font-bold" :class="department.public_is_visible ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'">{{ department.public_is_visible ? (department.public_is_featured ? 'Featured public' : 'Public') : 'Private' }}</span></td>
                            <td class="p-4">{{ department.status }}</td>
                            <td class="p-4">{{ department.display_order }}</td>
                            <td class="p-4"><button v-if="can('departments.manage')" class="rounded-md border px-3 py-2 text-xs font-bold" type="button" @click="openEdit(department)">Edit</button></td>
                        </tr>
                        <tr v-if="departments.data.length === 0"><td class="p-4 text-slate-500" colspan="6">No departments found.</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <FormModal :show="showForm" :form="form" :title="editing ? 'Edit Department' : 'Add Department'" :submit-label="editing ? 'Save changes' : 'Create department'" size="lg" @close="showForm = false" @submit="saveDepartment">
            <div class="grid gap-4 sm:grid-cols-2">
                <TextInput id="dept_name" v-model="form.name" label="Name" :error="form.errors.name" />
                <TextInput id="dept_code" v-model="form.code" label="Code" :error="form.errors.code" />
                <TextInput id="dept_category" v-model="form.category" label="Category" :error="form.errors.category" />
                <TextInput id="dept_order" v-model="form.display_order" label="Display order" type="number" :error="form.errors.display_order" />
                <label class="grid gap-1 text-sm font-semibold">Facility<select v-model="form.facility_id" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900"><option value="">Hospital-wide</option><option v-for="facility in facilities" :key="facility.id" :value="facility.id">{{ facility.name }}</option></select><span v-if="form.errors.facility_id" class="text-xs text-red-700">{{ form.errors.facility_id }}</span></label>
                <label class="grid gap-1 text-sm font-semibold">Status<select v-model="form.status" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900"><option value="active">Active</option><option value="inactive">Inactive</option></select><span v-if="form.errors.status" class="text-xs text-red-700">{{ form.errors.status }}</span></label>
                <label class="grid gap-1 text-sm font-semibold sm:col-span-2">Description<textarea v-model="form.description" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900" rows="3"></textarea><span v-if="form.errors.description" class="text-xs text-red-700">{{ form.errors.description }}</span></label>
                <div class="grid gap-3 rounded-md border border-slate-200 p-4 sm:col-span-2 sm:grid-cols-2 dark:border-slate-800">
                    <label class="flex items-center gap-2 text-sm font-semibold"><input v-model="form.public_is_visible" type="checkbox"> Show on public website</label>
                    <label class="flex items-center gap-2 text-sm font-semibold"><input v-model="form.public_is_featured" type="checkbox"> Featured on homepage</label>
                    <TextInput id="dept_public_name" v-model="form.public_name" label="Public name" :error="form.errors.public_name" />
                    <TextInput id="dept_public_slug" v-model="form.public_slug" label="Public slug" :error="form.errors.public_slug" />
                    <TextInput id="dept_public_icon" v-model="form.public_icon" label="Public icon" :error="form.errors.public_icon" />
                    <TextInput id="dept_public_order" v-model="form.public_display_order" label="Public display order" type="number" :error="form.errors.public_display_order" />
                    <TextInput id="dept_public_image" v-model="form.public_image_path" label="Public image URL/path" :error="form.errors.public_image_path" />
                    <label class="grid gap-1 text-sm font-semibold sm:col-span-2">Public description<textarea v-model="form.public_description" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900" rows="3"></textarea><span v-if="form.errors.public_description" class="text-xs text-red-700">{{ form.errors.public_description }}</span></label>
                </div>
            </div>
        </FormModal>
    </AppLayout>
</template>
