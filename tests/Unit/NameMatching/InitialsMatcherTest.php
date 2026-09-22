<?php

declare(strict_types=1);

namespace Tests\Unit\NameMatching;

use App\Application\Telegram\Services\NameMatching\Evidence\InitialsEvidence;
use App\Application\Telegram\Services\NameMatching\Evidence\InitialsMatcher;
use App\Application\Telegram\Services\NameMatching\NameTokenizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InitialsMatcherTest extends TestCase
{
    private InitialsMatcher $matcher;

    private NameTokenizer $tokenizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->matcher = new InitialsMatcher;
        $this->tokenizer = new NameTokenizer;
    }

    private function match(string $driverName, string $telegramName): ?InitialsEvidence
    {
        return $this->matcher->match(
            $this->tokenizer->tokenize($driverName),
            $telegramName,
        );
    }

    public function test_three_initials_out_of_order_match_the_whole_name(): void
    {
        $evidence = $this->match('BOBOJONOV KUDRAT ABDULLAEVICH', 'К.Б.А');

        $this->assertNotNull($evidence);
        $this->assertGreaterThanOrEqual(75.0, $evidence->decision->score);
        $this->assertSame('initials_match', $evidence->decision->decision);
        $this->assertCount(3, $evidence->assignments);
    }

    public function test_repeated_initials_match_distinct_name_parts(): void
    {
        $evidence = $this->match('АҲМАДИЁВ АНВАР АЛИ ЎҒЛИ', 'А,А,А,');

        $this->assertNotNull($evidence);
        $this->assertGreaterThanOrEqual(75.0, $evidence->decision->score);

        $matchedParts = array_map(
            static fn ($assignment): string => $assignment->driverToken->displayUpper(),
            $evidence->assignments,
        );

        $this->assertSame(['AHMADIYOV', 'ANVAR', 'ALI'], $matchedParts);
    }

    public function test_emoji_and_separators_around_the_initials_are_ignored(): void
    {
        $evidence = $this->match('BOBOJONOV KUDRAT ABDULLAEVICH', '🇺🇿🕋🚛 К.Б.А');

        $this->assertNotNull($evidence);
    }

    public function test_initials_in_document_order_score_slightly_higher(): void
    {
        $inOrder = $this->match('BOBOJONOV KUDRAT ABDULLAEVICH', 'Б.К.А');
        $shuffled = $this->match('BOBOJONOV KUDRAT ABDULLAEVICH', 'К.Б.А');

        $this->assertNotNull($inOrder);
        $this->assertNotNull($shuffled);
        $this->assertGreaterThan($shuffled->decision->score, $inOrder->decision->score);
    }

    public function test_a_cyrillic_initial_stands_for_its_latin_spelling_variants(): void
    {
        // К stands for the Q of QODIROV exactly as it stands for a K:
        // the two letters are one as far as spelling variance goes.
        $this->assertNotNull($this->match('QODIROV SHERZOD AKMALOVICH', 'К.Ш.А'));
    }

    public function test_two_initials_are_evidence_but_not_a_match(): void
    {
        $evidence = $this->match('ALIYEV BEKZOD', 'A.B.');

        $this->assertNotNull($evidence);
        $this->assertGreaterThan(0.0, $evidence->decision->score);
        $this->assertLessThan(75.0, $evidence->decision->score);
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function notInitialsProvider(): iterable
    {
        yield 'a real name' => ['BOBOJONOV KUDRAT ABDULLAEVICH', 'Kudrat'];
        yield 'two real names' => ['BOBOJONOV KUDRAT ABDULLAEVICH', 'Kudrat Bobojonov'];
        yield 'a name next to an initial' => ['BOBOJONOV KUDRAT ABDULLAEVICH', 'K. Bobojonov'];
        yield 'one initial only' => ['BOBOJONOV KUDRAT ABDULLAEVICH', 'Б.'];
        yield 'emoji only' => ['BOBOJONOV KUDRAT ABDULLAEVICH', '🖤🖤🖤'];
        yield 'digits' => ['BOBOJONOV KUDRAT ABDULLAEVICH', '1.2.3'];
    }

    #[DataProvider('notInitialsProvider')]
    public function test_display_names_that_are_not_initials_produce_nothing(
        string $driverName,
        string $telegramName,
    ): void {
        $this->assertNull($this->match($driverName, $telegramName));
    }

    public function test_an_initial_that_belongs_to_no_name_part_rejects_the_whole_monogram(): void
    {
        // B and A both fit, C fits nothing -- so this is somebody else's
        // monogram, not a partial match.
        $this->assertNull($this->match('BOBOJONOV KUDRAT ABDULLAEVICH', 'A.B.C'));
    }

    public function test_more_initials_than_name_parts_reject_the_monogram(): void
    {
        $this->assertNull($this->match('ALIYEV AKMAL', 'A.A.A.'));
    }

    public function test_each_initial_needs_its_own_name_part(): void
    {
        // Two A's against a name with a single A-part: the second has
        // nothing left to stand for.
        $this->assertNull($this->match('ALIYEV BEKZOD', 'A.A.'));
    }
}
