<script setup>
import ActionToolbar from '@/Components/Admin/ActionToolbar.vue';
import FormModal from '@/Components/Admin/FormModal.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ donor: Object });

const showDecision = ref(false);
const decision = useForm({ eligibility_status: 'eligible', decision_reason: '', deferred_until: '', responses: {} });

function submitDecision() {
    decision.post(`/admin/blood-bank/donors/${props.donor.id}/screening-decisions`, {
        preserveScroll: true,
        onSuccess: () => {
            showDecision.value = false;
            decision.reset();
        },
    });
}
</script>

<template>
    <AppLayout title="Blood Donor">
        <PageHeader :title="donor.full_name" :description="`${donor.donor_number} - ${donor.status}`">
            <template #actions>
                <ActionToolbar align="end">
                    <Link href="/admin/blood-bank" class="rounded-md border px-3 py-2 text-sm font-bold" style="border-color: var(--admin-border);">Back</Link>
                    <PrimaryButton type="button" @click="showDecision = true">Screening Decision</PrimaryButton>
                </ActionToolbar>
            </template>
        </PageHeader>

        <div class="space-y-5 overflow-x-hidden">
            <section class="grid gap-3 md:grid-cols-3">
                <div class="rounded-md border p-4" style="border-color: var(--admin-border); background: var(--admin-surface);">
                    <p class="text-xs font-black uppercase" style="color: var(--admin-text-muted);">Category</p>
                    <p class="mt-1 font-bold">{{ donor.category?.name || 'Uncategorised' }}</p>
                </div>
                <div class="rounded-md border p-4" style="border-color: var(--admin-border); background: var(--admin-surface);">
                    <p class="text-xs font-black uppercase" style="color: var(--admin-text-muted);">Phone</p>
                    <p class="mt-1 font-bold break-words">{{ donor.phone || 'Not recorded' }}</p>
                </div>
                <div class="rounded-md border p-4" style="border-color: var(--admin-border); background: var(--admin-surface);">
                    <p class="text-xs font-black uppercase" style="color: var(--admin-text-muted);">Email</p>
                    <p class="mt-1 font-bold break-words">{{ donor.email || 'Not recorded' }}</p>
                </div>
            </section>

            <section class="rounded-md border p-4 sm:p-5" style="border-color: var(--admin-border); background: var(--admin-surface);">
                <h2 class="font-black">Donation History</h2>
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead style="color: var(--admin-text-muted);">
                            <tr>
                                <th class="whitespace-nowrap py-2 pr-4">Donation</th>
                                <th class="whitespace-nowrap py-2 pr-4">Collection</th>
                                <th class="whitespace-nowrap py-2 pr-4">Status</th>
                                <th class="whitespace-nowrap py-2 pr-4 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="donation in donor.donations" :key="donation.id" class="border-t" style="border-color: var(--admin-border);">
                                <td class="py-3 pr-4 font-bold">{{ donation.donation_number }}</td>
                                <td class="py-3 pr-4">{{ donation.collection_number || 'Pending' }}</td>
                                <td class="py-3 pr-4">{{ donation.status }}</td>
                                <td class="py-3 pr-4 text-right">
                                    <Link :href="`/admin/blood-bank/donations/${donation.id}`" class="font-bold" style="color: var(--public-accent);">Open</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="donor.donations.length === 0" class="py-5 text-sm" style="color: var(--admin-text-muted);">No donations recorded.</p>
                </div>
            </section>
        </div>

        <FormModal :show="showDecision" title="Manual Screening Decision" description="Record the authorized donor screening outcome." size="lg" :form="decision" submit-label="Record decision" @close="showDecision = false" @submit="submitDecision">
            <div class="grid gap-3 md:grid-cols-2">
                <label class="grid gap-1 text-sm font-semibold">
                    Eligibility
                    <select v-model="decision.eligibility_status" class="rounded-md border p-2" style="border-color: var(--admin-border);">
                        <option value="eligible">Eligible</option>
                        <option value="deferred">Deferred</option>
                        <option value="ineligible">Ineligible</option>
                    </select>
                    <span v-if="decision.errors.eligibility_status" class="text-xs text-red-700">{{ decision.errors.eligibility_status }}</span>
                </label>
                <label class="grid gap-1 text-sm font-semibold">
                    Deferred until
                    <input v-model="decision.deferred_until" type="date" class="rounded-md border p-2" style="border-color: var(--admin-border);">
                    <span v-if="decision.errors.deferred_until" class="text-xs text-red-700">{{ decision.errors.deferred_until }}</span>
                </label>
                <label class="grid gap-1 text-sm font-semibold md:col-span-2">
                    Decision reason
                    <textarea v-model="decision.decision_reason" class="min-h-28 rounded-md border p-2" style="border-color: var(--admin-border);" required></textarea>
                    <span v-if="decision.errors.decision_reason" class="text-xs text-red-700">{{ decision.errors.decision_reason }}</span>
                </label>
            </div>
        </FormModal>
    </AppLayout>
</template>
