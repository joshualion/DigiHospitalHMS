<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import PrimaryButton from '../../../Components/PrimaryButton.vue';
import TextInput from '../../../Components/TextInput.vue';

const props = defineProps({
    hospital: { type: Object, required: true },
});

const form = useForm({
    ...props.hospital,
    phone_numbers: props.hospital.phone_numbers || [],
});
</script>

<template>
    <Head title="Hospital Profile" />
    <AppLayout title="Hospital Profile">
        <form class="grid gap-5 rounded-lg border border-slate-200 bg-white p-6 md:grid-cols-2 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="form.patch('/admin/hospital')">
            <TextInput id="legal_name" v-model="form.legal_name" label="Legal name" :error="form.errors.legal_name" />
            <TextInput id="display_name" v-model="form.display_name" label="Display name" :error="form.errors.display_name" />
            <TextInput id="registration_reference" v-model="form.registration_reference" label="Registration or licence reference" :error="form.errors.registration_reference" />
            <TextInput id="email" v-model="form.email" label="Email" type="email" :error="form.errors.email" />
            <TextInput id="website" v-model="form.website" label="Website" :error="form.errors.website" />
            <TextInput id="timezone" v-model="form.timezone" label="Timezone" :error="form.errors.timezone" />
            <TextInput id="city" v-model="form.city" label="City" :error="form.errors.city" />
            <TextInput id="state" v-model="form.state" label="State" :error="form.errors.state" />
            <TextInput id="country" v-model="form.country" label="Country" :error="form.errors.country" />
            <TextInput id="default_currency" v-model="form.default_currency" label="Currency" :error="form.errors.default_currency" />
            <TextInput id="primary_contact_name" v-model="form.primary_contact_name" label="Primary contact name" :error="form.errors.primary_contact_name" />
            <TextInput id="primary_contact_email" v-model="form.primary_contact_email" label="Primary contact email" type="email" :error="form.errors.primary_contact_email" />
            <div class="md:col-span-2">
                <label for="address" class="block text-sm font-medium">Address</label>
                <textarea id="address" v-model="form.address" class="mt-1 block w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900" rows="3" />
            </div>
            <div class="md:col-span-2 flex justify-end">
                <PrimaryButton :disabled="form.processing">Save hospital profile</PrimaryButton>
            </div>
        </form>
    </AppLayout>
</template>
