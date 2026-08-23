<script setup>
import FormError from './FormError.vue';

defineProps({
    id: {
        type: String,
        required: true,
    },
    label: {
        type: String,
        required: true,
    },
    modelValue: {
        type: [String, Number],
        default: '',
    },
    type: {
        type: String,
        default: 'text',
    },
    error: {
        type: String,
        default: '',
    },
    autocomplete: {
        type: String,
        default: '',
    },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <label :for="id" class="block text-sm font-semibold" style="color: var(--public-text-secondary);">{{ label }}</label>
    <input
        :id="id"
        :autocomplete="autocomplete"
        :type="type"
        :value="modelValue"
        class="mt-1 block w-full rounded-md border shadow-sm"
        style="background: var(--public-input); border-color: var(--public-border); color: var(--public-text);"
        @input="$emit('update:modelValue', $event.target.value)"
    >
    <FormError :message="error" />
</template>
