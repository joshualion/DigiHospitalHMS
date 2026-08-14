<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import TextInput from '../../Components/TextInput.vue';
import PublicLayout from '../../Layouts/PublicLayout.vue';

defineProps({
    status: {
        type: String,
        default: '',
    },
});

const form = useForm({ email: '' });
</script>

<template>
    <Head title="Forgot Password" />
    <PublicLayout>
        <section class="mx-auto max-w-md px-4 py-16">
            <h1 class="text-2xl font-bold">Reset password</h1>
            <p v-if="status" class="mt-4 rounded-md bg-green-50 p-3 text-sm text-green-800">{{ status }}</p>
            <form class="mt-6 space-y-4" @submit.prevent="form.post('/forgot-password')">
                <TextInput id="email" v-model="form.email" label="Email" type="email" autocomplete="username" :error="form.errors.email" />
                <PrimaryButton :disabled="form.processing">Email reset link</PrimaryButton>
            </form>
        </section>
    </PublicLayout>
</template>
