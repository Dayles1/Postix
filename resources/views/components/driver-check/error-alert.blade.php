@props(['title' => null, 'retry' => 'load()', 'model' => 'error'])

<div
    x-show="{{ $model }}"
    x-cloak
    x-transition.opacity
    role="alert"
    class="flex flex-col gap-3 rounded-2xl border border-error-200 bg-error-25 p-3.5
           sm:flex-row sm:items-center sm:justify-between
           dark:border-error-500/30 dark:bg-error-500/[0.07]"
>
    <div class="flex min-w-0 items-start gap-3">
        <x-driver-check.icon name="alert" class="mt-0.5 h-[18px] w-[18px] shrink-0 text-error-500" />

        <div class="min-w-0">
            <p class="text-sm font-semibold text-error-700 dark:text-error-300">
                {{ $title ?? __('telegram.ui.error') }}
            </p>

            <p class="dc-break mt-0.5 text-[13px] text-error-600 dark:text-error-400" x-text="{{ $model }}"></p>
        </div>
    </div>

    <x-driver-check.button size="sm" icon="refresh" class="shrink-0 self-start sm:self-auto" x-on:click="{{ $retry }}">
        {{ __('telegram.ui.retry') }}
    </x-driver-check.button>
</div>
