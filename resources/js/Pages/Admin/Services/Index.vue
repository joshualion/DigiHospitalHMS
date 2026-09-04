<script setup>
import ActionToolbar from '@/Components/Admin/ActionToolbar.vue';
import ConfirmDialog from '@/Components/Admin/ConfirmDialog.vue';
import FormModal from '@/Components/Admin/FormModal.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    services: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    facilities: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const page = usePage();
const permissions = computed(() => page.props.auth.permissions || []);
const roles = computed(() => page.props.auth.roles || []);
const can = (permission) => roles.value.includes('superadmin') || permissions.value.includes(permission);
const search = useForm({ search: props.filters.search || '', status: props.filters.status || '', public: props.filters.public || '' });
const blankService = () => ({
    billable_service_category_id: '',
    department_id: '',
    code: '',
    name: '',
    description: '',
    is_active: true,
    facility_ids: [],
    public_is_visible: true,
    public_is_featured: true,
    public_slug: '',
    public_name: '',
    public_description: '',
    public_icon: 'stethoscope',
    public_image_path: '',
    public_display_order: 0,
});
const form = useForm(blankService());
const deleteForm = useForm({});
const showForm = ref(false);
const editing = ref(null);
const deleteTarget = ref(null);

function filter() {
    router.get('/admin/services', search.data(), { preserveState: true, replace: true });
}

function openCreate() {
    form.clearErrors();
    form.defaults(blankService());
    form.reset();
    editing.value = null;
    showForm.value = true;
}

function openEdit(service) {
    form.clearErrors();
    form.defaults({
        ...blankService(),
        ...service,
        billable_service_category_id: service.billable_service_category_id || '',
        department_id: service.department_id || '',
        facility_ids: service.facilities?.map((facility) => facility.id) || [],
        public_icon: service.public_icon || 'stethoscope',
    });
    form.reset();
    editing.value = service;
    showForm.value = true;
}

function saveService() {
    const options = { preserveScroll: true, onSuccess: () => { showForm.value = false; editing.value = null; form.defaults(blankService()); form.reset(); } };
    editing.value ? form.patch(`/admin/services/${editing.value.id}`, options) : form.post('/admin/services', options);
}

function confirmDelete(service) {
    deleteTarget.value = service;
}

function deleteService() {
    if (!deleteTarget.value) return;

    deleteForm.delete(`/admin/services/${deleteTarget.value.id}`, {
        preserveScroll: true,
        onSuccess: () => { deleteTarget.value = null; },
    });
}
</script>

