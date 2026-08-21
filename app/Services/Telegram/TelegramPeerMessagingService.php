<?php

namespace App\Services\Telegram;

use App\Models\UserPhone;
use danog\MadelineProto\API;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramPeerMessagingService
{
    protected ?API $madeline = null;

    public function initMadeline(UserPhone $userPhone): bool
    {
        $path = $userPhone->session_path;

        Log::info('telegram_init_madeline_start', [
            'user_phone_id' => $userPhone->id,
            'phone' => $this->normalizePhone((string) $userPhone->phone),
            'session_path_exists' => (bool) ($path && File::exists($path)),
            'session_path' => $path,
        ]);

        if (!$path || !File::exists($path)) {
            $this->madeline = null;

            Log::warning('telegram_init_madeline_failed', [
                'user_phone_id' => $userPhone->id,
                'reason' => 'session_file_missing',
            ]);

            return false;
        }

        try {
            $startedAt = microtime(true);

            $this->madeline = new API($path);
            $this->madeline->start();

            Log::info('telegram_init_madeline_ok', [
                'user_phone_id' => $userPhone->id,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return true;
        } catch (Throwable $e) {
            $this->madeline = null;

            $err = $this->cleanError($e->getMessage());
            $key = $this->mapErrorToKey($err);

            Log::error('telegram_unknown_error', [
                'stage' => 'init_madeline',
                'message' => $e->getMessage(),
                'context' => [
                    'user_phone_id' => $userPhone->id,
                    'phone' => $this->normalizePhone((string) $userPhone->phone),
                    'session_path' => $path,
                    'error_key' => $key,
                    'clean_error' => $err,
                    'exception_class' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
            ]);

            return false;
        }
    }

    public function cleanupSession(UserPhone $userPhone): void
    {
        $this->forceLogout();
    }

    public function forceLogout(): void
    {
        try {
            if ($this->madeline && method_exists($this->madeline, 'logout')) {
                $this->madeline->logout();
            }
        } catch (Throwable $e) {
            // intentionally silent
        }

        $this->madeline = null;
    }

    public function parsePeer(string $rawPeer): array
    {
        $rawPeer = trim($rawPeer);

        if ($rawPeer === '') {
            return [
                'ok' => false,
                'type' => 'invalid',
                'raw' => $rawPeer,
                'reason' => 'peer bo‘sh',
            ];
        }

        if (preg_match('~^(?:https?://)?(?:www\.)?(?:t\.me|telegram\.me)/\+([A-Za-z0-9_-]+)$~i', $rawPeer, $m)) {
            return [
                'ok' => true,
                'type' => 'invite_link',
                'raw' => $rawPeer,
                'hash' => $m[1],
            ];
        }

        if (preg_match('~^(?:https?://)?(?:www\.)?(?:t\.me|telegram\.me)/c/(\d+)(?:/\d+)?$~i', $rawPeer, $m)) {
            return [
                'ok' => true,
                'type' => 'internal_link',
                'raw' => $rawPeer,
                'internal_id' => $m[1],
            ];
        }

        if (preg_match('~^(?:https?://)?(?:www\.)?(?:t\.me|telegram\.me)/@?([A-Za-z0-9_]{5,32})$~i', $rawPeer, $m)) {
            return [
                'ok' => true,
                'type' => 'username',
                'raw' => $rawPeer,
                'username' => $m[1],
            ];
        }

        if (preg_match('~^@([A-Za-z0-9_]{5,32})$~', $rawPeer, $m)) {
            return [
                'ok' => true,
                'type' => 'username',
                'raw' => $rawPeer,
                'username' => $m[1],
            ];
        }

        if (preg_match('~^-?\d+$~', $rawPeer)) {
            return [
                'ok' => true,
                'type' => 'id',
                'raw' => $rawPeer,
                'id' => (int) $rawPeer,
            ];
        }

        if (preg_match('~^\+?\d{7,15}$~', $rawPeer)) {
            return [
                'ok' => true,
                'type' => 'phone',
                'raw' => $rawPeer,
                'phone' => $this->normalizePhone($rawPeer),
            ];
        }

        return [
            'ok' => false,
            'type' => 'invalid',
            'raw' => $rawPeer,
            'reason' => 'peer formati noma’lum',
        ];
    }

    public function inspectPeer(string $rawPeer): array
    {
        $peer = $this->parsePeer($rawPeer);

        Log::info('telegram_peer_inspect_start', [
            'raw_peer' => $rawPeer,
            'parsed_type' => $peer['type'] ?? null,
            'parsed_ok' => $peer['ok'] ?? false,
        ]);

        if (!($peer['ok'] ?? false)) {
            $result = $this->failResult('peer_invalid', $peer['reason'] ?? 'peer invalid', $rawPeer);

            Log::info('telegram_peer_inspect_result', [
                'raw_peer' => $rawPeer,
                'result' => $result,
            ]);

            return $result;
        }

        if (!$this->madeline) {
            $result = $this->failResult('madeline_not_initialized', 'session ishlamayapti', $rawPeer);

            Log::warning('telegram_peer_inspect_result', [
                'raw_peer' => $rawPeer,
                'result' => $result,
            ]);

            return $result;
        }

        try {
            $result = match ($peer['type']) {
                'username' => $this->inspectUsername($peer),
                'id' => $this->inspectId($peer),
                'invite_link' => $this->inspectInviteLink($peer),
                'internal_link' => $this->inspectInternalLink($peer),
                'phone' => $this->inspectPhone($peer),
                default => $this->failResult('peer_invalid', 'peer turi noma’lum', $rawPeer),
            };

            Log::info('telegram_peer_inspect_result', [
                'raw_peer' => $rawPeer,
                'result' => [
                    'ok' => $result['ok'] ?? false,
                    'sendable' => $result['sendable'] ?? false,
                    'error_key' => $result['error_key'] ?? null,
                    'reason' => $result['reason'] ?? null,
                    'resolved_peer' => $result['resolved_peer'] ?? null,
                    'peer_type' => $result['peer_type'] ?? null,
                ],
            ]);

            return $result;
        } catch (Throwable $e) {
            $err = $this->cleanError($e->getMessage());
            $key = $this->mapErrorToKey($err);

            if ($key === 'unknown_error') {
                $this->logUnknownError('inspect_peer', $e, [
                    'peer' => $rawPeer,
                    'clean_error' => $err,
                ]);
            }

            $result = $this->failResult($key, $this->humanReason($key), $rawPeer, $err);

            Log::warning('telegram_peer_inspect_result', [
                'raw_peer' => $rawPeer,
                'result' => $result,
            ]);

            return $result;
        }
    }

    public function debugInspectPeers(array $peers, int $limit = 10): array
    {
        $items = array_slice(array_values($peers), 0, $limit);
        $results = [];

        Log::info('telegram_peer_debug_batch_start', [
            'count' => count($items),
            'limit' => $limit,
        ]);

        foreach ($items as $index => $peer) {
            $peer = trim((string) $peer);

            $startedAt = microtime(true);
            $result = $this->inspectPeer($peer);
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            $row = [
                'index' => $index,
                'peer' => $peer,
                'duration_ms' => $durationMs,
                'ok' => $result['ok'] ?? false,
                'sendable' => $result['sendable'] ?? false,
                'error_key' => $result['error_key'] ?? null,
                'reason' => $result['reason'] ?? null,
                'resolved_peer' => $result['resolved_peer'] ?? null,
                'peer_type' => $result['peer_type'] ?? null,
            ];

            $results[] = $row;

            Log::info('telegram_peer_debug_item', $row);
        }

        Log::info('telegram_peer_debug_batch_done', [
            'count' => count($results),
        ]);

        return $results;
    }

    public function sendMessageToPeer(string $rawPeer, string $message, ?array $precheck = null): array
    {
        $inspect = $precheck ?? $this->inspectPeer($rawPeer);

        Log::info('telegram_send_prepare', [
            'raw_peer' => $rawPeer,
            'inspect_ok' => $inspect['ok'] ?? false,
            'inspect_sendable' => $inspect['sendable'] ?? false,
            'inspect_error_key' => $inspect['error_key'] ?? null,
            'inspect_reason' => $inspect['reason'] ?? null,
            'resolved_peer' => $inspect['resolved_peer'] ?? null,
        ]);

        if (!($inspect['ok'] ?? false) || !($inspect['sendable'] ?? false)) {
            return [
                'ok' => false,
                'status' => 'failed',
                'telegram_message_id' => null,
                'error_key' => $inspect['error_key'] ?? 'peer_invalid',
                'result_label' => 'yozolmaymiz',
                'reason' => $inspect['reason'] ?? null,
                'stage' => $inspect['stage'] ?? 'precheck',
                'session_error' => $inspect['session_error'] ?? false,
                'resolved_peer' => null,
            ];
        }

        try {
            $startedAt = microtime(true);

            $response = $this->madeline->messages->sendMessage([
                'peer' => $inspect['resolved_peer'],
                'message' => $message,
                'parse_mode' => 'HTML',
            ]);

            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            [$status, $telegramMessageId] = $this->extractSendResult($response);

            Log::info('telegram_send_result', [
                'raw_peer' => $rawPeer,
                'duration_ms' => $durationMs,
                'status' => $status,
                'telegram_message_id' => $telegramMessageId,
                'resolved_peer' => $inspect['resolved_peer'],
            ]);

            return [
                'ok' => true,
                'status' => $status,
                'telegram_message_id' => $telegramMessageId,
                'error_key' => null,
                'result_label' => 'yozolamiz',
                'reason' => 'xabar yuborildi',
                'stage' => 'sendMessage',
                'session_error' => false,
                'resolved_peer' => $inspect['resolved_peer'],
            ];
        } catch (Throwable $e) {
            $err = $this->cleanError($e->getMessage());
            $key = $this->mapErrorToKey($err);

            if ($key === 'unknown_error') {
                $this->logUnknownError('after_send_message', $e, [
                    'peer' => $rawPeer,
                ]);
            }

            if ($this->isHtmlParseError($err)) {
                try {
                    $startedAt = microtime(true);

                    $response = $this->madeline->messages->sendMessage([
                        'peer' => $inspect['resolved_peer'],
                        'message' => $message,
                    ]);

                    $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
                    [$status, $telegramMessageId] = $this->extractSendResult($response);

                    Log::info('telegram_send_result', [
                        'raw_peer' => $rawPeer,
                        'duration_ms' => $durationMs,
                        'status' => $status,
                        'telegram_message_id' => $telegramMessageId,
                        'resolved_peer' => $inspect['resolved_peer'],
                        'fallback' => 'plain_text',
                    ]);

                    return [
                        'ok' => true,
                        'status' => $status,
                        'telegram_message_id' => $telegramMessageId,
                        'error_key' => null,
                        'result_label' => 'yozolamiz',
                        'reason' => 'xabar yuborildi (plain text fallback)',
                        'stage' => 'sendMessageRetryPlainText',
                        'session_error' => false,
                        'resolved_peer' => $inspect['resolved_peer'],
                    ];
                } catch (Throwable $e2) {
                    $err2 = $this->cleanError($e2->getMessage());
                    $key2 = $this->mapErrorToKey($err2);

                    if ($key2 === 'unknown_error') {
                        Log::error('telegram_unknown_error', [
                            'stage' => 'send_message_retry_plain_text',
                            'peer' => $rawPeer,
                        ]);
                    }

                    return [
                        'ok' => false,
                        'status' => 'failed',
                        'telegram_message_id' => null,
                        'error_key' => $key2,
                        'result_label' => 'yozolmaymiz',
                        'reason' => $this->humanReason($key2),
                        'stage' => 'sendMessageRetryPlainText',
                        'session_error' => $this->isSessionErrorKey($key2),
                        'exception' => $err2,
                        'resolved_peer' => $inspect['resolved_peer'] ?? null,
                    ];
                }
            }

            return [
                'ok' => false,
                'status' => 'failed',
                'telegram_message_id' => null,
                'error_key' => $key,
                'result_label' => 'yozolmaymiz',
                'reason' => $this->humanReason($key),
                'stage' => 'sendMessage',
                'session_error' => $this->isSessionErrorKey($key),
                'exception' => $err,
                'resolved_peer' => $inspect['resolved_peer'] ?? null,
            ];
        }
    }

    private function inspectUsername(array $peer): array
    {
        $username = $peer['username'] ?? '';
        $rawPeer = $peer['raw'] ?? $username;

        Log::info('telegram_inspect_username_start', [
            'raw_peer' => $rawPeer,
            'username' => $username,
        ]);

        try {
            $info = $this->madeline->getInfo($username);

            Log::info('telegram_inspect_username_info', [
                'raw_peer' => $rawPeer,
                'username' => $username,
                'info_type' => is_array($info) ? ($info['type'] ?? $info['_'] ?? null) : null,
                'info_keys' => is_array($info) ? array_keys($info) : null,
            ]);
        } catch (Throwable $e) {
            $err = $this->cleanError($e->getMessage());
            $key = $this->mapErrorToKey($err);

            if ($key === 'unknown_error') {
                $this->logUnknownError('inspect_username', $e, [
                    'peer' => $rawPeer,
                    'username' => $username,
                ]);
            }

            return $this->failResult($key, $this->humanReason($key), $rawPeer, $err);
        }

        return $this->inspectResolvedPeer(
            sourcePeer: $peer,
            info: $info,
            resolvedPeer: $username,
            rawPeer: $rawPeer
        );
    }

    private function inspectId(array $peer): array
    {
        $id = $peer['id'] ?? null;
        $rawPeer = $peer['raw'] ?? (string) $id;

        if ($id === null || $id === '') {
            return $this->failResult('peer_invalid', 'peer id bo‘sh', $rawPeer);
        }

        Log::info('telegram_inspect_id_start', [
            'raw_peer' => $rawPeer,
            'id' => $id,
        ]);

        try {
            $info = $this->madeline->getInfo($id);

            Log::info('telegram_inspect_id_info', [
                'raw_peer' => $rawPeer,
                'id' => $id,
                'info_type' => is_array($info) ? ($info['type'] ?? $info['_'] ?? null) : null,
                'info_keys' => is_array($info) ? array_keys($info) : null,
            ]);
        } catch (Throwable $e) {
            $err = $this->cleanError($e->getMessage());
            $key = $this->mapErrorToKey($err);

            if ($key === 'unknown_error') {
                $this->logUnknownError('inspect_id', $e, [
                    'peer' => $rawPeer,
                    'id' => $id,
                ]);
            }

            return $this->failResult($key, $this->humanReason($key), $rawPeer, $err);
        }

        return $this->inspectResolvedPeer(
            sourcePeer: $peer,
            info: $info,
            resolvedPeer: $id,
            rawPeer: $rawPeer
        );
    }

    private function inspectInternalLink(array $peer): array
    {
        $chatId = $peer['internal_id'] ?? null;
        $rawPeer = $peer['raw'] ?? null;

        if (!$chatId) {
            return $this->failResult('peer_invalid', 'internal link invalid', $rawPeer);
        }

        $resolvedPeer = '-100' . $chatId;

        Log::info('telegram_inspect_internal_link_start', [
            'raw_peer' => $rawPeer,
            'resolved_peer' => $resolvedPeer,
        ]);

        try {
            $info = $this->madeline->getInfo($resolvedPeer);

            Log::info('telegram_inspect_internal_link_info', [
                'raw_peer' => $rawPeer,
                'resolved_peer' => $resolvedPeer,
                'info_type' => is_array($info) ? ($info['type'] ?? $info['_'] ?? null) : null,
                'info_keys' => is_array($info) ? array_keys($info) : null,
            ]);
        } catch (Throwable $e) {
            $err = $this->cleanError($e->getMessage());
            $key = $this->mapErrorToKey($err);

            if ($key === 'unknown_error') {
                $this->logUnknownError('inspect_internal_link', $e, [
                    'peer' => $rawPeer,
                    'resolved_peer' => $resolvedPeer,
                ]);
            }

            return $this->failResult($key, $this->humanReason($key), $rawPeer, $err);
        }

        return $this->inspectResolvedPeer(
            sourcePeer: $peer,
            info: $info,
            resolvedPeer: $resolvedPeer,
            rawPeer: $rawPeer
        );
    }

    private function inspectInviteLink(array $peer): array
    {
        $hash = $peer['hash'] ?? null;
        $rawPeer = $peer['raw'] ?? null;

        if (!$hash) {
            return $this->failResult('invite_invalid', 'invite link yaroqsiz', $rawPeer);
        }

        Log::info('telegram_inspect_invite_start', [
            'raw_peer' => $rawPeer,
            'hash' => $hash,
        ]);

        try {
            $invite = $this->madeline->messages->checkChatInvite([
                'hash' => $hash,
            ]);

            Log::info('telegram_inspect_invite_result', [
                'raw_peer' => $rawPeer,
                'invite_type' => $invite['_'] ?? null,
                'invite_keys' => array_keys($invite ?? []),
            ]);
        } catch (Throwable $e) {
            $err = $this->cleanError($e->getMessage());
            $key = $this->mapErrorToKey($err);

            if ($key === 'unknown_error') {
                $this->logUnknownError('inspect_invite_link', $e, [
                    'peer' => $rawPeer,
                    'hash' => $hash,
                ]);
            }

            return $this->failResult($key, $this->humanReason($key), $rawPeer, $err);
        }

        $type = $invite['_'] ?? null;

        if ($type === 'chatInviteAlready') {
            $chat = $invite['chat'] ?? [];
            $resolvedPeer = $this->resolvePeerIdFromInfo($chat, $rawPeer);

            if ($resolvedPeer === '' || $resolvedPeer === null) {
                return $this->failResult('peer_not_found', 'chat invite already, lekin peer topilmadi', $rawPeer);
            }

            return $this->inspectResolvedPeer(
                sourcePeer: $peer,
                info: $chat,
                resolvedPeer: $resolvedPeer,
                rawPeer: $rawPeer,
                fromInviteAlready: true
            );
        }

        if ($type === 'chatInvite') {
            return $this->failResult('not_member', 'member emas', $rawPeer);
        }

        return $this->failResult('invite_invalid', 'invite link yaroqsiz', $rawPeer);
    }

    private function inspectPhone(array $peer): array
    {
        return $this->failResult(
            'phone_not_supported_directly',
            'telefon raqam peer emas',
            $peer['raw'] ?? null
        );
    }

    private function inspectResolvedPeer(
        array $sourcePeer,
        mixed $info,
        string|int|null $resolvedPeer,
        ?string $rawPeer = null,
        bool $fromInviteAlready = false
    ): array {
        $rawPeer = $rawPeer ?? ($sourcePeer['raw'] ?? null);

        if (!is_array($info)) {
            $info = [];
        }

        $peerType = $this->resolvePeerTypeFromInfo($info);
        $peerId = $this->resolvePeerIdFromInfo($info, $resolvedPeer);

        Log::info('telegram_inspect_resolved_peer', [
            'raw_peer' => $rawPeer,
            'resolved_peer' => $resolvedPeer,
            'peer_id' => $peerId,
            'peer_type' => $peerType,
            'info_keys' => array_keys($info),
            'from_invite_already' => $fromInviteAlready,
        ]);

        if ($peerId === '' || $peerId === null) {
            return $this->failResult('peer_not_found', 'peer topilmadi', $rawPeer);
        }

        if ($peerType === 'user' || $peerType === 'bot') {
            return $this->inspectUserPeer($sourcePeer, $info, $peerId, $rawPeer);
        }

        if (in_array($peerType, ['chat', 'channel', 'supergroup', 'megagroup'], true)) {
            return $this->inspectChatPeer($sourcePeer, $info, $peerId, $rawPeer, $fromInviteAlready);
        }

        if (($info['user_id'] ?? null) || ($info['bot_api_id'] ?? null)) {
            return $this->inspectUserPeer($sourcePeer, $info, $peerId, $rawPeer);
        }

        if (($info['channel_id'] ?? null) || ($info['chat_id'] ?? null) || ($info['peer_id'] ?? null)) {
            return $this->inspectChatPeer($sourcePeer, $info, $peerId, $rawPeer, $fromInviteAlready);
        }

        return $this->failResult('peer_not_found', 'peer topilmadi', $rawPeer);
    }

    private function inspectUserPeer(array $sourcePeer, array $info, string|int $resolvedPeer, ?string $rawPeer = null): array
    {
        $rawPeer = $rawPeer ?? ($sourcePeer['raw'] ?? null);

        Log::info('telegram_inspect_user_peer', [
            'raw_peer' => $rawPeer,
            'resolved_peer' => $resolvedPeer,
            'info_keys' => array_keys($info),
            'deleted' => $info['deleted'] ?? null,
            'blocked' => $info['blocked'] ?? null,
            'restricted' => $info['restricted'] ?? null,
        ]);

        if (($info['deleted'] ?? false) === true) {
            return $this->failResult('peer_not_found', 'foydalanuvchi o‘chirilgan', $rawPeer);
        }

        if (($info['blocked'] ?? false) === true) {
            return $this->failResult('user_is_blocked', 'foydalanuvchi bloklangan', $rawPeer);
        }

        if (($info['restricted'] ?? false) === true) {
            return $this->failResult('restricted', 'foydalanuvchi restricted', $rawPeer);
        }

        return [
            'ok' => true,
            'sendable' => true,
            'peer_type' => 'user',
            'resolved_peer' => $resolvedPeer,
            'error_key' => null,
            'reason' => 'user ok',
            'session_error' => false,
        ];
    }

    private function inspectChatPeer(
        array $sourcePeer,
        array $info,
        string|int $resolvedPeer,
        ?string $rawPeer = null,
        bool $fromInviteAlready = false
    ): array {
        $rawPeer = $rawPeer ?? ($sourcePeer['raw'] ?? null);

        Log::info('telegram_inspect_chat_peer_start', [
            'raw_peer' => $rawPeer,
            'resolved_peer' => $resolvedPeer,
            'from_invite_already' => $fromInviteAlready,
            'info_keys' => array_keys($info),
        ]);

        $fullInfo = $this->safeGetFullInfo($resolvedPeer);

        Log::info('telegram_inspect_chat_peer_fullinfo', [
            'raw_peer' => $rawPeer,
            'resolved_peer' => $resolvedPeer,
            'fullinfo_is_array' => is_array($fullInfo),
            'fullinfo_keys' => is_array($fullInfo) ? array_keys($fullInfo) : null,
            'fullinfo_top_keys' => is_array($fullInfo) ? array_slice(array_keys($fullInfo), 0, 15) : null,
        ]);

        $chat = $this->extractChatBlock($fullInfo);

        if (!$chat) {
            Log::warning('telegram_inspect_chat_peer_missing_chat_block', [
                'raw_peer' => $rawPeer,
                'resolved_peer' => $resolvedPeer,
                'fullinfo_keys' => is_array($fullInfo) ? array_keys($fullInfo) : null,
                'fullinfo_dump_short' => is_array($fullInfo) ? array_slice($fullInfo, 0, 5, true) : null,
            ]);

            return $this->failResult('chat_info_missing', 'chat ma’lumoti topilmadi', $rawPeer);
        }

        $participant = $this->safeGetParticipant($resolvedPeer);

        Log::info('telegram_inspect_chat_peer_participant', [
            'raw_peer' => $rawPeer,
            'resolved_peer' => $resolvedPeer,
            'participant_is_null' => $participant === null,
            'participant_type' => is_array($participant)
                ? ($participant['participant']['_'] ?? $participant['_'] ?? null)
                : null,
            'participant_keys' => is_array($participant) ? array_keys($participant) : null,
        ]);

        if (!$fromInviteAlready) {
            if ($participant === null) {
                if (($chat['left'] ?? false) === true || ($chat['kicked'] ?? false) === true) {
                    return $this->failResult('not_member', 'member emas', $rawPeer);
                }

                if (($chat['deactivated'] ?? false) === true) {
                    return $this->failResult('peer_not_found', 'chat deaktivatsiya qilingan', $rawPeer);
                }

                return $this->failResult('not_member', 'member emas', $rawPeer);
            }

            if ($this->isLeftOrBannedParticipant($participant)) {
                return $this->failResult('not_member', 'member emas', $rawPeer);
            }
        }

        $isAdmin = $this->isAdminOrCreator($participant);
        $isBroadcast = (bool) ($chat['broadcast'] ?? false);
        $slowmodeSeconds = (int) ($chat['slowmode_seconds'] ?? 0);
        $canSendMessages = $chat['can_send_messages'] ?? null;
        $defaultBannedRights = $chat['default_banned_rights'] ?? [];

        Log::info('telegram_inspect_chat_peer_rules', [
            'raw_peer' => $rawPeer,
            'resolved_peer' => $resolvedPeer,
            'is_admin' => $isAdmin,
            'is_broadcast' => $isBroadcast,
            'slowmode_seconds' => $slowmodeSeconds,
            'can_send_messages' => $canSendMessages,
            'default_banned_rights' => $defaultBannedRights,
        ]);

        if ($isBroadcast && !$isAdmin) {
            return $this->failResult('chat_write_forbidden', 'broadcast kanalga faqat admin yozishi mumkin', $rawPeer);
        }

        if (!$isAdmin && $this->isWriteForbiddenByDefaults($canSendMessages, $defaultBannedRights)) {
            return $this->failResult('chat_write_forbidden', 'guruh/channel yozishni taqiqlagan', $rawPeer);
        }

        return [
            'ok' => true,
            'sendable' => true,
            'peer_type' => $this->resolvePeerTypeFromInfo($info) ?? 'chat',
            'resolved_peer' => $resolvedPeer,
            'error_key' => null,
            'reason' => $slowmodeSeconds > 0
                ? "slowmode bor ({$slowmodeSeconds}s)"
                : 'member ok',
            'slowmode_seconds' => $slowmodeSeconds > 0 ? $slowmodeSeconds : null,
            'session_error' => false,
        ];
    }

    private function safeGetParticipant(string|int $channel): mixed
    {
        try {
            $result = $this->madeline->channels->getParticipant([
                'channel' => $channel,
                'participant' => 'me',
            ]);

            return $result;
        } catch (Throwable $e) {
            Log::warning('telegram_safe_get_participant_failed', [
                'channel' => $channel,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function safeGetFullInfo(string|int $peer): mixed
    {
        try {
            $result = $this->madeline->getFullInfo($peer);

            return $result;
        } catch (Throwable $e) {
            Log::warning('telegram_safe_get_full_info_failed', [
                'peer' => $peer,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function extractParticipantBlock(mixed $participant): array
    {
        if (!is_array($participant)) {
            return [];
        }

        if (isset($participant['participant']) && is_array($participant['participant'])) {
            return $participant['participant'];
        }

        return $participant;
    }

    private function extractChatBlock(mixed $fullInfo): ?array
    {
        if (!is_array($fullInfo)) {
            return null;
        }

        foreach (['Chat', 'chat', 'full_chat', 'fullChat', 'full'] as $key) {
            if (isset($fullInfo[$key]) && is_array($fullInfo[$key])) {
                return $fullInfo[$key];
            }
        }

        return null;
    }

    private function resolvePeerIdFromInfo(array $info, string|int|null $fallback): string|int
    {
        foreach ([
            'bot_api_id',
            'peer_id',
            'channel_id',
            'chat_id',
            'user_id',
            'id',
        ] as $key) {
            if (array_key_exists($key, $info) && $info[$key] !== null && $info[$key] !== '') {
                return $info[$key];
            }
        }

        return $fallback ?? '';
    }

    private function resolvePeerTypeFromInfo(array $info): ?string
    {
        $type = $info['type'] ?? $info['_'] ?? null;

        if (is_string($type) && $type !== '') {
            return match ($type) {
                'megagroup' => 'supergroup',
                'chatInviteAlready' => 'chat',
                default => $type,
            };
        }

        if (($info['bot_api_id'] ?? null) !== null || ($info['user_id'] ?? null) !== null) {
            return 'user';
        }

        if (($info['channel_id'] ?? null) !== null) {
            return !empty($info['broadcast']) ? 'channel' : (!empty($info['megagroup']) ? 'supergroup' : 'channel');
        }

        if (($info['chat_id'] ?? null) !== null) {
            return 'chat';
        }

        return null;
    }

    private function isLeftOrBannedParticipant(mixed $participant): bool
    {
        $block = $this->extractParticipantBlock($participant);
        $type = $block['_'] ?? null;

        if (in_array($type, [
            'channelParticipantBanned',
            'channelParticipantLeft',
            'chatParticipantLeft',
        ], true)) {
            return true;
        }

        if (($block['banned_rights'] ?? null) && is_array($block['banned_rights'])) {
            if (($block['banned_rights']['view_messages'] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    private function isAdminOrCreator(mixed $participant): bool
    {
        $block = $this->extractParticipantBlock($participant);
        $type = $block['_'] ?? null;

        if (in_array($type, ['channelParticipantCreator', 'channelParticipantAdmin'], true)) {
            return true;
        }

        if (in_array($type, ['chatParticipantCreator', 'chatParticipantAdmin'], true)) {
            return true;
        }

        if (!empty($block['admin_rights'])) {
            return true;
        }

        return false;
    }

    private function isWriteForbiddenByDefaults(mixed $canSendMessages, array $defaultBannedRights): bool
    {
        if ($canSendMessages === false) {
            return true;
        }

        if (($defaultBannedRights['send_messages'] ?? false) === true) {
            return true;
        }

        return false;
    }

    private function extractSendResult(mixed $response): array
    {
        $telegramMessageId = null;
        $status = 'sent';

        if (!is_array($response)) {
            return [$status, null];
        }

        $type = $response['_'] ?? null;

        if (in_array($type, ['updateShortSentMessage', 'updateShortMessage', 'updateShortChatMessage'], true)) {
            $telegramMessageId = $response['id'] ?? null;

            if ($type === 'updateShortSentMessage' && (($response['scheduled'] ?? false) === true)) {
                $status = 'scheduled';
            }

            return [$status, $telegramMessageId];
        }

        if (!empty($response['updates']) && is_array($response['updates'])) {
            foreach ($response['updates'] as $update) {
                if (!is_array($update)) {
                    continue;
                }

                $updateType = $update['_'] ?? null;

                if ($updateType === 'updateNewScheduledMessage' && isset($update['message']['id'])) {
                    $status = 'scheduled';
                    $telegramMessageId = $update['message']['id'];
                    break;
                }

                if (in_array($updateType, [
                    'updateNewMessage',
                    'updateNewChannelMessage',
                    'updateEditMessage',
                    'updateEditChannelMessage',
                ], true)) {
                    $message = $update['message'] ?? null;

                    if (is_array($message) && isset($message['id'])) {
                        $telegramMessageId = $message['id'];
                        break;
                    }
                }
            }
        }

        return [$status, $telegramMessageId];
    }

    private function failResult(string $errorKey, string $reason, ?string $peer = null, ?string $exception = null): array
    {
        return [
            'ok' => false,
            'sendable' => false,
            'resolved_peer' => $peer,
            'error_key' => $errorKey,
            'result_label' => 'yozolmaymiz',
            'reason' => $reason,
            'exception' => $exception,
            'session_error' => $this->isSessionErrorKey($errorKey),
        ];
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\s+/', '', $phone);
    }

    public function mapErrorToKey(string $err): string
    {
        $e = strtolower($err);

        if (preg_match('/flood[_ ]?wait[_ ]?(\d+)/i', $err, $m)) {
            return !empty($m[1]) ? "flood_wait_{$m[1]}" : 'flood_wait';
        }

        if (preg_match('/slowmode[_ ]?wait[_ ]?(\d+)/i', $err, $m)) {
            return !empty($m[1]) ? "slowmode_wait_{$m[1]}" : 'slowmode_wait';
        }

        if (str_contains($e, 'peer flood')) {
            return 'peer_flood';
        }

        if (
            str_contains($e, 'not member') ||
            str_contains($e, 'not_member') ||
            str_contains($e, 'member is not a participant') ||
            str_contains($e, 'user not participant') ||
            str_contains($e, 'participant not found') ||
            str_contains($e, 'user is not a participant')
        ) {
            return 'not_member';
        }

        if (
            str_contains($e, 'chat_write_forbidden') ||
            str_contains($e, 'chat write forbidden') ||
            str_contains($e, 'chat admin required') ||
            str_contains($e, 'CHAT_SEND_PLAIN_FORBIDDEN') ||
            str_contains($e, 'chat_send_plain_forbidden') ||
            str_contains($e, 'send plain') ||
            str_contains($e, 'write forbidden')
        ) {
            return 'chat_write_forbidden';
        }

        if (str_contains($e, 'user_banned_in_channel')) {
            return 'user_banned_in_channel';
        }

        if (str_contains($e, 'user is blocked') || str_contains($e, 'user_is_blocked') || str_contains($e, 'bot was blocked')) {
            return 'user_is_blocked';
        }

        if (str_contains($e, 'topic closed')) {
            return 'topic_closed';
        }

        if (str_contains($e, 'topic deleted')) {
            return 'topic_deleted';
        }

        if (str_contains($e, 'auth_key_unregistered') || str_contains($e, 'session_revoked') || str_contains($e, 'auth_key_invalid')) {
            return 'auth_key_invalid';
        }

        if (preg_match('/timeout|timed out|connection.*reset|broken pipe|could not connect/i', $e)) {
            return 'network_error';
        }

        if (
            str_contains($e, 'peer is not present in the internal peer database') ||
            str_contains($e, 'peer not found') ||
            str_contains($e, 'chat not found') ||
            str_contains($e, 'group not found')
        ) {
            return 'peer_not_found';
        }

        if (str_contains($e, 'madeline api is null') || str_contains($e, 'getinfo() on null')) {
            return 'madeline_not_initialized';
        }

        if (str_contains($e, 'invite hash invalid') || str_contains($e, 'invite link')) {
            return 'invite_invalid';
        }

        if (str_contains($e, 'phone number invalid') || str_contains($e, 'phone not supported')) {
            return 'phone_not_supported_directly';
        }

        return 'unknown_error';
    }

    public function isSessionErrorKey(string $errorKey): bool
    {
        return in_array($errorKey, [
            'auth_key_invalid',
            'phone_code_expired',
            'auth_key_unregistered',
            'session_revoked',
            'network_error',
            'madeline_not_initialized',
        ], true);
    }

    private function isHtmlParseError(string $err): bool
    {
        $e = strtolower($err);

        return str_contains($e, 'can\'t parse entities')
            || str_contains($e, 'cannot parse entities')
            || str_contains($e, 'parse entities')
            || str_contains($e, 'entity')
            || str_contains($e, 'tag');
    }

    private function humanReason(string $errorKey): string
    {
        return match ($errorKey) {
            'peer_invalid' => 'peer noto‘g‘ri',
            'chat_write_forbidden' => 'chat/channel yozishni taqiqlagan',
            'not_member' => 'guruh a’zosi emas',
            'chat_info_missing' => 'chat ma’lumoti topilmadi',
            'slowmode_wait' => 'slowmode bor',
            'peer_flood' => 'telegram limit',
            'flood_wait' => 'flood wait',
            'user_banned_in_channel' => 'kanalda yozish taqiqlangan',
            'user_is_blocked' => 'foydalanuvchi bloklagan',
            'topic_closed' => 'topic yopilgan',
            'topic_deleted' => 'topic o‘chirilgan',
            'peer_not_found' => 'peer topilmadi',
            'auth_key_invalid' => 'session buzilgan',
            'network_error' => 'tarmoq xatosi',
            'madeline_not_initialized' => 'madeline yoqilmagan',
            'invite_invalid' => 'invite link yaroqsiz',
            'phone_not_supported_directly' => 'telefon raqamni direkt peer qilib bo‘lmaydi',
            'restricted' => 'cheklangan',
            default => $errorKey,
        };
    }

    private function cleanError(string $err): string
    {
        return trim(preg_replace('/\s+/', ' ', $err));
    }

    private function logUnknownError(string $stage, Throwable $e, array $context = []): void
    {
        Log::error('telegram_unknown_error', [
            'stage' => $stage,
            'message' => $e->getMessage(),
            'context' => $context,
        ]);
    }
}