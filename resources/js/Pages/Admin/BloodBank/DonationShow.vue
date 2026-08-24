<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({ donation: Object, locations: Array, storageUnits: Array, componentTypes: Array, screeningTests: Array, amendments: Array });
const group = useForm({ abo_group: 'O', rh_factor: 'positive', notes: '' });
const screening = useForm({ blood_screening_test_id: props.screeningTests?.[0]?.id || '', result_value: '', release_cleared: false, notes: '' });
const component = useForm({ blood_component_type_id: props.componentTypes?.[0]?.id || '', blood_bank_location_id: props.locations?.[0]?.id || '', blood_storage_unit_id: '', volume_ml: '', expires_on: '', notes: '' });
const action = useForm({ action: 'release', reason: '', to_location_id: props.locations?.[0]?.id || '', to_storage_unit_id: '' });
const amendment = useForm({ reason: '', content: '' });
const blank = useForm({});

function postBlank(url) {
    blank.post(url, { preserveScroll: true });
}
</script>

<template>
    <AppLayout title="Blood Donation">
        <div class="space-y-6">
            <section class="rounded-md border p-5" style="border-color: var(--admin-border);">
                <p class="text-sm font-bold">{{ donation.collection_number }} · {{ donation.status }}</p>
                <h2 class="text-2xl font-black">{{ donation.donation_number }}</h2>
                <p>{{ donation.donor?.full_name }} · {{ donation.bag_type }} · {{ donation.volume_ml }}ml</p>
            </section>
            <section class="grid gap-4 lg:grid-cols-3">
                <form class="space-y-3 rounded-md border p-4" style="border-color: var(--admin-border);" @submit.prevent="group.post(`/admin/blood-bank/donations/${donation.id}/group-results`, { preserveScroll: true })">
                    <h3 class="font-black">Blood Group</h3>
                    <select v-model="group.abo_group" class="w-full rounded-md border p-2"><option>A</option><option>B</option><option>AB</option><option>O</option></select>
                    <select v-model="group.rh_factor" class="w-full rounded-md border p-2"><option value="positive">Rh positive</option><option value="negative">Rh negative</option></select>
                    <textarea v-model="group.notes" class="w-full rounded-md border p-2" placeholder="Notes"></textarea>
                    <button class="rounded-md border px-3 py-2 font-bold">Enter</button>
                    <button v-if="donation.group_result?.status === 'draft'" type="button" class="ml-2 rounded-md border px-3 py-2 font-bold" @click="postBlank(`/admin/blood-bank/group-results/${donation.group_result.id}/verify`)">Verify</button>
                </form>
                <form class="space-y-3 rounded-md border p-4" style="border-color: var(--admin-border);" @submit.prevent="screening.post(`/admin/blood-bank/donations/${donation.id}/screening-results`, { preserveScroll: true })">
                    <h3 class="font-black">Screening Result</h3>
                    <select v-model="screening.blood_screening_test_id" class="w-full rounded-md border p-2"><option v-for="test in screeningTests" :key="test.id" :value="test.id">{{ test.name }}</option></select>
                    <input v-model="screening.result_value" class="w-full rounded-md border p-2" placeholder="Result value">
                    <label class="text-sm"><input v-model="screening.release_cleared" type="checkbox"> Manually cleared for release</label>
                    <button class="rounded-md border px-3 py-2 font-bold">Enter</button>
                </form>
                <form class="space-y-3 rounded-md border p-4" style="border-color: var(--admin-border);" @submit.prevent="component.post(`/admin/blood-bank/donations/${donation.id}/components`, { preserveScroll: true })">
                    <h3 class="font-black">Prepare Component</h3>
                    <select v-model="component.blood_component_type_id" class="w-full rounded-md border p-2"><option v-for="type in componentTypes" :key="type.id" :value="type.id">{{ type.name }}</option></select>
                    <select v-model="component.blood_bank_location_id" class="w-full rounded-md border p-2"><option v-for="location in locations" :key="location.id" :value="location.id">{{ location.name }}</option></select>
                    <select v-model="component.blood_storage_unit_id" class="w-full rounded-md border p-2"><option value="">No storage unit</option><option v-for="unit in storageUnits" :key="unit.id" :value="unit.id">{{ unit.name }}</option></select>
                    <input v-model="component.volume_ml" type="number" class="w-full rounded-md border p-2" placeholder="Volume ml">
                    <input v-model="component.expires_on" type="date" class="w-full rounded-md border p-2">
                    <button class="rounded-md border px-3 py-2 font-bold">Prepare</button>
                </form>
            </section>
            <section class="rounded-md border p-5" style="border-color: var(--admin-border);">
                <h3 class="font-black">Components</h3>
                <article v-for="item in donation.components" :key="item.id" class="mt-4 rounded-md border p-4" style="border-color: var(--admin-border);">
                    <p class="font-black">{{ item.component_number }} · {{ item.type?.name }} · {{ item.state }}</p>
                    <p class="text-sm">{{ item.abo_group || 'Group pending' }} {{ item.rh_factor || '' }} · expires {{ item.expires_on || 'not set' }}</p>
                    <form class="mt-3 grid gap-2 md:grid-cols-5" @submit.prevent="action.patch(`/admin/blood-bank/components/${item.id}`, { preserveScroll: true })">
                        <select v-model="action.action" class="rounded-md border p-2"><option value="release">Release</option><option value="transfer">Transfer</option><option value="recall">Recall</option><option value="discard">Discard</option></select>
                        <select v-model="action.to_location_id" class="rounded-md border p-2"><option v-for="location in locations" :key="location.id" :value="location.id">{{ location.name }}</option></select>
                        <select v-model="action.to_storage_unit_id" class="rounded-md border p-2"><option value="">Storage</option><option v-for="unit in storageUnits" :key="unit.id" :value="unit.id">{{ unit.name }}</option></select>
                        <input v-model="action.reason" class="rounded-md border p-2" placeholder="Reason" required>
                        <button class="rounded-md border px-3 py-2 font-bold">Apply</button>
                    </form>
                    <form class="mt-3 grid gap-2 md:grid-cols-3" @submit.prevent="amendment.post(`/admin/blood-bank/components/${item.id}/amendments`, { preserveScroll: true, onSuccess: () => amendment.reset() })">
                        <input v-model="amendment.reason" class="rounded-md border p-2" placeholder="Correction reason">
                        <input v-model="amendment.content" class="rounded-md border p-2" placeholder="Correction note">
                        <button class="rounded-md border px-3 py-2 font-bold">Add amendment</button>
                    </form>
                </article>
            </section>
            <section class="rounded-md border p-5" style="border-color: var(--admin-border);">
                <h3 class="font-black">Screening Results</h3>
                <div v-for="result in donation.screening_results" :key="result.id" class="mt-3 flex items-center justify-between border-t pt-3" style="border-color: var(--admin-border);">
                    <span>{{ result.test?.name }} · {{ result.status }} · cleared: {{ result.release_cleared ? 'yes' : 'no' }}</span>
                    <button v-if="result.status === 'draft'" class="rounded-md border px-3 py-2 font-bold" @click="postBlank(`/admin/blood-bank/screening-results/${result.id}/verify`)">Verify</button>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

