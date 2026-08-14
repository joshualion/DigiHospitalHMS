import './bootstrap';
import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { createApp, h } from 'vue';

router.on('start', () => document.documentElement.classList.add('inertia-loading'));
router.on('finish', () => document.documentElement.classList.remove('inertia-loading'));

createInertiaApp({
    title: (title) => (title ? `${title} - HMS` : 'HMS'),
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });

        return pages[`./Pages/${name}.vue`];
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
});
