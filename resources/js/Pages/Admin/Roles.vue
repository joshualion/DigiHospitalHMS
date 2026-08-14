<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    roles: { type: Array, default: () => [] },
    permissions: { type: Array, default: () => [] },
    canManagePermissions: { type: Boolean, default: false },
});

const forms = {};

function formFor(role) {
    if (!forms[role.id]) {
        forms[role.id] = useForm({
            permissions: role.permissions.map((permission) => permission.name),
        });
    }

    return forms[role.id];
}
</script>

<template>
    <Head title="Roles" />
    <AppLayout title="Roles And Permissions">
        <div class="space-y-4">
            <div v-for="role in props.roles" :key="role.id" class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="font-semibold">{{ role.name }}</h2>
                        <p class="text-sm text-slate-500">{{ role.permissions.length }} permissions</p>
                    </div>
                    <button v-if="canManagePermissions" class="rounded-md bg-red-800 px-3 py-2 text-sm font-semibold text-white" type="button" @click="formFor(role).patch(`/admin/roles/${role.id}`, { preserveScroll: true })">Save</button>
                </div>
                <div class="mt-4 grid gap-2 md:grid-cols-2 lg:grid-cols-3">
                    <label v-for="permission in props.permissions" :key="permission.id" class="flex items-center gap-2 text-sm">
                        <input v-model="formFor(role).permissions" :disabled="!canManagePermissions" :value="permission.name" type="checkbox" class="rounded border-slate-300 text-red-800">
                        {{ permission.name }}
                    </label>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
