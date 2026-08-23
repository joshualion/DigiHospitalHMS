<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import PublicLayout from '../../Layouts/PublicLayout.vue';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import TextInput from '../../Components/TextInput.vue';

defineProps({
    facilities: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
});

const form = useForm({ name: '', phone: '', email: '', preferred_facility_id: '', preferred_department_id: '', preferred_date: '', consent: false, website: '' });
</script>

<template>
    <Head title="Request an Appointment" />
    <PublicLayout>
        <section class="public-section px-4">
            <form class="public-card mx-auto max-w-2xl rounded-2xl p-6 sm:p-8" @submit.prevent="form.post('/appointment/request')">
                <p class="public-kicker">Appointment request</p>
                <h1 class="mt-2 text-3xl font-black">Request a callback</h1>
                <p class="mt-3 text-sm leading-6" style="color: var(--public-text-secondary);">This form asks only for contact and preference details. Do not enter symptoms, diagnoses, or medical history.</p>
                <input v-model="form.website" class="hidden" tabindex="-1" autocomplete="off">
                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <TextInput id="request_name" v-model="form.name" label="Name" :error="form.errors.name" />
                    <TextInput id="request_phone" v-model="form.phone" label="Phone" :error="form.errors.phone" />
                    <TextInput id="request_email" v-model="form.email" label="Email" type="email" :error="form.errors.email" />
                    <label class="grid gap-1 text-sm font-semibold" style="color: var(--public-text-secondary);">Preferred date<input v-model="form.preferred_date" class="rounded-md border" style="background: var(--public-input); border-color: var(--public-border); color: var(--public-text);" type="date"></label>
                    <label class="grid gap-1 text-sm font-semibold" style="color: var(--public-text-secondary);">Facility<select v-model="form.preferred_facility_id" class="rounded-md border" style="background: var(--public-input); border-color: var(--public-border); color: var(--public-text);"><option value="">Any facility</option><option v-for="facility in facilities" :key="facility.id" :value="facility.id">{{ facility.name }}</option></select></label>
                    <label class="grid gap-1 text-sm font-semibold" style="color: var(--public-text-secondary);">Department<select v-model="form.preferred_department_id" class="rounded-md border" style="background: var(--public-input); border-color: var(--public-border); color: var(--public-text);"><option value="">Any department</option><option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option></select></label>
                </div>
                <label class="mt-5 flex gap-3 text-sm font-semibold" style="color: var(--public-text-secondary);"><input v-model="form.consent" class="mt-1 rounded" type="checkbox"> I consent to being contacted about this appointment request.</label>
                <p v-if="form.errors.consent" class="mt-2 text-sm" style="color: var(--public-danger);">{{ form.errors.consent }}</p>
                <PrimaryButton class="mt-6" :disabled="form.processing">Submit request</PrimaryButton>
            </form>
        </section>
    </PublicLayout>
</template>
