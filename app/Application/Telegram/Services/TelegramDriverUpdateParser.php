<?php

declare(strict_types=1);

namespace App\Application\Telegram\Services;

/**
 * Reads a "👤 Изменены данные водителя" message.
 *
 *   #UPDATED  RAJABOV  JONIBEK SHODIEVICH
 *
 *   👤 Изменены данные водителя
 *
 *   • Номер телефона 1
 *   +998944491141 ⟶ +998952760019
 *
 *   Пользователь:
 *   MUSAYEV ISMOIL DILSHOD O'G'LI
 *
 * Only the change to "Номер телефона 1" matters: that is the phone the
 * driver check resolves (see TelegramDriverMessageParser), and a new
 * one is what operators fix after a check has flagged the driver.
 */
final class TelegramDriverUpdateParser
{
    /**
     * The arrows the source system has been seen to put between the old
     * and the new value.
     */
    private const ARROWS = '⟶|→|->|=>|—>|➡️|➡';

    public function __construct(
        private readonly TelegramDriverMessageParser $driverParser,
    ) {
    }

    /**
     * @return array{
     *     driver_name: string|null,
     *     old_phone_raw: string|null,
     *     old_phone_normalized: string|null,
     *     new_phone_raw: string,
     *     new_phone_normalized: string,
     * }|null  null when the message does not change the phone
     */
    public function parsePhoneChange(string $text): ?array
    {
        $pattern = '/Номер\s+телефона(?:\s*(\d+))?\s*:?\s*\R?\s*'
            . '([^\r\n]*?)\s*(?:' . self::ARROWS . ')\s*([^\r\n]+)/iu';

        if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER) === 0) {
            return null;
        }

        foreach ($matches as $match) {
            $index = $match[1] !== '' ? (int) $match[1] : 1;

            if ($index !== 1) {
                continue;
            }

            $newRaw = $this->cleanPhone($match[3]);
            $newNormalized = $this->driverParser->normalizePhone($newRaw);

            if ($newNormalized === null) {
                return null;
            }

            $oldRaw = $this->cleanPhone($match[2]);
            $oldNormalized = $this->driverParser->normalizePhone($oldRaw);

            if ($oldNormalized === $newNormalized) {
                return null;
            }

            return [
                'driver_name' => $this->driverName($text),
                'old_phone_raw' => $oldRaw !== '' ? $oldRaw : null,
                'old_phone_normalized' => $oldNormalized,
                'new_phone_raw' => $newRaw,
                'new_phone_normalized' => $newNormalized,
            ];
        }

        return null;
    }

    /**
     * The driver named in the "#UPDATED" header, spacing tidied.
     */
    public function driverName(string $text): ?string
    {
        if (! preg_match('/#UPDATED\s+([^\r\n]+)/iu', $text, $matches)) {
            return null;
        }

        $name = trim((string) preg_replace('/\s+/u', ' ', $matches[1]));

        return $name !== '' ? mb_strtoupper($name, 'UTF-8') : null;
    }

    private function cleanPhone(string $value): string
    {
        $value = trim($value);

        // "—" / "-" / "нет" for a phone that was not set before.
        return preg_match('/\d{5,}/', $value) === 1 ? $value : '';
    }
}
