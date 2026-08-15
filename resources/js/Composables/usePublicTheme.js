import { computed, onMounted, ref, watch } from 'vue';

const APPEARANCES = ['light', 'dark', 'system'];
const ACCENTS = ['calm-blue', 'healing-green', 'warm-gold', 'vital-red'];
const STORAGE_KEY = 'public-theme-preference';

const appearance = ref('system');
const accent = ref('calm-blue');
const allowedAccents = ref([...ACCENTS]);
const switcherVisible = ref(true);
const systemDark = ref(false);
let initialized = false;

function readPreference() {
    try {
        return JSON.parse(window.localStorage.getItem(STORAGE_KEY) || '{}');
    } catch (error) {
        return {};
    }
}

function resolvedAppearanceValue(value = appearance.value) {
    return value === 'system' ? (systemDark.value ? 'dark' : 'light') : value;
}

function applyTheme() {
    const resolved = resolvedAppearanceValue();
    document.documentElement.dataset.publicAppearance = resolved;
    document.documentElement.dataset.publicAppearancePreference = appearance.value;
    document.documentElement.dataset.publicAccent = accent.value;
    document.documentElement.style.colorScheme = resolved;
}

function persistTheme() {
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify({ appearance: appearance.value, accent: accent.value }));
}

export function usePublicTheme(defaults = {}) {
    const configuredAccents = (defaults.allowedAccents || ACCENTS).filter((value) => ACCENTS.includes(value));
    allowedAccents.value = configuredAccents.length ? configuredAccents : [...ACCENTS];
    switcherVisible.value = defaults.switcherVisible !== false;

    if (!initialized && typeof window !== 'undefined') {
        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        systemDark.value = mediaQuery.matches;

        const stored = readPreference();
        const defaultAppearance = APPEARANCES.includes(defaults.appearance) ? defaults.appearance : 'system';
        const defaultAccent = allowedAccents.value.includes(defaults.accent) ? defaults.accent : 'calm-blue';

        appearance.value = APPEARANCES.includes(stored.appearance) ? stored.appearance : defaultAppearance;
        accent.value = allowedAccents.value.includes(stored.accent) ? stored.accent : defaultAccent;

        mediaQuery.addEventListener('change', (event) => {
            systemDark.value = event.matches;
            applyTheme();
        });

        watch([appearance, accent, systemDark], () => {
            if (!allowedAccents.value.includes(accent.value)) {
                accent.value = allowedAccents.value[0] || 'calm-blue';
            }
            persistTheme();
            applyTheme();
        }, { immediate: true });

        initialized = true;
    }

    onMounted(applyTheme);

    return {
        appearances: APPEARANCES,
        accents: ACCENTS,
        allowedAccents,
        switcherVisible,
        appearance,
        accent,
        resolvedAppearance: computed(() => resolvedAppearanceValue()),
        setAppearance: (value) => { appearance.value = APPEARANCES.includes(value) ? value : 'system'; },
        setAccent: (value) => { accent.value = allowedAccents.value.includes(value) ? value : (allowedAccents.value[0] || 'calm-blue'); },
    };
}