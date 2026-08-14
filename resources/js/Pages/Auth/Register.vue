<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import TextInput from '../../Components/TextInput.vue';
import PublicLayout from '../../Layouts/PublicLayout.vue';

const form = useForm({
    firstname: '',
    lastname: '',
    email: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head title="Register" />
    <PublicLayout>
        <section class="mx-auto max-w-md px-4 py-16">
            <h1 class="text-2xl font-bold">Create account</h1>
            <form class="mt-6 space-y-4" @submit.prevent="submit">
                <TextInput id="firstname" v-model="form.firstname" label="First name" autocomplete="given-name" :error="form.errors.firstname" />
                <TextInput id="lastname" v-model="form.lastname" label="Last name" autocomplete="family-name" :error="form.errors.lastname" />
                <TextInput id="email" v-model="form.email" label="Email" type="email" autocomplete="username" :error="form.errors.email" />
                <TextInput id="password" v-model="form.password" label="Password" type="password" autocomplete="new-password" :error="form.errors.password" />
                <TextInput id="password_confirmation" v-model="form.password_confirmation" label="Confirm password" type="password" autocomplete="new-password" :error="form.errors.password_confirmation" />
                <div class="flex items-center justify-between">
                    <Link href="/login" class="text-sm text-red-800 dark:text-red-300">Already registered?</Link>
                    <PrimaryButton :disabled="form.processing">Register</PrimaryButton>
                </div>
            </form>
        </section>
    </PublicLayout>
</template>
