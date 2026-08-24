<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    donors: Object,
    donations: Array,
    components: Array,
    reports: Object,
    facilities: Array,
    locations: Array,
    categories: Array,
    componentTypes: Array,
    screeningTests: Array,
    labTests: Array,
});

const donorForm = useForm({ blood_donor_category_id: '', first_name: '', last_name: '', phone: '', email: '', identifier_type: '', identifier_value: '', consented_at: '', consent_reference: '' });
const locationForm = useForm({ facility_id: props.facilities?.[0]?.id || '', code: '', name: '', type: 'blood_bank', notes: '' });
const storageForm = useForm({ blood_bank_location_id: props.locations?.[0]?.id || '', code: '', name: '', storage_type: 'refrigerator', notes: '' });
const categoryForm = useForm({ code: '', name: '', description: '' });
const componentTypeForm = useForm({ code: '', name: '', default_shelf_life_days: '', notes: '' });
const screeningTestForm = useForm({ lab_test_id: '', code: '', name: '', is_required_for_release: true, notes: '' });
const appointmentForm = useForm({ facility_id: props.facilities?.[0]?.id || '', blood_donor_id: '', blood_bank_location_id: props.locations?.[0]?.id || '', scheduled_at: '', notes: '' });
const collectionForm = useForm({ facility_id: props.facilities?.[0]?.id || '', blood_donor_id: '', blood_donation_appointment_id: '', blood_bank_location_id: props.locations?.[0]?.id || '', collected_at: '', bag_type: '', volume_ml: '', notes: '' });

function submit(form, url) {
    form.post(url, { preserveScroll: true, onSuccess: () => form.reset() });
}
</script>

