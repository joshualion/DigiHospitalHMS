<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import PrimaryButton from '../../../Components/PrimaryButton.vue';
import TextInput from '../../../Components/TextInput.vue';

const props = defineProps({
    categories: { type: Array, default: () => [] },
    services: { type: Array, default: () => [] },
    facilities: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    publicServiceItems: { type: Array, default: () => [] },
});

const category = useForm({ name: '', code: '', description: '' });
const service = useForm({ billable_service_category_id: '', department_id: '', public_site_item_id: '', code: '', name: '', description: '', is_tax_exempt: false, tax_rate_basis_points: 0, is_discount_eligible: true, is_active: true, facility_ids: [] });
const price = useForm({ billable_service_id: '', facility_id: '', currency: 'NGN', amount_minor: '', effective_from: new Date().toISOString().slice(0, 10), effective_to: '', reason: '' });
</script>

<template>
    <Head title="Service Catalogue" />
    <AppLayout title="Service Catalogue">
        <div class="grid gap-6 xl:grid-cols-[1fr_420px]">
            <section class="rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 p-4 dark:border-slate-800">
                    <h2 class="text-lg font-black">Billable Services</h2>
                </div>
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    <article v-for="item in services" :key="item.id" class="grid gap-3 p-4 md:grid-cols-[1fr_160px]">
                        <div>
                            <p class="font-bold">{{ item.code }} · {{ item.name }}</p>
                            <p class="text-sm text-slate-500">{{ item.category?.name }} · {{ item.department?.name || 'All departments' }}</p>
                            <p class="mt-1 text-sm text-slate-500">{{ item.description }}</p>
                            <p class="mt-1 text-xs text-slate-500">Facilities: {{ item.facilities?.map((facility) => facility.name).join(', ') || 'Default' }}</p>
                            <p v-if="item.public_site_item" class="mt-1 text-xs text-slate-500">Public mapping: {{ item.public_site_item.title }}</p>
                        </div>
                        <div class="text-sm">
                            <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-bold text-slate-700">{{ item.is_active ? 'Active' : 'Inactive' }}</span>
                            <p class="mt-2 text-slate-500">Tax {{ item.is_tax_exempt ? 'exempt' : `${item.tax_rate_basis_points} bps` }}</p>
                            <p class="text-slate-500">{{ item.is_discount_eligible ? 'Discount eligible' : 'No discounts' }}</p>
                        </div>
                    </article>
                    <p v-if="services.length === 0" class="p-4 text-sm text-slate-500">No billable services configured.</p>
                </div>
            </section>

            <aside class="space-y-6">
                <form class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="category.post('/admin/billing/categories', { preserveScroll: true, onSuccess: () => category.reset() })">
                    <h2 class="font-black">Category</h2>
                    <div class="mt-4 grid gap-3">
                        <TextInput id="category_name" v-model="category.name" label="Name" :error="category.errors.name" />
                        <TextInput id="category_code" v-model="category.code" label="Code" :error="category.errors.code" />
                        <textarea v-model="category.description" class="rounded-md border-slate-300" rows="2" placeholder="Description"></textarea>
                        <PrimaryButton :disabled="category.processing">Create category</PrimaryButton>
                    </div>
                </form>

                <form class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="service.post('/admin/billing/services', { preserveScroll: true, onSuccess: () => service.reset() })">
                    <h2 class="font-black">Service</h2>
                    <div class="mt-4 grid gap-3">
                        <select v-model="service.billable_service_category_id" class="rounded-md border-slate-300"><option value="">Category</option><option v-for="item in categories" :key="item.id" :value="item.id">{{ item.name }}</option></select>
                        <select v-model="service.department_id" class="rounded-md border-slate-300"><option value="">Department</option><option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option></select>
                        <select v-model="service.public_site_item_id" class="rounded-md border-slate-300"><option value="">Public service mapping</option><option v-for="item in publicServiceItems" :key="item.id" :value="item.id">{{ item.title }}</option></select>
                        <TextInput id="service_code" v-model="service.code" label="Code" :error="service.errors.code" />
                        <TextInput id="service_name" v-model="service.name" label="Name" :error="service.errors.name" />
                        <textarea v-model="service.description" class="rounded-md border-slate-300" rows="2" placeholder="Description"></textarea>
                        <label class="grid gap-2 text-sm">Facilities<select v-model="service.facility_ids" class="rounded-md border-slate-300" multiple><option v-for="facility in facilities" :key="facility.id" :value="facility.id">{{ facility.name }}</option></select></label>
                        <label class="flex items-center gap-2 text-sm"><input v-model="service.is_tax_exempt" type="checkbox"> Tax exempt</label>
                        <TextInput id="tax_bps" v-model="service.tax_rate_basis_points" label="Tax rate basis points" type="number" />
                        <label class="flex items-center gap-2 text-sm"><input v-model="service.is_discount_eligible" type="checkbox"> Discount eligible</label>
                        <PrimaryButton :disabled="service.processing">Create service</PrimaryButton>
                    </div>
                </form>

                <form class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="price.post(`/admin/billing/services/${price.billable_service_id}/prices`, { preserveScroll: true, onSuccess: () => price.reset('amount_minor', 'effective_to', 'reason') })">
                    <h2 class="font-black">Price</h2>
                    <div class="mt-4 grid gap-3">
                        <select v-model="price.billable_service_id" class="rounded-md border-slate-300"><option value="">Service</option><option v-for="item in services" :key="item.id" :value="item.id">{{ item.code }} · {{ item.name }}</option></select>
                        <select v-model="price.facility_id" class="rounded-md border-slate-300"><option value="">Default price</option><option v-for="facility in facilities" :key="facility.id" :value="facility.id">{{ facility.name }}</option></select>
                        <TextInput id="price_currency" v-model="price.currency" label="Currency" maxlength="3" />
                        <TextInput id="price_amount" v-model="price.amount_minor" label="Amount minor units" type="number" />
                        <TextInput id="price_from" v-model="price.effective_from" label="Effective from" type="date" />
                        <TextInput id="price_to" v-model="price.effective_to" label="Effective to" type="date" />
                        <textarea v-model="price.reason" class="rounded-md border-slate-300" rows="2" placeholder="Reason"></textarea>
                        <PrimaryButton :disabled="price.processing || !price.billable_service_id">Add price</PrimaryButton>
                    </div>
                </form>
            </aside>
        </div>
    </AppLayout>
</template>
