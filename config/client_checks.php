<?php

/*
|--------------------------------------------------------------------------
| Client checks (CRM penalties) - the defaults
|--------------------------------------------------------------------------
|
| A bot posts a penalty when a request sits in its status too long. The
| listener forwards it to the person responsible and sends that person one
| comment, from the account it runs on.
|
| These are only the starting values: the panel ("Настройки штрафов")
| saves its own copy to the database (ClientCheckRulesStore), and from then
| on that copy is what is used. "Restore defaults" comes back here.
|
| What is said depends on how many times the bot has sent the penalty -
| "⚠️ Штраф по запросу #…" is the first time, "🆘 Повторное отправление
| штрафа №N по запросу #…" the N-th - and on the person: their language
| (Russian for sales, Uzbek for operators, unless changed on their card)
| and whether they are written to with respect (people older than the one
| writing).
|
*/

return [

    /*
     * The bot may post the same request twice in a burst. Penalties for the
     * same person AND the same request within this many seconds get one
     * comment, at the highest repeat number. Different requests never
     * share a comment.
     *
     * 5, not 20: the comment is meant to follow the forward within seconds
     * (2026-10-07). Together with the listener's 2-second tick
     * (TelegramDriverCheckHandler::CLIENT_CHECK_PERIOD) it arrives about
     * 5-7 seconds after the forward.
     */
    'batch_quiet_seconds' => 5,

    /*
     * Failed steps are retried this many times in total, and only while the
     * penalty is younger than retry_minutes.
     */
    'max_attempts' => 3,

    'retry_minutes' => 30,

    /*
     * One level per repeat count, from the first penalty up. `from` is the
     * repeat number the level starts at; the last level covers everything
     * after it ("4+").
     *
     * Placeholders: {name}, {request}, {repeat_number}, {status_limit},
     * {time_in_status}, {crm_status}. Telegram HTML is allowed.
     */
    'levels' => [
        [
            'name' => 'Первый раз',
            'from' => 1,
            'phrases' => [
                'uz' => [
                    'plain' => ['Narx berib yubor, {status_limit} vaqt o\'tdi'],
                    'respectful' => ['Narx berib yuboring, iltimos, {status_limit} vaqt o\'tdi'],
                ],
                'ru' => [
                    'plain' => ['Дай цену, уже {status_limit} прошло'],
                    'respectful' => ['Дайте, пожалуйста, цену, уже {status_limit} прошло'],
                ],
            ],
        ],
        [
            'name' => 'Второй раз',
            'from' => 2,
            'phrases' => [
                'uz' => [
                    'plain' => ['Narx berasanmi?'],
                    'respectful' => ['Moshina chiqmadimi?'],
                ],
                'ru' => [
                    'plain' => ['Цену дашь?'],
                    'respectful' => ['Машина не нашлась?'],
                ],
            ],
        ],
        [
            'name' => 'Третий раз',
            'from' => 3,
            'phrases' => [
                'uz' => [
                    'plain' => ['Baraka topkur, qancha kutish mumkin?'],
                    'respectful' => ['Baraka toping, yana qancha kutaylik?'],
                ],
                'ru' => [
                    'plain' => ['Ну сколько можно ждать?'],
                    'respectful' => ['Подскажите, пожалуйста, сколько ещё ждать?'],
                ],
            ],
        ],
        [
            'name' => '4 и больше',
            'from' => 4,
            'phrases' => [
                'uz' => [
                    'plain' => ['Nima qilay, boshqaga olaymi?'],
                    'respectful' => ['Nima qilasiz, yopa olasizmi yoki boshqaga beramizmi?'],
                ],
                'ru' => [
                    'plain' => ['Что делать, отдать другому?'],
                    'respectful' => ['Как поступим, передать запрос другому?'],
                ],
            ],
        ],
    ],

];
