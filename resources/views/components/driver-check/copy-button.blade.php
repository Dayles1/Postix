@props(['value', 'title' => null])

{{--
    `value` is an Alpine expression, e.g. :value="row.telegram_id".

    Always visible on touch screens (there is no hover to reveal it) and
    revealed by the row hover from lg up.
--}}

<button
    type="button"
    x-on:click.stop.prevent="copy({{ $value }}, @js(__('telegram.ui.copied')))"
    class="dc-tap inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-gray-400
           transition hover:bg-gray-100 hover:text-gray-700 focus-visible:opacity-100
           lg:opacity-0 lg:group-hover:opacity-100
           dark:hover:bg-white/[0.08] dark:hover:text-gray-200"
    title="{{ $title ?? __('telegram.ui.copy') }}"
    aria-label="{{ $title ?? __('telegram.ui.copy') }}"
>
    <x-driver-check.icon name="copy" class="h-3.5 w-3.5" />
</button>
