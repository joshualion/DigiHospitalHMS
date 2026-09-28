<script setup>
import InfoBand from '@/Components/Public/InfoBand.vue';
import PublicButton from '@/Components/Public/PublicButton.vue';
import PublicImage from '@/Components/Public/PublicImage.vue';
import PublicPageHero from '@/Components/Public/PublicPageHero.vue';
import SectionHeading from '@/Components/Public/SectionHeading.vue';
import ServicesAccordion from '@/Components/Public/ServicesAccordion.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Activity, ArrowRight, Building2, Quote, ShieldCheck, Users } from '@lucide/vue';
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
const departmentsSection = computed(() => props.sections.departments?.content || {});
const trustSection = computed(() => props.sections.why_choose_us?.content || {});
const cliniciansSection = computed(() => props.sections.doctors?.content || {});
const testimonialsSection = computed(() => props.sections.testimonials?.content || {});
const newsSection = computed(() => props.sections.news?.content || {});
const services = computed(() => props.items.service || []);
const departments = computed(() => props.items.department || []);
const doctors = computed(() => props.items.clinician || []);
const testimonials = computed(() => props.items.testimonial || []);
const isPlaceholderCopy = (value) => {
    const text = String(value || '').trim().toLowerCase();
    if (!text) return false;
    return [
        'replace this placeholder',
        'placeholder',
        'approved hospital information',
        'approved hospital news',
        'publishing workflow',
        'publishing is ready',
        'informational only',
        'marketing services',
    ].some((marker) => text.includes(marker));
};
const articles = computed(() => (props.items.article || []).filter((article) => {
    const content = article.content || {};
    return ![article.title, article.summary, content.excerpt, content.body]
        .filter(Boolean)
        .some((value) => isPlaceholderCopy(value));
}));
const activeSlide = computed(() => slides.value[slideIndex.value] || slides.value[0] || {});
const trustIcons = [ShieldCheck, Users, Activity];
const hasHeroCopy = computed(() => slides.value.length > 0 || Boolean(pageTitle.value));
const pageTitle = computed(() => props.page.title || props.site.hospital?.display_name || 'Hospital');
const hasAboutContent = computed(() => Boolean(about.value.heading || about.value.description || about.value.image || about.value.primary_image || about.value.points?.length));
const hasServicesContent = computed(() => services.value.length > 0 || Boolean(props.sections.services?.content?.heading || props.sections.services?.content?.description));
const hasDepartmentsContent = computed(() => departments.value.length > 0 || Boolean(departmentsSection.value.heading || departmentsSection.value.description));
const hasTrustContent = computed(() => whyItems.value.length > 0 || Boolean(trustSection.value.heading || trustSection.value.description));
const hasCliniciansContent = computed(() => doctors.value.length > 0 || Boolean(cliniciansSection.value.heading || cliniciansSection.value.description));
const hasAppointmentContent = computed(() => Boolean(appointment.value.heading || appointment.value.text || appointment.value.button_label));
const hasNewsContent = computed(() => articles.value.length > 0 || Boolean(newsSection.value.heading || newsSection.value.description));
const hasContactDetails = computed(() => Boolean(contact.value.address || contact.value.phone || contact.value.email || contact.value.hours));
const rawStandardBody = computed(() => props.page.content?.body || props.page.content?.summary || '');
const standardBody = computed(() => isPlaceholderCopy(rawStandardBody.value) ? '' : rawStandardBody.value);
const cleanPageSummary = computed(() => {
    const value = props.page.content?.summary || '';
    return isPlaceholderCopy(value) ? '' : value;
});
const publicPageTitle = computed(() => ({
    about: `About ${props.site.hospital?.display_name || 'Our Hospital'}`,
    services: 'Our Healthcare Services',
    doctors: 'Our Doctors',
    departments: 'Hospital Departments',
    news: 'News & Health Information',
    contact: 'Contact Us',
    appointment: 'Appointments',
    policies: 'Hospital Policies',
}[props.page.slug] || props.page.title));
const defaultPageSummaries = computed(() => ({
    about: `Learn more about ${props.site.hospital?.display_name || 'our hospital'}, our commitment to patient care, and the people and values behind our services.`,
    services: `Explore the healthcare services available at ${props.site.hospital?.display_name || 'our hospital'}, delivered by our clinical team with a focus on safe, respectful and patient-centred care.`,
    doctors: 'Meet the doctors whose profiles are currently available on our website.',
    departments: 'Explore the hospital departments currently available to patients and visitors.',
    news: 'Hospital updates and health information for our patients, families and community.',
    contact: 'Reach the hospital using the contact details below. Our team will guide you to the right department or service.',
    appointment: 'Send an appointment request and our team will review your preferred date and contact details.',
    policies: 'Public hospital policies and patient information will be provided here as they are approved.',
}));
const publicPageSummary = computed(() => cleanPageSummary.value || defaultPageSummaries.value[props.page.slug] || '');
const pageEyebrow = computed(() => ({
    about: 'About us',
    services: 'Our services',
    departments: 'Departments',
    doctors: 'Our doctors',
    news: 'Health news',
    contact: 'Contact us',
    appointment: 'Appointments',
    policies: 'Policies',
    'doctor-profile': 'Doctor profile',
    article: 'Health update',
}[props.page.slug] || props.site.hospital?.display_name || 'Hospital'));

