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
    'Statusni yangilab qo\'ying',
    'Zayavka statusini o\'zgartirib qo\'ying, iltimos',
    '{address}, statusni yangilang',
    'Status eskirib qoldi, yangilab qo\'ying',
    'Iltimos, statusni to\'g\'rilab qo\'ying',
    'Statusni yangilashni esdan chiqarmang',
    'Zayavka statusi yangilanmagan, iltimos',
    'Statusni CRMda yangilab qo\'ying',
    '{address}, iltimos, status',
    'Status bo\'yicha qarab qo\'ying, iltimos',
    'Statusni o\'zgartirib qo\'ysangiz',
    'Klient javob bergan bo\'lsa, statusni yangilang',
    'Status hali eski, iltimos',
    'Statusga qarab qo\'ying, {address}',
    'Yangilab qo\'ying statusni, iltimos',
];

$salesRu = [
    'Обновите, пожалуйста, статус',
    'Обновите статус, пожалуйста',
    'Поменяйте, пожалуйста, статус запроса',
    'Статус обновите, {address}, пожалуйста',
    'Не забудьте обновить статус, пожалуйста',
    /* As written by hand (2026-10-07). */
    'обновите статус',
    'Обновите статус, {address}',
    'Статус устарел, обновите, пожалуйста',
    'Поменяйте статус, пожалуйста',
    'Статус, пожалуйста',
    'Обновите статус в CRM, пожалуйста',
    'Не забудьте про статус',
    'Статус запроса не обновлён, пожалуйста',
    'Посмотрите статус, пожалуйста',
    '{address}, статус обновите, пожалуйста',
    'Если клиент ответил, обновите статус',
    'Статус бы обновить',
    'Обновите, пожалуйста, статус по запросу',
    'Статус всё ещё старый, {address}',
    'Поправьте статус, пожалуйста',
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
                                'Narxni tashlab yubor, {status_limit} o\'tdi',
                                '{address}, narx kerak, {status_limit} bo\'ldi',
                                'Zayavkaga narx qo\'y, vaqt o\'tdi',
                                'Narx ber, {status_limit} bo\'lib qoldi',
                                'Narx yo\'q hali, {status_limit} o\'tdi',
                                'Narxni kiritib qo\'y, {status_limit} bo\'ldi',
                                'Shu zayavkaga narx kerak, {address}',
                                'Narx berib qo\'y, vaqti o\'tib ketdi',
                                'Narxni yubor, {status_limit} vaqt berilgandi',
                                'Zayavka narxsiz turibdi, {status_limit} o\'tdi',
                                'Narx bergin, {address}, {status_limit} bo\'ldi',
                                'Tezroq narx ber, {status_limit} o\'tdi',
                                'Narxni CRMga kirit, {status_limit} bo\'ldi',
                                'Narx kerak edi, {status_limit} o\'tib ketdi',
                                '{address}, zayavkaga narx tashla, vaqt o\'tdi',
                                'Narxini ber, {status_limit} o\'tdi-ku',
                            ],
                            'respectful' => [
                                'Narx berib yuboring, iltimos, {status_limit} vaqt o\'tdi',
                                'Narx bera olasizmi, {address}? {status_limit} o\'tdi',
                                'Iltimos, narxni yuboring, {status_limit} vaqt bo\'ldi',
                                'Narxingizni kutyapmiz, {status_limit} o\'tib ketdi',
                                'Narxni yuborib qo\'ysangiz, {status_limit} o\'tdi',
                                '{address}, narx kerak edi, {status_limit} bo\'ldi',
                                'Iltimos, zayavkaga narx bering, {status_limit} o\'tdi',
                                'Narxni kiritib qo\'ysangiz, iltimos, vaqt o\'tdi',
                                'Zayavka narxsiz turibdi, {address}, {status_limit} o\'tdi',
                                'Narx berib yuborsangiz, {status_limit} bo\'lib qoldi',
                                'Iltimos, narxni CRMga kiritib qo\'ying, {status_limit} o\'tdi',
                                'Narx bo\'yicha qarab bersangiz, {status_limit} o\'tdi',
                                '{address}, narxni kutyapmiz, vaqt o\'tib ketdi',
                                'Narx berib qo\'ysangiz, iltimos, {status_limit} bo\'ldi',
                                'Shu zayavkaga narx kerak, {address}, iltimos',
                                'Iltimos, narxni tezroq yuboring, {status_limit} o\'tdi',
                                'Narx bera olasizmi? {status_limit} o\'tib ketdi',
                                '{status_limit} o\'tdi, narxni yuborib qo\'ysangiz',
                                'Narx masalasida qarab bering, iltimos, {address}',
                                'Narxni kiritsangiz, {address}, {status_limit} bo\'ldi',
                            ],
                        ],
                        'ru' => [
                            'plain' => [
                                'Дай цену, уже {status_limit} прошло',
                                'Скинь цену, {status_limit} уже прошло',
                                'Нужна цена, {address}, прошло {status_limit}',
                                'Дай цену по запросу, уже {status_limit}',
                                'Скинь цену, {address}, {status_limit} прошло',
                                'Цену по запросу дай, {status_limit} уже',
                                'Поставь цену, уже {status_limit} прошло',
                                'Нужна цена, {status_limit} уже прошло',
                                'Запрос без цены, прошло {status_limit}',
                                'Цену внеси, {status_limit} прошло',
                                'Давай цену, {status_limit} уже вышло',
                                'По запросу цена нужна, {address}',
                                'Цену закинь, время вышло',
                                '{address}, дай цену, {status_limit} прошло',
                                'Цена нужна, время уже вышло',
                                'Внеси цену в CRM, {status_limit} прошло',
                                'Цену бы, {status_limit} уже прошло',
                                'Скинь цену по запросу, время прошло',
                                'Цены нет, а {status_limit} уже прошло',
                                'Давай цену по запросу, {address}',
                            ],
                            'respectful' => [
                                'Дайте, пожалуйста, цену, уже {status_limit} прошло',
                                'Подскажите, пожалуйста, цену, {address}, прошло {status_limit}',
                                'Пришлите, пожалуйста, цену, уже {status_limit} прошло',
                                'Ждём вашу цену, уже {status_limit} прошло',
                                'Дайте цену, пожалуйста, {status_limit} уже прошло',
                                '{address}, нужна цена, прошло {status_limit}',
                                'Внесите, пожалуйста, цену, {status_limit} прошло',
                                'Скиньте, пожалуйста, цену по запросу, {status_limit} прошло',
                                'Поставьте, пожалуйста, цену, время уже вышло',
                                'Запрос без цены, {address}, прошло {status_limit}',
                                'Подскажите цену, пожалуйста, уже {status_limit}',
                                'Нужна ваша цена, {status_limit} уже прошло',
                                'Цену, пожалуйста, {address}, {status_limit} прошло',
                                'Пришлите цену, пожалуйста, {status_limit} уже прошло',
                                'Внесите цену в CRM, пожалуйста, {status_limit} прошло',
                                'Дайте, пожалуйста, цену по запросу, время вышло',
                                '{address}, подскажите цену, уже {status_limit}',
                                'Ждём цену, {address}, прошло {status_limit}',
                                'Посмотрите, пожалуйста, цену, {status_limit} прошло',
                                'Цена нужна, пожалуйста, {status_limit} уже прошло',
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
                                'Moshina chiqmadimi?',
                                'Narx bo\'ladimi?',
                                'Nima bo\'ldi, narx qani?',
                                'Moshina bormi?',
                                'Narx qachon bo\'ladi?',
                                'Hali ham moshina yo\'qmi?',
                                '{address}, narx qani?',
                                'Narx chiqmadimi?',
                                'Moshina topdingmi?',
                                'Hali qidiryapsanmi?',
                                'Narx tashlaysanmi?',
                                'Nima gap, narx bormi?',
                                'Narx bo\'lmadimi hali?',
                                'Moshina qidiryapsanmi?',
                                'Topildimi moshina?',
                                '{address}, moshina chiqdimi?',
                            ],
                            'respectful' => [
                                'Moshina chiqmadimi?',
                                'Narx bo\'ladimi?',
                                'Hali moshina topilmadimi?',
                                'Narx qachon bo\'ladi, {address}?',
                                'Narx bormi, {address}?',
                                'Moshina topildimi?',
                                'Narx chiqmadimi hali?',
                                'Hali ham qidiryapsizmi?',
                                'Moshina bo\'ladimi, {address}?',
                                'Narx qachonga bo\'ladi?',
                                'Hali narx yo\'qmi?',
                                'Moshina topa oldingizmi?',
                                'Narx bera olasizmi?',
                                'Nima bo\'ldi, moshina chiqmadimi?',
                                '{address}, narx bo\'ladimi?',
                                'Moshina bormi, {address}?',
                                'Narx bo\'yicha nima gap?',
                                'Hali moshina chiqmadimi, {address}?',
                                'Narx bo\'lsa yuborib qo\'ysangiz',
                                'Moshina topilsa, narxini yuboring',
                            ],
                        ],
                        'ru' => [
                            'plain' => [
                                'Цену дашь?',
                                'Где цена?',
                                'Цены всё ещё нет?',
                                'Машину не нашёл?',
                                'Цена будет?',
                                'Машина не вышла?',
                                'Ну что, цена есть?',
                                'Что по цене?',
                                'Цена есть?',
                                'Когда цена?',
                                'Всё ещё ищешь?',
                                '{address}, где цена?',
                                'Машина есть?',
                                'Что с машиной?',
                                'Цену скинешь?',
                                'Цены так и нет?',
                                'Нашлась машина?',
                                'Что там по запросу?',
                                '{address}, что по машине?',
                                'Ну что там с ценой?',
                            ],
                            'respectful' => [
                                'Машина не нашлась?',
                                'Цена будет?',
                                'Машину пока не нашли?',
                                'Когда будет цена, {address}?',
                                'Что по цене, {address}?',
                                'Машина нашлась?',
                                'Цены пока нет?',
                                'Всё ещё ищете?',
                                'Цена будет, {address}?',
                                'Когда примерно будет цена?',
                                'Удалось найти машину?',
                                'Есть цена?',
                                'Что с машиной, подскажите?',
                                'Цену дадите?',
                                '{address}, что по запросу?',
                                'Машина есть, {address}?',
                                'Как там с ценой?',
                                'Пока не нашлась машина?',
                                'Подскажите, цена будет?',
                                'Если есть цена, скиньте, пожалуйста',
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
                                'Qachongacha kutamiz?',
                                'Klient kutib qoldi, tezroq',
                                'Hali ham narx yo\'q, {address}?',
                                'Bu zayavka qancha turadi?',
                                'Tezlashtir, klient kutyapti',
                                'Uch marta yozdim, narx qani?',
                                'Nima gap, nega narx yo\'q?',
                                'Klient so\'rayapti, nima deyman?',
                                'Kutish qolmadi, narx ber',
                                '{address}, bu zayavka hali turibdi',
                                'Juda cho\'zilib ketdi, narx ber',
                                'Klientga nima deyin? Narx kerak',
                                'Yana kutamizmi?',
                                'Muammo bormi? Ayt',
                                'Tezroq bo\'l, {address}',
                                'Qancha vaqt o\'tdi, hali narx yo\'q',
                            ],
                            'respectful' => [
                                'Baraka toping, yana qancha kutaylik?',
                                'Klient kutyapti, tezroq bo\'larmikan?',
                                'Narx hali ham yo\'q, biror muammo bormi?',
                                'Yana qancha kutishimiz kerak, {address}?',
                                'Klient kutyapti, iltimos, tezroq',
                                'Hali ham narx yo\'q, {address}, nima bo\'ldi?',
                                'Klientga nima deylik? Narx kerak',
                                'Biror muammo bo\'lsa, ayting',
                                'Juda cho\'zilib ketdi, iltimos, narx bering',
                                'Klient so\'rayapti, {address}, narx bo\'ladimi?',
                                'Yana qancha kutamiz, iltimos?',
                                'Iltimos, tezlashtiring, klient kutib qoldi',
                                'Bu zayavka hali turibdi, {address}',
                                'Uchinchi marta yozyapman, narx bo\'ladimi?',
                                'Narx qachon bo\'lishini ayting, iltimos',
                                'Klient bezovta bo\'lyapti, {address}',
                                'Tezroq qarab bersangiz, iltimos',
                                'Qiyinchilik bo\'lsa ayting, {address}',
                                'Ancha vaqt o\'tdi, narx bo\'ladimi?',
                                'Iltimos, shu zayavkani tezroq yoping',
                            ],
                        ],
                        'ru' => [
                            'plain' => [
                                'Ну сколько можно ждать?',
                                'Долго ещё?',
                                'Клиент ждёт, давай быстрее',
                                'Цены всё ещё нет, что случилось?',
                                'Сколько ещё ждать?',
                                'Клиент ждёт, давай',
                                '{address}, цены всё нет?',
                                'Третий раз пишу, где цена?',
                                'Что не так, почему нет цены?',
                                'Клиент спрашивает, что ему сказать?',
                                'Затянулось, давай цену',
                                'Ещё ждём?',
                                'Проблема какая-то? Скажи',
                                'Быстрее, {address}',
                                'Сколько можно, клиент ждёт',
                                'Запрос висит, давай быстрее',
                                'Клиенту что отвечать?',
                                'Долго, давай цену уже',
                                'Время идёт, цены нет',
                                'Что с запросом, {address}?',
                            ],
                            'respectful' => [
                                'Подскажите, пожалуйста, сколько ещё ждать?',
                                'Клиент ждёт, можно побыстрее?',
                                'Цены всё ещё нет, какая-то проблема?',
                                'Сколько ещё ждать, {address}, подскажите?',
                                'Клиент ждёт, можно побыстрее, пожалуйста?',
                                'Цены всё нет, {address}, что случилось?',
                                'Что сказать клиенту? Нужна цена',
                                'Если есть проблема, скажите',
                                'Затянулось, дайте цену, пожалуйста',
                                'Клиент спрашивает, {address}, цена будет?',
                                'Сколько ещё ждём, подскажите?',
                                'Ускорьте, пожалуйста, клиент ждёт',
                                'Запрос всё ещё висит, {address}',
                                'Третий раз пишу, цена будет?',
                                'Подскажите, когда будет цена',
                                'Клиент уже нервничает, {address}',
                                'Посмотрите, пожалуйста, побыстрее',
                                'Если сложно, скажите, {address}',
                                'Прошло много времени, цена будет?',
                                'Закройте, пожалуйста, запрос побыстрее',
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
                                'Boshqaga beraman, mayli?',
                                'Bo\'lmasa ayt, boshqaga o\'tkazaman',
                                'Boshqa hamkasbga beraymi?',
                                'Yopolmasang boshqaga beraman',
                                'Nima qilamiz, {address}? Boshqaga beraymi?',
                                'Yoki o\'zing yopasanmi?',
                                'Ko\'p kutdik, boshqaga beraymi?',
                                'Bu zayavkani boshqaga o\'tkazaymi?',
                                'Javob ber, boshqaga beraymi?',
                                'Endi boshqaga beramiz shekilli',
                                'Yopa olasanmi o\'zi?',
                                'Qila olmasang muammo yo\'q, ayt',
                                '{address}, yopasanmi yoki boshqaga?',
                                'Boshqaga bersam bo\'ladimi?',
                                'Bo\'ldi, boshqaga beraymi?',
                            ],
                            'respectful' => [
                                'Nima qilasiz, yopa olasizmi yoki boshqaga beramizmi?',
                                'Boshqaga beraylikmi?',
                                'Yopasizmi yoki boshqa hamkasbga beraylikmi?',
                                'Uddalay olmasangiz, boshqaga beraylikmi?',
                                'Qila olmasangiz ayting, {address}, boshqaga beramiz',
                                'Boshqaga o\'tkazsak bo\'ladimi?',
                                'Yopa olasizmi, {address}, yoki boshqaga beraylikmi?',
                                'Qiyin bo\'lsa ayting, boshqaga beramiz',
                                'Boshqa hamkasbga o\'tkazaylikmi?',
                                'Nima qilamiz, {address}?',
                                'Ko\'p kutdik, boshqaga beraylikmi?',
                                'O\'zingiz yopasizmi yoki boshqagami?',
                                'Bu zayavkani boshqaga o\'tkazaylikmi, {address}?',
                                'Javob bersangiz, boshqaga beraylikmi?',
                                'Vaqtingiz bo\'lmasa, boshqaga beramiz',
                                'Yopishga ulgurasizmi?',
                                'Muammo bo\'lsa ayting, boshqaga o\'tkazamiz',
                                'Boshqaga bersak qarshi emasmisiz?',
                                '{address}, qanday qilamiz?',
                                'Iltimos, ayting: yopasizmi yoki boshqagami?',
                            ],
                        ],
                        'ru' => [
                            'plain' => [
                                'Что делать, отдать другому?',
                                'Отдать другому?',
                                'Закроешь или отдать другому?',
                                'Не успеваешь? Передать другому?',
                                'Если не получается, скажи, отдам другому',
                                'Отдам другому, ок?',
                                'Не выходит - скажи, передам',
                                'Передать коллеге?',
                                'Не закроешь - отдам другому',
                                'Что делаем, {address}? Отдать другому?',
                                'Или сам закроешь?',
                                'Долго ждём, отдать другому?',
                                'Этот запрос передать?',
                                'Ответь, отдать другому?',
                                'Похоже, придётся отдать другому',
                                'Закроешь вообще?',
                                'Не можешь - не страшно, скажи',
                                '{address}, закроешь или другому?',
                                'Можно отдам другому?',
                                'Всё, отдаю другому?',
                            ],
                            'respectful' => [
                                'Как поступим, передать запрос другому?',
                                'Передадим другому?',
                                'Закроете или передадим коллеге?',
                                'Если не получается, давайте передадим другому?',
                                'Если не успеваете, {address}, скажите, передадим другому',
                                'Можно передадим другому?',
                                'Закроете, {address}, или передадим?',
                                'Если сложно, скажите, передадим',
                                'Передадим коллеге?',
                                'Как поступим, {address}?',
                                'Долго ждём, передадим другому?',
                                'Сами закроете или другому?',
                                'Этот запрос передать, {address}?',
                                'Ответьте, пожалуйста, передать другому?',
                                'Если нет времени, передадим',
                                'Успеете закрыть?',
                                'Если есть проблема, скажите, передадим',
                                'Не против, если передадим другому?',
                                '{address}, что решим?',
                                'Скажите, пожалуйста: закроете или передать?',
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
