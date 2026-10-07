<?php

/*
|--------------------------------------------------------------------------
| Auto replies
|--------------------------------------------------------------------------
|
| What the listener answers when an operator or a sales manager writes to
| it in a private chat: "+", "ok", "хоп" -> "Rahmat, Ali aka".
|
| Everything lives in one JSON file (`path`), written from the admin panel
| (Driver check -> Auto replies) and read on every message, so a change
| applies at once, without restarting the listener. The file can be edited
| by hand too; until it exists, `defaults` below are used, and "reset" in
| the panel deletes it.
|
| The file:
|
|   enabled                 - the switch: false answers nothing
|   only_after_penalty      - true: only a message after a penalty is
|                             answered; false: any message of theirs
|   penalty_window_minutes  - a message this soon after a penalty is that
|                             penalty's reply, kept on it in the journal
|   cooldown_minutes        - after an answer, the same person is not
|                             answered again for this long (a penalty's
|                             own reply is always answered once)
|   max_words               - a longer message is a conversation, not an
|                             "ok": it is not answered
|   replies                 - the kinds, tried top to bottom, the first
|                             whose keyword is in the message wins:
|       name                - shown in the panel and the journal
|       keywords            - what they write; case, Uzbek apostrophes
|                             (bo'ldi = boldi), ё/е, ў/у, қ/к, ғ/г, ҳ/х
|                             and stretched letters (okkk, ++) do not
|                             count; a "*" at the end takes any ending
|                             (клиент* - клиента, клиентом)
|       max_words           - optional: this kind takes longer messages
|                             than the file's max_words (an explanation
|                             like "клиент трубку не берёт")
|       answers             - what we write back, per language (uz, ru)
|                             and tone (plain, respectful - from the
|                             person's card); a random one is picked.
|                             Placeholders: {address} - how the card says
|                             to call them, left out with its comma when
|                             empty; {name}; {request} - the penalty's
|                             request, "—" without one.
|   silence                 - the nudge when a penalty gets no answer:
|       enabled, after_minutes (counted from the comment), answers (as
|       above). Once per penalty, in the penalties' working hours.
|
*/

