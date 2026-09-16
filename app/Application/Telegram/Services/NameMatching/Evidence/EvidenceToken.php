<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services\NameMatching\Evidence;

use App\Application\Telegram\Services\NameMatching\Token;

/**
 * A Telegram-side token tagged with the field it came from.
 */
final class EvidenceToken
{
    public function __construct(
        public readonly Token $token,
        public readonly EvidenceSource $source,
    ) {}
}
