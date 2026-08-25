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
    staff: { type: Object, required: true },
    facilities: { type: Array, default: () => [] },
    roles: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const page = usePage();
const permissions = computed(() => page.props.auth.permissions || []);
const pageRoles = computed(() => page.props.auth.roles || []);
const can = (permission) => pageRoles.value.includes('superadmin') || permissions.value.includes(permission);
const search = useForm({ search: props.filters.search || '', status: props.filters.status || '' });
const blankStaff = () => ({ firstname: '', lastname: '', email: '', staff_number: '', job_title: '', staff_category: 'administrative', professional_license_number: '', license_expires_at: '', work_phone: '', hire_date: '', roles: [], facility_ids: [], default_facility_id: '', notes: '' });
const form = useForm(blankStaff());
const statusForm = useForm({ status: 'active' });
const showForm = ref(false);
const editing = ref(null);
const statusTarget = ref(null);

function filter() {
    router.get('/admin/staff', search.data(), { preserveState: true, replace: true });
}

function openCreate() {
    form.clearErrors();
    form.defaults(blankStaff());
    form.reset();
    editing.value = null;
    showForm.value = true;
}

function openEdit(entry) {
    form.clearErrors();
    form.defaults({
        ...blankStaff(),
        firstname: entry.user?.firstname || '',
        lastname: entry.user?.lastname || '',
        email: entry.user?.email || '',
        staff_number: entry.staff_number || '',
        job_title: entry.job_title || '',
        staff_category: entry.staff_category || 'administrative',
        professional_license_number: entry.professional_license_number || '',
        license_expires_at: entry.license_expires_at || '',
        work_phone: entry.work_phone || '',
        hire_date: entry.hire_date || '',
        notes: entry.notes || '',
        roles: entry.user?.roles?.map((role) => role.name) || [],
        facility_ids: entry.memberships?.filter((membership) => membership.status === 'active').map((membership) => membership.facility_id) || [],
        default_facility_id: entry.memberships?.find((membership) => membership.is_default)?.facility_id || '',
    });
    form.reset();
    editing.value = entry;
    showForm.value = true;
}

function saveStaff() {
    const options = { preserveScroll: true, onSuccess: () => { showForm.value = false; editing.value = null; form.defaults(blankStaff()); form.reset(); } };
    editing.value ? form.patch(`/admin/staff/${editing.value.id}`, options) : form.post('/admin/staff', options);
}

function openStatus(entry) {
    statusTarget.value = entry;
    statusForm.defaults({ status: entry.employment_status === 'active' ? 'suspended' : 'active' });
    statusForm.reset();
}

function saveStatus() {
    statusForm.patch(`/admin/staff/${statusTarget.value.id}/status`, { preserveScroll: true, onSuccess: () => { statusTarget.value = null; statusForm.reset(); } });
}
</script>

