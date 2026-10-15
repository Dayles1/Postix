<?php

namespace Tests\Unit\Telegram;

use App\Application\Telegram\Services\UzbekScript;
use PHPUnit\Framework\TestCase;

/**
 * Uzbek in Latin written in Cyrillic: what the bot sends to people.
 */
final class UzbekScriptTest extends TestCase
{
    public function test_the_phrases_the_bot_says(): void
    {
        $cases = [
            "Assalomu alaykum, Ali aka!" => "Ассалому алайкум, Али ака!",
            "Va alaykum assalom" => "Ва алайкум ассалом",
            "Rahmat, katta rahmat" => "Рахмат, катта рахмат",
            "Xayrli tong, xayrli kun" => "Хайрли тонг, хайрли кун",
            "Zo'r, rahmat!" => "Зур, рахмат!",
            "Yaxshi, kelishdik" => "Яхши, келишдик",
            "Bo'ldi, boshqaga beraymi?" => "Булди, бошкага берайми?",
            "Javob kutyapman, Afzal aka" => "Жавоб кутяпман, Афзал ака",
            "Iltimos, javob bering" => "Илтимос, жавоб беринг",
            "Ertalab, Ukam" => "Эрталаб, Укам",
            "To'g'ri, a'lo" => "Тугри, аъло",
            "Shahar, choy, Chilonzor" => "Шахар, чой, Чилонзор",
            "Yuz, yo'q, yetdi" => "Юз, йук, етди",
        ];

        foreach ($cases as $latin => $cyrillic) {
            $this->assertSame($cyrillic, UzbekScript::toCyrillic($latin), $latin);
        }
    }

    public function test_the_apostrophe_may_be_any_of_the_usual_ones(): void
    {
        foreach (["Zo'r", 'Zo’r', 'Zoʻr', 'Zoʼr'] as $word) {
            $this->assertSame('Зур', UzbekScript::toCyrillic($word));
        }
    }

    public function test_capitals_stay_capitals(): void
    {
        $this->assertSame('Шахар ШАХАР', UzbekScript::toCyrillic('Shahar SHAHAR'));
        $this->assertSame('Узбекистон', UzbekScript::toCyrillic("O'zbekiston"));
    }

    public function test_what_is_not_a_word_is_left_alone(): void
    {
        $this->assertSame('Рахмат, {address} 👍 12:30', UzbekScript::toCyrillic('Rahmat, {address} 👍 12:30'));
        $this->assertSame('<b>Рахмат</b> &amp; Салом', UzbekScript::toCyrillic('<b>Rahmat</b> &amp; Salom'));
        $this->assertSame('Рахмат & Жерри', UzbekScript::toCyrillic('Rahmat & Jerri'));
        $this->assertSame('Спасибо, Салом', UzbekScript::toCyrillic('Спасибо, Salom'), 'Cyrillic passes through');
    }
}
