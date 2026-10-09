{{--
    The GIF search in Telegram (media-picker.js telegramGifPicker()): the
    listener searches, a GIF picked goes to the page's telegramGifPicked().
    Needs translations.media - the auto replies media translations.
--}}
<x-driver-check.modal open="tg.open" close="closeTelegramGifs()" size="sm:max-w-2xl" :title="__('telegram.auto_replies.media.telegram_title')">
    <div class="flex flex-col gap-3">
        <div>
            <div class="relative">
                <x-driver-check.icon
                    name="search"
                    class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                />
                <input
                    id="tg-gif-search"
                    type="search"
                    inputmode="search"
                    autocomplete="off"
                    maxlength="60"
                    x-model="tg.query"
                    x-on:input.debounce.700ms="searchTelegramGifs()"
                    x-on:keydown.enter.prevent="searchTelegramGifs()"
                    placeholder="{{ __('telegram.auto_replies.media.telegram_search') }}"
                    aria-label="{{ __('telegram.auto_replies.media.telegram_search') }}"
                    class="h-11 w-full rounded-xl border border-gray-300 bg-white pl-10 pr-3 text-sm text-gray-900
                           outline-none transition placeholder:text-gray-400 focus:border-brand-500 focus:ring-4
                           focus:ring-brand-500/10 sm:h-10 dark:border-gray-700 dark:bg-gray-900 dark:text-white
                           dark:placeholder:text-gray-500"
                >
            </div>
            <p class="mt-1.5 text-[11px] text-gray-400 dark:text-gray-500">{{ __('telegram.auto_replies.media.telegram_search_hint') }}</p>
        </div>

        <p
            x-show="tg.error"
            x-cloak
            class="rounded-lg bg-error-50 px-3 py-2 text-[12px] text-error-600 dark:bg-error-500/10 dark:text-error-400"
            x-text="tg.error"
        ></p>

        <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
            <template x-for="item in tg.items" :key="item.request + item.id">
                <button
                    type="button"
                    x-on:click="pickTelegramGif(item)"
                    :disabled="tg.picking !== null"
                    :aria-label="translations.media.telegram_pick"
                    class="dc-tap relative aspect-square overflow-hidden rounded-lg bg-gray-100 ring-1 ring-gray-200 transition
                           hover:ring-2 hover:ring-brand-500 disabled:opacity-60 dark:bg-gray-800 dark:ring-gray-700"
                >
                    <template x-if="item.preview.endsWith('.mp4')">
                        <video :src="tgPreviewUrl(item.preview)" autoplay loop muted playsinline class="h-full w-full object-cover"></video>
                    </template>
                    <template x-if="!item.preview.endsWith('.mp4')">
                        <img :src="tgPreviewUrl(item.preview)" alt="" class="h-full w-full object-cover">
                    </template>
                    <span
                        x-show="tg.picking === item.id"
                        class="absolute inset-0 flex items-center justify-center bg-white/60 dark:bg-gray-900/60"
                    >
                        <x-driver-check.icon name="refresh" class="h-5 w-5 animate-spin text-brand-600" />
                    </span>
                </button>
            </template>
        </div>

        <p x-show="tg.loading" x-cloak class="flex items-center justify-center gap-2 py-4 text-[12px] text-gray-500 dark:text-gray-400">
            <x-driver-check.icon name="refresh" class="h-4 w-4 animate-spin" />
            <span x-text="translations.media.telegram_searching"></span>
        </p>

        <p
            x-show="!tg.loading && !tg.error && tg.items.length === 0"
            x-cloak
            class="py-4 text-center text-[12px] text-gray-500 dark:text-gray-400"
            x-text="translations.media.telegram_empty"
        ></p>

        <div x-show="!tg.loading && tg.next" x-cloak class="flex justify-center">
            <x-driver-check.button size="sm" x-on:click="searchTelegramGifs(true)">
                <span x-text="translations.media.telegram_more"></span>
            </x-driver-check.button>
        </div>
    </div>
</x-driver-check.modal>
