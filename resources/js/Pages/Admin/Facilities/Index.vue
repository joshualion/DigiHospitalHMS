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
    facilities: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const page = usePage();
const permissions = computed(() => page.props.auth.permissions || []);
const roles = computed(() => page.props.auth.roles || []);
const isSuperadmin = computed(() => roles.value.includes('superadmin'));
const can = (permission) => isSuperadmin.value || permissions.value.includes(permission);
const search = useForm({ search: props.filters.search || '', status: props.filters.status || '' });
const blankFacility = () => ({ name: '', code: '', facility_type: 'branch', email: '', phone: '', address: '', city: '', state: '', country: 'Nigeria', timezone: '', is_primary: false, status: 'active', opening_hours: {} });
const form = useForm(blankFacility());
const statusForm = useForm({ status: 'active' });
const showForm = ref(false);
const editing = ref(null);
const statusTarget = ref(null);

function filter() {
    router.get('/admin/facilities', search.data(), { preserveState: true, replace: true });
}

function openCreate() {
    form.clearErrors();
    form.defaults(blankFacility());
    form.reset();
    editing.value = null;
    showForm.value = true;
}

function openEdit(facility) {
    form.clearErrors();
    form.defaults({ ...blankFacility(), ...facility });
    form.reset();
    editing.value = facility;
    showForm.value = true;
}

function saveFacility() {
    const options = { preserveScroll: true, onSuccess: () => { showForm.value = false; editing.value = null; form.defaults(blankFacility()); form.reset(); } };
    editing.value ? form.patch(`/admin/facilities/${editing.value.id}`, options) : form.post('/admin/facilities', options);
}

function openStatus(facility) {
    statusTarget.value = facility;
    statusForm.defaults({ status: facility.status === 'active' ? 'inactive' : 'active' });
    statusForm.reset();
}

function saveStatus() {
    statusForm.patch(`/admin/facilities/${statusTarget.value.id}/status`, { preserveScroll: true, onSuccess: () => { statusTarget.value = null; statusForm.reset(); } });
}
</script>

<template>
    <Head title="Facilities" />
    <AppLayout title="Facilities">
        <PageHeader title="Facilities" description="Manage hospital sites and operational locations.">
            <template #actions>
                <ActionToolbar align="end">
                    <PrimaryButton v-if="can('facilities.create')" type="button" @click="openCreate">Add Facility</PrimaryButton>
                </ActionToolbar>
            </template>
        </PageHeader>

        <section class="min-w-0 rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="grid gap-3 border-b border-slate-200 p-4 md:grid-cols-[1fr_180px] dark:border-slate-800">
                <input v-model="search.search" class="min-w-0 rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900" placeholder="Search facilities" @change="filter">
                <select v-model="search.status" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900" @change="filter">
                    <option value="">All</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500 dark:bg-slate-950">
                        <tr><th class="p-4">Facility</th><th class="p-4">Location</th><th class="p-4">Status</th><th class="p-4">Type</th><th class="p-4">Actions</th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="facility in facilities.data" :key="facility.id" class="border-b border-slate-100 dark:border-slate-800">
                            <td class="p-4"><strong>{{ facility.name }}</strong><br><span class="text-slate-500">{{ facility.code }}{{ facility.is_primary ? ' - Primary' : '' }}</span></td>
                            <td class="p-4">{{ [facility.city, facility.state].filter(Boolean).join(', ') || 'Not set' }}</td>
                            <td class="p-4"><span class="rounded bg-slate-100 px-2 py-1 dark:bg-slate-800">{{ facility.status }}</span></td>
                            <td class="p-4">{{ facility.facility_type }}</td>
                            <td class="p-4">
                                <ActionToolbar>
                                    <button v-if="can('facilities.update')" class="rounded-md border px-3 py-2 text-xs font-bold" type="button" @click="openEdit(facility)">Edit</button>
                                    <button v-if="can('facilities.activate')" class="rounded-md border px-3 py-2 text-xs font-bold" type="button" @click="openStatus(facility)">{{ facility.status === 'active' ? 'Deactivate' : 'Activate' }}</button>
                                </ActionToolbar>
                            </td>
                        </tr>
                        <tr v-if="facilities.data.length === 0"><td class="p-4 text-slate-500" colspan="5">No facilities found.</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <FormModal :show="showForm" :form="form" :title="editing ? 'Edit Facility' : 'Add Facility'" :submit-label="editing ? 'Save changes' : 'Create facility'" size="xl" @close="showForm = false" @submit="saveFacility">
            <div class="grid gap-4 sm:grid-cols-2">
                <TextInput id="facility_name" v-model="form.name" label="Name" :error="form.errors.name" />
                <TextInput id="facility_code" v-model="form.code" label="Code" :error="form.errors.code" />
                <TextInput id="facility_type" v-model="form.facility_type" label="Type" :error="form.errors.facility_type" />
                <TextInput id="facility_email" v-model="form.email" label="Email" type="email" :error="form.errors.email" />
                <TextInput id="facility_phone" v-model="form.phone" label="Phone" :error="form.errors.phone" />
                <TextInput id="facility_city" v-model="form.city" label="City" :error="form.errors.city" />
                <TextInput id="facility_state" v-model="form.state" label="State" :error="form.errors.state" />
                <TextInput id="facility_country" v-model="form.country" label="Country" :error="form.errors.country" />
                <TextInput id="facility_timezone" v-model="form.timezone" label="Timezone" :error="form.errors.timezone" />
                <label class="grid gap-1 text-sm font-semibold">Status<select v-model="form.status" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900"><option value="active">Active</option><option value="inactive">Inactive</option></select></label>
                <label class="flex items-center gap-2 text-sm font-semibold sm:col-span-2"><input v-model="form.is_primary" type="checkbox" class="rounded border-slate-300 text-red-800"> Primary facility</label>
                <label class="grid gap-1 text-sm font-semibold sm:col-span-2">Address<textarea v-model="form.address" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900" rows="3"></textarea></label>
            </div>
        </FormModal>

        <ConfirmDialog :show="Boolean(statusTarget)" :form="statusForm" :title="statusForm.status === 'active' ? 'Activate Facility' : 'Deactivate Facility'" :message="statusTarget ? `Update ${statusTarget.name} status?` : ''" confirm-label="Update status" @close="statusTarget = null" @confirm="saveStatus" />
    </AppLayout>
</template>
