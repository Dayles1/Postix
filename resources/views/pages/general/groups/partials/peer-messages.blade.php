<div class="messages-list p-4 space-y-3 max-h-[28rem] overflow-auto">
    @foreach ($messages as $msg)
        @php
            $failed = ($msg->status ?? '') === 'failed' || !empty($msg->error_key);
            $statusLabel = $msg->status ?? 'n/a';

            $messageId = $msg->telegram_message_id ?? null;
        // \Illuminate\Support\Facades\Log::info('telegram_message_id_debug', [
        //     'message_id' => $messageId,
        //     'peer' => $peer ?? null,
        //     $msg
        // ]);
            $telegramLink = null;

            if ($messageId && !empty($peer)) {
                $rawPeer = trim((string) $peer);

                if (str_starts_with($rawPeer, '@')) {
                    $username = substr($rawPeer, 1);
                    $telegramLink = "https://t.me/{$username}/{$messageId}";
                } elseif (preg_match('~(?:https?://)?(?:www\.)?(?:t\.me/)?([A-Za-z0-9_]{5,32})$~', $rawPeer, $m)) {
                    $telegramLink = "https://t.me/{$m[1]}/{$messageId}";
                } elseif (preg_match('~^-100(\d+)$~', $rawPeer, $m)) {
                    $telegramLink = "https://t.me/c/{$m[1]}/{$messageId}";
                } elseif (preg_match('~t\.me/c/(\d+)~', $rawPeer, $m)) {
                    $telegramLink = "https://t.me/c/{$m[1]}/{$messageId}";
                }
            }
        @endphp

        <article class="message-item bg-gray-50 dark:bg-gray-900 rounded-2xl p-4 border border-gray-100 dark:border-gray-700"
            data-msg-status="{{ $msg->status ?? 'unknown' }}">
            <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2 text-sm text-gray-700 dark:text-gray-200">

                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                            <strong>{{ __('messages.op_show.status') }}:</strong>
                            <span class="font-medium">{{ __('messages.' . $statusLabel) }}</span>
                        </span>

                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                            <strong>{{ __('messages.op_show.scheduled_at') }}:</strong>
                            <span class="text-gray-500 dark:text-gray-400">
                                {{ $msg->send_at ? \Carbon\Carbon::parse($msg->send_at)->format('d-m-Y H:i:s') : '-' }}
                            </span>
                        </span>

                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                            <strong>{{ __('messages.op_show.sent_at') }}:</strong>
                            <span class="text-gray-500 dark:text-gray-400">
                                {{ $msg->sent_at ? \Carbon\Carbon::parse($msg->sent_at)->format('d-m-Y H:i:s') : '-' }}
                            </span>
                        </span>

                        @if ($telegramLink)
                            <a href="{{ $telegramLink }}"
                               target="_blank"
                               rel="noopener noreferrer"
                               class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-blue-600 hover:bg-blue-50 dark:hover:bg-gray-800 transition"
                               title="{{ __('messages.remove_peer.open_telegram') }}">
                                <span>🔗</span>
                                <span class="text-xs font-medium">{{ __('messages.remove_peer.open_telegram') ?? 'Open' }}</span>
                            </a>
                        @endif
                    </div>

                    @if ($msg->message)
                        <div class="mt-3 text-sm text-gray-800 dark:text-gray-100 whitespace-pre-wrap break-words bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-3">
                            {{ $msg->message }}
                        </div>
                    @endif
                </div>

                <div class="flex-shrink-0 lg:text-right">
                    @if ($failed)
                        <div class="text-sm font-semibold text-red-600 dark:text-red-400">
                            {{ $msg->error_text }}
                        </div>
                    @endif
                </div>
            </div>
        </article>
    @endforeach

    @if ($messages->hasMorePages())
        <div class="messages-pagination flex justify-center pt-2">
            <button type="button"
                class="message-page-btn inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800"
                data-page="{{ $messages->currentPage() + 1 }}" data-peer="{{ e($peer) }}">
                {{ __('messages.load_more_messages') }}
            </button>
        </div>
    @endif
</div>