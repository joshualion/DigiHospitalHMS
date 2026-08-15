<script setup>
import InfoBand from '@/Components/Public/InfoBand.vue';
import PublicButton from '@/Components/Public/PublicButton.vue';
import PublicPageHero from '@/Components/Public/PublicPageHero.vue';
import SectionHeading from '@/Components/Public/SectionHeading.vue';
import ServicesAccordion from '@/Components/Public/ServicesAccordion.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Activity, ArrowRight, Building2, ChevronLeft, ChevronRight, Quote, ShieldCheck, Users } from '@lucide/vue';
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
const trustIcons = [ShieldCheck, Users, Activity];

function showSlide(index) {
    if (!slides.value.length) return;
    slideIndex.value = (index + slides.value.length) % slides.value.length;
    paused.value = true;
}

function standardSummary() {
    return props.page.content?.summary || props.page.content?.body || 'This page is ready for approved public content.';
}

onMounted(() => {
    if (slides.value.length < 2 || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    timer = window.setInterval(() => {
        if (!paused.value) slideIndex.value = (slideIndex.value + 1) % slides.value.length;
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
            <section class="relative grid min-h-[680px] place-items-center overflow-hidden px-4 py-24 text-center text-white sm:px-6 lg:px-8" @mouseenter="paused = true" @mouseleave="paused = false">
                <img v-if="activeSlide.image" :src="activeSlide.image" :alt="activeSlide.alt || activeSlide.headline" class="absolute inset-0 h-full w-full object-cover" width="1800" height="1000" fetchpriority="high">
                <div class="absolute inset-0" style="background: var(--public-hero-overlay);"></div>
                <div class="absolute inset-x-0 bottom-0 h-40 bg-gradient-to-t from-black/35 to-transparent"></div>
                <div class="relative mx-auto max-w-5xl">
                    <p v-if="activeSlide.label || activeSlide.eyebrow" class="text-sm font-black uppercase tracking-[0.22em]" style="color: var(--public-accent);">{{ activeSlide.label || activeSlide.eyebrow }}</p>
                    <h1 class="mx-auto mt-5 max-w-5xl text-4xl font-black leading-[1.03] tracking-tight sm:text-6xl lg:text-7xl">{{ activeSlide.headline || page.title }}</h1>
                    <p v-if="activeSlide.text" class="mx-auto mt-6 max-w-2xl text-lg leading-8 text-white/86 sm:text-xl">{{ activeSlide.text }}</p>
                    <div class="mt-9 flex flex-wrap justify-center gap-3">
                        <PublicButton v-if="activeSlide.primary_label" :href="activeSlide.primary_url || '/contact'">{{ activeSlide.primary_label }} <ArrowRight class="h-4 w-4" aria-hidden="true" /></PublicButton>
                        <PublicButton v-if="activeSlide.secondary_label" :href="activeSlide.secondary_url || '/services'" variant="secondary">{{ activeSlide.secondary_label }}</PublicButton>
                    </div>
                </div>
                <div v-if="slides.length > 1" class="absolute inset-x-0 bottom-24 mx-auto flex max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                    <button class="public-focus grid h-12 w-12 place-items-center rounded-full border border-white/30 bg-black/20 text-white backdrop-blur" type="button" aria-label="Previous hero slide" @click="showSlide(slideIndex - 1)"><ChevronLeft class="h-5 w-5" /></button>
                    <div class="flex gap-2 rounded-full bg-black/20 p-2 backdrop-blur">
                        <button v-for="(_, index) in slides" :key="index" class="public-focus h-3 w-3 rounded-full" :style="index === slideIndex ? 'background: var(--public-accent);' : 'background: rgba(255,255,255,0.48);'" type="button" :aria-label="`Show slide ${index + 1}`" @click="showSlide(index)"></button>
                    </div>
                    <button class="public-focus grid h-12 w-12 place-items-center rounded-full border border-white/30 bg-black/20 text-white backdrop-blur" type="button" aria-label="Next hero slide" @click="showSlide(slideIndex + 1)"><ChevronRight class="h-5 w-5" /></button>
                </div>
            </section>

            <InfoBand :items="infoItems" />

            <section class="public-section">
                <div class="public-container">
                    <SectionHeading :kicker="about.label || 'About the hospital'" :title="about.heading || 'Hospital care designed around people'" :description="about.description" />
                    <div class="mt-12 grid items-center gap-10 lg:grid-cols-[0.95fr_1.05fr]">
                        <div class="relative order-2 lg:order-1">
                            <div class="absolute -inset-4 rounded-[2rem]" style="background: var(--public-accent-soft);"></div>
                            <img v-if="about.image || about.primary_image" :src="about.image || about.primary_image" :alt="about.image_alt || about.primary_alt || about.heading" class="relative aspect-[4/3] w-full rounded-[2rem] object-cover shadow-2xl" width="760" height="570" loading="lazy">
                        </div>
                        <div class="order-1 lg:order-2">
                            <p class="public-prose text-left">{{ about.description }}</p>
                            <ul class="mt-7 grid gap-3 sm:grid-cols-2">
                                <li v-for="point in about.points || []" :key="point" class="public-card flex items-center gap-3 rounded-2xl p-4 text-sm font-bold">
                                    <ShieldCheck class="h-5 w-5 shrink-0 public-accent" aria-hidden="true" />{{ point }}
                                </li>
                            </ul>
                            <PublicButton v-if="about.cta_label" class="mt-8" :href="about.cta_url || '/about'">{{ about.cta_label }}</PublicButton>
                        </div>
                    </div>
                </div>
            </section>

            <section class="public-section public-muted">
                <div class="public-container">
                    <SectionHeading kicker="Services" :title="sections.services?.content?.heading || 'Hospital services'" :description="sections.services?.content?.description || 'Explore approved public service information managed through the publishing workflow.'" />
                    <ServicesAccordion :services="services" />
                    <div class="mt-10 text-center"><PublicButton href="/services" variant="secondary">View all services</PublicButton></div>
                </div>
            </section>

            <section class="public-section">
                <div class="public-container">
                    <SectionHeading kicker="Departments" title="Care teams and public departments" description="Published department profiles reference approved public presentation fields, not internal administrative notes." />
                    <div class="mt-10 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                        <article v-for="department in departments.slice(0, 6)" :key="department.slug" class="public-card rounded-3xl p-6 transition hover:-translate-y-1">
                            <Building2 class="h-8 w-8 public-accent" aria-hidden="true" />
                            <h3 class="mt-5 text-xl font-black" style="color: var(--public-text);">{{ department.title }}</h3>
                            <p class="mt-3 text-sm leading-7" style="color: var(--public-text-secondary);">{{ department.summary }}</p>
                        </article>
                        <p v-if="departments.length === 0" class="public-card rounded-3xl p-8 text-center md:col-span-2 lg:col-span-3" style="color: var(--public-text-secondary);">No public department profiles are published yet.</p>
                    </div>
                </div>
            </section>

            <section class="public-section" style="background: var(--public-footer); color: var(--public-footer-text);">
                <div class="public-container">
                    <SectionHeading kicker="Why choose us" title="Clear information, accessible care and accountable publishing." description="Every public section is governed, previewed and published deliberately." />
                    <div class="mt-10 grid gap-5 md:grid-cols-3">
                        <article v-for="(item, index) in whyItems" :key="item.heading" class="rounded-3xl border border-white/10 p-7 text-center transition hover:-translate-y-1" style="background: rgba(255,255,255,0.045);">
                            <component :is="trustIcons[index % trustIcons.length]" class="mx-auto h-9 w-9" style="color: var(--public-accent);" aria-hidden="true" />
                            <h3 class="mt-5 text-xl font-black text-white">{{ item.heading }}</h3>
                            <p class="mt-3 text-sm leading-7 text-white/72">{{ item.text }}</p>
                        </article>
                    </div>
                </div>
            </section>

            <section class="public-section">
                <div class="public-container">
                    <SectionHeading kicker="Clinicians" title="Featured public profiles" description="Clinician profiles publish only approved public information and remain separate from private staff records." />
                    <div class="mt-10 grid gap-6 md:grid-cols-3">
                        <article v-for="doctor in doctors.slice(0, 3)" :key="doctor.slug" class="public-card overflow-hidden rounded-[2rem] transition hover:-translate-y-1">
                            <img v-if="doctor.content.photo" :src="doctor.content.photo" :alt="doctor.content.alt || doctor.title" class="h-72 w-full object-cover" width="520" height="420" loading="lazy">
                            <div class="p-6 text-center">
                                <h3 class="text-xl font-black" style="color: var(--public-text);">{{ doctor.title }}</h3>
                                <p class="mt-1 text-sm font-bold public-accent">{{ doctor.content.professional_title || doctor.summary }}</p>
                                <p class="mt-4 text-sm leading-7" style="color: var(--public-text-secondary);">{{ doctor.content.bio || doctor.content.biography || doctor.summary }}</p>
                                <Link :href="`/doctors/${doctor.slug}`" class="public-focus public-link mt-5 inline-flex text-sm font-black">View profile</Link>
                            </div>
                        </article>
                        <p v-if="doctors.length === 0" class="public-card rounded-3xl p-8 text-center md:col-span-3" style="color: var(--public-text-secondary);">No clinician profiles are published yet.</p>
                    </div>
                </div>
            </section>

            <section v-if="testimonials.length" class="public-section public-muted">
                <div class="public-container">
                    <SectionHeading kicker="Testimonials" title="Approved public statements" description="Placeholder statements remain visibly marked until replaced with consented, approved content." />
                    <div class="mx-auto mt-10 grid max-w-5xl gap-5 md:grid-cols-2">
                        <blockquote v-for="testimonial in testimonials.slice(0, 2)" :key="testimonial.slug" class="public-card rounded-[2rem] p-8 text-center">
                            <Quote class="mx-auto h-9 w-9 public-accent" aria-hidden="true" />
                            <p class="mt-5 text-lg leading-8" style="color: var(--public-text);">{{ testimonial.content.text || testimonial.content.quote || testimonial.summary }}</p>
                            <footer class="mt-5 text-sm font-black" style="color: var(--public-text-secondary);">{{ testimonial.title }}</footer>
                        </blockquote>
                    </div>
                </div>
            </section>

            <section class="px-4 py-16 sm:px-6 lg:px-8" style="background: var(--public-accent); color: var(--public-accent-foreground);">
                <div class="public-container text-center">
                    <h2 class="mx-auto max-w-3xl text-3xl font-black sm:text-4xl">{{ appointment.heading || 'Need appointment information?' }}</h2>
                    <p class="mx-auto mt-4 max-w-2xl text-base leading-8 opacity-90">{{ appointment.text || 'Online booking is not active yet. Contact the hospital for current visit information.' }}</p>
                    <PublicButton class="mt-8" :href="appointment.button_url || '/appointment'" variant="secondary">{{ appointment.button_label || 'Appointment information' }}</PublicButton>
                </div>
            </section>

            <section class="public-section">
                <div class="public-container grid gap-10 lg:grid-cols-[1fr_0.9fr]">
                    <div>
                        <SectionHeading kicker="News" title="Latest public updates" description="Approved announcements and public information from the hospital." align="left" />
                        <div class="mt-8 space-y-4">
                            <article v-for="article in articles.slice(0, 3)" :key="article.slug" class="public-card rounded-3xl p-6">
                                <h3 class="text-lg font-black" style="color: var(--public-text);">{{ article.title }}</h3>
                                <p class="mt-2 text-sm leading-7" style="color: var(--public-text-secondary);">{{ article.summary }}</p>
                                <Link :href="`/news/${article.slug}`" class="public-focus public-link mt-4 inline-flex text-sm font-black">Read update</Link>
                            </article>
                            <p v-if="articles.length === 0" class="public-card rounded-3xl p-8" style="color: var(--public-text-secondary);">No news articles are published yet.</p>
                        </div>
                    </div>
                    <div class="public-card rounded-[2rem] p-8 text-center">
                        <SectionHeading kicker="Contact" :title="contact.heading || 'Contact and location'" description="Reach the hospital through approved public contact details." />
                        <div class="mt-7 space-y-3 text-sm leading-7" style="color: var(--public-text-secondary);">
                            <p v-if="contact.address">{{ contact.address }}</p>
                            <p v-if="contact.phone">{{ contact.phone }}</p>
                            <p v-if="contact.email">{{ contact.email }}</p>
                            <p v-if="contact.hours">{{ contact.hours }}</p>
                        </div>
                        <PublicButton class="mt-7" href="/contact">Contact us</PublicButton>
                    </div>
                </div>
            </section>
        </template>

        <template v-else>
            <PublicPageHero :page="page" :title="page.title" :summary="standardSummary()" />
            <section class="public-section">
                <div class="public-container">
                    <div v-if="page.slug === 'doctors'" class="grid gap-6 md:grid-cols-3">
                        <article v-for="doctor in doctors" :key="doctor.slug" class="public-card overflow-hidden rounded-[2rem]">
                            <img v-if="doctor.content.photo" :src="doctor.content.photo" :alt="doctor.content.alt || doctor.title" class="h-72 w-full object-cover" width="520" height="420" loading="lazy">
                            <div class="p-6 text-center"><h2 class="text-xl font-black">{{ doctor.title }}</h2><p class="mt-2 public-accent text-sm font-bold">{{ doctor.content.professional_title || doctor.summary }}</p><Link :href="`/doctors/${doctor.slug}`" class="public-focus public-link mt-4 inline-flex text-sm font-black">View profile</Link></div>
                        </article>
                        <p v-if="doctors.length === 0" class="public-card rounded-3xl p-8 text-center md:col-span-3">No clinician profiles are published yet.</p>
                    </div>
                    <div v-else-if="page.slug === 'services'">
                        <SectionHeading kicker="Services" title="Approved public service information" description="These are marketing entries, not operational billing catalogue records." />
                        <ServicesAccordion :services="services" />
                    </div>
                    <div v-else-if="page.slug === 'departments'" class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                        <article v-for="department in departments" :key="department.slug" class="public-card rounded-3xl p-6"><Building2 class="h-8 w-8 public-accent" /><h2 class="mt-4 text-xl font-black">{{ department.title }}</h2><p class="mt-3 public-prose text-sm">{{ department.summary }}</p></article>
                        <p v-if="departments.length === 0" class="public-card rounded-3xl p-8 text-center md:col-span-3">No public department profiles are published yet.</p>
                    </div>
                    <div v-else-if="page.slug === 'news'" class="grid gap-5 md:grid-cols-3">
                        <article v-for="article in articles" :key="article.slug" class="public-card rounded-3xl p-6"><h2 class="text-xl font-black">{{ article.title }}</h2><p class="mt-3 public-prose text-sm">{{ article.summary }}</p><Link :href="`/news/${article.slug}`" class="public-focus public-link mt-4 inline-flex text-sm font-black">Read update</Link></article>
                        <p v-if="articles.length === 0" class="public-card rounded-3xl p-8 text-center md:col-span-3">No news articles are published yet.</p>
                    </div>
                    <article v-else-if="page.slug === 'doctor-profile' || page.slug === 'article'" class="public-card mx-auto max-w-4xl rounded-[2rem] p-8">
                        <img v-if="page.content?.photo || page.content?.image" :src="page.content.photo || page.content.image" :alt="page.content.alt || page.title" class="mb-8 aspect-video w-full rounded-3xl object-cover" width="900" height="520">
                        <p class="public-prose text-lg">{{ page.content?.bio || page.content?.biography || page.content?.body || page.content?.summary }}</p>
                    </article>
                    <div v-else class="public-card mx-auto max-w-4xl rounded-[2rem] p-8 text-center">
                        <p class="public-prose text-lg">{{ page.content?.body || page.content?.summary || 'This public page is ready for approved content.' }}</p>
                        <div v-if="page.slug === 'contact'" class="mt-8 rounded-3xl p-6 text-base" style="background: var(--public-accent-soft); color: var(--public-text);">
                            <p v-if="contact.address">{{ contact.address }}</p><p v-if="contact.phone">{{ contact.phone }}</p><p v-if="contact.email">{{ contact.email }}</p><p v-if="contact.hours">{{ contact.hours }}</p>
                        </div>
                    </div>
                </div>
            </section>
        </template>
    </PublicLayout>
</template>
