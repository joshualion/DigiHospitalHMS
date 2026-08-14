<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import TextInput from '../../Components/TextInput.vue';
import PublicLayout from '../../Layouts/PublicLayout.vue';

const form = useForm({ password: '' });

function submit() {
    form.post('/confirm-password', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Confirm Password" />
    <PublicLayout>
        <section class="mx-auto max-w-md px-4 py-16">
            <h1 class="text-2xl font-bold">Confirm password</h1>
            <form class="mt-6 space-y-4" @submit.prevent="submit">
                <TextInput id="password" v-model="form.password" label="Password" type="password" autocomplete="current-password" :error="form.errors.password" />
                <PrimaryButton :disabled="form.processing">Confirm</PrimaryButton>
            </form>
        </section>
    </PublicLayout>
</template>
