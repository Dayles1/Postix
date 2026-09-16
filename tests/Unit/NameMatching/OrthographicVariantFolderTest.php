<?php

declare(strict_types=1);

namespace Tests\Unit\NameMatching;

use App\Application\Telegram\Services\NameMatching\Support\OrthographicVariantFolder;
use PHPUnit\Framework\TestCase;

final class OrthographicVariantFolderTest extends TestCase
{
    private OrthographicVariantFolder $folder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->folder = new OrthographicVariantFolder;
    }

    public function test_zh_and_j_fold_to_the_same_canonical_form(): void
    {
        $this->assertSame(
            $this->folder->fold('ikramzhon'),
            $this->folder->fold('ikramjon'),
        );
    }

    public function test_kh_x_and_h_fold_to_the_same_canonical_form(): void
    {
        $canonical = $this->folder->fold('hamid');

        $this->assertSame($canonical, $this->folder->fold('khamid'));
        $this->assertSame($canonical, $this->folder->fold('xamid'));
    }

    public function test_q_and_k_fold_to_the_same_canonical_form(): void
    {
        $this->assertSame(
            $this->folder->fold('kodir'),
            $this->folder->fold('qodir'),
        );
    }

    public function test_gh_and_g_fold_to_the_same_canonical_form(): void
    {
        $this->assertSame(
            $this->folder->fold('gofur'),
            $this->folder->fold('ghofur'),
        );
    }

    public function test_folding_does_not_collapse_unrelated_words(): void
    {
        $this->assertNotSame(
            $this->folder->fold('namanjanovich'),
            $this->folder->fold('sarvenaz'),
        );
    }

    public function test_stretched_vowels_collapse_in_canonical_form(): void
    {
        $this->assertSame(
            $this->folder->fold('ikramjon'),
            $this->folder->fold('ikraamjoon'),
        );
    }

    public function test_patronymic_suffix_is_stripped_from_core_form_only(): void
    {
        $core = $this->folder->foldCore('abdurahimovich');

        $this->assertSame($this->folder->fold('abdurahim'), $core);

        // fold() (no suffix stripping) must keep the full form intact.
        $this->assertNotSame($core, $this->folder->fold('abdurahimovich'));
    }

    public function test_short_words_ending_in_suffix_pattern_are_not_stripped_to_nothing(): void
    {
        // "ovna" alone must not be treated as "root + suffix" and be
        // reduced to an empty/near-empty string.
        $core = $this->folder->foldCore('ovna');

        $this->assertNotSame('', $core);
    }
}
