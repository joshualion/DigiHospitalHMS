<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import PublicLayout from '../../Layouts/PublicLayout.vue';
import PublicButton from '../../Components/Public/PublicButton.vue';
import TextInput from '../../Components/TextInput.vue';
import { computed, nextTick, ref } from 'vue';

defineProps({
    site: { type: Object, required: true },
    facilities: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
});

const page = usePage();
const form = useForm({ name: '', phone: '', email: '', preferred_facility_id: '', preferred_department_id: '', preferred_date: '', consent: false, website: '' });
const feedback = ref(null);
const feedbackPanel = ref(null);
const successMessage = computed(() => page.props.flash?.success || '');
const hasErrors = computed(() => Object.keys(form.errors || {}).length > 0);

async function focusFeedback() {
    await nextTick();
    feedbackPanel.value?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    feedbackPanel.value?.focus();
}

function submit() {
    if (form.processing) return;

    feedback.value = null;
    form.post('/appointment/request', {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            feedback.value = 'success';
            form.reset();
            focusFeedback();
        },
        onError: () => {
            feedback.value = hasErrors.value ? 'validation' : 'server';
            focusFeedback();
        },
        onFinish: () => {
            if (!feedback.value && successMessage.value) {
                feedback.value = 'success';
                focusFeedback();
            }
        },
    });
}
</script>

<template>
    <Head title="Request an Appointment">
        <meta name="description" content="Request a staff review for an appointment without entering clinical information.">
        <meta name="robots" content="index,follow">
    </Head>
    <PublicLayout :site="site">
        <section class="public-section px-4">
            <form class="public-card mx-auto max-w-2xl rounded-2xl p-6 sm:p-8" @submit.prevent="submit">
                <p class="public-kicker">Appointment request</p>
                <h1 class="mt-2 text-3xl font-black">Request a callback</h1>
                <p class="mt-3 text-sm leading-6" style="color: var(--public-text-secondary);">This form asks only for contact and preference details. Do not enter symptoms, diagnoses, or medical history.</p>
                <div
                    v-if="feedback || successMessage || hasErrors"
                    ref="feedbackPanel"
                    class="mt-5 rounded-2xl border p-4 text-sm leading-6"
                    :style="(feedback === 'success' || successMessage) ? 'background: color-mix(in srgb, var(--public-success) 10%, var(--public-surface)); border-color: color-mix(in srgb, var(--public-success) 35%, var(--public-border)); color: var(--public-text);' : 'background: color-mix(in srgb, var(--public-danger) 8%, var(--public-surface)); border-color: color-mix(in srgb, var(--public-danger) 35%, var(--public-border)); color: var(--public-text);'"
                    role="status"
                    aria-live="polite"
                    tabindex="-1"
                >
                    <p v-if="feedback === 'success' || successMessage" class="font-bold">{{ successMessage || 'Your appointment request was received. Staff will review it and contact you about availability.' }}</p>
                    <p v-else-if="feedback === 'validation' || hasErrors" class="font-bold">Some details need attention before this request can be sent.</p>
                    <p v-else class="font-bold">The request could not be sent right now. Please try again or contact the hospital directly if this is urgent.</p>
                </div>
                <input v-model="form.website" class="hidden" tabindex="-1" autocomplete="off">
                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <TextInput id="request_name" v-model="form.name" label="Name" :error="form.errors.name" />
                    <TextInput id="request_phone" v-model="form.phone" label="Phone" :error="form.errors.phone" />
                    <TextInput id="request_email" v-model="form.email" label="Email" type="email" :error="form.errors.email" />
                    <label class="grid gap-1 text-sm font-semibold" style="color: var(--public-text-secondary);">Preferred date<input v-model="form.preferred_date" class="rounded-md border" style="background: var(--public-input); border-color: var(--public-border); color: var(--public-text);" type="date"><span v-if="form.errors.preferred_date" class="text-sm font-medium" style="color: var(--public-danger);">{{ form.errors.preferred_date }}</span></label>
                    <label class="grid gap-1 text-sm font-semibold" style="color: var(--public-text-secondary);">Facility<select v-model="form.preferred_facility_id" class="rounded-md border" style="background: var(--public-input); border-color: var(--public-border); color: var(--public-text);"><option value="">Any facility</option><option v-for="facility in facilities" :key="facility.id" :value="facility.id">{{ facility.name }}</option></select><span v-if="form.errors.preferred_facility_id" class="text-sm font-medium" style="color: var(--public-danger);">{{ form.errors.preferred_facility_id }}</span></label>
                    <label class="grid gap-1 text-sm font-semibold" style="color: var(--public-text-secondary);">Department<select v-model="form.preferred_department_id" class="rounded-md border" style="background: var(--public-input); border-color: var(--public-border); color: var(--public-text);"><option value="">Any department</option><option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option></select><span v-if="form.errors.preferred_department_id" class="text-sm font-medium" style="color: var(--public-danger);">{{ form.errors.preferred_department_id }}</span></label>
                </div>
                <label class="mt-5 flex gap-3 text-sm font-semibold" style="color: var(--public-text-secondary);"><input v-model="form.consent" class="mt-1 rounded" type="checkbox"> I consent to being contacted about this appointment request.</label>
                <p v-if="form.errors.consent" class="mt-2 text-sm" style="color: var(--public-danger);">{{ form.errors.consent }}</p>
                <PublicButton class="mt-6" type="submit" :disabled="form.processing">{{ form.processing ? 'Sending request...' : 'Submit request' }}</PublicButton>
            </form>
        </section>
    </PublicLayout>
</template>
