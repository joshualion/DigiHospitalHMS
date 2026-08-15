<script setup>
import { Link } from '@inertiajs/vue3';
defineProps({ href: { type: String, default: null }, variant: { type: String, default: 'primary' } });
</script>
<template>
    <Link v-if="href" :href="href" class="public-focus" :class="variant === 'primary' ? 'btn-public-primary' : 'btn-public-secondary'"><slot /></Link>
    <button v-else type="button" class="public-focus" :class="variant === 'primary' ? 'btn-public-primary' : 'btn-public-secondary'"><slot /></button>
</template>