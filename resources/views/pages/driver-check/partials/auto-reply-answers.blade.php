{{--
    The texts of one answer set, a column per tone. $owner is the JS
    expression for the owner (see auto-reply-media), $uid keeps the x-for
    keys apart. Each text can be edited in place; below it is how Telegram
    will show it (already reduced to Telegram's tags by telegramHtml()).
--}}
<div class="grid gap-3 md:grid-cols-2">
    <template x-for="t in tones" :key="'{{ $uid }}-' + t">
        <div class="flex min-w-0 flex-col gap-2 rounded-xl border border-gray-200 bg-gray-25 p-2.5 dark:border-gray-800 dark:bg-white/[0.02]">
            <div class="flex items-baseline justify-between gap-2 px-1">
                <p class="text-[12px] font-semibold text-gray-800 dark:text-gray-100" x-text="translations.tones[t]"></p>
                <p class="truncate text-[11px] text-gray-400 dark:text-gray-500" x-text="translations.tones[t + '_hint']"></p>
            </div>

            <p
                x-show="answers({{ $owner }}, t).length === 0"
                class="rounded-lg border border-dashed border-gray-300 px-2.5 py-2 text-[12px] text-gray-400 dark:border-gray-700 dark:text-gray-500"
                x-text="translations.kind.empty"
            ></p>

            <template x-for="(answer, a) in answers({{ $owner }}, t)" :key="answer.key">
                <div class="group rounded-lg border border-gray-200 bg-white transition focus-within:border-brand-500 focus-within:ring-[3px] focus-within:ring-brand-500/15 dark:border-gray-700 dark:bg-gray-950">
                    <div class="flex items-start gap-1 p-1">
                        <textarea
                            :id="answerId({{ $owner }}, t, a)"
                            x-model="answer.text"
                            x-on:focus="focusAnswer({{ $owner }}, t, a)"
                            rows="1"
                            maxlength="1000"
                            class="min-h-[2.25rem] w-full resize-y rounded-md border-0 bg-transparent px-1.5 py-1.5 text-[13px]
                                   leading-relaxed text-gray-900 outline-none focus:ring-0 dark:text-white"
                        ></textarea>

                        <button
                            type="button"
                            x-on:click="removeAnswer({{ $owner }}, t, a)"
                            class="dc-tap inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-gray-400 transition
                                   hover:bg-error-50 hover:text-error-600 dark:hover:bg-error-500/10 dark:hover:text-error-400"
                            :title="translations.kind.remove_answer"
                            :aria-label="translations.kind.remove_answer"
                        >
                            <x-driver-check.icon name="close" class="h-3.5 w-3.5" />
                        </button>
                    </div>

                    <div
                        x-show="String(answer.text || '').trim() !== ''"
                        class="flex items-start gap-1.5 border-t border-gray-100 bg-gray-25 px-2.5 py-1.5 dark:border-gray-800 dark:bg-white/[0.02]"
                    >
                        <x-driver-check.icon name="send" class="mt-0.5 h-3 w-3 shrink-0 text-gray-300 dark:text-gray-600" />
                        <p
                            class="dc-break whitespace-pre-wrap text-[12px] leading-relaxed text-gray-500 dark:text-gray-400"
                            x-html="preview(answer.text)"
                        ></p>
                    </div>
                </div>
            </template>

            <button
                type="button"
                x-on:click="addAnswer({{ $owner }}, t)"
                class="dc-tap inline-flex h-9 items-center gap-1.5 self-start rounded-lg px-2 text-[12px] font-medium text-gray-500 transition
                       hover:bg-white hover:text-gray-900 sm:h-8 dark:text-gray-400 dark:hover:bg-white/[0.06] dark:hover:text-white"
            >
                <x-driver-check.icon name="plus" class="h-3.5 w-3.5" />
                <span x-text="translations.kind.add_answer"></span>
            </button>
        </div>
    </template>
</div>
