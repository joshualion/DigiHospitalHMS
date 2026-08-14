<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import TextInput from '../../Components/TextInput.vue';
import PublicLayout from '../../Layouts/PublicLayout.vue';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Login" />
    <PublicLayout>
        <section class="mx-auto max-w-md px-4 py-16">
            <h1 class="text-2xl font-bold">Staff login</h1>
            <form class="mt-6 space-y-4" @submit.prevent="submit">
                <TextInput id="email" v-model="form.email" label="Email" type="email" autocomplete="username" :error="form.errors.email" />
                <TextInput id="password" v-model="form.password" label="Password" type="password" autocomplete="current-password" :error="form.errors.password" />
                <label class="flex items-center gap-2 text-sm">
                    <input v-model="form.remember" type="checkbox" class="rounded border-slate-300 text-red-800 focus:ring-red-700">
                    Remember me
                </label>
                <div class="flex items-center justify-between">
                    <Link href="/forgot-password" class="text-sm text-red-800 dark:text-red-300">Forgot password?</Link>
                    <PrimaryButton :disabled="form.processing">Login</PrimaryButton>
                </div>
            </form>
        </section>
    </PublicLayout>
</template>
