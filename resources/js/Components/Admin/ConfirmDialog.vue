<script setup>
import BaseModal from './BaseModal.vue';
import ActionToolbar from './ActionToolbar.vue';

defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, required: true },
    message: { type: String, default: '' },
    form: { type: Object, required: true },
    confirmLabel: { type: String, default: 'Confirm' },
    requireReason: { type: Boolean, default: false },
    reasonLabel: { type: String, default: 'Reason' },
});

const emit = defineEmits(['close', 'confirm']);
</script>

<template>
    <BaseModal :show="show" :title="title" size="md" :busy="form.processing" @close="$emit('close')">
        <p v-if="message" class="text-sm" style="color: var(--admin-text-muted);">{{ message }}</p>
        <label v-if="requireReason" class="mt-4 grid gap-1 text-sm font-semibold">
            {{ reasonLabel }}
            <textarea v-model="form.reason" class="min-h-24 rounded-md border p-2" style="border-color: var(--admin-border); background: var(--public-input); color: var(--admin-text);" rows="3"></textarea>
            <span v-if="form.errors.reason" class="text-xs text-red-700">{{ form.errors.reason }}</span>
        </label>
        <slot />
        <template #footer>
            <ActionToolbar align="end">
                <button class="rounded-md border px-4 py-2 text-sm font-bold disabled:opacity-60" style="border-color: var(--admin-border);" type="button" :disabled="form.processing" @click="$emit('close')">Cancel</button>
                <button class="rounded-md px-4 py-2 text-sm font-bold disabled:cursor-not-allowed disabled:opacity-60" style="background: var(--public-accent); color: var(--public-accent-foreground);" type="button" :disabled="form.processing" @click="$emit('confirm')">{{ form.processing ? 'Working...' : confirmLabel }}</button>
            </ActionToolbar>
        </template>
    </BaseModal>
</template>
