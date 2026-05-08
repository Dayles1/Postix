@if(($operation->status ?? null) === 'pending')
    <div id="updateModal"
         class="fixed inset-0 z-[99999] hidden overflow-y-auto"
         aria-hidden="true">
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/50" data-close-modal></div>

        <!-- Modal Container -->
        <div class="relative flex min-h-screen items-center justify-center p-4 sm:p-6">
            <div class="w-full max-w-lg mx-auto bg-white dark:bg-gray-900 rounded-3xl shadow-2xl border border-gray-200 dark:border-gray-700 flex flex-col max-h-[92vh] overflow-hidden">

                <!-- Header -->
                <div class="flex items-start justify-between gap-4 p-5 border-b border-gray-100 dark:border-gray-800">
                    <div class="flex-1 pr-2">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white leading-tight">
                            {{ __('messages.op_show.update_modal_title') }}
                        </h3>
                        {{-- <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ __('messages.op_show.update_modal_subtitle') }}
                        </p> --}}
                    </div>

                    <button type="button"
                            id="closeUpdateModalBtn"
                            class="flex-shrink-0 h-10 w-10 flex items-center justify-center rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors text-xl">
                        ✕
                    </button>
                </div>

                <!-- Form -->
                <form id="updateMessageForm"
                      method="POST"
                      action="{{ route('message-groups.update', $operation->id) }}"
                      class="flex-1 flex flex-col overflow-hidden">
                    @csrf
                    @method('PUT')

                    <div class="flex-1 p-5 overflow-y-auto space-y-5">
                        <!-- Warning Note -->
                        <div class="rounded-2xl border border-amber-300 dark:border-amber-700 bg-amber-100/90 dark:bg-amber-900/40 p-4 text-sm text-amber-900 dark:text-amber-100">
                            {{ __('messages.op_show.update_modal_note') }}
                        </div>

                        <!-- Message Input -->
                        <div>
                            <label for="updateMessageInput"
                                   class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                {{ __('messages.op_show.new_message_label') }}
                            </label>

                            <textarea id="updateMessageInput"
                                      name="message"
                                      rows="10"
                                      maxlength="3900"
                                      class="w-full rounded-2xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-3.5 text-base leading-relaxed text-gray-900 dark:text-gray-100 focus:border-brand-500 focus:ring-brand-500 resize-y min-h-[220px] sm:min-h-[260px]"
                                      placeholder="{{ __('messages.op_show.current_message') }}">{{ $operation->message_text ?? '' }}</textarea>

                            <p class="mt-1.5 text-xs font-medium text-gray-500 dark:text-gray-400 text-right">
                                <span id="charCount">0</span>/3900
                            </p>
                        </div>
                    </div>

                    <!-- Footer Buttons -->
                    <div class="p-5 border-t border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 flex flex-col sm:flex-row gap-3">
                        <button type="button"
                                id="cancelUpdateBtn"
                                class="w-full sm:w-auto px-6 py-3.5 rounded-2xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            {{ __('messages.op_show.cancel') }}
                        </button>

                        <button type="submit"
                                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3.5 rounded-2xl bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium transition-colors">
                            <span class="text-lg">💾</span>
                            <span>{{ __('messages.op_show.save_changes') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif