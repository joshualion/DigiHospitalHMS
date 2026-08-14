<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import PrimaryButton from '../../../Components/PrimaryButton.vue';
import TextInput from '../../../Components/TextInput.vue';

const props = defineProps({
    settings: { type: Object, required: true },
    facilities: { type: Array, default: () => [] },
});

const form = useForm({ ...props.settings });
</script>

<template>
    <Head title="Hospital Settings" />
    <AppLayout title="Hospital Settings">
        <form class="grid gap-5 rounded-lg border border-slate-200 bg-white p-6 md:grid-cols-2 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="form.patch('/admin/settings')">
            <select v-model="form.default_facility_id" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-900">
                <option :value="null">Default facility</option>
                <option v-for="facility in facilities" :key="facility.id" :value="facility.id">{{ facility.name }}</option>
            </select>
            <TextInput id="locale" v-model="form.locale" label="Locale" :error="form.errors.locale" />
            <TextInput id="timezone" v-model="form.timezone" label="Timezone" :error="form.errors.timezone" />
            <TextInput id="currency" v-model="form.currency" label="Currency" :error="form.errors.currency" />
            <TextInput id="date_format" v-model="form.date_format" label="Date format" :error="form.errors.date_format" />
            <TextInput id="time_format" v-model="form.time_format" label="Time format" :error="form.errors.time_format" />
            <div class="md:col-span-2 flex justify-end"><PrimaryButton :disabled="form.processing">Save settings</PrimaryButton></div>
        </form>
    </AppLayout>
</template>