<template>
    <Head title="Staff" />
    <AppLayout title="Staff And Users">
        <PageHeader title="Staff And Users" description="Manage staff identities, roles and facility access.">
            <template #actions>
                <ActionToolbar align="end">
                    <PrimaryButton v-if="can('staff.invite')" type="button" @click="openCreate">Add Staff</PrimaryButton>
                </ActionToolbar>
            </template>
        </PageHeader>

        <section class="min-w-0 rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="grid gap-3 border-b border-slate-200 p-4 md:grid-cols-[1fr_180px] dark:border-slate-800">
                <input v-model="search.search" class="min-w-0 rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900" placeholder="Search staff" @change="filter">
                <select v-model="search.status" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900" @change="filter"><option value="">All</option><option value="active">Active</option><option value="suspended">Suspended</option></select>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500 dark:bg-slate-950">
                        <tr><th class="p-4">Staff</th><th class="p-4">Role</th><th class="p-4">Facilities</th><th class="p-4">Status</th><th class="p-4">Actions</th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="entry in staff.data" :key="entry.id" class="border-b border-slate-100 dark:border-slate-800">
                            <td class="p-4"><strong>{{ entry.user.full_name }}</strong><br><span class="text-slate-500">{{ entry.staff_number }} - {{ entry.user.email }}</span></td>
                            <td class="p-4">{{ entry.user.roles.map((role) => role.name).join(', ') || entry.job_title || entry.staff_category }}</td>
                            <td class="p-4">{{ entry.memberships.map((membership) => membership.facility?.name).filter(Boolean).join(', ') || 'None' }}</td>
                            <td class="p-4">{{ entry.employment_status }}</td>
                            <td class="p-4">
                                <ActionToolbar>
                                    <button v-if="can('staff.update')" class="rounded-md border px-3 py-2 text-xs font-bold" type="button" @click="openEdit(entry)">Edit</button>
                                    <button v-if="can('staff.suspend')" class="rounded-md border px-3 py-2 text-xs font-bold" type="button" @click="openStatus(entry)">{{ entry.employment_status === 'active' ? 'Suspend' : 'Activate' }}</button>
                                </ActionToolbar>
                            </td>
                        </tr>
                        <tr v-if="staff.data.length === 0"><td class="p-4 text-slate-500" colspan="5">No staff found.</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <FormModal :show="showForm" :form="form" :title="editing ? 'Edit Staff' : 'Add Staff'" :submit-label="editing ? 'Save changes' : 'Create staff'" size="full" @close="showForm = false" @submit="saveStaff">
            <div class="grid gap-4 sm:grid-cols-2">
                <TextInput id="staff_firstname" v-model="form.firstname" label="First name" :error="form.errors.firstname" />
                <TextInput id="staff_lastname" v-model="form.lastname" label="Last name" :error="form.errors.lastname" />
                <TextInput id="staff_email" v-model="form.email" label="Email" type="email" :error="form.errors.email" />
                <TextInput id="staff_number" v-model="form.staff_number" label="Staff number" :error="form.errors.staff_number" />
                <TextInput id="job_title" v-model="form.job_title" label="Job title" :error="form.errors.job_title" />
                <TextInput id="staff_category" v-model="form.staff_category" label="Category" :error="form.errors.staff_category" />
                <TextInput id="license_number" v-model="form.professional_license_number" label="Professional license" :error="form.errors.professional_license_number" />
                <TextInput id="license_expires" v-model="form.license_expires_at" label="License expiry" type="date" :error="form.errors.license_expires_at" />
                <TextInput id="work_phone" v-model="form.work_phone" label="Work phone" :error="form.errors.work_phone" />
                <TextInput id="hire_date" v-model="form.hire_date" label="Hire date" type="date" :error="form.errors.hire_date" />
                <div class="sm:col-span-2">
                    <p class="text-sm font-semibold">Roles</p>
                    <div class="mt-2 flex flex-wrap gap-3">
                        <label v-for="role in roles" :key="role.id" class="inline-flex items-center gap-2 text-sm"><input v-model="form.roles" :value="role.name" type="checkbox" class="rounded border-slate-300 text-red-800">{{ role.name }}</label>
                    </div>
                    <p v-if="form.errors.roles" class="mt-1 text-xs text-red-700">{{ form.errors.roles }}</p>
                </div>
                <div class="sm:col-span-2">
                    <p class="text-sm font-semibold">Facilities</p>
                    <div class="mt-2 flex flex-wrap gap-3">
                        <label v-for="facility in facilities" :key="facility.id" class="inline-flex items-center gap-2 text-sm"><input v-model="form.facility_ids" :value="facility.id" type="checkbox" class="rounded border-slate-300 text-red-800">{{ facility.name }}</label>
                    </div>
                    <p v-if="form.errors.facility_ids" class="mt-1 text-xs text-red-700">{{ form.errors.facility_ids }}</p>
                </div>
                <label class="grid gap-1 text-sm font-semibold sm:col-span-2">Default facility<select v-model="form.default_facility_id" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900"><option value="">Default facility</option><option v-for="facility in facilities" :key="facility.id" :value="facility.id">{{ facility.name }}</option></select><span v-if="form.errors.default_facility_id" class="text-xs text-red-700">{{ form.errors.default_facility_id }}</span></label>
                <label class="grid gap-1 text-sm font-semibold sm:col-span-2">Notes<textarea v-model="form.notes" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900" rows="3"></textarea></label>
            </div>
        </FormModal>

        <ConfirmDialog :show="Boolean(statusTarget)" :form="statusForm" :title="statusForm.status === 'active' ? 'Activate Staff' : 'Suspend Staff'" :message="statusTarget ? `Update ${statusTarget.user?.full_name}?` : ''" confirm-label="Update status" @close="statusTarget = null" @confirm="saveStatus" />
    </AppLayout>
</template>