function showSlide(index) {
    if (!slides.value.length) return;
    slideIndex.value = (index + slides.value.length) % slides.value.length;
    paused.value = true;
}

function standardSummary() {
    const value = props.page.content?.summary || props.page.content?.body || '';
    return isPlaceholderCopy(value) ? '' : value;
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
            <meta property="og:type" :content="seo.og_type || 'website'">
            <meta v-if="seo.canonical_url" property="og:url" :content="seo.canonical_url">
            <meta v-if="seo.image_alt" property="og:image:alt" :content="seo.image_alt">
            <meta name="twitter:card" :content="seo.twitter_card || 'summary_large_image'">
            <meta name="twitter:title" :content="seo.title || page.title">
            <meta v-if="seo.description" name="twitter:description" :content="seo.description">
            <meta v-if="seo.image" name="twitter:image" :content="seo.image">
            <meta v-if="preview || seo.robots" name="robots" :content="preview ? 'noindex,nofollow' : seo.robots">
            <link v-if="!preview && seo.canonical_url" rel="canonical" :href="seo.canonical_url">
            <link rel="icon" href="/favicon.ico">
        </Head>

        <template v-if="page.slug === 'home'">
            <section class="relative grid min-h-[31rem] md:min-h-[clamp(31rem,63svh,36rem)] xl:min-h-[clamp(33rem,60svh,38rem)] place-items-center overflow-hidden px-4 pb-24 pt-20 text-center text-white sm:px-6 sm:pb-28 sm:pt-24 lg:px-8 lg:pb-32" @mouseenter="paused = true" @mouseleave="paused = false">
                <PublicImage :src="activeSlide.image" :alt="activeSlide.alt || activeSlide.headline || pageTitle" class="absolute inset-0 h-full w-full" img-class="h-full w-full object-cover" fallback-class="h-full w-full" width="1800" height="1000" loading="eager" fetchpriority="high" sizes="100vw" :show-fallback-label="false" />
                <div class="absolute inset-0" style="background: var(--public-hero-overlay);"></div>
                <div class="absolute inset-x-0 bottom-0 h-40 bg-gradient-to-t from-black/35 to-transparent"></div>
                <div v-if="hasHeroCopy" class="relative mx-auto max-w-5xl -translate-y-4 px-2 pb-20 sm:-translate-y-6 sm:pb-24 lg:pb-28">
                    <p v-if="activeSlide.label || activeSlide.eyebrow" class="text-sm font-black uppercase tracking-[0.22em]" style="color: var(--public-accent);">{{ activeSlide.label || activeSlide.eyebrow }}</p>
                    <h1 class="mx-auto mt-5 max-w-5xl text-4xl font-black leading-[1.03] tracking-tight sm:text-6xl lg:text-7xl">{{ activeSlide.headline || pageTitle }}</h1>
                    <p v-if="activeSlide.text" class="mx-auto mt-6 max-w-2xl text-lg leading-8 text-white/86 sm:text-xl">{{ activeSlide.text }}</p>
                    <div class="mt-9 flex flex-wrap justify-center gap-3">
                        <PublicButton v-if="activeSlide.primary_label" :href="activeSlide.primary_url || '/contact'">{{ activeSlide.primary_label }} <ArrowRight class="h-4 w-4" aria-hidden="true" /></PublicButton>
                        <PublicButton v-if="activeSlide.secondary_label" :href="activeSlide.secondary_url || '/services'" variant="secondary">{{ activeSlide.secondary_label }}</PublicButton>
                    </div>
                </div>
                <div v-if="slides.length > 1" class="absolute inset-x-0 bottom-36 mx-auto flex justify-center px-4 sm:bottom-40 sm:px-6 lg:bottom-44 lg:px-8">
                    <div class="flex gap-2 rounded-full bg-black/20 p-2 backdrop-blur">
                        <button
                            v-for="(_, index) in slides"
                            :key="index"
                            class="public-focus h-3 w-3 rounded-full transition-transform hover:scale-110"
                            :style="index === slideIndex ? 'background: var(--public-accent);' : 'background: rgba(255,255,255,0.48);'"
                            type="button"
                            :aria-label="`Show slide ${index + 1}`"
                            :aria-current="index === slideIndex ? 'true' : 'false'"
                            @click="showSlide(index)"
                        ></button>
                    </div>
                </div>
            </section>

            <InfoBand :items="infoItems" />

            <section v-if="hasAboutContent" class="public-section">
                <div class="public-container">
                    <SectionHeading :kicker="about.label" :title="about.heading || pageTitle" :description="about.description" />
                    <div class="mt-12 grid items-center gap-10 lg:grid-cols-[0.95fr_1.05fr]">
                        <div class="relative order-2 lg:order-1">
                            <div class="absolute -inset-4 rounded-[2rem]" style="background: var(--public-accent-soft);"></div>
                            <PublicImage v-if="about.image || about.primary_image" :src="about.image || about.primary_image" :alt="about.image_alt || about.primary_alt || about.heading || pageTitle" class="relative aspect-[4/3] w-full overflow-hidden rounded-[2rem] shadow-2xl" width="760" height="570" loading="lazy" sizes="(min-width: 1024px) 45vw, 100vw" />
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

            <section v-if="hasServicesContent" class="public-section public-muted">
                <div class="public-container">
                    <SectionHeading kicker="Services" :title="sections.services?.content?.heading || 'Services'" :description="sections.services?.content?.description" />
                    <ServicesAccordion :services="services" />
                    <div v-if="services.length" class="mt-10 text-center"><PublicButton href="/services" variant="secondary">View all services</PublicButton></div>
                </div>
            </section>

            <section v-if="hasDepartmentsContent" class="public-section">
                <div class="public-container">
                    <SectionHeading kicker="Departments" :title="departmentsSection.heading || 'Departments'" :description="departmentsSection.description" />
                    <div class="mt-10 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                        <article v-for="department in departments.slice(0, 6)" :key="department.slug" class="public-card rounded-3xl p-6 transition hover:-translate-y-1">
                            <Building2 class="h-8 w-8 public-accent" aria-hidden="true" />
                            <h3 class="mt-5 text-xl font-black" style="color: var(--public-text);">{{ department.title }}</h3>
                            <p class="mt-3 text-sm leading-7" style="color: var(--public-text-secondary);">{{ department.summary }}</p>
                        </article>
                        <p v-if="departments.length === 0" class="public-card rounded-3xl p-8 text-center md:col-span-2 lg:col-span-3" style="color: var(--public-text-secondary);">Department information is being updated. Please contact the hospital if you need assistance.</p>
                    </div>
                </div>
            </section>

            <section v-if="hasTrustContent" class="public-section" style="background: var(--public-footer); color: var(--public-footer-text);">
                <div class="public-container">
                    <SectionHeading :kicker="trustSection.label" :title="trustSection.heading || 'Information'" :description="trustSection.description" />
                    <div class="mt-10 grid gap-5 md:grid-cols-3">
                        <article v-for="(item, index) in whyItems" :key="item.heading" class="rounded-3xl border border-white/10 p-7 text-center transition hover:-translate-y-1" style="background: rgba(255,255,255,0.045);">
                            <component :is="trustIcons[index % trustIcons.length]" class="mx-auto h-9 w-9" style="color: var(--public-accent);" aria-hidden="true" />
                            <h3 class="mt-5 text-xl font-black text-white">{{ item.heading }}</h3>
                            <p class="mt-3 text-sm leading-7 text-white/72">{{ item.text }}</p>
                        </article>
                    </div>
                </div>
            </section>

            <section v-if="hasCliniciansContent" class="public-section">
                <div class="public-container">
                    <SectionHeading kicker="Clinicians" :title="cliniciansSection.heading || 'Clinicians'" :description="cliniciansSection.description" />
                    <div class="mt-10 grid gap-6 md:grid-cols-3">
                        <article v-for="doctor in doctors.slice(0, 3)" :key="doctor.slug" class="public-card overflow-hidden rounded-[2rem] transition hover:-translate-y-1">
                            <PublicImage v-if="doctor.content.photo" :src="doctor.content.photo" :alt="doctor.content.alt || doctor.title" class="h-72 w-full" width="520" height="420" loading="lazy" sizes="(min-width: 768px) 33vw, 100vw" />
                            <div class="p-6 text-center">
                                <h3 class="text-xl font-black" style="color: var(--public-text);">{{ doctor.title }}</h3>
                                <p class="mt-1 text-sm font-bold public-accent">{{ doctor.content.professional_title || doctor.summary }}</p>
                                <p class="mt-4 text-sm leading-7" style="color: var(--public-text-secondary);">{{ doctor.content.bio || doctor.content.biography || doctor.summary }}</p>
                                <Link :href="`/doctors/${doctor.slug}`" class="public-focus public-link mt-5 inline-flex text-sm font-black">View profile</Link>
                            </div>
                        </article>
                        <p v-if="doctors.length === 0" class="public-card rounded-3xl p-8 text-center md:col-span-3" style="color: var(--public-text-secondary);">Clinician information is being updated. Please contact the hospital if you need assistance.</p>
                    </div>
                </div>
            </section>

            <section v-if="testimonials.length" class="public-section public-muted">
                <div class="public-container">
                    <SectionHeading kicker="Testimonials" :title="testimonialsSection.heading || 'Patient experiences'" :description="testimonialsSection.description" />
                    <div class="mx-auto mt-10 grid max-w-5xl gap-5 md:grid-cols-2">
                        <blockquote v-for="testimonial in testimonials.slice(0, 2)" :key="testimonial.slug" class="public-card rounded-[2rem] p-8 text-center">
                            <Quote class="mx-auto h-9 w-9 public-accent" aria-hidden="true" />
                            <p class="mt-5 text-lg leading-8" style="color: var(--public-text);">{{ testimonial.content.text || testimonial.content.quote || testimonial.summary }}</p>
                            <footer class="mt-5 text-sm font-black" style="color: var(--public-text-secondary);">{{ testimonial.title }}</footer>
                        </blockquote>
                    </div>
                </div>
            </section>

            <section v-if="hasAppointmentContent" class="px-4 py-16 sm:px-6 lg:px-8" style="background: var(--public-accent); color: var(--public-accent-foreground);">
                <div class="public-container text-center">
                    <h2 class="mx-auto max-w-3xl text-3xl font-black sm:text-4xl">{{ appointment.heading }}</h2>
                    <p v-if="appointment.text" class="mx-auto mt-4 max-w-2xl text-base leading-8 opacity-90">{{ appointment.text }}</p>
                    <PublicButton v-if="appointment.button_label" class="mt-8" :href="appointment.button_url || '/appointment/request'" variant="secondary">{{ appointment.button_label }}</PublicButton>
                </div>
            </section>

            <section v-if="hasNewsContent || hasContactDetails" class="public-section">
                <div class="public-container grid gap-10 lg:grid-cols-[1fr_0.9fr]">
                    <div v-if="hasNewsContent">
                        <SectionHeading kicker="News" :title="newsSection.heading || 'News'" :description="newsSection.description" align="left" />
                        <div class="mt-8 space-y-4">
                            <article v-for="article in articles.slice(0, 3)" :key="article.slug" class="public-card rounded-3xl p-6">
                                <h3 class="text-lg font-black" style="color: var(--public-text);">{{ article.title }}</h3>
                                <p class="mt-2 text-sm leading-7" style="color: var(--public-text-secondary);">{{ article.summary }}</p>
                                <Link :href="`/news/${article.slug}`" class="public-focus public-link mt-4 inline-flex text-sm font-black">Read update</Link>
                            </article>
                            <p v-if="articles.length === 0" class="public-card rounded-3xl p-8" style="color: var(--public-text-secondary);">Health news and hospital updates will appear here.</p>
                        </div>
                    </div>
                    <div v-if="hasContactDetails" class="public-card rounded-[2rem] p-8 text-center">
                        <SectionHeading kicker="Contact" :title="contact.heading || 'Contact and location'" />
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
            <PublicPageHero :page="page" :eyebrow="pageEyebrow" :title="publicPageTitle" :summary="publicPageSummary" />
            <section class="public-section">
                <div class="public-container">
                    <div v-if="page.slug === 'doctors'" class="grid gap-6 md:grid-cols-3">
                        <article v-for="doctor in doctors" :key="doctor.slug" class="public-card overflow-hidden rounded-[2rem]">
                            <PublicImage v-if="doctor.content.photo" :src="doctor.content.photo" :alt="doctor.content.alt || doctor.title" class="h-72 w-full" width="520" height="420" loading="lazy" sizes="(min-width: 768px) 33vw, 100vw" />
                            <div class="p-6 text-center"><h2 class="text-xl font-black">{{ doctor.title }}</h2><p class="mt-2 public-accent text-sm font-bold">{{ doctor.content.professional_title || doctor.summary }}</p><Link :href="`/doctors/${doctor.slug}`" class="public-focus public-link mt-4 inline-flex text-sm font-black">View profile</Link></div>
                        </article>
                        <p v-if="doctors.length === 0" class="public-card rounded-3xl p-8 text-center md:col-span-3">Clinician information is being updated. Please contact the hospital if you need assistance.</p>
                    </div>
                    <div v-else-if="page.slug === 'services'">
                        <div v-if="services.length" class="grid gap-6 md:grid-cols-2">
                            <article
                                v-for="service in services"
                                :key="`${service.source || service.type}-${service.id}-${service.slug}`"
                                class="public-card rounded-[2rem] p-7 transition hover:-translate-y-1"
                            >
                                <div class="flex items-start gap-4">
                                    <div class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl" style="background: var(--public-accent-soft); color: var(--public-accent);">
                                        <Activity class="h-6 w-6" aria-hidden="true" />
                                    </div>
                                    <div class="min-w-0">
                                        <p v-if="service.content?.department" class="text-xs font-black uppercase tracking-[0.14em] public-accent">{{ service.content.department }}</p>
                                        <h2 class="mt-1 text-xl font-black" style="color: var(--public-text);">{{ service.title }}</h2>
                                    </div>
                                </div>
                                <p class="mt-5 text-sm leading-7" style="color: var(--public-text-secondary);">
                                    {{ service.content?.description || service.summary || 'Please contact the hospital for more information about this service.' }}
                                </p>
                                <PublicButton class="mt-6" href="/appointment/request" variant="secondary">Request an appointment</PublicButton>
                            </article>
                        </div>
                        <div v-else class="public-card mx-auto max-w-2xl rounded-3xl p-8 text-center">
                            <p class="font-bold" style="color: var(--public-text-secondary);">Service information is being updated. Please contact the hospital if you need assistance.</p>
                            <PublicButton class="mt-6" href="/contact">Contact us</PublicButton>
                        </div>
                    </div>
                    <div v-else-if="page.slug === 'departments'" class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                        <article v-for="department in departments" :key="department.slug" class="public-card rounded-3xl p-6"><Building2 class="h-8 w-8 public-accent" /><h2 class="mt-4 text-xl font-black">{{ department.title }}</h2><p class="mt-3 public-prose text-sm">{{ department.summary }}</p></article>
                        <p v-if="departments.length === 0" class="public-card rounded-3xl p-8 text-center md:col-span-3">Department information is being updated. Please contact the hospital if you need assistance.</p>
                    </div>
                    <div v-else-if="page.slug === 'news'" class="grid gap-5 md:grid-cols-3">
                        <article v-for="article in articles" :key="article.slug" class="public-card rounded-3xl p-6"><h2 class="text-xl font-black">{{ article.title }}</h2><p class="mt-3 public-prose text-sm">{{ article.summary }}</p><Link :href="`/news/${article.slug}`" class="public-focus public-link mt-4 inline-flex text-sm font-black">Read update</Link></article>
                        <p v-if="articles.length === 0" class="public-card rounded-3xl p-8 text-center md:col-span-3">Health news and hospital updates will appear here.</p>
                    </div>
                    <article v-else-if="page.slug === 'doctor-profile' || page.slug === 'article'" class="public-card mx-auto max-w-4xl rounded-[2rem] p-8">
                        <PublicImage v-if="page.content?.photo || page.content?.image" :src="page.content.photo || page.content.image" :alt="page.content.alt || page.title" class="mb-8 aspect-video w-full overflow-hidden rounded-3xl" width="900" height="520" loading="eager" sizes="(min-width: 1024px) 900px, 100vw" />
                        <div v-if="page.content?.bio || page.content?.biography || page.content?.body || page.content?.summary" class="public-prose text-lg" v-html="page.content?.bio || page.content?.biography || page.content?.body || page.content?.summary"></div>
                        <p v-else class="text-center text-sm font-bold" style="color: var(--public-text-secondary);">This information is currently being updated. Please contact the hospital if you need assistance.</p>
                        <PublicButton v-if="page.slug === 'doctor-profile' && page.content?.staff_profile_id" class="mt-7" :href="`/appointment/request?doctor=${page.content.staff_profile_id}`">Request appointment with this doctor</PublicButton>
                    </article>
                    <div v-else-if="page.slug === 'about'" class="mx-auto max-w-5xl">
                        <article v-if="standardBody" class="public-card rounded-[2rem] p-8 sm:p-10">
                            <div class="public-prose whitespace-pre-line text-left text-lg">{{ standardBody }}</div>
                        </article>
                        <div v-else class="min-h-24"></div>
                    </div>
                    <div v-else-if="page.slug === 'contact'" class="mx-auto grid max-w-5xl gap-6 md:grid-cols-2">
                        <article class="public-card rounded-[2rem] p-8">
                            <h2 class="text-2xl font-black" style="color: var(--public-text);">Hospital contact</h2>
                            <div class="mt-6 space-y-4 text-base leading-7" style="color: var(--public-text-secondary);">
                                <p v-if="contact.address"><strong style="color: var(--public-text);">Address:</strong> {{ contact.address }}</p>
                                <p v-if="contact.phone"><strong style="color: var(--public-text);">Phone:</strong> {{ contact.phone }}</p>
                                <p v-if="contact.email"><strong style="color: var(--public-text);">Email:</strong> {{ contact.email }}</p>
                                <p v-if="contact.hours"><strong style="color: var(--public-text);">Opening hours:</strong> {{ contact.hours }}</p>
                            </div>
                        </article>
                        <article class="public-card rounded-[2rem] p-8">
                            <h2 class="text-2xl font-black" style="color: var(--public-text);">Need assistance?</h2>
                            <p class="mt-4 leading-7" style="color: var(--public-text-secondary);">Contact the hospital and our team will direct you to the appropriate service or department.</p>
                            <PublicButton class="mt-6" href="/appointment/request">Request an appointment</PublicButton>
                        </article>
                    </div>
                    <div v-else-if="page.slug === 'appointment'" class="public-card mx-auto max-w-3xl rounded-[2rem] p-8 text-center sm:p-10">
                        <h2 class="text-2xl font-black" style="color: var(--public-text);">Request an appointment</h2>
                        <p class="mx-auto mt-4 max-w-2xl leading-7" style="color: var(--public-text-secondary);">Share your preferred date and contact details. The hospital team will review your request and follow up with you.</p>
                        <PublicButton class="mt-7" href="/appointment/request">Start appointment request</PublicButton>
                    </div>
                    <div v-else-if="page.slug === 'policies'" class="mx-auto max-w-4xl">
                        <article v-if="standardBody" class="public-card rounded-[2rem] p-8 sm:p-10">
                            <div class="public-prose whitespace-pre-line text-left">{{ standardBody }}</div>
                        </article>
                        <div v-else class="min-h-24"></div>
                    </div>
                    <div v-else class="mx-auto max-w-4xl">
                        <article v-if="standardBody" class="public-card rounded-[2rem] p-8 text-center">
                            <div class="public-prose whitespace-pre-line text-lg">{{ standardBody }}</div>
                        </article>
                    </div>
                </div>
            </section>
        </template>
    </PublicLayout>
</template>
