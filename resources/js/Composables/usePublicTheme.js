import { computed, onMounted, ref, watch } from 'vue';

const APPEARANCES = ['light', 'dark', 'system'];
const ACCENTS = ['calm', 'healing', 'alert', 'blood', 'seagrass'];
const STORAGE_KEY = 'public-theme-preference';

const appearance = ref('system');
const accent = ref('calm');
const allowedAccents = ref([...ACCENTS]);
const switcherVisible = ref(true);
const accentSwitchingEnabled = ref(true);
const systemDark = ref(false);
let initialized = false;

function readPreference() {
    try {
        return JSON.parse(window.localStorage.getItem(STORAGE_KEY) || '{}');
    } catch (error) {
        return {};
    }
}

function normalizeAccent(value) {
    return {
        'calm-blue': 'calm',
        'healing-green': 'healing',
        'warm-gold': 'alert',
        'vital-red': 'blood',
    }[value] || value;
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
    const configuredAccents = (defaults.allowedAccents || ACCENTS).map((value) => normalizeAccent(value)).filter((value) => ACCENTS.includes(value));
    allowedAccents.value = configuredAccents.length ? configuredAccents : [...ACCENTS];
    switcherVisible.value = defaults.switcherVisible !== false;
    accentSwitchingEnabled.value = defaults.switcherVisible !== false && defaults.allowAccentSwitching !== false;

    const defaultAppearance = APPEARANCES.includes(defaults.appearance) ? defaults.appearance : 'system';
    const defaultAccent = allowedAccents.value.includes(normalizeAccent(defaults.accent)) ? normalizeAccent(defaults.accent) : 'calm';

    if (!initialized && typeof window !== 'undefined') {
        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        const stored = readPreference();

        systemDark.value = mediaQuery.matches;
        appearance.value = APPEARANCES.includes(stored.appearance) ? stored.appearance : defaultAppearance;
        accent.value = accentSwitchingEnabled.value && allowedAccents.value.includes(normalizeAccent(stored.accent)) ? normalizeAccent(stored.accent) : defaultAccent;

        mediaQuery.addEventListener('change', (event) => {
            systemDark.value = event.matches;
            applyTheme();
        });

        watch([appearance, accent, systemDark], () => {
            if (!accentSwitchingEnabled.value && accent.value !== defaultAccent) {
                accent.value = defaultAccent;
            } else if (!allowedAccents.value.includes(accent.value)) {
                accent.value = defaultAccent;
            }
            persistTheme();
            applyTheme();
        }, { immediate: true });

        initialized = true;
    } else if (typeof window !== 'undefined') {
        if (!accentSwitchingEnabled.value) {
            accent.value = defaultAccent;
        } else if (!allowedAccents.value.includes(accent.value)) {
            accent.value = defaultAccent;
        }
    }

    onMounted(applyTheme);

    return {
        appearances: APPEARANCES,
        accents: ACCENTS,
        allowedAccents,
        switcherVisible,
        accentSwitchingEnabled,
        appearance,
        accent,
        resolvedAppearance: computed(() => resolvedAppearanceValue()),
        setAppearance: (value) => { appearance.value = APPEARANCES.includes(value) ? value : 'system'; },
        setAccent: (value) => {
            accent.value = accentSwitchingEnabled.value && allowedAccents.value.includes(value) ? value : defaultAccent;
        },
    };
}
