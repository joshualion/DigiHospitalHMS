<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import PrimaryButton from '../../../Components/PrimaryButton.vue';
import TextInput from '../../../Components/TextInput.vue';

const props = defineProps({
    facilities: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const search = useForm({ search: props.filters.search || '', status: props.filters.status || '' });
const form = useForm({ name: '', code: '', facility_type: 'branch', email: '', phone: '', address: '', city: '', state: '', country: 'Nigeria', timezone: '', is_primary: false, status: 'active', opening_hours: {} });

function filter() {
    router.get('/admin/facilities', search.data(), { preserveState: true, replace: true });
}
</script>

<template>
    <Head title="Facilities" />
    <AppLayout title="Facilities">
        <div class="grid gap-6 lg:grid-cols-[1fr_360px]">
            <section class="rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                <div class="flex gap-3 border-b border-slate-200 p-4 dark:border-slate-800">
                    <input v-model="search.search" class="w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900" placeholder="Search facilities" @change="filter">
                    <select v-model="search.status" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900" @change="filter">
                        <option value="">All</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <tbody>
                            <tr v-for="facility in facilities.data" :key="facility.id" class="border-b border-slate-100 dark:border-slate-800">
                                <td class="p-4"><strong>{{ facility.name }}</strong><br><span class="text-slate-500">{{ facility.code }} · {{ facility.facility_type }}</span></td>
                                <td class="p-4">{{ facility.city }}, {{ facility.state }}</td>
                                <td class="p-4"><span class="rounded bg-slate-100 px-2 py-1 dark:bg-slate-800">{{ facility.status }}</span></td>
                                <td class="p-4">{{ facility.is_primary ? 'Primary' : '' }}</td>
                            </tr>
                            <tr v-if="facilities.data.length === 0"><td class="p-4 text-slate-500" colspan="4">No facilities found.</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>
            <form class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="form.post('/admin/facilities')">
                <h2 class="font-semibold">Create facility</h2>
                <div class="mt-4 space-y-4">
                    <TextInput id="facility_name" v-model="form.name" label="Name" :error="form.errors.name" />
                    <TextInput id="facility_code" v-model="form.code" label="Code" :error="form.errors.code" />
                    <TextInput id="facility_type" v-model="form.facility_type" label="Type" :error="form.errors.facility_type" />
                    <TextInput id="facility_city" v-model="form.city" label="City" :error="form.errors.city" />
                    <TextInput id="facility_state" v-model="form.state" label="State" :error="form.errors.state" />
                    <label class="flex items-center gap-2 text-sm"><input v-model="form.is_primary" type="checkbox" class="rounded border-slate-300 text-red-800"> Primary facility</label>
                    <PrimaryButton :disabled="form.processing">Create</PrimaryButton>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
