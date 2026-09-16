<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services\NameMatching\Comparison;

/**
 * The outcome of comparing two tokens: a bounded score plus an
 * explainable "kind" and a plain-English reason. Every
 * {@see TokenComparator} produces one of these (or {@see self::none()}
 * when it has no opinion).
 */
final class ComparisonResult
{
    private function __construct(
        public readonly float $score,
        public readonly string $kind,
        public readonly string $reason,
    ) {}

    public static function make(float $score, string $kind, string $reason): self
    {
        return new self(round(max(0.0, min(100.0, $score)), 2), $kind, $reason);
    }

    public static function none(): self
    {
        return new self(0.0, 'none', 'No match');
    }

    public function isMeaningful(): bool
    {
        return $this->score > 0.0;
    }
}