<template>
    <Head title="Services" />
    <AppLayout title="Services">
        <PageHeader title="Services" description="Create and publish hospital services for the public website.">
            <template #actions>
                <ActionToolbar align="end">
                    <PrimaryButton v-if="can('billing.catalogue.manage')" type="button" @click="openCreate">Add Service</PrimaryButton>
                </ActionToolbar>
            </template>
        </PageHeader>

        <section class="min-w-0 rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="grid gap-3 border-b border-slate-200 p-4 md:grid-cols-[1fr_160px_170px] dark:border-slate-800">
                <input v-model="search.search" class="min-w-0 rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900" placeholder="Search services" @change="filter">
                <select v-model="search.status" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900" @change="filter"><option value="">All statuses</option><option value="active">Active</option><option value="inactive">Inactive</option></select>
                <select v-model="search.public" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900" @change="filter"><option value="">All public states</option><option value="visible">Public</option><option value="featured">Featured</option><option value="private">Private</option></select>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500 dark:bg-slate-950">
                        <tr><th class="p-4">Service</th><th class="p-4">Department</th><th class="p-4">Public</th><th class="p-4">Status</th><th class="p-4">Order</th><th class="p-4">Actions</th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="service in services.data" :key="service.id" class="border-b border-slate-100 dark:border-slate-800">
                            <td class="p-4">
                                <strong>{{ service.public_name || service.name }}</strong><br>
                                <span class="text-slate-500">{{ service.code }} - {{ service.category?.name || 'Public services' }}</span>
                                <p class="mt-1 max-w-xl text-slate-500">{{ service.public_description || service.description }}</p>
                            </td>
                            <td class="p-4">{{ service.department?.name || 'All departments' }}</td>
                            <td class="p-4"><span class="rounded-full px-2 py-1 text-xs font-bold" :class="service.public_is_visible ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'">{{ service.public_is_visible ? (service.public_is_featured ? 'Featured public' : 'Public') : 'Private' }}</span></td>
                            <td class="p-4">{{ service.is_active ? 'active' : 'inactive' }}</td>
                            <td class="p-4">{{ service.public_display_order }}</td>
                            <td class="p-4">
                                <div v-if="can('billing.catalogue.manage')" class="flex flex-wrap gap-2">
                                    <button class="rounded-md border px-3 py-2 text-xs font-bold" type="button" @click="openEdit(service)">Edit</button>
                                    <button class="rounded-md border border-rose-300 px-3 py-2 text-xs font-bold text-rose-700" type="button" @click="confirmDelete(service)">Delete</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="services.data.length === 0"><td class="p-4 text-slate-500" colspan="6">No services found.</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <FormModal :show="showForm" :form="form" :title="editing ? 'Edit Service' : 'Add Service'" :submit-label="editing ? 'Save changes' : 'Create service'" size="lg" @close="showForm = false" @submit="saveService">
            <div class="grid gap-4 sm:grid-cols-2">
                <TextInput id="service_name" v-model="form.name" label="Name" :error="form.errors.name" />
                <TextInput id="service_code" v-model="form.code" label="Code" :error="form.errors.code" />
                <label class="grid gap-1 text-sm font-semibold">Category<select v-model="form.billable_service_category_id" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900"><option value="">Public services</option><option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option></select><span v-if="form.errors.billable_service_category_id" class="text-xs text-red-700">{{ form.errors.billable_service_category_id }}</span></label>
                <label class="grid gap-1 text-sm font-semibold">Department<select v-model="form.department_id" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900"><option value="">All departments</option><option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option></select><span v-if="form.errors.department_id" class="text-xs text-red-700">{{ form.errors.department_id }}</span></label>
                <label class="grid gap-1 text-sm font-semibold sm:col-span-2">Description<textarea v-model="form.description" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900" rows="3"></textarea><span v-if="form.errors.description" class="text-xs text-red-700">{{ form.errors.description }}</span></label>
                <label class="grid gap-2 text-sm font-semibold sm:col-span-2">Facilities<select v-model="form.facility_ids" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900" multiple><option v-for="facility in facilities" :key="facility.id" :value="facility.id">{{ facility.name }}</option></select><span v-if="form.errors.facility_ids" class="text-xs text-red-700">{{ form.errors.facility_ids }}</span></label>
                <label class="flex items-center gap-2 text-sm font-semibold"><input v-model="form.is_active" type="checkbox"> Active</label>
                <div class="grid gap-3 rounded-md border border-slate-200 p-4 sm:col-span-2 sm:grid-cols-2 dark:border-slate-800">
                    <label class="flex items-center gap-2 text-sm font-semibold"><input v-model="form.public_is_visible" type="checkbox"> Show on public website</label>
                    <label class="flex items-center gap-2 text-sm font-semibold"><input v-model="form.public_is_featured" type="checkbox"> Featured on homepage</label>
                    <TextInput id="service_public_name" v-model="form.public_name" label="Public name" :error="form.errors.public_name" />
                    <TextInput id="service_public_slug" v-model="form.public_slug" label="Public slug" :error="form.errors.public_slug" />
                    <TextInput id="service_public_icon" v-model="form.public_icon" label="Public icon" :error="form.errors.public_icon" />
                    <TextInput id="service_public_order" v-model="form.public_display_order" label="Public display order" type="number" :error="form.errors.public_display_order" />
                    <TextInput id="service_public_image" v-model="form.public_image_path" label="Public image URL/path" :error="form.errors.public_image_path" />
                    <label class="grid gap-1 text-sm font-semibold sm:col-span-2">Public description<textarea v-model="form.public_description" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900" rows="3"></textarea><span v-if="form.errors.public_description" class="text-xs text-red-700">{{ form.errors.public_description }}</span></label>
                </div>
            </div>
        </FormModal>

        <ConfirmDialog
            :show="Boolean(deleteTarget)"
            title="Delete Service"
            :message="deleteTarget ? `Delete '${deleteTarget.public_name || deleteTarget.name}' from services? This removes it from the public website and admin service list.` : ''"
            confirm-label="Delete"
            :form="deleteForm"
            @close="deleteTarget = null"
            @confirm="deleteService"
        />
    </AppLayout>
</template>
