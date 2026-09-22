<?php

declare(strict_types=1);

namespace Tests\Unit\NameMatching;

use App\Application\Telegram\Services\NameMatching\Support\OrthographicVariantFolder;
use App\Application\Telegram\Services\NameMatching\Support\PhoneticKeyBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PhoneticKeyBuilderTest extends TestCase
{
    private PhoneticKeyBuilder $builder;

    private OrthographicVariantFolder $folder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = new PhoneticKeyBuilder;
        $this->folder = new OrthographicVariantFolder;
    }

    private function key(string $token): string
    {
        return $this->builder->build($this->folder->fold($token));
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function sameNameProvider(): iterable
    {
        yield 'uzbek o vs russian a' => ['odil', 'adil'];
        yield 'yodgor / yadgor' => ['yodgor', 'yadgor'];
        yield 'anvar / anvor' => ['anvar', 'anvor'];
        yield 'rustam / rustom' => ['rustam', 'rustom'];
        yield 'sobir / sabir' => ['sobir', 'sabir'];
        yield 'front vowel e vs i' => ['elyor', 'ilyor'];
        yield 'front vowel i vs e mid-word' => ['shermat', 'shirmat'];
    }

    #[DataProvider('sameNameProvider')]
    public function test_one_name_spelled_two_ways_gets_one_key(string $a, string $b): void
    {
        $this->assertSame($this->key($a), $this->key($b));
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function differentNameProvider(): iterable
    {
        yield 'different consonants' => ['max', 'sam'];
        yield 'unrelated given names' => ['akmal', 'ikrom'];
        yield 'unrelated surnames' => ['aliev', 'karimov'];
        yield 'u is not folded into a' => ['umid', 'omad'];
        yield 'different length' => ['bek', 'bekzod'];
    }

    #[DataProvider('differentNameProvider')]
    public function test_different_names_keep_different_keys(string $a, string $b): void
    {
        $this->assertNotSame($this->key($a), $this->key($b));
    }

    public function test_the_two_vowel_classes_are_not_merged_with_each_other(): void
    {
        // a/o is one axis and e/i/y another; merging them as well would
        // make nearly every short name collide. KARIM vs KERIM is a
        // one-character spelling variation and belongs to the
        // edit-distance tier, not here.
        $this->assertNotSame($this->key('karim'), $this->key('kerim'));
    }

    public function test_empty_token_has_an_empty_key(): void
    {
        $this->assertSame('', $this->builder->build(''));
    }
}
