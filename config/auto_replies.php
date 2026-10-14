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
|       id                  - what personal answers ("Личные ответы",
|                             PersonalAnswers) point at; kept when the
|                             kind is renamed or moved (greetings too)
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
|       media               - gifs: [{file, name}] for every language; one
|                             found in Telegram has telegram: {id,
|                             access_hash, file_reference} too and goes
|                             as that document, a real GIF (the file is
|                             its preview); voices: {uz: [...], ru: [...]}, only in the
|                             person's own language. Files are uploaded in
|                             the panel. The answer is one random pick out
|                             of the texts, the GIFs and the voices.
|   silence                 - the nudge for someone who ignores the
|       penalties: enabled, after_penalties (this many in a row without a
|       word back from them), answers and media (as above). In the
|       penalties' working hours; the count starts over after it.
|   greetings               - "Доброе утро. Готово": greeted back first, in
|       a reply of its own, then the kind's answer `greeting_pause` later.
|       enabled; list - tried top to bottom, the first found picks the
|       answer: name, keywords, answers (as above, texts only); fillers -
|       words that do not count after a greeting ("aka", "всем", "🙂"): a
|       greeting with only these is greeted back alone. A greeting before
|       a question is not answered, as any question. Once a day per person.
|   opening                 - we greet first: before the day's first
|       penalty to a person, "Assalomu alaykum, Ali aka", then the penalty
|       `greeting_pause` later. enabled, answers and media (as above). Not
|       when they greeted us first that day (greetings above).
|   follow_up               - "Это тоже": a penalty comment of the same level
|       as the one this person got less than `window_minutes` ago goes as a
|       short follow-up instead of the full phrase again. enabled,
|       window_minutes, answers (one penalty: "Это тоже", "Bu ham"),
|       answers_many (a burst: "Вот эти тоже не забудьте", "Bular ham") -
|       per language and tone, as above.
|
*/

