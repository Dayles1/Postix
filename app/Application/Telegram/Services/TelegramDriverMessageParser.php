<?php

namespace App\Application\Telegram\Services;

class TelegramDriverMessageParser
{
    public function parse(string $messageText): array
    {
        $phoneRaw = $this->extractPhone($messageText);

        $parts = $this->extractDriverNameParts(
            $messageText
        );

        return [
            'phone_raw' => $phoneRaw,

            'phone_normalized' =>
                $this->normalizePhone($phoneRaw),

            'first_name' =>
                $parts['first_name'],

            'last_name' =>
                $parts['last_name'],

            'patronymic' =>
                $parts['patronymic'],

            'driver_name' =>
                $this->buildFullName($parts),
        ];
    }

    /**
     * Extract driver phone.
     */
    private function extractPhone(
        string $text
    ): ?string {
        /*
         * Prefer:
         *
         * Номер телефона 1
         */
        $patterns = [
            '/(?:номер\s+телефона|телефон)\s*1\s*[:\-]\s*([+\d][\d\s\-\(\)]{8,})/iu',

            '/(?:phone)\s*1\s*[:\-]\s*([+\d][\d\s\-\(\)]{8,})/iu',
        ];

        foreach ($patterns as $pattern) {
            if (
                preg_match(
                    $pattern,
                    $text,
                    $matches
                )
            ) {
                $phone = trim(
                    $matches[1]
                );

                if (
                    $this->normalizePhone($phone)
                ) {
                    return $phone;
                }
            }
        }

        /*
         * Fallback:
         *
         * Generic international phone.
         */
        $genericPattern =
            '/(?<!\d)\+?\d[\d\s\-\(\)]{8,}\d(?!\d)/u';

        if (
            preg_match(
                $genericPattern,
                $text,
                $matches
            )
        ) {
            $phone = trim(
                $matches[0]
            );

            if (
                $this->normalizePhone($phone)
            ) {
                return $phone;
            }
        }

        return null;
    }

    /**
     * Normalize phone.
     */
    private function normalizePhone(
        ?string $phone
    ): ?string {
        if (! $phone) {
            return null;
        }

        $digits = preg_replace(
            '/\D+/',
            '',
            $phone
        );

        if (! $digits) {
            return null;
        }

        /*
         * Uzbekistan local number.
         */
        if (strlen($digits) === 9) {
            return '+998'.$digits;
        }

        /*
         * Uzbekistan international.
         */
        if (
            str_starts_with(
                $digits,
                '998'
            )
        ) {
            return '+'.$digits;
        }

        /*
         * Belarus.
         */
        if (
            str_starts_with(
                $digits,
                '375'
            )
        ) {
            return '+'.$digits;
        }

        /*
         * Russia / Kazakhstan.
         */
        if (
            str_starts_with(
                $digits,
                '7'
            )
        ) {
            return '+'.$digits;
        }

        /*
         * Generic international.
         */
        return '+'.$digits;
    }