return [

    'path' => env('AUTO_REPLIES_PATH', storage_path('app/telegram/auto-replies.json')),

    'defaults' => [

        'enabled' => true,

        'only_after_penalty' => false,

        'penalty_window_minutes' => 60,

        'cooldown_minutes' => 10,

        'max_words' => 5,

        /*
         * Built from real answers to "обновите статус" / "Narx berib
         * yubor" (2026-10-06). The order matters: an explanation ("клиент
         * трубку не берёт") is tried before a bare "ok".
         */
        'replies' => [
            [
                /*
                 * "хали клент билан гаплашолмадим телефонни кутармади",
                 * "гаплашиб кейин узгартирсам буладими?", "Ещё ждем ответ
                 * от клиента" - longer, so more words are allowed.
                 */
                'name' => 'Ждём клиента',
                'max_words' => 15,
                'keywords' => [
                    'клиент*', 'клент*', 'mijoz*', 'мижоз*', 'klient*',
                    'кутарма*', 'kotarma*', 'трубк*', 'не отвечает', 'не берет',
                    'javob berma*', 'жавоб берма*', 'ждем', 'жду ответ',
                    'kutyapman', 'кутяпман', 'kutyapmiz', 'кутяпмиз', 'гаплаш*', 'gaplash*', 'созвон*',
                ],
                'answers' => [
                    'uz' => [
                        'plain' => ['Tushunarli, gaplashib bo\'lgach statusni yangilab qo\'y'],
                        'respectful' => ['Tushunarli, {address}, gaplashganingizdan keyin statusni yangilab qo\'ying, iltimos'],
                    ],
                    'ru' => [
                        'plain' => ['Понял, после разговора обнови статус'],
                        'respectful' => ['Хорошо, {address}, после разговора обновите, пожалуйста, статус'],
                    ],
                ],
            ],
            [
                /*
                 * "обновила", "Done ✅", "qildim".
                 */
                'name' => 'Готово',
                'keywords' => [
                    'обновил', 'обновила', 'обновлено', 'поменял', 'поменяла', 'изменил', 'изменила', 'сделал', 'сделала', 'готово',
                    'yangiladim', 'янгиладим', 'ozgartirdim', 'узгартирдим', 'qildim', 'килдим', 'boldi', 'булди',
                    'done', '✅',
                ],
                'answers' => [
                    'uz' => [
                        'plain' => ['Zo\'r, rahmat!', 'Rahmat, {address}'],
                        'respectful' => ['Rahmat, {address}!', 'Zo\'r, rahmat, {address}!'],
                    ],
                    'ru' => [
                        'plain' => ['Отлично, спасибо!', 'Спасибо, {address}'],
                        'respectful' => ['Спасибо, {address}!', 'Отлично, спасибо, {address}!'],
                    ],
                ],
            ],
            [
                /*
                 * "Aka narx berdimku" - done on their side, the CRM still
                 * says otherwise: that is what the penalty is about.
                 */
                'name' => 'Уже сделано',
                'max_words' => 8,
                'keywords' => [
                    'berdim*', 'бердим*', 'qoydim*', 'куйдим*', 'уже', 'allaqachon', 'аллакачон', 'дал цену', 'дала цену',
                ],
                'answers' => [
                    'uz' => [
                        'plain' => ['Rahmat! Tizimda hali eski status turibdi, bir tekshirib qo\'y'],
                        'respectful' => ['Rahmat, {address}! Tizimda hali eski status ko\'rinyapti, bir tekshirib qo\'ysangiz'],
                    ],
                    'ru' => [
                        'plain' => ['Спасибо! В системе пока старый статус, проверь, пожалуйста'],
                        'respectful' => ['Спасибо, {address}! В системе пока старый статус, проверьте, пожалуйста'],
                    ],
                ],
            ],
            [
                /*
                 * "Ассалому алайкум Ёпаман акажон узим" - a promise.
                 */
                'name' => 'Сделаю',
                'max_words' => 8,
                'keywords' => [
                    'yopaman', 'ёпаман', 'qilaman', 'киламан', 'hozir', 'хозир', 'сейчас', 'щас',
                    'обновлю', 'поменяю', 'сделаю', 'исправлю',
                    'ozgartiraman', 'узгартираман', 'yangilayman', 'янгилайман', 'beraman', 'бераман',
                ],
                'answers' => [
                    'uz' => [
                        'plain' => ['Kutaman, {address}'],
                        'respectful' => ['Rahmat, {address}, kutib turaman'],
                    ],
                    'ru' => [
                        'plain' => ['Жду, спасибо'],
                        'respectful' => ['Спасибо, {address}, жду'],
                    ],
                ],
            ],
            [
                /*
                 * "+", "Assalomu aleykum xo'p bo'ladi".
                 */
                'name' => 'Согласие',
                'keywords' => [
                    '+', 'ok', 'okay', 'okey', 'ок', 'окей', 'оке', 'хорошо', 'ладно', 'понял', 'поняла',
                    'hop', 'xop', 'хоп', 'xo\'p', 'хўп', 'tushundim', 'тушундим', 'tushunarli', 'тушунарли',
                    '👍', '👌',
                ],
                'answers' => [
                    'uz' => [
                        'plain' => ['Rahmat, {address}'],
                        'respectful' => ['Rahmat, {address}!'],
                    ],
                    'ru' => [
                        'plain' => ['Спасибо, {address}'],
                        'respectful' => ['Спасибо, {address}!'],
                    ],
                ],
            ],
        ],

        /*
         * Nobody answered the penalty: once, this long after the comment,
         * a nudge - what was done by hand on 2026-10-06 ("Рушана апа,
         * илтимос", "Ukam, men bularni prosto takka yozmayman"). Only in
         * the penalties' working hours, never twice for one penalty.
         */
        'silence' => [
            'enabled' => true,
            'after_minutes' => 15,
            'answers' => [
                'uz' => [
                    'plain' => ['Javob kutyapman, {address}', 'Men bularni shunchaki yozmayman, {address}'],
                    'respectful' => ['Iltimos, {address}'],
                ],
                'ru' => [
                    'plain' => ['Жду ответа, {address}'],
                    'respectful' => ['Пожалуйста, {address}'],
                ],
            ],
        ],
    ],

];
