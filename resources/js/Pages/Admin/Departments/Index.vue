<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import PrimaryButton from '../../../Components/PrimaryButton.vue';
import TextInput from '../../../Components/TextInput.vue';

const props = defineProps({
    departments: { type: Object, required: true },
    facilities: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const search = useForm({ search: props.filters.search || '', status: props.filters.status || '' });
const form = useForm({ facility_id: '', name: '', code: '', description: '', category: 'administrative', status: 'active', display_order: 0 });

function filter() {
    router.get('/admin/departments', search.data(), { preserveState: true, replace: true });
}
</script>

<template>
    <Head title="Departments" />
    <AppLayout title="Departments">
        <div class="grid min-w-0 gap-6 lg:grid-cols-[1fr_360px]">
            <section class="min-w-0 rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row dark:border-slate-800">
                    <input v-model="search.search" class="w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900" placeholder="Search departments" @change="filter">
                    <select v-model="search.status" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900" @change="filter"><option value="">All</option><option value="active">Active</option><option value="inactive">Inactive</option></select>
                </div>
                <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <tbody>
                        <tr v-for="department in departments.data" :key="department.id" class="border-b border-slate-100 dark:border-slate-800">
                            <td class="p-4"><strong>{{ department.name }}</strong><br><span class="text-slate-500">{{ department.code }} · {{ department.category }}</span></td>
                            <td class="p-4">{{ department.facility?.name || 'Hospital-wide' }}</td>
                            <td class="p-4">{{ department.status }}</td>
                        </tr>
                        <tr v-if="departments.data.length === 0"><td class="p-4 text-slate-500" colspan="3">No departments found.</td></tr>
                    </tbody>
                </table>
                </div>
            </section>
            <form class="min-w-0 rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="form.post('/admin/departments')">
                <h2 class="font-semibold">Create department</h2>
                <div class="mt-4 space-y-4">
                    <TextInput id="dept_name" v-model="form.name" label="Name" :error="form.errors.name" />
                    <TextInput id="dept_code" v-model="form.code" label="Code" :error="form.errors.code" />
                    <TextInput id="dept_category" v-model="form.category" label="Category" :error="form.errors.category" />
                    <select v-model="form.facility_id" class="block w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900">
                        <option value="">Hospital-wide</option>
                        <option v-for="facility in facilities" :key="facility.id" :value="facility.id">{{ facility.name }}</option>
                    </select>
                    <PrimaryButton :disabled="form.processing">Create</PrimaryButton>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
