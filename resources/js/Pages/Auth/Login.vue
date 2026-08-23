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
        <section class="public-section px-4">
            <form class="public-card mx-auto max-w-md rounded-2xl p-6 sm:p-8" @submit.prevent="submit">
                <p class="public-kicker">Secure workspace</p>
                <h1 class="mt-2 text-3xl font-black">Staff login</h1>
                <p class="mt-3 text-sm leading-6" style="color: var(--public-text-secondary);">Access the hospital administration area with your assigned staff account.</p>

                <div class="mt-7 space-y-4">
                    <TextInput id="email" v-model="form.email" label="Email" type="email" autocomplete="username" :error="form.errors.email" />
                    <TextInput id="password" v-model="form.password" label="Password" type="password" autocomplete="current-password" :error="form.errors.password" />
                    <label class="flex items-center gap-2 text-sm font-semibold" style="color: var(--public-text-secondary);">
                        <input v-model="form.remember" type="checkbox" class="rounded" style="border-color: var(--public-border); color: var(--public-accent);">
                        Remember me
                    </label>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <Link href="/forgot-password" class="public-focus text-sm font-bold" style="color: var(--public-link);">Forgot password?</Link>
                        <PrimaryButton :disabled="form.processing">Login</PrimaryButton>
                    </div>
                </div>
            </form>
        </section>
    </PublicLayout>
</template>
