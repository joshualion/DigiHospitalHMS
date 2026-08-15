<script setup>
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    mode: { type: String, default: 'public' },
    site: { type: Object, required: true },
    page: { type: Object, required: true },
    sections: { type: Object, default: () => ({}) },
    items: { type: Object, default: () => ({}) },
});

const slideIndex = ref(0);
const paused = ref(false);
let timer;

const preview = computed(() => props.mode === 'preview');
const seo = computed(() => props.page.seo || {});
const hero = computed(() => props.sections.hero?.content || {});
const slides = computed(() => (hero.value.slides || []).filter((slide) => slide.active !== false));
const infoItems = computed(() => props.sections.info_banner?.content?.items || []);
const about = computed(() => props.sections.about?.content || {});
const whyItems = computed(() => props.sections.why_choose_us?.content?.items || []);
const appointment = computed(() => props.sections.appointment_cta?.content || {});
const contact = computed(() => props.sections.contact?.content || props.site.contact || {});
const services = computed(() => props.items.service || []);
const departments = computed(() => props.items.department || []);
const doctors = computed(() => props.items.clinician || []);
const testimonials = computed(() => props.items.testimonial || []);
const articles = computed(() => props.items.article || []);
const activeSlide = computed(() => slides.value[slideIndex.value] || slides.value[0] || {});

function showSlide(index) {
    if (!slides.value.length) return;
    slideIndex.value = (index + slides.value.length) % slides.value.length;
    paused.value = true;
}