return [

    'path' => env('AUTO_REPLIES_PATH', storage_path('app/telegram/auto-replies.json')),

    /*
     * The GIFs and voice messages uploaded in the panel (AutoReplyMedia).
     */
    'media_path' => env('AUTO_REPLIES_MEDIA_PATH', storage_path('app/telegram/auto-replies-media')),

    /*
     * An answer in a second gives the bot away: it waits a random few
     * seconds, "typing…" (or "recording a voice message…") meanwhile.
     * Seconds; both 0 answers at once.
     */
    'reply_delay' => [
        'min' => 5,
        'max' => 6,
    ],

    /*
     * Between the greeting and the kind's answer after it (greetings
     * below): a second message is typed faster than the first.
     */
    'greeting_pause' => [
        'min' => 2,
        'max' => 3,
    ],

    /*
     * Between a penalty and its short "это тоже" in a second wave
     * (PenaltyBurst): the next penalty waits for it.
     */
    'burst_pause' => [
        'min' => 1,
        'max' => 2,
    ],

    'defaults' => [

        'enabled' => true,

        'only_after_penalty' => false,

        'penalty_window_minutes' => 60,

        'cooldown_minutes' => 10,

        'max_words' => 5,

        /*
         * One kind: whatever they answer - "+", "обновила", "ёпаман" - a
         * thank-you fits, and a different one each time. GIFs and voice
         * messages are added in the panel.
         */
        'replies' => [
            [
                'name' => 'Благодарность',
                'max_words' => 8,
                'keywords' => [
                    /* agreed */
                    '+',
                    'ok',
                    'okay',
                    'okey',
                    'ок',
                    'окей',
                    'оке',
                    'хорошо',
                    'ладно',
                    'понял',
                    'поняла',
                    'hop',
                    'xop',
                    'хоп',
                    'xo\'p',
                    'хўп',
                    'tushundim',
                    'тушундим',
                    'tushunarli',
                    'тушунарли',
                    '👍',
                    '👌',
                    /* done */
                    'обновил',
                    'обновила',
                    'обновлено',
                    'поменял',
                    'поменяла',
                    'изменил',
                    'изменила',
                    'сделал',
                    'сделала',
                    'готово',
                    'yangiladim',
                    'янгиладим',
                    'ozgartirdim',
                    'узгартирдим',
                    'qildim',
                    'килдим',
                    'boldi',
                    'булди',
                    'done',
                    '✅',
                    /* will do */
                    'yopaman',
                    'ёпаман',
                    'qilaman',
                    'киламан',
                    'hozir',
                    'хозир',
                    'сейчас',
                    'щас',
                    'обновлю',
                    'поменяю',
                    'сделаю',
                    'исправлю',
                    'ozgartiraman',
                    'узгартираман',
                    'yangilayman',
                    'янгилайман',
                    'beraman',
                    'бераман',
                    /* working on it: "обрабатываю" (2026-10-13) */
                    'обрабатываю',
                    'обрабатываем',
                    'обработаю',
                    'занимаюсь',
                    'в процессе',
                    'qilyapman',
                    'киляпман',
                    'jarayonda',
                    'жараёнда',
                ],
                'answers' => [
                    'uz' => [
                        'plain' => [
                            'Zo\'r, rahmat!',
                            'Rahmat, {address}',
                            'Rahmat!',
                            'Katta rahmat, {address}',
                            'Zo\'r 👍',
                            'Yaxshi, rahmat',
                            'Ok, rahmat, {address}',
                            'Kelishdik, rahmat',
                            'Barakalla, {address}!',
                            'Mana bu boshqa gap, rahmat',
                            'Tushunarli, rahmat',
                            'Xo\'p, rahmat!',
                            'Rahmat 👍',
                            'Zo\'r ish, {address}',
                            'Raxmat, {address}',
                            'Ajoyib, rahmat!',
                            'Rahmat, {address}, zo\'r',
                            'Yaxshi, kelishdik',
                            'Qoyil, rahmat!',
                            'Rahmat, {address} 🤝',
                        ],
                        'respectful' => [
                            'Rahmat, {address}!',
                            'Zo\'r, rahmat, {address}!',
                            'Katta rahmat, {address}!',
                            'Rahmat sizga, {address}',
                            'Juda yaxshi, rahmat!',
                            'Raxmat, {address} 🙏',
                            'Kelishdik, rahmat sizga',
                            'Tushunarli, rahmat, {address}',
                            'Xo\'p, katta rahmat!',
                            'Rahmat, {address}, sog\' bo\'ling',
                            'Minnatdorman, {address}',
                            'Barakalla, rahmat sizga',
                            'Ajoyib, rahmat, {address}',
                            'Juda soz, rahmat!',
                            'Rahmat, {address} 👍',
                            'Kelishdik, {address}, rahmat',
                            'Yaxshi, katta rahmat',
                            'Rahmat sizga!',
                            'Zo\'r, rahmat sizga, {address}',
                            'Rahmat, {address}, charchamang',
                        ],
                    ],
                    'ru' => [
                        'plain' => [
                            'Отлично, спасибо!',
                            'Спасибо, {address}',
                            'Спасибо!',
                            'Супер, спасибо',
                            'Отлично 👍',
                            'Принято, спасибо',
                            'Спасибо, {address}, отлично',
                            'Хорошо, спасибо!',
                            'Договорились, спасибо',
                            'Класс, спасибо, {address}',
                            'Ясно, спасибо',
                            'Благодарю!',
                            'Спасибо большое',
                            'Ок, спасибо, {address}',
                            'Отлично, договорились',
                            'Спасибо 🙌',
                            'Супер, {address}!',
                            'Спасибо, так держать',
                            'Принято 👍',
                            'Отлично, благодарю',
                            'Сэнк ю!',
                        ],
                        'respectful' => [
                            'Спасибо, {address}!',
                            'Отлично, спасибо, {address}!',
                            'Спасибо большое, {address}!',
                            'Благодарю, {address}!',
                            'Большое спасибо!',
                            'Отлично, благодарю вас',
                            'Спасибо вам, {address}',
                            'Договорились, спасибо вам!',
                            'Принято, спасибо, {address}',
                            'Очень хорошо, спасибо!',
                            'Спасибо вам большое 🙏',
                            'Благодарю вас, {address}',
                            'Замечательно, спасибо!',
                            'Спасибо, {address}, договорились',
                            'Хорошо, большое спасибо',
                            'Спасибо вам!',
                            'Отлично, {address}, спасибо вам',
                            'Принято, благодарю',
                            'Спасибо, {address} 👍',
                            'Прекрасно, спасибо вам!',
                        ],
                    ],
                ],
                'media' => ['gifs' => [], 'voices' => ['uz' => [], 'ru' => []]],
            ],
        ],

        /*
         * Someone ignores the penalties: after this many in a row with not
         * a word back, a nudge - what was done by hand on 2026-10-06
         * ("Рушана апа, илтимос", "Ukam, men bularni prosto takka
         * yozmayman"). Only in the penalties' working hours; the count
         * starts over after it.
         */
        'silence' => [
            'enabled' => true,
            'after_penalties' => 5,
            'answers' => [
                'uz' => [
                    'plain' => [
                        'Javob kutyapman, {address}',
                        'Men bularni shunchaki yozmayman, {address}',
                        'Javob bormi, {address}?',
                        'Ko\'ryapsizmi, {address}?',
                        '{address}, jarimalar to\'planib qoldi',
                        'Bularga javob kerak, {address}',
                        'Hech bo\'lmasa «+» yozib qo\'ying',
                        '{address}, javob yo\'q-ku',
                        'Javobsiz qolmasin, {address}',
                        'Bir og\'iz javob yozing, {address}',
                        'Jarimalarni ko\'rib chiqing, {address}',
                        'Kutyapman, {address}, javob yozing',
                        'Statuslar hali ham turibdi, {address}',
                        'Shunchaki yozmayapman, javob kerak',
                        'Iltimos, javob qaytaring, {address}',
                        '{address}, jim turmang',
                        'Javob yozib qo\'ying, {address}',
                        '{address}, xabarlarni o\'qing, iltimos',
                    ],
                    'respectful' => [
                        'Iltimos, {address}',
                        'Iltimos, javob bering, {address}',
                        '{address}, ko\'rib chiqsangiz',
                        'Javobingizni kutyapman, {address}',
                        'Iltimos, bir qarab qo\'ying, {address}',
                        '{address}, iltimos, javob yozib qo\'ysangiz',
                        'Jarimalar to\'planib qoldi, {address}, iltimos',
                        'Kechirasiz, {address}, javob kutyapman',
                        'Iltimos, «+» bo\'lsa ham yozib qo\'ying',
                        '{address}, vaqtingiz bo\'lsa, qarab bering',
                        'Iltimos, {address}, statuslarni yangilab bering',
                    ],
                ],
                'ru' => [
                    'plain' => [
                        'Жду ответа, {address}',
                        'Ответьте, пожалуйста, {address}',
                        '{address}, ответ будет?',
                        'Штрафы копятся, {address}',
                        'Хотя бы «+» напишите',
                        '{address}, видите сообщения?',
                        'Я не просто так пишу, {address}',
                        'Нужен ответ, {address}',
                        'Жду обратную связь, {address}',
                        '{address}, отпишитесь, пожалуйста',
                        'Ответа так и нет, {address}',
                    ],
                    'respectful' => [
                        'Пожалуйста, {address}',
                        'Ответьте, пожалуйста, {address}',
                        '{address}, посмотрите, пожалуйста',
                        'Жду вашего ответа, {address}',
                        'Извините, {address}, жду ответа',
                        '{address}, будьте добры, отпишитесь',
                        'Пожалуйста, хотя бы «+», {address}',
                        'Штрафы накопились, {address}, посмотрите, пожалуйста',
                        '{address}, когда будет минутка, ответьте',
                        'Очень жду ответа, {address}',
                    ],
                ],
            ],
            'media' => ['gifs' => [], 'voices' => ['uz' => [], 'ru' => []]],
        ],

        /*
         * "Доброе утро. Готово" -> "Доброе утро, Анна!", then the thanks.
         * Names are only for the panel.
         */
        'greetings' => [
            'enabled' => true,

            'fillers' => [
                'aka', 'akajon', 'opa', 'opajon', 'apa', 'uka', 'ukam', 'brat', 'hammaga',
                'ака', 'акажон', 'опа', 'опажон', 'апа', 'ука', 'укам', 'брат', 'хаммага',
                'всем', 'коллеги', 'вам', 'тебе',
                '🙂', '😊', '☺', '🤝', '👋', '🌞', '☀',
            ],

            'list' => [
                [
                    'name' => 'Утро',
                    'keywords' => ['доброе утро', 'доброго утра', 'утро доброе', 'xayrli tong', 'hayrli tong', 'хайрли тонг', 'good morning'],
                    'answers' => [
                        'uz' => [
                            'plain' => [
                                'Xayrli tong, {address}!',
                                'Xayrli tong!',
                                'Assalomu alaykum, {address}, xayrli tong!',
                                'Tong xayrli bo\'lsin, {address}',
                                'Xayrli tong, {address} ☀️',
                                'Xayrli tong, ishlar omadli bo\'lsin!',
                                'Sizga ham xayrli tong, {address}',
                                'Xayrli tong, kuningiz yaxshi o\'tsin',
                                'Xayrli tong, {address}! 🙂',
                                'Hayrli tong, {address}',
                            ],
                            'respectful' => [
                                'Xayrli tong, {address}!',
                                'Xayrli tong!',
                                'Assalomu alaykum, {address}, xayrli tong!',
                                'Tong xayrli bo\'lsin, {address}',
                                'Xayrli tong, {address} ☀️',
                                'Xayrli tong, ishlar omadli bo\'lsin!',
                                'Sizga ham xayrli tong, {address}',
                                'Xayrli tong, kuningiz yaxshi o\'tsin',
                                'Xayrli tong, {address}! 🙂',
                                'Hayrli tong, {address}',
                            ],
                        ],
                        'ru' => [
                            'plain' => [
                                'Доброе утро, {address}!',
                                'Доброе утро!',
                                'И вам доброе утро, {address}!',
                                'Доброе утро, {address} ☀️',
                                'Доброе утро, хорошего дня!',
                                'Утро доброе, {address}',
                                'Доброе утро, {address}! Хорошего дня',
                                'Доброе, {address}!',
                                'Доброе утро, {address} 🙂',
                                'Доброго утра, {address}!',
                            ],
                            'respectful' => [
                                'Доброе утро, {address}!',
                                'Доброе утро!',
                                'И вам доброе утро, {address}!',
                                'Доброе утро, {address} ☀️',
                                'Доброе утро, хорошего дня!',
                                'Утро доброе, {address}',
                                'Доброе утро, {address}! Хорошего дня',
                                'Доброе, {address}!',
                                'Доброе утро, {address} 🙂',
                                'Доброго утра, {address}!',
                            ],
                        ],
                    ],
                ],
                [
                    'name' => 'День',
                    'keywords' => ['добрый день', 'xayrli kun', 'hayrli kun', 'хайрли кун'],
                    'answers' => [
                        'uz' => [
                            'plain' => [
                                'Xayrli kun, {address}!',
                                'Xayrli kun!',
                                'Assalomu alaykum, {address}, xayrli kun!',
                                'Sizga ham xayrli kun, {address}',
                                'Xayrli kun, {address} 🙂',
                                'Kuningiz xayrli o\'tsin, {address}',
                                'Xayrli kun, ishlaringizga omad!',
                                'Hayrli kun, {address}',
                                'Xayrli kun, {address}, omad!',
                                'Xayrli kun, charchamang!',
                            ],
                            'respectful' => [
                                'Xayrli kun, {address}!',
                                'Xayrli kun!',
                                'Assalomu alaykum, {address}, xayrli kun!',
                                'Sizga ham xayrli kun, {address}',
                                'Xayrli kun, {address} 🙂',
                                'Kuningiz xayrli o\'tsin, {address}',
                                'Xayrli kun, ishlaringizga omad!',
                                'Hayrli kun, {address}',
                                'Xayrli kun, {address}, omad!',
                                'Xayrli kun, charchamang!',
                            ],
                        ],
                        'ru' => [
                            'plain' => [
                                'Добрый день, {address}!',
                                'Добрый день!',
                                'И вам добрый день, {address}!',
                                'Добрый день, {address} 🙂',
                                'Добрый день, хорошего дня!',
                                'День добрый, {address}',
                                'Добрый, {address}!',
                                'Добрый день, {address}! Удачного дня',
                                'Приветствую, {address}, добрый день!',
                                'Добрый день, {address}, удачи!',
                            ],
                            'respectful' => [
                                'Добрый день, {address}!',
                                'Добрый день!',
                                'И вам добрый день, {address}!',
                                'Добрый день, {address} 🙂',
                                'Добрый день, хорошего дня!',
                                'День добрый, {address}',
                                'Добрый, {address}!',
                                'Добрый день, {address}! Удачного дня',
                                'Приветствую, {address}, добрый день!',
                                'Добрый день, {address}, удачи!',
                            ],
                        ],
                    ],
                ],
                [
                    'name' => 'Вечер',
                    'keywords' => ['добрый вечер', 'xayrli kech', 'hayrli kech', 'хайрли кеч'],
                    'answers' => [
                        'uz' => [
                            'plain' => [
                                'Xayrli kech, {address}!',
                                'Xayrli kech!',
                                'Sizga ham xayrli kech, {address}',
                                'Xayrli kech, {address} 🙂',
                                'Xayrli kech, charchamang!',
                                'Kechingiz xayrli bo\'lsin, {address}',
                                'Xayrli kech, {address}, charchamang',
                                'Hayrli kech, {address}',
                                'Assalomu alaykum, {address}, xayrli kech!',
                                'Xayrli kech, yaxshi dam oling!',
                            ],
                            'respectful' => [
                                'Xayrli kech, {address}!',
                                'Xayrli kech!',
                                'Sizga ham xayrli kech, {address}',
                                'Xayrli kech, {address} 🙂',
                                'Xayrli kech, charchamang!',
                                'Kechingiz xayrli bo\'lsin, {address}',
                                'Xayrli kech, {address}, charchamang',
                                'Hayrli kech, {address}',
                                'Assalomu alaykum, {address}, xayrli kech!',
                                'Xayrli kech, yaxshi dam oling!',
                            ],
                        ],
                        'ru' => [
                            'plain' => [
                                'Добрый вечер, {address}!',
                                'Добрый вечер!',
                                'И вам добрый вечер, {address}!',
                                'Добрый вечер, {address} 🙂',
                                'Добрый вечер, хорошего отдыха!',
                                'Вечер добрый, {address}',
                                'Добрый, {address}!',
                                'Добрый вечер, {address}, спасибо за работу',
                                'Добрый вечер, {address}! Хорошего вечера',
                                'Приветствую, {address}, добрый вечер!',
                            ],
                            'respectful' => [
                                'Добрый вечер, {address}!',
                                'Добрый вечер!',
                                'И вам добрый вечер, {address}!',
                                'Добрый вечер, {address} 🙂',
                                'Добрый вечер, хорошего отдыха!',
                                'Вечер добрый, {address}',
                                'Добрый, {address}!',
                                'Добрый вечер, {address}, спасибо за работу',
                                'Добрый вечер, {address}! Хорошего вечера',
                                'Приветствую, {address}, добрый вечер!',
                            ],
                        ],
                    ],
                ],
                [
                    'name' => 'Ассалому алайкум',
                    'keywords' => [
                        'assalomu alaykum', 'assalomu aleykum', 'assalamu alaykum', 'assalamu aleykum',
                        'ассалому алайкум', 'ассалому алейкум', 'ассаламу алайкум', 'ассаламу алейкум',
                        'salom alaykum', 'salam aleykum', 'салом алайкум', 'салам алейкум',
                        /*
                         * Typed every way there is - "Ассалому алекейкум"
                         * (2026-10-13): the second word only has to start
                         * with "ал".
                         */
                        'assalomu al*', 'assalamu al*', 'ассалому ал*', 'ассаламу ал*',
                        'salom al*', 'salam al*', 'салом ал*', 'салам ал*',
                        'assalom*', 'ассалом*', 'ассалам*',
                    ],
                    'answers' => [
                        'uz' => [
                            'plain' => [
                                'Va alaykum assalom, {address}!',
                                'Va alaykum assalom!',
                                'Vaalaykum assalom, {address}',
                                'Va alaykum assalom, {address} 🙂',
                                'Va alaykum assalom, xayrli kun!',
                                'Valeykum assalom, {address}',
                                'Va alaykum assalom, {address}, ishlar omadli bo\'lsin',
                                'Vaalaykum assalom!',
                                'Va alaykum assalom, {address} 🤝',
                                'Va alaykum assalom, {address}, charchamang',
                            ],
                            'respectful' => [
                                'Va alaykum assalom, {address}!',
                                'Va alaykum assalom!',
                                'Vaalaykum assalom, {address}',
                                'Va alaykum assalom, {address} 🙂',
                                'Va alaykum assalom, xayrli kun!',
                                'Valeykum assalom, {address}',
                                'Va alaykum assalom, {address}, ishlar omadli bo\'lsin',
                                'Vaalaykum assalom!',
                                'Va alaykum assalom, {address} 🤝',
                                'Va alaykum assalom, {address}, charchamang',
                            ],
                        ],
                        'ru' => [
                            'plain' => [
                                'Ва алейкум ассалам, {address}!',
                                'Ва алейкум ассалам!',
                                'Ваалейкум ассалам, {address}',
                                'Ва алейкум ассалам, {address} 🙂',
                                'Ва алейкум ассалам, хорошего дня!',
                                'Ва алейкум ассалам, {address}! Добрый день',
                                'Ваалейкум ассалам!',
                                'Ва алейкум ассалам, {address} 🤝',
                                'Ва алейкум ассалам, {address}, удачного дня',
                                'Алейкум ассалам, {address}',
                            ],
                            'respectful' => [
                                'Ва алейкум ассалам, {address}!',
                                'Ва алейкум ассалам!',
                                'Ваалейкум ассалам, {address}',
                                'Ва алейкум ассалам, {address} 🙂',
                                'Ва алейкум ассалам, хорошего дня!',
                                'Ва алейкум ассалам, {address}! Добрый день',
                                'Ваалейкум ассалам!',
                                'Ва алейкум ассалам, {address} 🤝',
                                'Ва алейкум ассалам, {address}, удачного дня',
                                'Алейкум ассалам, {address}',
                            ],
                        ],
                    ],
                ],
                [
                    'name' => 'Салом',
                    'keywords' => ['здравствуйте', 'здраствуйте', 'здравствуй', 'приветствую', 'привет', 'salom', 'salam', 'салом', 'салам', 'hello'],
                    'answers' => [
                        'uz' => [
                            'plain' => [
                                'Salom, {address}!',
                                'Salom!',
                                'Salom, {address} 🙂',
                                'Assalomu alaykum, {address}!',
                                'Salom, {address}, xayrli kun',
                                'Salom-salom, {address}!',
                                'Salom, {address} 👋',
                                'Assalomu alaykum!',
                                'Salom, {address}, ishlar omadli bo\'lsin',
                                'Salom, charchamang!',
                            ],
                            'respectful' => [
                                'Assalomu alaykum, {address}!',
                                'Assalomu alaykum!',
                                'Assalomu alaykum, {address} 🙂',
                                'Va alaykum assalom, {address}!',
                                'Assalomu alaykum, {address}, xayrli kun!',
                                'Assalomu alaykum, {address}, charchamang',
                                'Assalomu alaykum, ishlaringizga omad!',
                                'Assalomu alaykum, {address} 🤝',
                                'Assalomu alaykum, {address}, kuningiz xayrli o\'tsin',
                                'Assalomu aleykum, {address}',
                            ],
                        ],
                        'ru' => [
                            'plain' => [
                                'Привет, {address}!',
                                'Привет!',
                                'Привет, {address} 🙂',
                                'Приветствую, {address}!',
                                'Привет-привет, {address}',
                                'Привет, {address}, хорошего дня',
                                'Здравствуйте, {address}!',
                                'Привет, {address} 👋',
                                'Привет! Хорошего дня',
                                'Салют, {address}!',
                            ],
                            'respectful' => [
                                'Здравствуйте, {address}!',
                                'Здравствуйте!',
                                'Здравствуйте, {address} 🙂',
                                'Приветствую вас, {address}!',
                                'Здравствуйте, {address}, хорошего дня!',
                                'Приветствую, {address}!',
                                'Здравствуйте, {address} 🤝',
                                'Здравствуйте, удачного дня!',
                                'Здравствуйте, {address}, всего доброго',
                                'Доброго дня, {address}!',
                            ],
                        ],
                    ],
                ],
            ],
        ],

        /*
         * The penalty used to come first and the person greeted us - the
         * older one greeting first, which the account's owner had to put
         * right by hand (2026-10-13). Now the day starts with ours.
         */
        'opening' => [
            'enabled' => true,
            'answers' => [
                'uz' => [
                    'plain' => [
                        'Assalomu alaykum, {address}!',
                        'Assalomu alaykum!',
                        'Assalomu alaykum, {address} 🙂',
                        'Assalomu alaykum, {address}, xayrli kun!',
                        'Assalomu alaykum, {address}, charchamang',
                        'Assalomu alaykum, {address} 🤝',
                        'Assalomu alaykum, kuningiz xayrli o\'tsin!',
                        'Assalomu alaykum, {address}, ishlaringizga omad',
                        'Assalomu aleykum, {address}',
                        'Assalomu alaykum, {address}, omad tilayman',
                        'Salom, {address}!',
                        'Salom, {address} 👋',
                    ],
                    'respectful' => [
                        'Assalomu alaykum, {address}!',
                        'Assalomu alaykum!',
                        'Assalomu alaykum, {address} 🙂',
                        'Assalomu alaykum, {address}, xayrli kun!',
                        'Assalomu alaykum, {address}, charchamang',
                        'Assalomu alaykum, {address} 🤝',
                        'Assalomu alaykum, kuningiz xayrli o\'tsin!',
                        'Assalomu alaykum, {address}, ishlaringizga omad',
                        'Assalomu aleykum, {address}',
                        'Assalomu alaykum, {address}, omad tilayman',
                    ],
                ],
                'ru' => [
                    'plain' => [
                        'Здравствуйте, {address}!',
                        'Здравствуйте!',
                        'Здравствуйте, {address} 🙂',
                        'Приветствую, {address}!',
                        'Здравствуйте, {address}, хорошего дня!',
                        'Ассаламу алейкум, {address}!',
                        'Здравствуйте, {address} 🤝',
                        'Приветствую вас, {address}!',
                        'Здравствуйте, {address}, удачного дня',
                        'Доброго дня, {address}!',
                        'Привет, {address}!',
                        'Привет, {address} 👋',
                    ],
                    'respectful' => [
                        'Здравствуйте, {address}!',
                        'Здравствуйте!',
                        'Здравствуйте, {address} 🙂',
                        'Приветствую, {address}!',
                        'Здравствуйте, {address}, хорошего дня!',
                        'Ассаламу алейкум, {address}!',
                        'Здравствуйте, {address} 🤝',
                        'Приветствую вас, {address}!',
                        'Здравствуйте, {address}, удачного дня',
                        'Доброго дня, {address}!',
                    ],
                ],
            ],
            'media' => ['gifs' => [], 'voices' => ['uz' => [], 'ru' => []]],
        ],

        /*
         * "Обновите статус" to the same person twice in a row reads like a
         * bot; a person writes "это тоже" the second time.
         */
        'follow_up' => [
            'enabled' => true,
            'window_minutes' => 60,
            'answers' => [
                'uz' => [
                    'plain' => [
                        'Bu ham',
                        'Bunisi ham',
                        'Bu ham bor',
                        'Buni ham unutmang',
                        'Mana bu ham',
                        'Bunga ham qarang',
                        'Bu ham shunaqa',
                        'Yana bittasi',
                        'Buni ham, {address}',
                        'Bu ham o\'sha',
                    ],
                    'respectful' => [
                        'Bu ham, {address}',
                        'Bunisi ham, iltimos',
                        'Buni ham unutmang, {address}',
                        'Mana bu ham',
                        'Bunga ham qarab qo\'ying, iltimos',
                        'Bu ham, iltimos',
                        'Yana bittasi, {address}',
                        'Bunisini ham ko\'rib qo\'ying',
                        'Bu ham shu qatorda',
                        'Buni ham, iltimos, {address}',
                    ],
                ],
                'ru' => [
                    'plain' => [
                        'Это тоже',
                        'И это',
                        'Вот это тоже',
                        'Это тоже не забудьте',
                        'И вот это',
                        'Ещё одна',
                        'Это тоже, {address}',
                        'И это тоже',
                        'Сюда тоже гляньте',
                        'Этот тоже',
                    ],
                    'respectful' => [
                        'Это тоже, {address}',
                        'И это, пожалуйста',
                        'Вот это тоже, {address}',
                        'Это тоже не забудьте, пожалуйста',
                        'И вот это, пожалуйста',
                        'Ещё одна, {address}',
                        'Посмотрите и это, пожалуйста',
                        'И это тоже, {address}',
                        'Сюда тоже взгляните, пожалуйста',
                        'Этот тоже, пожалуйста',
                    ],
                ],
            ],
            'answers_many' => [
                'uz' => [
                    'plain' => [
                        'Bular ham',
                        'Bulari ham',
                        'Mana bular ham',
                        'Bularni ham unutmang',
                        'Bularga ham qarang',
                        'Yana bir nechtasi',
                        'Bular ham shunaqa',
                        'Bularni ham, {address}',
                        'Bular ham bor',
                        'Mana yana',
                    ],
                    'respectful' => [
                        'Bular ham, {address}',
                        'Bularni ham unutmang, iltimos',
                        'Mana bular ham, {address}',
                        'Bularga ham qarab qo\'ying, iltimos',
                        'Bular ham, iltimos',
                        'Yana bir nechtasi, {address}',
                        'Bularni ham ko\'rib qo\'ying',
                        'Bular ham shu qatorda',
                        'Bularni ham, iltimos, {address}',
                        'Mana yana, {address}',
                    ],
                ],
                'ru' => [
                    'plain' => [
                        'Вот эти тоже',
                        'И эти',
                        'Эти тоже не забудьте',
                        'Вот эти тоже не забудьте',
                        'И вот эти',
                        'Ещё несколько',
                        'Эти тоже, {address}',
                        'И эти тоже',
                        'Сюда тоже гляньте',
                        'Вот ещё',
                    ],
                    'respectful' => [
                        'Вот эти тоже, {address}',
                        'И эти, пожалуйста',
                        'Эти тоже не забудьте, пожалуйста',
                        'Вот эти тоже, пожалуйста',
                        'И вот эти, {address}',
                        'Ещё несколько, {address}',
                        'Посмотрите и эти, пожалуйста',
                        'И эти тоже, {address}',
                        'Сюда тоже взгляните, пожалуйста',
                        'Вот ещё, {address}',
                    ],
                ],
            ],
        ],
    ],

];
