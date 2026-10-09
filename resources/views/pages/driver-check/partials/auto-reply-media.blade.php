{{--
    GIFs and voice messages of a kind - or of the nudge. $owner is the JS
    expression for it: "k" inside the kinds loop, "'silence'" for the nudge.
    GIFs serve every language; voices belong to the language being edited.
    A GIF comes from Telegram (the dialog in auto-replies.blade.php) or is
    uploaded.
--}}
<div class="grid gap-4 md:grid-cols-2">
    @foreach (['gif', 'voice'] as $type)
        <div class="flex min-w-0 flex-col gap-2 rounded-xl bg-gray-50 p-3 ring-1 ring-inset ring-gray-100 dark:bg-white/[0.02] dark:ring-gray-800">
            <div class="flex items-baseline justify-between gap-2">
                <p class="text-[12px] font-semibold text-gray-700 dark:text-gray-200">
                    @if ($type === 'gif')
                        {{ __('telegram.auto_replies.media.gifs') }}
                    @else
                        <span x-text="translations.media.voices.replace(':language', languageNames[language])"></span>
                    @endif
                </p>
                <p class="truncate text-[11px] text-gray-400 dark:text-gray-500">
                    {{ __("telegram.auto_replies.media.{$type}_hint") }}
                </p>
            </div>

            <template x-for="(item, m) in mediaList({{ $owner }}, '{{ $type }}')" :key="item.file">
                <div class="flex items-center gap-2 rounded-lg bg-white p-2 shadow-xs ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
                    @if ($type === 'gif')
                        <template x-if="item.file.endsWith('.mp4')">
                            <video :src="mediaUrl(item.file)" autoplay loop muted playsinline class="h-12 w-16 shrink-0 rounded-md bg-gray-100 object-cover dark:bg-gray-800"></video>
                        </template>
                        <template x-if="!item.file.endsWith('.mp4')">
                            <img :src="mediaUrl(item.file)" alt="" class="h-12 w-16 shrink-0 rounded-md bg-gray-100 object-cover dark:bg-gray-800">
                        </template>
                        <span class="dc-break min-w-0 flex-1 truncate text-[12px] text-gray-600 dark:text-gray-300" x-text="item.name"></span>
                        {{-- From Telegram: a real GIF; an upload may arrive as a video --}}
                        <span
                            class="shrink-0 rounded-md px-1.5 py-0.5 text-[10px] font-semibold"
                            :class="item.telegram
                                ? 'bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400'
                                : 'bg-gray-100 text-gray-500 dark:bg-white/[0.06] dark:text-gray-400'"
                            :title="item.telegram ? translations.media.telegram_badge_hint : translations.media.file_badge_hint"
                            x-text="item.telegram ? translations.media.telegram_badge : translations.media.file_badge"
                        ></span>
                    @else
                        <audio :src="mediaUrl(item.file)" controls preload="none" class="h-9 min-w-0 flex-1"></audio>
                    @endif

                    <button
                        type="button"
                        x-on:click="removeMedia({{ $owner }}, '{{ $type }}', m)"
                        class="dc-tap inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-gray-400
                               transition hover:bg-error-50 hover:text-error-600 dark:hover:bg-error-500/10
                               dark:hover:text-error-400"
                        :title="translations.media.remove + ': ' + item.name"
                        :aria-label="translations.media.remove + ': ' + item.name"
                    >
                        <x-driver-check.icon name="close" class="h-3.5 w-3.5" />
                    </button>
                </div>
            </template>

            <div class="flex flex-wrap items-center gap-1">
            @if ($type === 'gif')
                <button
                    type="button"
                    x-on:click="openTelegramGifs({{ $owner }})"
                    class="dc-tap inline-flex h-8 items-center gap-1.5 rounded-lg px-2 text-[12px] font-medium
                           text-brand-600 transition hover:bg-white hover:text-brand-700 dark:text-brand-400
                           dark:hover:bg-white/[0.06] dark:hover:text-brand-300"
                >
                    <x-driver-check.icon name="search" class="h-3.5 w-3.5" />
                    <span x-text="translations.media.add_telegram"></span>
                </button>
            @endif

            <label
                class="dc-tap inline-flex h-8 cursor-pointer items-center gap-1.5 self-start rounded-lg px-2 text-[12px] font-medium
                       text-gray-500 transition hover:bg-white hover:text-gray-900 dark:text-gray-400
                       dark:hover:bg-white/[0.06] dark:hover:text-white"
                :class="uploading[uploadKey({{ $owner }}, '{{ $type }}')] ? 'pointer-events-none opacity-60' : ''"
            >
                <x-driver-check.icon name="plus" class="h-3.5 w-3.5" />
                <span
                    x-text="uploading[uploadKey({{ $owner }}, '{{ $type }}')]
                        ? translations.media.uploading
                        : translations.media.add_{{ $type }}"
                ></span>
                <input
                    type="file"
                    class="sr-only"
                    accept="{{ $type === 'gif' ? '.gif,.mp4,image/gif,video/mp4' : '.ogg,.oga,.opus,audio/ogg' }}"
                    x-on:change="uploadMedia({{ $owner }}, '{{ $type }}', $event)"
                >
            </label>
            </div>
        </div>
    @endforeach
</div>
