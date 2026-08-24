<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({ donor: Object });
const decision = useForm({ eligibility_status: 'eligible', decision_reason: '', deferred_until: '', responses: {} });
</script>

<template>
    <AppLayout title="Blood Donor">
        <div class="space-y-6">
            <section class="rounded-md border p-5" style="border-color: var(--admin-border);">
                <p class="text-sm font-bold" style="color: var(--admin-text-muted);">{{ donor.donor_number }} · {{ donor.status }}</p>
                <h2 class="text-2xl font-black">{{ donor.full_name }}</h2>
                <p class="mt-2 text-sm">{{ donor.category?.name }} · {{ donor.phone }} · {{ donor.email }}</p>
            </section>
            <form class="grid gap-3 rounded-md border p-5 md:grid-cols-2" style="border-color: var(--admin-border);" @submit.prevent="decision.post(`/admin/blood-bank/donors/${donor.id}/screening-decisions`, { preserveScroll: true, onSuccess: () => decision.reset() })">
                <h3 class="font-black md:col-span-2">Manual Screening Decision</h3>
                <select v-model="decision.eligibility_status" class="rounded-md border p-2"><option value="eligible">Eligible</option><option value="deferred">Deferred</option><option value="ineligible">Ineligible</option></select>
                <input v-model="decision.deferred_until" type="date" class="rounded-md border p-2">
                <textarea v-model="decision.decision_reason" class="rounded-md border p-2 md:col-span-2" placeholder="Decision reason" required></textarea>
                <button class="rounded-md px-4 py-2 font-bold md:w-fit" style="background: var(--public-accent); color: var(--public-accent-foreground);">Record decision</button>
            </form>
            <section class="rounded-md border p-5" style="border-color: var(--admin-border);">
                <h3 class="font-black">Donation History</h3>
                <div v-for="donation in donor.donations" :key="donation.id" class="mt-3 flex items-center justify-between border-t pt-3" style="border-color: var(--admin-border);">
                    <span>{{ donation.donation_number }} · {{ donation.status }}</span>
                    <Link :href="`/admin/blood-bank/donations/${donation.id}`" class="font-bold">Open</Link>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
