<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import TextInput from '../../Components/TextInput.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
        default: false,
    },
    status: {
        type: String,
        default: '',
    },
});

const page = usePage();
const user = page.props.auth.user || {};

const profileForm = useForm({
    firstname: user.firstname || '',
    lastname: user.lastname || '',
    email: user.email || '',
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const deleteForm = useForm({
    password: '',
});

function updatePassword() {
    passwordForm.put('/password', {
        preserveScroll: true,
        onFinish: () => passwordForm.reset(),
    });
}
</script>

<template>
    <Head title="Profile" />
    <AppLayout title="Profile">
        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-lg font-semibold">Profile information</h2>
                <form class="mt-5 space-y-4" @submit.prevent="profileForm.patch('/profile', { preserveScroll: true })">
                    <TextInput id="firstname" v-model="profileForm.firstname" label="First name" :error="profileForm.errors.firstname" />
                    <TextInput id="lastname" v-model="profileForm.lastname" label="Last name" :error="profileForm.errors.lastname" />
                    <TextInput id="email" v-model="profileForm.email" label="Email" type="email" :error="profileForm.errors.email" />
                    <p v-if="mustVerifyEmail && !user.email_verified_at" class="text-sm text-amber-700">Email verification is pending.</p>
                    <PrimaryButton :disabled="profileForm.processing">Save</PrimaryButton>
                </form>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-lg font-semibold">Update password</h2>
                <form class="mt-5 space-y-4" @submit.prevent="updatePassword">
                    <TextInput id="current_password" v-model="passwordForm.current_password" label="Current password" type="password" :error="passwordForm.errors.current_password" />
                    <TextInput id="password" v-model="passwordForm.password" label="New password" type="password" :error="passwordForm.errors.password" />
                    <TextInput id="password_confirmation" v-model="passwordForm.password_confirmation" label="Confirm password" type="password" :error="passwordForm.errors.password_confirmation" />
                    <PrimaryButton :disabled="passwordForm.processing">Update password</PrimaryButton>
                </form>
            </section>

            <section class="rounded-lg border border-red-200 bg-white p-6 lg:col-span-2 dark:border-red-900 dark:bg-slate-900">
                <h2 class="text-lg font-semibold">Delete account</h2>
                <form class="mt-5 flex flex-col gap-4 sm:flex-row sm:items-end" @submit.prevent="deleteForm.delete('/profile', { preserveScroll: true })">
                    <div class="grow">
                        <TextInput id="delete_password" v-model="deleteForm.password" label="Password" type="password" :error="deleteForm.errors.password" />
                    </div>
                    <button class="rounded-md bg-red-700 px-4 py-2 text-sm font-semibold text-white" type="submit" :disabled="deleteForm.processing">Delete account</button>
                </form>
            </section>
        </div>
    </AppLayout>
</template>
