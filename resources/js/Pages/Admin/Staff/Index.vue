<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import PrimaryButton from '../../../Components/PrimaryButton.vue';
import TextInput from '../../../Components/TextInput.vue';

const props = defineProps({
    staff: { type: Object, required: true },
    facilities: { type: Array, default: () => [] },
    roles: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const search = useForm({ search: props.filters.search || '', status: props.filters.status || '' });
const form = useForm({ firstname: '', lastname: '', email: '', staff_number: '', job_title: '', staff_category: 'administrative', work_phone: '', roles: [], facility_ids: [], default_facility_id: '', notes: '' });

function filter() {
    router.get('/admin/staff', search.data(), { preserveState: true, replace: true });
}
</script>

<template>
    <Head title="Staff" />
    <AppLayout title="Staff And Users">
        <div class="grid min-w-0 gap-6 xl:grid-cols-[1fr_420px]">
            <section class="min-w-0 rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row dark:border-slate-800">
                    <input v-model="search.search" class="w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900" placeholder="Search staff" @change="filter">
                    <select v-model="search.status" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900" @change="filter"><option value="">All</option><option value="active">Active</option><option value="suspended">Suspended</option></select>
                </div>
                <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <tbody>
                        <tr v-for="entry in staff.data" :key="entry.id" class="border-b border-slate-100 dark:border-slate-800">
                            <td class="p-4"><strong>{{ entry.user.full_name }}</strong><br><span class="text-slate-500">{{ entry.staff_number }} · {{ entry.user.email }}</span></td>
                            <td class="p-4">{{ entry.job_title || entry.staff_category }}</td>
                            <td class="p-4">{{ entry.user.roles.map((role) => role.name).join(', ') }}</td>
                            <td class="p-4">{{ entry.employment_status }}</td>
                        </tr>
                        <tr v-if="staff.data.length === 0"><td class="p-4 text-slate-500" colspan="4">No staff found.</td></tr>
                    </tbody>
                </table>
                </div>
            </section>
            <form class="min-w-0 rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="form.post('/admin/staff')">
                <h2 class="font-semibold">Create or invite staff</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <TextInput id="staff_firstname" v-model="form.firstname" label="First name" :error="form.errors.firstname" />
                    <TextInput id="staff_lastname" v-model="form.lastname" label="Last name" :error="form.errors.lastname" />
                    <TextInput id="staff_email" v-model="form.email" label="Email" type="email" :error="form.errors.email" />
                    <TextInput id="staff_number" v-model="form.staff_number" label="Staff number" :error="form.errors.staff_number" />
                    <TextInput id="job_title" v-model="form.job_title" label="Job title" :error="form.errors.job_title" />
                    <TextInput id="staff_category" v-model="form.staff_category" label="Category" :error="form.errors.staff_category" />
                    <div class="sm:col-span-2">
                        <p class="text-sm font-medium">Roles</p>
                        <label v-for="role in roles" :key="role.id" class="mr-3 inline-flex items-center gap-2 text-sm"><input v-model="form.roles" :value="role.name" type="checkbox" class="rounded border-slate-300 text-red-800">{{ role.name }}</label>
                    </div>
                    <div class="sm:col-span-2">
                        <p class="text-sm font-medium">Facilities</p>
                        <label v-for="facility in facilities" :key="facility.id" class="mr-3 inline-flex items-center gap-2 text-sm"><input v-model="form.facility_ids" :value="facility.id" type="checkbox" class="rounded border-slate-300 text-red-800">{{ facility.name }}</label>
                    </div>
                    <select v-model="form.default_facility_id" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900 sm:col-span-2">
                        <option value="">Default facility</option>
                        <option v-for="facility in facilities" :key="facility.id" :value="facility.id">{{ facility.name }}</option>
                    </select>
                    <div class="sm:col-span-2"><PrimaryButton :disabled="form.processing">Create staff</PrimaryButton></div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
