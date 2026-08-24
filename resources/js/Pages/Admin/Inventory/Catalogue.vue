<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    items: { type: Array, default: () => [] },
    units: { type: Array, default: () => [] },
    locations: { type: Array, default: () => [] },
    facilities: { type: Array, default: () => [] },
    billableServices: { type: Array, default: () => [] },
});

const location = useForm({ facility_id: props.facilities[0]?.id || '', code: '', name: '', type: 'main_store' });
const unit = useForm({ code: '', name: '', base_unit_id: '', base_factor: 1 });
const item = useForm({ base_unit_id: '', billable_service_id: '', sku: '', barcode: '', type: 'medicine', generic_name: '', brand_name: '', name: '', dosage_form: '', strength: '', route: '', description: '', reorder_level: 0 });
</script>

<template>
    <Head title="Inventory Catalogue" />
    <AppLayout title="Inventory Catalogue">
        <div class="mb-4 flex flex-wrap gap-2">
            <Link href="/admin/inventory/stock" class="rounded-md border px-3 py-2 text-sm font-bold">Stock</Link>
            <Link href="/admin/inventory/transfers" class="rounded-md border px-3 py-2 text-sm font-bold">Transfers</Link>
            <Link href="/admin/inventory/reports" class="rounded-md border px-3 py-2 text-sm font-bold">Reports</Link>
        </div>
        <div class="grid gap-6 xl:grid-cols-[1fr_380px]">
            <section class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-lg font-black">Items</h2>
                <form class="mt-4 grid gap-3 md:grid-cols-3" @submit.prevent="item.post('/admin/inventory/items', { preserveScroll: true, onSuccess: () => item.reset() })">
                    <select v-model="item.base_unit_id" class="rounded-md border-slate-300"><option value="">Base unit</option><option v-for="u in units" :key="u.id" :value="u.id">{{ u.code }} - {{ u.name }}</option></select>
                    <select v-model="item.billable_service_id" class="rounded-md border-slate-300"><option value="">Billable service</option><option v-for="service in billableServices" :key="service.id" :value="service.id">{{ service.code }} - {{ service.name }}</option></select>
                    <TextInput id="item_sku" v-model="item.sku" label="SKU" />
                    <TextInput id="item_barcode" v-model="item.barcode" label="Barcode" />
                    <select v-model="item.type" class="rounded-md border-slate-300"><option value="medicine">Medicine</option><option value="supply">Supply</option><option value="equipment">Equipment</option><option value="other">Other</option></select>
                    <TextInput id="item_name" v-model="item.name" label="Name" />
                    <TextInput id="generic_name" v-model="item.generic_name" label="Generic name" />
                    <TextInput id="brand_name" v-model="item.brand_name" label="Brand/product" />
                    <TextInput id="dosage_form" v-model="item.dosage_form" label="Dosage form" />
                    <TextInput id="strength" v-model="item.strength" label="Strength" />
                    <TextInput id="route" v-model="item.route" label="Route" />
                    <TextInput id="reorder_level" v-model="item.reorder_level" label="Reorder level" type="number" />
                    <textarea v-model="item.description" class="rounded-md border-slate-300 md:col-span-2" rows="2" placeholder="Pharmacist/storekeeper configuration notes"></textarea>
                    <PrimaryButton :disabled="item.processing">Create item</PrimaryButton>
                </form>
                <div class="mt-5 divide-y divide-slate-100 dark:divide-slate-800">
                    <article v-for="entry in items" :key="entry.id" class="py-3">
                        <p class="font-bold">{{ entry.sku }} - {{ entry.name }}</p>
                        <p class="text-sm text-slate-500">{{ entry.type }} - {{ entry.base_unit?.code }} - reorder {{ entry.reorder_level }}</p>
                    </article>
                </div>
            </section>
            <aside class="space-y-6">
                <form class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="location.post('/admin/inventory/locations', { preserveScroll: true, onSuccess: () => location.reset() })">
                    <h2 class="font-black">Location</h2>
                    <div class="mt-4 grid gap-3">
                        <select v-model="location.facility_id" class="rounded-md border-slate-300"><option value="">Facility</option><option v-for="facility in facilities" :key="facility.id" :value="facility.id">{{ facility.name }}</option></select>
                        <select v-model="location.type" class="rounded-md border-slate-300"><option value="main_store">Main store</option><option value="pharmacy">Pharmacy</option><option value="ward_store">Ward store</option><option value="other">Other</option></select>
                        <TextInput id="location_code" v-model="location.code" label="Code" />
                        <TextInput id="location_name" v-model="location.name" label="Name" />
                        <PrimaryButton :disabled="location.processing">Create location</PrimaryButton>
                    </div>
                </form>
                <form class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="unit.post('/admin/inventory/units', { preserveScroll: true, onSuccess: () => unit.reset() })">
                    <h2 class="font-black">Unit Conversion</h2>
                    <div class="mt-4 grid gap-3">
                        <TextInput id="unit_code" v-model="unit.code" label="Code" />
                        <TextInput id="unit_name" v-model="unit.name" label="Name" />
                        <select v-model="unit.base_unit_id" class="rounded-md border-slate-300"><option value="">Self/base unit</option><option v-for="u in units" :key="u.id" :value="u.id">{{ u.code }}</option></select>
                        <TextInput id="base_factor" v-model="unit.base_factor" label="Base factor" type="number" />
                        <PrimaryButton :disabled="unit.processing">Create unit</PrimaryButton>
                    </div>
                </form>
            </aside>
        </div>
    </AppLayout>
</template>
