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
    categories: { type: Array, default: () => [] },
    services: { type: Array, default: () => [] },
    facilities: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    publicServiceItems: { type: Array, default: () => [] },
});

const category = useForm({ name: '', code: '', description: '' });
const service = useForm({ billable_service_category_id: '', department_id: '', public_site_item_id: '', code: '', name: '', description: '', is_tax_exempt: false, tax_rate_basis_points: 0, is_discount_eligible: true, is_active: true, facility_ids: [] });
const price = useForm({ billable_service_id: '', facility_id: '', currency: 'NGN', amount_minor: '', effective_from: new Date().toISOString().slice(0, 10), effective_to: '', reason: '' });
const activeModal = ref(null);

function closeModal() {
    activeModal.value = null;
}

function saveCategory() {
    category.post('/admin/billing/categories', { preserveScroll: true, onSuccess: () => { closeModal(); category.reset(); } });
}

function saveService() {
    service.post('/admin/billing/services', { preserveScroll: true, onSuccess: () => { closeModal(); service.reset(); } });
}

function savePrice() {
    price.post(`/admin/billing/services/${price.billable_service_id}/prices`, { preserveScroll: true, onSuccess: () => { closeModal(); price.reset('amount_minor', 'effective_to', 'reason'); } });
}
</script>

<template>
    <Head title="Service Catalogue" />
    <AppLayout title="Service Catalogue">
        <PageHeader title="Service Catalogue" description="Billing services, categories and price configuration.">
            <template #actions>
                <ActionToolbar align="end">
                    <PrimaryButton type="button" @click="activeModal = 'category'">Add Category</PrimaryButton>
                    <PrimaryButton type="button" @click="activeModal = 'service'">Add Service</PrimaryButton>
                    <PrimaryButton type="button" @click="activeModal = 'price'">Add Price</PrimaryButton>
                </ActionToolbar>
            </template>
        </PageHeader>

        <section class="min-w-0 rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 p-4 dark:border-slate-800">
                <h2 class="text-lg font-black">Billable Services</h2>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                <article v-for="item in services" :key="item.id" class="grid min-w-0 gap-3 p-4 md:grid-cols-[1fr_160px]">
                    <div class="min-w-0">
                        <p class="break-words font-bold">{{ item.code }} - {{ item.name }}</p>
                        <p class="break-words text-sm text-slate-500">{{ item.category?.name }} - {{ item.department?.name || 'All departments' }}</p>
                        <p class="mt-1 break-words text-sm text-slate-500">{{ item.description }}</p>
                        <p class="mt-1 break-words text-xs text-slate-500">Facilities: {{ item.facilities?.map((facility) => facility.name).join(', ') || 'Default' }}</p>
                        <p v-if="item.public_site_item" class="mt-1 break-words text-xs text-slate-500">Public mapping: {{ item.public_site_item.title }}</p>
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

        <FormModal :show="activeModal === 'category'" title="Add Category" :form="category" submit-label="Create category" @close="closeModal" @submit="saveCategory">
            <div class="grid gap-3">
                <TextInput id="category_name" v-model="category.name" label="Name" :error="category.errors.name" />
                <TextInput id="category_code" v-model="category.code" label="Code" :error="category.errors.code" />
                <textarea v-model="category.description" class="rounded-md border-slate-300" rows="2" placeholder="Description"></textarea>
            </div>
        </FormModal>

        <FormModal :show="activeModal === 'service'" title="Add Service" :form="service" submit-label="Create service" size="full" @close="closeModal" @submit="saveService">
            <div class="grid gap-3 md:grid-cols-2">
                <select v-model="service.billable_service_category_id" class="rounded-md border-slate-300"><option value="">Category</option><option v-for="item in categories" :key="item.id" :value="item.id">{{ item.name }}</option></select>
                <select v-model="service.department_id" class="rounded-md border-slate-300"><option value="">Department</option><option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option></select>
                <select v-model="service.public_site_item_id" class="rounded-md border-slate-300"><option value="">Public service mapping</option><option v-for="item in publicServiceItems" :key="item.id" :value="item.id">{{ item.title }}</option></select>
                <TextInput id="service_code" v-model="service.code" label="Code" :error="service.errors.code" />
                <TextInput id="service_name" v-model="service.name" label="Name" :error="service.errors.name" />
                <textarea v-model="service.description" class="rounded-md border-slate-300 md:col-span-2" rows="2" placeholder="Description"></textarea>
                <label class="grid gap-2 text-sm md:col-span-2">Facilities<select v-model="service.facility_ids" class="rounded-md border-slate-300" multiple><option v-for="facility in facilities" :key="facility.id" :value="facility.id">{{ facility.name }}</option></select></label>
                <label class="flex items-center gap-2 text-sm"><input v-model="service.is_tax_exempt" type="checkbox"> Tax exempt</label>
                <TextInput id="tax_bps" v-model="service.tax_rate_basis_points" label="Tax rate basis points" type="number" />
                <label class="flex items-center gap-2 text-sm"><input v-model="service.is_discount_eligible" type="checkbox"> Discount eligible</label>
            </div>
        </FormModal>

        <FormModal :show="activeModal === 'price'" title="Add Price" :form="price" submit-label="Add price" @close="closeModal" @submit="savePrice">
            <div class="grid gap-3">
                <select v-model="price.billable_service_id" class="rounded-md border-slate-300"><option value="">Service</option><option v-for="item in services" :key="item.id" :value="item.id">{{ item.code }} - {{ item.name }}</option></select>
                <select v-model="price.facility_id" class="rounded-md border-slate-300"><option value="">Default price</option><option v-for="facility in facilities" :key="facility.id" :value="facility.id">{{ facility.name }}</option></select>
                <TextInput id="price_currency" v-model="price.currency" label="Currency" maxlength="3" />
                <TextInput id="price_amount" v-model="price.amount_minor" label="Amount minor units" type="number" />
                <TextInput id="price_from" v-model="price.effective_from" label="Effective from" type="date" />
                <TextInput id="price_to" v-model="price.effective_to" label="Effective to" type="date" />
                <textarea v-model="price.reason" class="rounded-md border-slate-300" rows="2" placeholder="Reason"></textarea>
            </div>
        </FormModal>
    </AppLayout>
</template>
