<?php

declare(strict_types=1);

namespace Tests\Feature\Telegram;

use App\Services\ErrorKeyService;
use App\Services\Telegram\TelegramPeerMessagingService;
use danog\MadelineProto\RPCError\FloodWaitError;
use danog\MadelineProto\RPCErrorException;
use ReflectionClass;
use RuntimeException;
use Tests\TestCase;

final class SendErrorKeyTest extends TestCase
{
    private TelegramPeerMessagingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new TelegramPeerMessagingService();
    }

    /**
     * MadelineProto 8 puts the bare RPC code into getMessage() and the
     * English text into $description — build one the way it does, without
     * going through make(), which phones home for unknown codes.
     */
    private function rpcError(string $rpc): RPCErrorException
    {
        $e = (new ReflectionClass(RPCErrorException::class))->newInstanceWithoutConstructor();

        (function () use ($rpc) {
            $this->rpc = $rpc;
            $this->description = 'description of ' . $rpc;
            $this->message = $rpc;
        })->call($e);

        return $e;
    }

    public function test_it_maps_current_telegram_rpc_codes(): void
    {
        $cases = [
            'PEER_FLOOD' => 'peer_flood',
            'TOPIC_CLOSED' => 'topic_closed',
            'CHAT_WRITE_FORBIDDEN' => 'chat_write_forbidden',
            'CHAT_SEND_PLAIN_FORBIDDEN' => 'chat_write_forbidden',
            'CHANNEL_PRIVATE' => 'channel_private',
            'USER_PRIVACY_RESTRICTED' => 'user_privacy_restricted',
            'ALLOW_PAYMENT_REQUIRED' => 'allow_payment_required',
            'USERNAME_NOT_OCCUPIED' => 'peer_not_found',
            'SLOWMODE_WAIT_75' => 'slowmode_wait_75',
            'AUTH_KEY_UNREGISTERED' => 'auth_key_invalid',
        ];

        foreach ($cases as $rpc => $key) {
            $this->assertSame($key, $this->service->mapErrorToKey($this->rpcError($rpc)), $rpc);
        }
    }

    public function test_flood_wait_keeps_the_wait_time(): void
    {
        $e = new FloodWaitError('FLOOD_WAIT_120', 120, 420, 'messages.sendMessage');

        $this->assertSame('flood_wait_120', $this->service->mapErrorToKey($e));
    }

    public function test_unmapped_rpc_code_is_kept_instead_of_unknown(): void
    {
        $key = $this->service->mapErrorToKey($this->rpcError('CHAT_SEND_MEDIA_FORBIDDEN'));

        $this->assertSame('chat_send_media_forbidden', $key);
        $this->assertSame(
            'Telegram xatosi: CHAT_SEND_MEDIA_FORBIDDEN',
            (new ErrorKeyService())->translateErrorKey($key, 'uz')
        );
    }

    public function test_madeline_plain_text_errors_still_map(): void
    {
        $e = new RuntimeException('This peer is not present in the internal peer database');

        $this->assertSame('peer_not_found', $this->service->mapErrorToKey($e));
        $this->assertSame('unknown_error', $this->service->mapErrorToKey(new RuntimeException('something odd happened')));
    }

    public function test_every_key_the_sender_saves_has_a_translation(): void
    {
        $mapped = array_values((new ReflectionClass(TelegramPeerMessagingService::class))->getConstant('RPC_ERROR_KEYS'));

        // keys written directly by the send command and peer inspection
        $internal = [
            'user_phone_not_found', 'session_invalid', 'peer_invalid', 'madeline_not_initialized',
            'chat_info_missing', 'not_member', 'restricted', 'user_is_blocked', 'invite_invalid',
            'phone_not_supported_directly', 'phone_code_expired', 'unknown_error',
        ];

        foreach (['uz', 'ru', 'en'] as $locale) {
            foreach (array_unique([...$mapped, ...$internal]) as $key) {
                $this->assertNotSame(
                    "messages.errors.$key",
                    __("messages.errors.$key", [], $locale),
                    "$locale: messages.errors.$key is missing"
                );
            }
        }
    }
}
