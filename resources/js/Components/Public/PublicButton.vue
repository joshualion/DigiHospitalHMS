<script setup>
import { Link } from '@inertiajs/vue3';
defineProps({ href: { type: String, default: null }, variant: { type: String, default: 'primary' }, type: { type: String, default: 'button' }, disabled: { type: Boolean, default: false } });
</script>
<template>
    <Link v-if="href" :href="href" class="public-focus" :class="variant === 'primary' ? 'btn-public-primary' : 'btn-public-secondary'"><slot /></Link>
    <button v-else :type="type" :disabled="disabled" class="public-focus disabled:cursor-not-allowed disabled:opacity-60" :class="variant === 'primary' ? 'btn-public-primary' : 'btn-public-secondary'"><slot /></button>
</template>
