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
const service = useForm({ billable_service_category_id: '', department_id: '', public_site_item_id: '', code: '', name: '', description: '', is_tax_exempt: false, tax_rate_basis_points: 0, is_discount_eligible: true, is_active: true, facility_ids: [], public_is_visible: false, public_is_featured: false, public_slug: '', public_name: '', public_description: '', public_icon: '', public_image_path: '', public_display_order: 0 });
const price = useForm({ billable_service_id: '', facility_id: '', currency: 'NGN', amount_minor: '', effective_from: new Date().toISOString().slice(0, 10), effective_to: '', reason: '' });
const activeModal = ref(null);
const editingService = ref(null);

function closeModal() {
    activeModal.value = null;
    editingService.value = null;
}

function saveCategory() {
    category.post('/admin/billing/categories', { preserveScroll: true, onSuccess: () => { closeModal(); category.reset(); } });
}

function saveService() {
    const options = { preserveScroll: true, onSuccess: () => { closeModal(); editingService.value = null; service.reset(); } };
    editingService.value ? service.patch(`/admin/billing/services/${editingService.value.id}`, options) : service.post('/admin/billing/services', options);
}

function openEditService(item) {
    editingService.value = item;
    service.defaults({
        billable_service_category_id: item.billable_service_category_id || '',
        department_id: item.department_id || '',
        public_site_item_id: item.public_site_item_id || '',
        code: item.code || '',
        name: item.name || '',
        description: item.description || '',
        is_tax_exempt: Boolean(item.is_tax_exempt),
        tax_rate_basis_points: item.tax_rate_basis_points || 0,
        is_discount_eligible: Boolean(item.is_discount_eligible),
        is_active: Boolean(item.is_active),
        facility_ids: item.facilities?.map((facility) => facility.id) || [],
        public_is_visible: Boolean(item.public_is_visible),
        public_is_featured: Boolean(item.public_is_featured),
        public_slug: item.public_slug || '',
        public_name: item.public_name || '',
        public_description: item.public_description || '',
        public_icon: item.public_icon || '',
        public_image_path: item.public_image_path || '',
        public_display_order: item.public_display_order || 0,
    });
    service.reset();
    activeModal.value = 'service';
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
                        <p class="mt-1 break-words text-xs" :class="item.public_is_visible ? 'text-emerald-700' : 'text-slate-500'">Public website: {{ item.public_is_visible ? (item.public_is_featured ? 'Featured public service' : 'Public service') : 'Private' }}</p>
                    </div>
                    <div class="text-sm">
                        <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-bold text-slate-700">{{ item.is_active ? 'Active' : 'Inactive' }}</span>
                        <p class="mt-2 text-slate-500">Tax {{ item.is_tax_exempt ? 'exempt' : `${item.tax_rate_basis_points} bps` }}</p>
                        <p class="text-slate-500">{{ item.is_discount_eligible ? 'Discount eligible' : 'No discounts' }}</p>
                        <button class="mt-3 rounded-md border px-3 py-2 text-xs font-bold" type="button" @click="openEditService(item)">Edit</button>
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

        <FormModal :show="activeModal === 'service'" :title="editingService ? 'Edit Service' : 'Add Service'" :form="service" :submit-label="editingService ? 'Save changes' : 'Create service'" size="full" @close="closeModal" @submit="saveService">
            <div class="grid gap-3 md:grid-cols-2">
                <select v-model="service.billable_service_category_id" class="rounded-md border-slate-300"><option value="">Category</option><option v-for="item in categories" :key="item.id" :value="item.id">{{ item.name }}</option></select>
                <select v-model="service.department_id" class="rounded-md border-slate-300"><option value="">Department</option><option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option></select>
                <select v-model="service.public_site_item_id" class="rounded-md border-slate-300"><option value="">Public service mapping</option><option v-for="item in publicServiceItems" :key="item.id" :value="item.id">{{ item.title }}</option></select>
                <TextInput id="service_code" v-model="service.code" label="Code" :error="service.errors.code" />
                <TextInput id="service_name" v-model="service.name" label="Name" :error="service.errors.name" />
                <textarea v-model="service.description" class="rounded-md border-slate-300 md:col-span-2" rows="2" placeholder="Description"></textarea>
                <label class="grid gap-2 text-sm md:col-span-2">Facilities<select v-model="service.facility_ids" class="rounded-md border-slate-300" multiple><option v-for="facility in facilities" :key="facility.id" :value="facility.id">{{ facility.name }}</option></select></label>
                <label class="flex items-center gap-2 text-sm"><input v-model="service.is_active" type="checkbox"> Active</label>
                <label class="flex items-center gap-2 text-sm"><input v-model="service.is_tax_exempt" type="checkbox"> Tax exempt</label>
                <TextInput id="tax_bps" v-model="service.tax_rate_basis_points" label="Tax rate basis points" type="number" />
                <label class="flex items-center gap-2 text-sm"><input v-model="service.is_discount_eligible" type="checkbox"> Discount eligible</label>
                <div class="grid gap-3 rounded-md border border-slate-200 p-4 md:col-span-2 md:grid-cols-2">
                    <label class="flex items-center gap-2 text-sm font-semibold"><input v-model="service.public_is_visible" type="checkbox"> Show on public website</label>
                    <label class="flex items-center gap-2 text-sm font-semibold"><input v-model="service.public_is_featured" type="checkbox"> Featured on homepage</label>
                    <TextInput id="service_public_name" v-model="service.public_name" label="Public name" :error="service.errors.public_name" />
                    <TextInput id="service_public_slug" v-model="service.public_slug" label="Public slug" :error="service.errors.public_slug" />
                    <TextInput id="service_public_icon" v-model="service.public_icon" label="Public icon" :error="service.errors.public_icon" />
                    <TextInput id="service_public_order" v-model="service.public_display_order" label="Public display order" type="number" :error="service.errors.public_display_order" />
                    <TextInput id="service_public_image" v-model="service.public_image_path" label="Public image URL/path" :error="service.errors.public_image_path" />
                    <label class="grid gap-1 text-sm font-semibold md:col-span-2">Public description<textarea v-model="service.public_description" class="rounded-md border-slate-300" rows="3"></textarea><span v-if="service.errors.public_description" class="text-xs text-red-700">{{ service.errors.public_description }}</span></label>
                </div>
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
