<script setup>
import BaseModal from './BaseModal.vue';
import ActionToolbar from './ActionToolbar.vue';

defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, required: true },
    description: { type: String, default: '' },
    size: { type: String, default: 'lg' },
    form: { type: Object, required: true },
    submitLabel: { type: String, default: 'Save' },
});

const emit = defineEmits(['close', 'submit']);
</script>

<template>
    <BaseModal :show="show" :title="title" :description="description" :size="size" :busy="form.processing" @close="$emit('close')">
        <form class="space-y-4" @submit.prevent="$emit('submit')">
            <slot />
        </form>
        <template #footer>
            <ActionToolbar align="end">
                <button class="rounded-md border px-4 py-2 text-sm font-bold disabled:opacity-60" style="border-color: var(--admin-border);" type="button" :disabled="form.processing" @click="$emit('close')">Cancel</button>
                <button class="rounded-md px-4 py-2 text-sm font-bold disabled:cursor-not-allowed disabled:opacity-60" style="background: var(--public-accent); color: var(--public-accent-foreground);" type="button" :disabled="form.processing" @click="$emit('submit')">
                    {{ form.processing ? 'Saving...' : submitLabel }}
                </button>
            </ActionToolbar>
        </template>
    </BaseModal>
</template>
