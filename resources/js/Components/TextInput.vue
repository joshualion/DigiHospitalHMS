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
    <label :for="id" class="block text-sm font-medium text-slate-700 dark:text-slate-200">{{ label }}</label>
    <input
        :id="id"
        :autocomplete="autocomplete"
        :type="type"
        :value="modelValue"
        class="mt-1 block w-full rounded-md border-slate-300 bg-white text-slate-900 shadow-sm focus:border-red-700 focus:ring-red-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
        @input="$emit('update:modelValue', $event.target.value)"
    >
    <FormError :message="error" />
</template>
