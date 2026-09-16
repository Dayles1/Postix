<?php

declare(strict_types=1);

namespace Tests\Unit\NameMatching;

use App\Application\Telegram\Services\NameMatching\Support\StringMetrics;
use PHPUnit\Framework\TestCase;

final class StringMetricsTest extends TestCase
{
    public function test_damerau_levenshtein_identical_strings(): void
    {
        $this->assertSame(0, StringMetrics::damerauLevenshtein('ikramjon', 'ikramjon'));
    }

    public function test_damerau_levenshtein_single_substitution(): void
    {
        $this->assertSame(1, StringMetrics::damerauLevenshtein('bekzod', 'bekhod'));
    }

    public function test_damerau_levenshtein_single_deletion(): void
    {
        $this->assertSame(1, StringMetrics::damerauLevenshtein('bekhzod', 'bekzod'));
    }

    /**
     * A transposition of two adjacent letters costs 1 under
     * Damerau-Levenshtein, vs 2 under plain Levenshtein -- this is the
     * whole point of using the transposition-aware variant.
     */
    public function test_damerau_levenshtein_adjacent_transposition_costs_one(): void
    {
        $this->assertSame(1, StringMetrics::damerauLevenshtein('ikarmjon', 'ikramjon'));
        $this->assertSame(2, levenshtein('ikarmjon', 'ikramjon'));
    }

    public function test_damerau_levenshtein_completely_different_strings_is_large(): void
    {
        $distance = StringMetrics::damerauLevenshtein('namanjanovich', 'sarvenaz');

        $this->assertGreaterThan(5, $distance);
    }

    public function test_jaro_winkler_identical_strings_is_one(): void
    {
        $this->assertSame(1.0, StringMetrics::jaroWinkler('ikramjon', 'ikramjon'));
    }

    public function test_jaro_winkler_rewards_shared_prefix(): void
    {
        $sharedPrefix = StringMetrics::jaroWinkler('ikramjon', 'ikramzhon');
        $noSharedPrefix = StringMetrics::jaroWinkler('ikramjon', 'nozhkarim');

        $this->assertGreaterThan($noSharedPrefix, $sharedPrefix);
    }

    public function test_jaro_winkler_unrelated_strings_is_low(): void
    {
        $this->assertLessThan(0.5, StringMetrics::jaroWinkler('namanjanovich', 'sarvenaz'));
    }

    public function test_jaro_winkler_empty_string_is_zero(): void
    {
        $this->assertSame(0.0, StringMetrics::jaroWinkler('', 'ikramjon'));
    }
}