    /**
     * Extract all possible driver name information.
     */
    private function extractDriverNameParts(
        string $text
    ): array {
        /*
         * -------------------------------------------------
         * 1. Explicit driver fields.
         * -------------------------------------------------
         */
        $firstName = $this->extractField(
            $text,
            [
                'имя водителя',
            ]
        );

        $lastName = $this->extractField(
            $text,
            [
                'фамилия водителя',
            ]
        );

        $patronymic = $this->extractField(
            $text,
            [
                'отчество водителя',
                'отечество водителя',
            ]
        );

        /*
         * IMPORTANT:
         *
         * "-" and "—" are NOT values.
         */
        if (
            $this->isEmptyValue($firstName)
        ) {
            $firstName = null;
        }

        if (
            $this->isEmptyValue($lastName)
        ) {
            $lastName = null;
        }

        if (
            $this->isEmptyValue($patronymic)
        ) {
            $patronymic = null;
        }

        /*
         * -------------------------------------------------
         * 2. Find FIO specifically inside "Водитель".
         * -------------------------------------------------
         *
         * Example:
         *
         * • Водитель:
         * -ФИО: РАБОТЬКО АНДРЕЙ ВИКТОРОВИЧ
         * -Номер телефона 1: ...
         */
        $driverFio = $this->extractDriverFio(
            $text
        );

        /*
         * If driver FIO exists, use it to fill
         * missing fields.
         */
        if ($driverFio) {
            $fioParts = $this->splitFio(
                $driverFio
            );

            $lastName ??=
                $fioParts['last_name'];

            $firstName ??=
                $fioParts['first_name'];

            $patronymic ??=
                $fioParts['patronymic'];
        }

        /*
         * -------------------------------------------------
         * 3. Generic FIO fallback.
         * -------------------------------------------------
         */
        if (
            ! $firstName
            && ! $lastName
            && ! $patronymic
        ) {
            $fio = $this->extractFio(
                $text
            );

            if ($fio) {
                $fioParts = $this->splitFio(
                    $fio
                );

                $lastName =
                    $fioParts['last_name'];

                $firstName =
                    $fioParts['first_name'];

                $patronymic =
                    $fioParts['patronymic'];
            }
        }

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'patronymic' => $patronymic,
        ];
    }

    /**
     * Extract FIO from "Водитель" section.
     */
    private function extractDriverFio(
        string $text
    ): ?string {
        /*
         * We search for:
         *
         * Водитель:
         * ...
         * ФИО: ...
         *
         * but only inside the nearby block.
         */
        $pattern =
            '/водитель\s*:\s*(?:\R\s*)?'
            .'(?:[-•]\s*)?фио\s*[:\-]\s*'
            .'([^\r\n]+)/iu';

        if (
            preg_match(
                $pattern,
                $text,
                $matches
            )
        ) {
            $fio = trim(
                $matches[1]
            );

            return $this->cleanNameValue(
                $fio
            );
        }

        /*
         * More permissive fallback.
         *
         * This handles cases where there are
         * extra lines between "Водитель" and "ФИО".
         */
        $pattern =
            '/водитель\s*:\s*'
            .'(.{0,500}?)'
            .'(?:^|\R)\s*[-•]?\s*фио\s*[:\-]\s*'
            .'([^\r\n]+)/isu';

        if (
            preg_match(
                $pattern,
                $text,
                $matches
            )
        ) {
            return $this->cleanNameValue(
                $matches[2]
            );
        }

        return null;
    }

    /**
     * Extract generic FIO.
     */
    private function extractFio(
        string $text
    ): ?string {
        $pattern =
            '/(?:^|\R)\s*'
            .'[-•]?\s*фио\s*[:\-]\s*'
            .'(.+?)(?=\R|$)/imu';

        if (
            preg_match(
                $pattern,
                $text,
                $matches
            )
        ) {
            return $this->cleanNameValue(
                $matches[1]
            );
        }

        return null;
    }

    /**
     * Extract a labeled field.
     */
    private function extractField(
        string $text,
        array $labels
    ): ?string {
        foreach ($labels as $label) {
            $quotedLabel =
                preg_quote(
                    $label,
                    '/'
                );

            $pattern =
                "/{$quotedLabel}"
                ."\s*[:\-]\s*"
                ."(.+?)(?=\R|$)/iu";

            if (
                preg_match(
                    $pattern,
                    $text,
                    $matches
                )
            ) {
                $value = trim(
                    $matches[1]
                );

                $value = $this->cleanNameValue(
                    $value
                );

                if (
                    ! $this->isEmptyValue($value)
                ) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * Split:
     *
     * SURNAME FIRSTNAME PATRONYMIC
     */
    private function splitFio(
        string $fio
    ): array {
        $fio = $this->cleanNameValue(
            $fio
        );

        if (! $fio) {
            return [
                'last_name' => null,
                'first_name' => null,
                'patronymic' => null,
            ];
        }

        $parts = preg_split(
            '/\s+/u',
            $fio
        );

        $parts = array_values(
            array_filter(
                $parts,
                fn ($value) =>
                    ! $this->isEmptyValue(
                        $value
                    )
            )
        );

        /*
         * 1 word:
         *
         * BAKHROM
         */
        if (count($parts) === 1) {
            return [
                'last_name' => null,
                'first_name' => $parts[0],
                'patronymic' => null,
            ];
        }

        /*
         * 2 words:
         *
         * ERGASHEV BAKHROM
         */
        if (count($parts) === 2) {
            return [
                'last_name' => $parts[0],
                'first_name' => $parts[1],
                'patronymic' => null,
            ];
        }

        /*
         * 3+:
         *
         * ERGASHEV BAKHROM YUNUSJONOVICH
         */
        return [
            'last_name' => $parts[0],
            'first_name' => $parts[1],
            'patronymic' => implode(
                ' ',
                array_slice(
                    $parts,
                    2
                )
            ),
        ];
    }

    /**
     * Build full driver name.
     */
    private function buildFullName(
        array $parts
    ): ?string {
        $name = implode(
            ' ',
            array_filter([
                $parts['last_name'] ?? null,
                $parts['first_name'] ?? null,
                $parts['patronymic'] ?? null,
            ])
        );

        return $name !== ''
            ? $name
            : null;
    }

    /**
     * Clean extracted value.
     */
    private function cleanNameValue(
        ?string $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim(
            $value
        );

        /*
         * Remove leading bullet/dash.
         */
        $value = preg_replace(
            '/^[\s\-•]+/u',
            '',
            $value
        );

        /*
         * Normalize whitespace.
         */
        $value = preg_replace(
            '/\s+/u',
            ' ',
            $value
        );

        $value = trim(
            $value
        );

        if (
            $this->isEmptyValue($value)
        ) {
            return null;
        }

        return $value;
    }

    /**
     * "-" / "—" / empty = no value.
     */
    private function isEmptyValue(
        ?string $value
    ): bool {
        if ($value === null) {
            return true;
        }

        $value = trim(
            $value
        );

        return $value === ''
            || $value === '-'
            || $value === '—'
            || $value === '–';
    }
}