<template>
    <AppLayout title="Blood Bank">
        <div class="space-y-6">
            <section class="grid gap-3 md:grid-cols-4">
                <div v-for="(value, key) in reports" :key="key" class="rounded-md border p-4" style="border-color: var(--admin-border); background: var(--admin-surface);">
                    <p class="text-xs font-black uppercase" style="color: var(--admin-text-muted);">{{ key.replace('_', ' ') }}</p>
                    <p class="mt-2 text-2xl font-black">{{ Array.isArray(value) ? value.length : value }}</p>
                </div>
            </section>

            <section class="grid gap-4 lg:grid-cols-3">
                <form class="space-y-3 rounded-md border p-4" style="border-color: var(--admin-border);" @submit.prevent="submit(donorForm, '/admin/blood-bank/donors')">
                    <h2 class="font-black">Register Donor</h2>
                    <select v-model="donorForm.blood_donor_category_id" class="w-full rounded-md border p-2"><option value="">Category</option><option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option></select>
                    <input v-model="donorForm.first_name" class="w-full rounded-md border p-2" placeholder="First name" required>
                    <input v-model="donorForm.last_name" class="w-full rounded-md border p-2" placeholder="Last name" required>
                    <input v-model="donorForm.phone" class="w-full rounded-md border p-2" placeholder="Phone">
                    <input v-model="donorForm.email" class="w-full rounded-md border p-2" placeholder="Email">
                    <input v-model="donorForm.identifier_type" class="w-full rounded-md border p-2" placeholder="Identifier type">
                    <input v-model="donorForm.identifier_value" class="w-full rounded-md border p-2" placeholder="Identifier value">
                    <input v-model="donorForm.consented_at" type="datetime-local" class="w-full rounded-md border p-2">
                    <input v-model="donorForm.consent_reference" class="w-full rounded-md border p-2" placeholder="Consent reference">
                    <button class="rounded-md px-4 py-2 font-bold" style="background: var(--public-accent); color: var(--public-accent-foreground);">Save donor</button>
                </form>

                <form class="space-y-3 rounded-md border p-4" style="border-color: var(--admin-border);" @submit.prevent="submit(collectionForm, '/admin/blood-bank/collections')">
                    <h2 class="font-black">Record Collection</h2>
                    <select v-model="collectionForm.blood_donor_id" class="w-full rounded-md border p-2" required><option value="">Donor</option><option v-for="donor in donors.data" :key="donor.id" :value="donor.id">{{ donor.donor_number }} - {{ donor.full_name }}</option></select>
                    <select v-model="collectionForm.facility_id" class="w-full rounded-md border p-2" required><option v-for="facility in facilities" :key="facility.id" :value="facility.id">{{ facility.name }}</option></select>
                    <select v-model="collectionForm.blood_bank_location_id" class="w-full rounded-md border p-2" required><option v-for="location in locations" :key="location.id" :value="location.id">{{ location.name }}</option></select>
                    <input v-model="collectionForm.collected_at" type="datetime-local" class="w-full rounded-md border p-2">
                    <input v-model="collectionForm.bag_type" class="w-full rounded-md border p-2" placeholder="Bag type" required>
                    <input v-model="collectionForm.volume_ml" type="number" class="w-full rounded-md border p-2" placeholder="Volume ml">
                    <button class="rounded-md px-4 py-2 font-bold" style="background: var(--public-accent); color: var(--public-accent-foreground);">Record collection</button>
                </form>

                <div class="space-y-3 rounded-md border p-4" style="border-color: var(--admin-border);">
                    <h2 class="font-black">Configuration</h2>
                    <form class="grid gap-2" @submit.prevent="submit(locationForm, '/admin/blood-bank/locations')">
                        <select v-model="locationForm.facility_id" class="rounded-md border p-2"><option v-for="facility in facilities" :key="facility.id" :value="facility.id">{{ facility.name }}</option></select>
                        <input v-model="locationForm.code" class="rounded-md border p-2" placeholder="Location code"><input v-model="locationForm.name" class="rounded-md border p-2" placeholder="Location name"><button class="rounded-md border px-3 py-2 font-bold">Add location</button>
                    </form>
                    <form class="grid gap-2" @submit.prevent="submit(storageForm, '/admin/blood-bank/storage-units')">
                        <select v-model="storageForm.blood_bank_location_id" class="rounded-md border p-2"><option v-for="location in locations" :key="location.id" :value="location.id">{{ location.name }}</option></select>
                        <input v-model="storageForm.code" class="rounded-md border p-2" placeholder="Storage code"><input v-model="storageForm.name" class="rounded-md border p-2" placeholder="Storage name"><button class="rounded-md border px-3 py-2 font-bold">Add storage</button>
                    </form>
                    <form class="grid gap-2" @submit.prevent="submit(categoryForm, '/admin/blood-bank/categories')">
                        <input v-model="categoryForm.code" class="rounded-md border p-2" placeholder="Category code"><input v-model="categoryForm.name" class="rounded-md border p-2" placeholder="Category name"><button class="rounded-md border px-3 py-2 font-bold">Add category</button>
                    </form>
                    <form class="grid gap-2" @submit.prevent="submit(componentTypeForm, '/admin/blood-bank/component-types')">
                        <input v-model="componentTypeForm.code" class="rounded-md border p-2" placeholder="Component code"><input v-model="componentTypeForm.name" class="rounded-md border p-2" placeholder="Component name"><button class="rounded-md border px-3 py-2 font-bold">Add component type</button>
                    </form>
                    <form class="grid gap-2" @submit.prevent="submit(screeningTestForm, '/admin/blood-bank/screening-tests')">
                        <input v-model="screeningTestForm.code" class="rounded-md border p-2" placeholder="Screening code"><input v-model="screeningTestForm.name" class="rounded-md border p-2" placeholder="Screening name"><label class="text-sm"><input v-model="screeningTestForm.is_required_for_release" type="checkbox"> Required for release</label><button class="rounded-md border px-3 py-2 font-bold">Add screening test</button>
                    </form>
                </div>
            </section>

            <section class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-md border p-4" style="border-color: var(--admin-border);">
                    <h2 class="font-black">Donors</h2>
                    <div v-for="donor in donors.data" :key="donor.id" class="mt-3 flex items-center justify-between border-t pt-3" style="border-color: var(--admin-border);">
                        <div><p class="font-bold">{{ donor.full_name }}</p><p class="text-sm">{{ donor.donor_number }} · {{ donor.status }}</p></div>
                        <Link class="font-bold" :href="`/admin/blood-bank/donors/${donor.id}`">Open</Link>
                    </div>
                </div>
                <div class="rounded-md border p-4" style="border-color: var(--admin-border);">
                    <h2 class="font-black">Recent Donations</h2>
                    <div v-for="donation in donations" :key="donation.id" class="mt-3 flex items-center justify-between border-t pt-3" style="border-color: var(--admin-border);">
                        <div><p class="font-bold">{{ donation.donation_number }}</p><p class="text-sm">{{ donation.donor?.full_name }} · {{ donation.status }}</p></div>
                        <Link class="font-bold" :href="`/admin/blood-bank/donations/${donation.id}`">Open</Link>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
