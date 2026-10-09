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

/*
 * Sales: the plain and the respectful sets say the same (see `sales` below).
 */
$salesUz = [
    'Statusni yangilang, iltimos',
    'Iltimos, statusni yangilab qo\'ying',
    'Zayavka statusini yangilang, iltimos',
    'Statusni yangilab qo\'ying, {address}, iltimos',
    'Statusni yangilashni unutmang, iltimos',
];

$salesRu = [
    'Обновите, пожалуйста, статус',
    'Обновите статус, пожалуйста',
    'Поменяйте, пожалуйста, статус запроса',
    'Статус обновите, {address}, пожалуйста',
    'Не забудьте обновить статус, пожалуйста',
];

return [

    /*
     * The bot often posts several penalties in a burst. One person's
     * penalties within this many seconds of each other get one comment -
     * different requests too - the strongest one's: the highest repeat
     * number whose level has a comment, the latest on a tie.
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
     * A penalty in one of these statuses is looked up in the CRM API
     * (services.crm): when the request already has a carrier price, the
     * operator has done their part and it is the sales manager's turn -
     * the penalty goes to the request's sales manager, whoever the bot
     * names as responsible. No price, no answer from the CRM: as before.
     * Compared without case. Empty: never looked up.
     */
    'sales_turn_statuses' => ['Актуальный'],

    /*
     * Penalties are only handled inside these hours (app timezone,
     * Asia/Tashkent): what the bot posts before 09:00 or from 18:00 on is
     * recorded and ignored - nothing is forwarded, no comment follows.
     * Both null: every hour counts.
     */
    'working_hours' => [
        'from' => '09:00',
        'to' => '18:00',
    ],

    /*
     * A ladder per role: operators and sales managers are told different
     * things. Each level starts at a repeat number (`from`) and says what
     * goes out at it (`mode`: 'all' - the forward and a comment,
     * 'forward' - the forward only, 'off' - nothing). The last level covers
     * everything after it ("4+").
     *
     * Placeholders: {address}, {name}, {request}, {repeat_number},
     * {status_limit}, {time_in_status}, {crm_status}. Telegram HTML is
     * allowed. {address} is how the person's card says to call them ("Ali
     * aka", "jigar"); with none written it is left out, with its comma.
     *
     * Respectful and plain are written apart: respectful is not a politer
     * copy of plain, it may say something else.
     */
    'roles' => [

        'operation' => [
            'levels' => [
                [
                    'name' => 'Первый раз',
                    'from' => 1,
                    'phrases' => [
                        'uz' => [
                            'plain' => [
                                'Narx berib yubor, {status_limit} vaqt o\'tdi',
                                'Narx tashla, {status_limit} bo\'ldi',
                                'Narx kerak, {address}, {status_limit} o\'tib ketdi',
                                'Zayavkaga narx ber, {status_limit} vaqt o\'tdi',
                            ],
                            'respectful' => [
                                'Narx berib yuboring, iltimos, {status_limit} vaqt o\'tdi',
                                'Narx bera olasizmi, {address}? {status_limit} o\'tdi',
                                'Iltimos, narxni yuboring, {status_limit} vaqt bo\'ldi',
                                'Narxingizni kutyapmiz, {status_limit} o\'tib ketdi',
                            ],
                        ],
                        'ru' => [
                            'plain' => [
                                'Дай цену, уже {status_limit} прошло',
                                'Скинь цену, {status_limit} уже прошло',
                                'Нужна цена, {address}, прошло {status_limit}',
                                'Дай цену по запросу, уже {status_limit}',
                            ],
                            'respectful' => [
                                'Дайте, пожалуйста, цену, уже {status_limit} прошло',
                                'Подскажите, пожалуйста, цену, {address}, прошло {status_limit}',
                                'Пришлите, пожалуйста, цену, уже {status_limit} прошло',
                                'Ждём вашу цену, уже {status_limit} прошло',
                            ],
                        ],
                    ],
                ],
                [
                    'name' => 'Второй раз',
                    'from' => 2,
                    'phrases' => [
                        'uz' => [
                            'plain' => [
                                'Narx berasanmi?',
                                'Narx qani?',
                                'Hali ham narx yo\'qmi?',
                                'Moshina topilmadimi?',
                            ],
                            'respectful' => [
                                'Moshina chiqmadimi?',
                                'Narx bo\'ladimi?',
                                'Hali moshina topilmadimi?',
                                'Narx qachon bo\'ladi, {address}?',
                            ],
                        ],
                        'ru' => [
                            'plain' => [
                                'Цену дашь?',
                                'Где цена?',
                                'Цены всё ещё нет?',
                                'Машину не нашёл?',
                            ],
                            'respectful' => [
                                'Машина не нашлась?',
                                'Цена будет?',
                                'Машину пока не нашли?',
                                'Когда будет цена, {address}?',
                            ],
                        ],
                    ],
                ],
                [
                    'name' => 'Третий раз',
                    'from' => 3,
                    'phrases' => [
                        'uz' => [
                            'plain' => [
                                'Baraka topkur, qancha kutish mumkin?',
                                'Yana qancha kutamiz?',
                                'Klient kutyapti, tezroq bo\'l',
                                'Narx hali ham yo\'q, nima bo\'ldi?',
                            ],
                            'respectful' => [
                                'Baraka toping, yana qancha kutaylik?',
                                'Klient kutyapti, tezroq bo\'larmikan?',
                                'Narx hali ham yo\'q, biror muammo bormi?',
                                'Yana qancha kutishimiz kerak, {address}?',
                            ],
                        ],
                        'ru' => [
                            'plain' => [
                                'Ну сколько можно ждать?',
                                'Долго ещё?',
                                'Клиент ждёт, давай быстрее',
                                'Цены всё ещё нет, что случилось?',
                            ],
                            'respectful' => [
                                'Подскажите, пожалуйста, сколько ещё ждать?',
                                'Клиент ждёт, можно побыстрее?',
                                'Цены всё ещё нет, какая-то проблема?',
                                'Сколько ещё ждать, {address}, подскажите?',
                            ],
                        ],
                    ],
                ],
                [
                    'name' => '4 и больше',
                    'from' => 4,
                    'phrases' => [
                        'uz' => [
                            'plain' => [
                                'Nima qilay, boshqaga olaymi?',
                                'Boshqaga beraymi?',
                                'Yopasanmi yoki boshqaga beraymi?',
                                'Uddalay olmayapsanmi? Boshqaga beraymi?',
                                'Qila olmasang ayt, boshqaga beraman',
                            ],
                            'respectful' => [
                                'Nima qilasiz, yopa olasizmi yoki boshqaga beramizmi?',
                                'Boshqaga beraylikmi?',
                                'Yopasizmi yoki boshqa hamkasbga beraylikmi?',
                                'Uddalay olmasangiz, boshqaga beraylikmi?',
                                'Qila olmasangiz ayting, {address}, boshqaga beramiz',
                            ],
                        ],
                        'ru' => [
                            'plain' => [
                                'Что делать, отдать другому?',
                                'Отдать другому?',
                                'Закроешь или отдать другому?',
                                'Не успеваешь? Передать другому?',
                                'Если не получается, скажи, отдам другому',
                            ],
                            'respectful' => [
                                'Как поступим, передать запрос другому?',
                                'Передадим другому?',
                                'Закроете или передадим коллеге?',
                                'Если не получается, давайте передадим другому?',
                                'Если не успеваете, {address}, скажите, передадим другому',
                            ],
                        ],
                    ],
                ],
            ],
        ],

        /*
         * Sales: one text for every penalty, and respectful to everyone -
         * the plain set says the same, so the respect switch on a sales
         * card changes nothing.
         */
        'sales' => [
            'levels' => [
                [
                    'name' => 'Sales',
                    'from' => 1,
                    'mode' => 'all',
                    'phrases' => [
                        'uz' => [
                            'plain' => $salesUz,
                            'respectful' => $salesUz,
                        ],
                        'ru' => [
                            'plain' => $salesRu,
                            'respectful' => $salesRu,
                        ],
                    ],
                ],
            ],
        ],

    ],

];
