<script setup>
import ActionToolbar from '@/Components/Admin/ActionToolbar.vue';
import ConfirmDialog from '@/Components/Admin/ConfirmDialog.vue';
import FormModal from '@/Components/Admin/FormModal.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ donation: Object, locations: Array, storageUnits: Array, componentTypes: Array, screeningTests: Array, amendments: Array });

const activeModal = ref(null);
const targetComponent = ref(null);
const verifyTarget = ref(null);
const verifyType = ref(null);

const group = useForm({ abo_group: 'O', rh_factor: 'positive', notes: '' });
const screening = useForm({ blood_screening_test_id: props.screeningTests?.[0]?.id || '', result_value: '', release_cleared: false, notes: '' });
const component = useForm({ blood_component_type_id: props.componentTypes?.[0]?.id || '', blood_bank_location_id: props.locations?.[0]?.id || '', blood_storage_unit_id: '', volume_ml: '', expires_on: '', notes: '' });
const action = useForm({ action: 'release', reason: '', to_location_id: props.locations?.[0]?.id || '', to_storage_unit_id: '' });
const amendment = useForm({ reason: '', content: '' });
const blank = useForm({});

function closeModal() {
    activeModal.value = null;
    targetComponent.value = null;
}

function submitForm(form, url) {
    form.post(url, {
        preserveScroll: true,
        onSuccess: () => {
            closeModal();
            form.reset();
        },
    });
}

function openAction(item) {
    targetComponent.value = item;
    action.defaults({ action: 'release', reason: '', to_location_id: props.locations?.[0]?.id || '', to_storage_unit_id: '' });
    action.reset();
    activeModal.value = 'action';
}

function submitAction() {
    action.patch(`/admin/blood-bank/components/${targetComponent.value.id}`, {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    });
}

function openAmendment(item) {
    targetComponent.value = item;
    amendment.reset();
    activeModal.value = 'amendment';
}

function submitAmendment() {
    amendment.post(`/admin/blood-bank/components/${targetComponent.value.id}/amendments`, {
        preserveScroll: true,
        onSuccess: () => {
            closeModal();
            amendment.reset();
        },
    });
}

function confirmVerify(type, item) {
    verifyType.value = type;
    verifyTarget.value = item;
}

function submitVerify() {
    const url = verifyType.value === 'group'
        ? `/admin/blood-bank/group-results/${verifyTarget.value.id}/verify`
        : `/admin/blood-bank/screening-results/${verifyTarget.value.id}/verify`;

    blank.post(url, {
        preserveScroll: true,
        onSuccess: () => {
            verifyTarget.value = null;
            verifyType.value = null;
        },
    });
}
</script>

