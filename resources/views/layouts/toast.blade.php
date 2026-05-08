<div x-data="toasts()" x-cloak class="fixed z-[999999] inset-0 pointer-events-none">

  <div class="fixed inset-x-4 top-4 md:inset-x-auto md:right-6 md:top-6 flex flex-col-reverse items-center md:items-end gap-3 pointer-events-none">

    <template x-for="t in toasts" :key="t.id">
      <div
        x-show="t.show"
        x-transition:enter="transform transition ease-out duration-250"
        x-transition:enter-start="-translate-y-6 opacity-0"
        x-transition:enter-end="translate-y-0 opacity-100"
        x-transition:leave="transform transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="pointer-events-auto w-full max-w-sm md:w-80 rounded-2xl shadow-2xl overflow-hidden"
        :class="`${baseClasses} ${computeStyle(t).bg} ${computeStyle(t).border}`">

        <div class="p-4 flex items-start gap-3">
          <!-- Icon -->
          <div class="flex-shrink-0 mt-0.5">
            <!-- Success -->
            <svg x-show="t.success" xmlns="http://www.w3.org/2000/svg"
                 :class="computeStyle(t).icon"
                 class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
            <!-- Error / boshqa -->
            <svg x-show="!t.success" xmlns="http://www.w3.org/2000/svg"
                 :class="computeStyle(t).icon"
                 class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </div>

          <!-- Matn -->
          <div class="flex-1 text-sm leading-tight" :class="computeStyle(t).text" x-html="t.message"></div>

          <!-- Yopish tugmasi -->
          <button @click="remove(t.id)" 
                  class="ml-1 -mr-1 p-1.5 rounded-xl hover:bg-black/10 dark:hover:bg-white/10 transition-colors">
            <span class="sr-only">Yopish</span>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-500 dark:text-gray-400" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
            </svg>
          </button>
        </div>
      </div>
    </template>
  </div>

  <script>
    function toasts() {
      const PALETTES = {
        emerald: { bg: 'bg-emerald-50 dark:bg-emerald-900/30', border: 'border border-emerald-200 dark:border-emerald-800', icon: 'text-emerald-600', text: 'text-emerald-900 dark:text-emerald-100' },
        red:     { bg: 'bg-red-50 dark:bg-red-900/30',     border: 'border border-red-200 dark:border-red-800',     icon: 'text-red-600',     text: 'text-red-900 dark:text-red-100' },
        amber:   { bg: 'bg-amber-50 dark:bg-amber-900/30', border: 'border border-amber-200 dark:border-amber-800', icon: 'text-amber-600',   text: 'text-amber-900 dark:text-amber-100' },
        blue:    { bg: 'bg-blue-50 dark:bg-blue-900/30',   border: 'border border-blue-200 dark:border-blue-800',   icon: 'text-blue-600',    text: 'text-blue-900 dark:text-blue-100' },
        gray:    { bg: 'bg-gray-50 dark:bg-gray-900/30',   border: 'border border-gray-200 dark:border-gray-700',   icon: 'text-gray-600',    text: 'text-gray-900 dark:text-gray-100' }
      };

      return {
        toasts: [],
        nextId: 1,
        baseClasses: 'rounded-2xl',

        add({ message = '', success = true, color = null, timeout = 4000 } = {}) {
          const id = this.nextId++;
          const t = { id, message: message || '', success: !!success, color, timeout: Number(timeout) || 4000, show: true };
          this.toasts.push(t);
          if (t.timeout > 0) setTimeout(() => this.remove(id), t.timeout);
          return id;
        },

        remove(id) {
          const idx = this.toasts.findIndex(x => x.id === id);
          if (idx === -1) return;
          this.toasts[idx].show = false;
          setTimeout(() => { this.toasts = this.toasts.filter(x => x.id !== id); }, 200);
        },

        computeStyle(t) {
          const key = (t.color && String(t.color).trim().toLowerCase()) || (t.success ? 'emerald' : 'red');
          return PALETTES[key] || PALETTES.gray;
        },

        success(msg = '', color = null, timeout = 4000) { return this.add({ message: msg, success: true, color, timeout }); },
        error(msg = '', color = null, timeout = 4000)   { return this.add({ message: msg, success: false, color, timeout }); },

        init() {
          window.addEventListener('toast', e => {
            const d = e.detail ?? {};
            if (typeof d === 'string') return this.add({ message: d });
            
            const message = d.message ?? d.msg ?? '';
            const success = typeof d.success === 'boolean' ? d.success : (d.type !== 'error');
            const color = d.color ?? null;
            const timeout = typeof d.timeout === 'number' ? d.timeout : 4000;
            this.add({ message, success, color, timeout });
          });
        }
      };
    }
  </script>
</div>