onMounted(() => {
    if (slides.value.length < 2 || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    timer = window.setInterval(() => {
        if (!paused.value) {
            slideIndex.value = (slideIndex.value + 1) % slides.value.length;
        }
    }, Number(hero.value.rotation_ms || 6500));
});

onBeforeUnmount(() => window.clearInterval(timer));
</script>

<template>
    <PublicLayout :site="site" :preview="preview">
        <Head :title="seo.title || page.title">
            <meta v-if="seo.description" name="description" :content="seo.description">
            <meta property="og:title" :content="seo.title || page.title">
            <meta v-if="seo.description" property="og:description" :content="seo.description">
            <meta v-if="seo.image" property="og:image" :content="seo.image">
            <meta v-if="preview" name="robots" content="noindex,nofollow">
            <link v-if="!preview && seo.canonical_url" rel="canonical" :href="seo.canonical_url">
        </Head>

        <template v-if="page.slug === 'home'">
            <section class="relative min-h-[590px] overflow-hidden bg-slate-950 text-white md:min-h-[660px]" @mouseenter="paused = true" @mouseleave="paused = false">
                <img v-if="activeSlide.image" :src="activeSlide.image" :alt="activeSlide.alt || activeSlide.headline" class="absolute inset-0 h-full w-full object-cover" width="1600" height="900" fetchpriority="high">
                <div class="absolute inset-0 bg-slate-950/60" :style="{ opacity: activeSlide.overlay ?? 0.62 }"></div>
                <div class="relative mx-auto flex min-h-[590px] max-w-7xl items-center px-4 py-20 sm:px-6 md:min-h-[660px] lg:px-8">
                    <div class="max-w-3xl">
                        <p v-if="activeSlide.eyebrow" class="text-sm font-bold uppercase tracking-[0.22em] text-cyan-200">{{ activeSlide.eyebrow }}</p>
                        <h1 class="mt-5 text-4xl font-black leading-tight sm:text-5xl lg:text-6xl">{{ activeSlide.headline || page.title }}</h1>
                        <p v-if="activeSlide.text" class="mt-6 max-w-2xl text-lg leading-8 text-slate-100">{{ activeSlide.text }}</p>
                        <div class="mt-8 flex flex-wrap gap-3">
                            <Link v-if="activeSlide.primary_label" :href="activeSlide.primary_url || '/contact'" class="rounded-md bg-rose-700 px-5 py-3 text-sm font-bold text-white hover:bg-rose-800">{{ activeSlide.primary_label }}</Link>
                            <Link v-if="activeSlide.secondary_label" :href="activeSlide.secondary_url || '/services'" class="rounded-md border border-white/70 px-5 py-3 text-sm font-bold text-white hover:bg-white hover:text-slate-950">{{ activeSlide.secondary_label }}</Link>
                        </div>
                    </div>
                </div>
                <div v-if="slides.length > 1" class="absolute bottom-8 left-0 right-0 mx-auto flex max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                    <button class="rounded-full border border-white/50 px-4 py-2 text-sm font-bold" type="button" @click="showSlide(slideIndex - 1)">Prev</button>
                    <div class="flex gap-2">
                        <button v-for="(_, index) in slides" :key="index" class="h-3 w-3 rounded-full" :class="index === slideIndex ? 'bg-white' : 'bg-white/40'" type="button" :aria-label="`Show slide ${index + 1}`" @click="showSlide(index)"></button>
                    </div>
                    <button class="rounded-full border border-white/50 px-4 py-2 text-sm font-bold" type="button" @click="showSlide(slideIndex + 1)">Next</button>
                </div>
            </section>

            <section v-if="infoItems.length" class="bg-teal-700 text-white">
                <div class="mx-auto grid max-w-7xl gap-px px-4 py-6 sm:px-6 md:grid-cols-2 lg:grid-cols-4 lg:px-8">
                    <article v-for="item in infoItems" :key="item.heading" class="bg-white/10 p-5">
                        <p class="text-xs font-bold uppercase tracking-wide text-cyan-100">{{ item.icon }}</p>
                        <h2 class="mt-2 text-lg font-bold">{{ item.heading }}</h2>
                        <p class="mt-2 text-sm leading-6 text-teal-50">{{ item.text }}</p>
                        <Link v-if="item.link_label" :href="item.url || '/contact'" class="mt-3 inline-block text-sm font-bold text-white underline">{{ item.link_label }}</Link>
                    </article>
                </div>
            </section>

            <section class="mx-auto grid max-w-7xl gap-12 px-4 py-20 sm:px-6 lg:grid-cols-2 lg:px-8">
                <div>
                    <p class="text-sm font-bold uppercase tracking-wide text-teal-700">{{ about.label || 'About us' }}</p>
                    <h2 class="mt-3 text-3xl font-black text-slate-950 sm:text-4xl">{{ about.heading || 'Hospital care designed around people' }}</h2>
                    <p class="mt-5 text-base leading-8 text-slate-600">{{ about.description }}</p>
                    <ul class="mt-6 grid gap-3 text-sm font-semibold text-slate-800 sm:grid-cols-2">
                        <li v-for="point in about.points || []" :key="point" class="rounded-md border border-slate-200 px-4 py-3">{{ point }}</li>
                    </ul>
                    <Link v-if="about.cta_label" :href="about.cta_url || '/about'" class="mt-8 inline-flex rounded-md bg-slate-950 px-5 py-3 text-sm font-bold text-white">{{ about.cta_label }}</Link>
                </div>
                <div class="grid gap-4 sm:grid-cols-5">
                    <img v-if="about.primary_image" :src="about.primary_image" :alt="about.primary_alt || about.heading" class="h-full min-h-80 w-full rounded-md object-cover sm:col-span-3" width="720" height="900">
                    <img v-if="about.secondary_image" :src="about.secondary_image" :alt="about.secondary_alt || about.heading" class="h-full min-h-80 w-full rounded-md object-cover sm:col-span-2" width="520" height="900" loading="lazy">
                </div>
            </section>

            <section class="bg-slate-50 py-20">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                        <div>
                            <p class="text-sm font-bold uppercase tracking-wide text-teal-700">Services</p>
                            <h2 class="mt-3 text-3xl font-black text-slate-950">Public service information</h2>
                        </div>
                        <Link href="/services" class="text-sm font-bold text-teal-800">View all services</Link>
                    </div>
                    <div class="mt-8 grid gap-5 md:grid-cols-3">
                        <article v-for="service in services.slice(0, 6)" :key="service.slug" class="rounded-md border border-slate-200 bg-white p-6 shadow-sm">
                            <p class="text-sm font-bold text-teal-700">{{ service.content.icon || 'Care' }}</p>
                            <h3 class="mt-3 text-xl font-black text-slate-950">{{ service.title }}</h3>
                            <p class="mt-3 text-sm leading-6 text-slate-600">{{ service.summary }}</p>
                            <Link :href="service.content.cta_url || '/services'" class="mt-5 inline-block text-sm font-bold text-rose-700">{{ service.content.cta_label || 'Learn more' }}</Link>
                        </article>
                    </div>
                </div>
            </section>

            <section class="mx-auto grid max-w-7xl gap-14 px-4 py-20 sm:px-6 lg:grid-cols-[0.9fr_1.1fr] lg:px-8">
                <div>
                    <p class="text-sm font-bold uppercase tracking-wide text-teal-700">Departments</p>
                    <h2 class="mt-3 text-3xl font-black text-slate-950">Care teams and public departments</h2>
                    <p class="mt-5 text-slate-600">Published department profiles are drawn from approved public presentation fields, not internal administrative notes.</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <article v-for="department in departments.slice(0, 6)" :key="department.slug" class="rounded-md border border-slate-200 p-5">
                        <h3 class="font-bold text-slate-950">{{ department.title }}</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600">{{ department.summary }}</p>
                    </article>
                    <p v-if="departments.length === 0" class="text-sm text-slate-600">No public department profiles are published yet.</p>
                </div>
            </section>

            <section class="bg-slate-950 py-20 text-white">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="max-w-2xl">
                        <p class="text-sm font-bold uppercase tracking-wide text-cyan-200">Why choose us</p>
                        <h2 class="mt-3 text-3xl font-black">Clear information, accessible care and accountable publishing.</h2>
                    </div>
                    <div class="mt-9 grid gap-5 md:grid-cols-3">
                        <article v-for="item in whyItems" :key="item.heading" class="rounded-md border border-white/10 p-6">
                            <p class="text-sm font-bold text-cyan-200">{{ item.icon }}</p>
                            <h3 class="mt-3 text-lg font-bold">{{ item.heading }}</h3>
                            <p class="mt-3 text-sm leading-6 text-slate-300">{{ item.text }}</p>
                        </article>
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
                <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div>
                        <p class="text-sm font-bold uppercase tracking-wide text-teal-700">Clinicians</p>
                        <h2 class="mt-3 text-3xl font-black text-slate-950">Featured public profiles</h2>
                    </div>
                    <Link href="/doctors" class="text-sm font-bold text-teal-800">View doctors</Link>
                </div>
                <div class="mt-8 grid gap-5 md:grid-cols-3">
                    <article v-for="doctor in doctors.slice(0, 3)" :key="doctor.slug" class="overflow-hidden rounded-md border border-slate-200 bg-white">
                        <img v-if="doctor.content.photo" :src="doctor.content.photo" :alt="doctor.content.alt || doctor.title" class="h-64 w-full object-cover" width="520" height="380" loading="lazy">
                        <div class="p-6">
                            <h3 class="text-xl font-black text-slate-950">{{ doctor.title }}</h3>
                            <p class="mt-1 text-sm font-semibold text-teal-700">{{ doctor.content.professional_title || doctor.summary }}</p>
                            <p class="mt-3 text-sm leading-6 text-slate-600">{{ doctor.content.biography }}</p>
                            <Link :href="`/doctors/${doctor.slug}`" class="mt-4 inline-block text-sm font-bold text-rose-700">View profile</Link>
                        </div>
                    </article>
                    <p v-if="doctors.length === 0" class="text-sm text-slate-600">No clinician profiles are published yet.</p>
                </div>
            </section>

            <section v-if="testimonials.length" class="bg-cyan-50 py-20">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <p class="text-sm font-bold uppercase tracking-wide text-teal-700">Testimonials</p>
                    <div class="mt-8 grid gap-5 md:grid-cols-2">
                        <blockquote v-for="testimonial in testimonials.slice(0, 2)" :key="testimonial.slug" class="rounded-md bg-white p-7 shadow-sm">
                            <p class="text-lg leading-8 text-slate-800">"{{ testimonial.content.quote || testimonial.summary }}"</p>
                            <footer class="mt-5 text-sm font-bold text-slate-950">{{ testimonial.title }}</footer>
                        </blockquote>
                    </div>
                </div>
            </section>

            <section class="bg-rose-700 py-16 text-white">
                <div class="mx-auto flex max-w-7xl flex-col justify-between gap-6 px-4 sm:px-6 md:flex-row md:items-center lg:px-8">
                    <div>
                        <h2 class="text-3xl font-black">{{ appointment.heading || 'Need hospital information?' }}</h2>
                        <p class="mt-3 max-w-2xl text-rose-50">{{ appointment.text || 'Appointment booking will be connected in a later phase. Use this page for current contact guidance.' }}</p>
                    </div>
                    <Link :href="appointment.button_url || '/appointment'" class="rounded-md bg-white px-5 py-3 text-sm font-bold text-rose-800">{{ appointment.button_label || 'Appointment information' }}</Link>
                </div>
            </section>

            <section class="mx-auto grid max-w-7xl gap-10 px-4 py-20 sm:px-6 lg:grid-cols-2 lg:px-8">
                <div>
                    <p class="text-sm font-bold uppercase tracking-wide text-teal-700">News</p>
                    <h2 class="mt-3 text-3xl font-black text-slate-950">Latest public updates</h2>
                    <div class="mt-8 space-y-4">
                        <article v-for="article in articles.slice(0, 3)" :key="article.slug" class="rounded-md border border-slate-200 p-5">
                            <h3 class="font-bold text-slate-950">{{ article.title }}</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ article.summary }}</p>
                            <Link :href="`/news/${article.slug}`" class="mt-3 inline-block text-sm font-bold text-teal-800">Read update</Link>
                        </article>
                        <p v-if="articles.length === 0" class="text-sm text-slate-600">No news articles are published yet.</p>
                    </div>
                </div>
                <div class="rounded-md bg-slate-100 p-8">
                    <p class="text-sm font-bold uppercase tracking-wide text-teal-700">Contact</p>
                    <h2 class="mt-3 text-3xl font-black text-slate-950">{{ contact.heading || 'Contact and location' }}</h2>
                    <div class="mt-6 space-y-3 text-slate-700">
                        <p v-if="contact.address">{{ contact.address }}</p>
                        <p v-if="contact.phone">{{ contact.phone }}</p>
                        <p v-if="contact.email">{{ contact.email }}</p>
                        <p v-if="contact.hours">{{ contact.hours }}</p>
                    </div>
                    <Link href="/contact" class="mt-6 inline-flex rounded-md bg-teal-700 px-5 py-3 text-sm font-bold text-white">Contact us</Link>
                </div>
            </section>
        </template>

        <template v-else>
            <section class="bg-slate-950 px-4 py-20 text-white sm:px-6 lg:px-8">
                <div class="mx-auto max-w-7xl">
                    <p class="text-sm font-bold uppercase tracking-wide text-cyan-200">{{ page.slug }}</p>
                    <h1 class="mt-4 max-w-4xl text-4xl font-black sm:text-5xl">{{ page.title }}</h1>
                    <p v-if="page.content?.summary" class="mt-5 max-w-2xl text-lg leading-8 text-slate-200">{{ page.content.summary }}</p>
                </div>
            </section>

            <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
                <div v-if="page.slug === 'doctors'" class="grid gap-5 md:grid-cols-3">
                    <article v-for="doctor in doctors" :key="doctor.slug" class="overflow-hidden rounded-md border border-slate-200">
                        <img v-if="doctor.content.photo" :src="doctor.content.photo" :alt="doctor.content.alt || doctor.title" class="h-64 w-full object-cover" width="520" height="380" loading="lazy">
                        <div class="p-6">
                            <h2 class="text-xl font-black">{{ doctor.title }}</h2>
                            <p class="mt-1 text-sm font-semibold text-teal-700">{{ doctor.content.professional_title || doctor.summary }}</p>
                            <Link :href="`/doctors/${doctor.slug}`" class="mt-4 inline-block text-sm font-bold text-rose-700">View profile</Link>
                        </div>
                    </article>
                    <p v-if="doctors.length === 0" class="text-sm text-slate-600">No clinician profiles are published yet.</p>
                </div>

                <div v-else-if="page.slug === 'news'" class="grid gap-5 md:grid-cols-3">
                    <article v-for="article in articles" :key="article.slug" class="rounded-md border border-slate-200 p-6">
                        <h2 class="text-xl font-black">{{ article.title }}</h2>
                        <p class="mt-3 text-sm leading-6 text-slate-600">{{ article.summary }}</p>
                        <Link :href="`/news/${article.slug}`" class="mt-4 inline-block text-sm font-bold text-teal-800">Read update</Link>
                    </article>
                    <p v-if="articles.length === 0" class="text-sm text-slate-600">No news articles are published yet.</p>
                </div>

                <div v-else-if="page.slug === 'services'" class="grid gap-5 md:grid-cols-3">
                    <article v-for="service in services" :key="service.slug" class="rounded-md border border-slate-200 p-6">
                        <h2 class="text-xl font-black">{{ service.title }}</h2>
                        <p class="mt-3 text-sm leading-6 text-slate-600">{{ service.summary }}</p>
                    </article>
                </div>

                <div v-else-if="page.slug === 'departments'" class="grid gap-5 md:grid-cols-2">
                    <article v-for="department in departments" :key="department.slug" class="rounded-md border border-slate-200 p-6">
                        <h2 class="text-xl font-black">{{ department.title }}</h2>
                        <p class="mt-3 text-sm leading-6 text-slate-600">{{ department.summary }}</p>
                    </article>
                    <p v-if="departments.length === 0" class="text-sm text-slate-600">No public department profiles are published yet.</p>
                </div>

                <article v-else-if="page.slug === 'doctor-profile' || page.slug === 'article'" class="max-w-3xl">
                    <img v-if="page.content?.photo || page.content?.image" :src="page.content.photo || page.content.image" :alt="page.content.alt || page.title" class="mb-8 rounded-md" width="900" height="520">
                    <p class="text-lg leading-8 text-slate-700">{{ page.content?.biography || page.content?.body || page.content?.summary }}</p>
                </article>

                <div v-else class="max-w-3xl text-lg leading-8 text-slate-700">
                    <p>{{ page.content?.body || page.content?.summary || 'This public page is ready for approved content.' }}</p>
                    <div v-if="page.slug === 'contact'" class="mt-8 rounded-md bg-slate-100 p-6 text-base">
                        <p v-if="contact.address">{{ contact.address }}</p>
                        <p v-if="contact.phone">{{ contact.phone }}</p>
                        <p v-if="contact.email">{{ contact.email }}</p>
                        <p v-if="contact.hours">{{ contact.hours }}</p>
                    </div>
                </div>
            </section>
        </template>
    </PublicLayout>
</template>
