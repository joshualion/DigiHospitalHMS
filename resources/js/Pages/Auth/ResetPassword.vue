<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import TextInput from '../../Components/TextInput.vue';
import PublicLayout from '../../Layouts/PublicLayout.vue';

const props = defineProps({
    email: {
        type: String,
        default: '',
    },
    token: {
        type: String,
        required: true,
    },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post('/reset-password', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head title="Reset Password" />
    <PublicLayout>
        <section class="mx-auto max-w-md px-4 py-16">
            <h1 class="text-2xl font-bold">Choose a new password</h1>
            <form class="mt-6 space-y-4" @submit.prevent="submit">
                <TextInput id="email" v-model="form.email" label="Email" type="email" autocomplete="username" :error="form.errors.email" />
                <TextInput id="password" v-model="form.password" label="Password" type="password" autocomplete="new-password" :error="form.errors.password" />
                <TextInput id="password_confirmation" v-model="form.password_confirmation" label="Confirm password" type="password" autocomplete="new-password" :error="form.errors.password_confirmation" />
                <PrimaryButton :disabled="form.processing">Reset password</PrimaryButton>
            </form>
        </section>
    </PublicLayout>
</template>
