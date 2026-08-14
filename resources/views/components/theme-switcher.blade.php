<div x-data="themeSwitcher()" x-init="init()" class="flex items-center gap-3">
  <!-- Dark/Light Toggle -->
  <button @click="toggleDark()"
          class="px-3 py-1 rounded-full border text-gray-100 dark:text-gray-800 hover:opacity-80"
          :class="dark ? 'bg-yellow-50 border-yellow-50' : 'bg-gray-900 border-gray-900'">
      <span x-show="!dark"><x-lucide-moon class="w-5 h-5" /></span>
      <span x-show="dark"><x-lucide-sun class="w-5 h-5" /></span>
  </button>

  <!-- Theme Switcher -->
  <div class="relative">
      <!-- Trigger button -->
      <button @click="open = !open"
              class="flex items-center gap-2 px-2 py-1 rounded-2xl border bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200">
          <x-lucide-palette class="w-5 h-5" />
          <span x-text="displayName(color)"></span>
      </button>

      <!-- Dropdown -->
      <div x-show="open" @click.outside="open = false"
           class="absolute right-0 mt-2 w-40 rounded-xl shadow-lg bg-white dark:bg-gray-800 border dark:border-gray-700 z-50"
           x-cloak>
          <ul class="py-2">
              <template x-for="c in colors" :key="c">
                  <li>
                      <button @click="setColor(c); open=false"
                              class="flex items-center gap-2 w-full px-3 py-2 text-left hover:bg-gray-100 dark:hover:bg-gray-700">
                          <span class="w-4 h-4 rounded-full border" :style="`background:${colorMap[c]}`"></span>
                          <span x-text="displayName(c)"></span>
                      </button>
                  </li>
              </template>
          </ul>
      </div>
  </div>
</div>

<script>
function themeSwitcher() {
  return {
    dark: false,
    open: false,
    // initial color must match tailwind.config.js keys
    color: localStorage.getItem('themeColor') || 'blood',
    colors: ['blood','healing','calm','alert','Seagrass'],

    // explicit mapping used for CSS var and swatches
    colorMap: {
      blood:   '#B22222',
      healing: '#22C55E',
      calm:    '#0EA5E9',
      alert:   '#F59E0B',
      Seagrass:    '#1E3A8A'
    },

    init() {
      this.dark = localStorage.getItem('darkMode') === 'true';
      this.applyDark();
      this.applyColor();
    },

    toggleDark() {
      this.dark = !this.dark;
      localStorage.setItem('darkMode', this.dark);
      this.applyDark();
    },

    applyDark() {
      document.documentElement.classList.toggle('dark', this.dark);
    },

    setColor(c) {
      this.color = c;
      localStorage.setItem('themeColor', c);
      this.applyColor();
    },

    applyColor() {
      // set dataset so any CSS/logic relying on data-theme-color will work
      document.documentElement.dataset.themeColor = this.color;

      // set CSS var used by bg-primary/text-primary
      const hex = this.colorMap[this.color] || this.colorMap.blood;
      document.documentElement.style.setProperty('--primary', hex);
    },

    displayName(c) {
      // nicify for UI: "blood" => "Blood"
      return c.charAt(0).toUpperCase() + c.slice(1);
    }
  }
}
</script>
