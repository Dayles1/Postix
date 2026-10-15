{{-- An on/off switch. $model is the form path it flips, $label its aria-label. --}}
<button
    type="button"
    role="switch"
    :aria-checked="{{ $model }}"
    aria-label="{{ $label }}"
    x-on:click="{{ $model }} = !{{ $model }}"
    :class="{{ $model }} ? 'bg-brand-600' : 'bg-gray-300 dark:bg-white/[0.16]'"
    class="dc-tap relative inline-flex h-6 w-10 shrink-0 items-center rounded-full transition outline-none
           focus-visible:ring-[3px] focus-visible:ring-brand-500/30"
>
    <span
        :class="{{ $model }} ? 'translate-x-5' : 'translate-x-1'"
        class="inline-block h-4 w-4 rounded-full bg-white shadow-sm transition-transform"
    ></span>
</button>