<template>
    <AppLayout title="Blood Donation">
        <PageHeader :title="donation.donation_number" :description="`${donation.collection_number || 'No collection number'} - ${donation.status}`">
            <template #actions>
                <ActionToolbar align="end">
                    <Link href="/admin/blood-bank" class="rounded-md border px-3 py-2 text-sm font-bold" style="border-color: var(--admin-border);">Back</Link>
                    <PrimaryButton type="button" @click="activeModal = 'group'">Blood Group</PrimaryButton>
                    <PrimaryButton type="button" @click="activeModal = 'screening'">Screening Result</PrimaryButton>
                    <PrimaryButton type="button" @click="activeModal = 'component'">Prepare Component</PrimaryButton>
                </ActionToolbar>
            </template>
        </PageHeader>

        <div class="space-y-5 overflow-x-hidden">
            <section class="grid gap-3 md:grid-cols-4">
                <div class="rounded-md border p-4" style="border-color: var(--admin-border); background: var(--admin-surface);">
                    <p class="text-xs font-black uppercase" style="color: var(--admin-text-muted);">Donor</p>
                    <p class="mt-1 font-bold">{{ donation.donor?.full_name }}</p>
                </div>
                <div class="rounded-md border p-4" style="border-color: var(--admin-border); background: var(--admin-surface);">
                    <p class="text-xs font-black uppercase" style="color: var(--admin-text-muted);">Bag</p>
                    <p class="mt-1 font-bold">{{ donation.bag_type || 'Not recorded' }}</p>
                </div>
                <div class="rounded-md border p-4" style="border-color: var(--admin-border); background: var(--admin-surface);">
                    <p class="text-xs font-black uppercase" style="color: var(--admin-text-muted);">Volume</p>
                    <p class="mt-1 font-bold">{{ donation.volume_ml || 0 }} ml</p>
                </div>
                <div class="rounded-md border p-4" style="border-color: var(--admin-border); background: var(--admin-surface);">
                    <p class="text-xs font-black uppercase" style="color: var(--admin-text-muted);">Group</p>
                    <p class="mt-1 font-bold">{{ donation.group_result ? `${donation.group_result.abo_group} ${donation.group_result.rh_factor}` : 'Pending' }}</p>
                </div>
            </section>

            <section class="rounded-md border p-4 sm:p-5" style="border-color: var(--admin-border); background: var(--admin-surface);">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="font-black">Components</h2>
                </div>
                <div class="mt-3 grid gap-3">
                    <article v-for="item in donation.components" :key="item.id" class="rounded-md border p-4" style="border-color: var(--admin-border);">
                        <div class="flex min-w-0 flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <p class="font-black break-words">{{ item.component_number }} - {{ item.type?.name }} - {{ item.state }}</p>
                                <p class="mt-1 text-sm" style="color: var(--admin-text-muted);">{{ item.abo_group || 'Group pending' }} {{ item.rh_factor || '' }} - expires {{ item.expires_on || 'not set' }}</p>
                            </div>
                            <ActionToolbar align="end">
                                <button class="rounded-md border px-3 py-2 text-sm font-bold" style="border-color: var(--admin-border);" type="button" @click="openAction(item)">Action</button>
                                <button class="rounded-md border px-3 py-2 text-sm font-bold" style="border-color: var(--admin-border);" type="button" @click="openAmendment(item)">Amend</button>
                            </ActionToolbar>
                        </div>
                    </article>
                    <p v-if="donation.components.length === 0" class="text-sm" style="color: var(--admin-text-muted);">No components prepared.</p>
                </div>
            </section>

            <section class="grid gap-5 xl:grid-cols-2">
                <div class="rounded-md border p-4 sm:p-5" style="border-color: var(--admin-border); background: var(--admin-surface);">
                    <h2 class="font-black">Blood Group Result</h2>
                    <div v-if="donation.group_result" class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-md border p-3 text-sm" style="border-color: var(--admin-border);">
                        <p><strong>{{ donation.group_result.abo_group }} {{ donation.group_result.rh_factor }}</strong> - {{ donation.group_result.status }}</p>
                        <button v-if="donation.group_result.status === 'draft'" class="rounded-md border px-3 py-2 font-bold" style="border-color: var(--admin-border);" type="button" @click="confirmVerify('group', donation.group_result)">Verify</button>
                    </div>
                    <p v-else class="mt-3 text-sm" style="color: var(--admin-text-muted);">No group result recorded.</p>
                </div>

                <div class="rounded-md border p-4 sm:p-5" style="border-color: var(--admin-border); background: var(--admin-surface);">
                    <h2 class="font-black">Screening Results</h2>
                    <div v-for="result in donation.screening_results" :key="result.id" class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-md border p-3 text-sm" style="border-color: var(--admin-border);">
                        <span>{{ result.test?.name }} - {{ result.status }} - cleared: {{ result.release_cleared ? 'yes' : 'no' }}</span>
                        <button v-if="result.status === 'draft'" class="rounded-md border px-3 py-2 font-bold" style="border-color: var(--admin-border);" type="button" @click="confirmVerify('screening', result)">Verify</button>
                    </div>
                    <p v-if="donation.screening_results.length === 0" class="mt-3 text-sm" style="color: var(--admin-text-muted);">No screening results recorded.</p>
                </div>
            </section>
        </div>

        <FormModal :show="activeModal === 'group'" title="Blood Group" size="lg" :form="group" submit-label="Enter result" @close="closeModal" @submit="submitForm(group, `/admin/blood-bank/donations/${donation.id}/group-results`)">
            <div class="grid gap-3 md:grid-cols-2">
                <select v-model="group.abo_group" class="rounded-md border p-2" style="border-color: var(--admin-border);"><option>A</option><option>B</option><option>AB</option><option>O</option></select>
                <select v-model="group.rh_factor" class="rounded-md border p-2" style="border-color: var(--admin-border);"><option value="positive">Rh positive</option><option value="negative">Rh negative</option></select>
                <textarea v-model="group.notes" class="min-h-24 rounded-md border p-2 md:col-span-2" style="border-color: var(--admin-border);" placeholder="Notes"></textarea>
            </div>
        </FormModal>

        <FormModal :show="activeModal === 'screening'" title="Screening Result" size="lg" :form="screening" submit-label="Enter result" @close="closeModal" @submit="submitForm(screening, `/admin/blood-bank/donations/${donation.id}/screening-results`)">
            <div class="grid gap-3 md:grid-cols-2">
                <select v-model="screening.blood_screening_test_id" class="rounded-md border p-2" style="border-color: var(--admin-border);"><option v-for="test in screeningTests" :key="test.id" :value="test.id">{{ test.name }}</option></select>
                <input v-model="screening.result_value" class="rounded-md border p-2" style="border-color: var(--admin-border);" placeholder="Result value">
                <label class="text-sm font-semibold"><input v-model="screening.release_cleared" type="checkbox"> Manually cleared for release</label>
                <textarea v-model="screening.notes" class="min-h-24 rounded-md border p-2 md:col-span-2" style="border-color: var(--admin-border);" placeholder="Notes"></textarea>
            </div>
        </FormModal>

        <FormModal :show="activeModal === 'component'" title="Prepare Component" size="xl" :form="component" submit-label="Prepare" @close="closeModal" @submit="submitForm(component, `/admin/blood-bank/donations/${donation.id}/components`)">
            <div class="grid gap-3 md:grid-cols-2">
                <select v-model="component.blood_component_type_id" class="rounded-md border p-2" style="border-color: var(--admin-border);"><option v-for="type in componentTypes" :key="type.id" :value="type.id">{{ type.name }}</option></select>
                <select v-model="component.blood_bank_location_id" class="rounded-md border p-2" style="border-color: var(--admin-border);"><option v-for="location in locations" :key="location.id" :value="location.id">{{ location.name }}</option></select>
                <select v-model="component.blood_storage_unit_id" class="rounded-md border p-2" style="border-color: var(--admin-border);"><option value="">No storage unit</option><option v-for="unit in storageUnits" :key="unit.id" :value="unit.id">{{ unit.name }}</option></select>
                <input v-model="component.volume_ml" type="number" class="rounded-md border p-2" style="border-color: var(--admin-border);" placeholder="Volume ml">
                <input v-model="component.expires_on" type="date" class="rounded-md border p-2" style="border-color: var(--admin-border);">
                <textarea v-model="component.notes" class="min-h-24 rounded-md border p-2 md:col-span-2" style="border-color: var(--admin-border);" placeholder="Notes"></textarea>
            </div>
        </FormModal>

        <FormModal :show="activeModal === 'action'" :title="targetComponent ? `Component Action - ${targetComponent.component_number}` : 'Component Action'" size="lg" :form="action" submit-label="Apply" @close="closeModal" @submit="submitAction">
            <div class="grid gap-3 md:grid-cols-2">
                <select v-model="action.action" class="rounded-md border p-2" style="border-color: var(--admin-border);"><option value="release">Release</option><option value="transfer">Transfer</option><option value="recall">Recall</option><option value="discard">Discard</option></select>
                <select v-model="action.to_location_id" class="rounded-md border p-2" style="border-color: var(--admin-border);"><option v-for="location in locations" :key="location.id" :value="location.id">{{ location.name }}</option></select>
                <select v-model="action.to_storage_unit_id" class="rounded-md border p-2" style="border-color: var(--admin-border);"><option value="">Storage</option><option v-for="unit in storageUnits" :key="unit.id" :value="unit.id">{{ unit.name }}</option></select>
                <input v-model="action.reason" class="rounded-md border p-2" style="border-color: var(--admin-border);" placeholder="Reason" required>
            </div>
        </FormModal>

        <FormModal :show="activeModal === 'amendment'" :title="targetComponent ? `Amend ${targetComponent.component_number}` : 'Amend Component'" size="lg" :form="amendment" submit-label="Add amendment" @close="closeModal" @submit="submitAmendment">
            <div class="grid gap-3">
                <input v-model="amendment.reason" class="rounded-md border p-2" style="border-color: var(--admin-border);" placeholder="Correction reason">
                <textarea v-model="amendment.content" class="min-h-28 rounded-md border p-2" style="border-color: var(--admin-border);" placeholder="Correction note"></textarea>
            </div>
        </FormModal>

        <ConfirmDialog :show="Boolean(verifyTarget)" title="Verify Result" message="Confirm that the manually entered result has been independently reviewed." :form="blank" confirm-label="Verify" @close="verifyTarget = null" @confirm="submitVerify" />
    </AppLayout>
</template>
