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

    /*
     * The script Uzbek texts go out in: "cyrillic" ("Рахмат", "Салом") or
     * "latin" (as the phrases are written). The phrases themselves stay in
     * Latin - they are converted on the way out (UzbekScript), for every
     * text the bot writes in Uzbek: replies, greetings, nudges, penalty
     * comments.
     */
    'uz_script' => env('AUTO_REPLIES_UZ_SCRIPT', 'cyrillic'),

    /*
     * How often a text calls the person by the card's address ("Пулатов,
     * нарх керак"), in percent: the rest leave it out with its comma, as
     * when none is written. Every text the bot writes - replies,
     * greetings, nudges, penalty comments. 20 - one message in five.
     */
    'address_chance' => (int) env('AUTO_REPLIES_ADDRESS_CHANCE', 20),

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

    /*
     * A message of more words than this is never answered, whatever kind
     * it looks like ("Клиент пока не ответил, он в Китае... что мне
     * написать?"): it is a conversation for the person in charge. It is
     * still kept on the penalty it replies to. Not in the panel: it holds
     * over every kind's own max_words.
     */
    'silent_over_words' => 15,

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
                            'Зур, рахмат!',
                            'Рахмат, {address}',
                            'Рахмат!',
                            'Катта рахмат, {address}',
                            'Зур 👍',
                            'Яхши, рахмат',
                            'Ок, рахмат, {address}',
                            'Келишдик, рахмат',
                            'Баракалла, {address}!',
                            'Мана бу бошка гап, рахмат',
                            'Тушунарли, рахмат',
                            'Хуп, рахмат!',
                            'Рахмат 👍',
                            'Зур иш, {address}',
                            'Рахмат, {address}',
                            'Ажойиб, рахмат!',
                            'Рахмат, {address}, зур',
                            'Яхши, келишдик',
                            'Койил, рахмат!',
                            'Рахмат, {address} 🤝',
                        ],
                        'respectful' => [
                            'Рахмат, {address}!',
                            'Зур, рахмат, {address}!',
                            'Катта рахмат, {address}!',
                            'Рахмат сизга, {address}',
                            'Жуда яхши, рахмат!',
                            'Рахмат, {address} 🙏',
                            'Келишдик, рахмат сизга',
                            'Тушунарли, рахмат, {address}',
                            'Хуп, катта рахмат!',
                            'Рахмат, {address}, сог булинг',
                            'Миннатдорман, {address}',
                            'Баракалла, рахмат сизга',
                            'Ажойиб, рахмат, {address}',
                            'Жуда соз, рахмат!',
                            'Рахмат, {address} 👍',
                            'Келишдик, {address}, рахмат',
                            'Яхши, катта рахмат',
                            'Рахмат сизга!',
                            'Зур, рахмат сизга, {address}',
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
                        'Жавоб кутяпман, {address}',
                        'Мен буларни шунчаки ёзмайман, {address}',
                        'Жавоб борми, {address}?',
                        'Куряпсизми, {address}?',
                        '{address}, жарималар тупланиб колди',
                        'Буларга жавоб керак, {address}',
                        'Хеч булмаса + ёзиб куйинг',
                        '{address}, жавоб йук-ку',
                        'Жавобсиз колмасин, {address}',
                        'Бир огиз жавоб ёзинг, {address}',
                        'Жарималарни куриб чикинг, {address}',
                        'Кутяпман, {address}, жавоб ёзинг',
                        'Статуслар хали хам турибди, {address}',
                        'Шунчаки ёзмаяпман, жавоб керак',
                        'Илтимос, жавоб кайтаринг, {address}',
                        '{address}, жим турманг',
                        'Жавоб ёзиб куйинг, {address}',
                        '{address}, хабарларни укинг, илтимос',
                    ],
                    'respectful' => [
                        'Илтимос, {address}',
                        'Илтимос, жавоб беринг, {address}',
                        '{address}, куриб чиксангиз',
                        'Жавобингизни кутяпман, {address}',
                        'Илтимос, бир караб куйинг, {address}',
                        '{address}, илтимос, жавоб ёзиб куйсангиз',
                        'Жарималар тупланиб колди, {address}, илтимос',
                        'Кечирасиз, {address}, жавоб кутяпман',
                        'Илтимос, + булса хам ёзиб куйинг',
                        '{address}, вактингиз булса, караб беринг',
                        'Илтимос, {address}, статусларни янгилаб беринг',
                    ],
                ],
                'ru' => [
                    'plain' => [
                        'Жду ответа, {address}',
                        'Ответьте, пожалуйста, {address}',
                        '{address}, ответ будет?',
                        'Штрафы копятся, {address}',
                        'Хотя бы + напишите',
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
                        'Пожалуйста, хотя бы +, {address}',
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
                                'Хайрли тонг, {address}!',
                                'Хайрли тонг!',
                                'Ассалому алайкум, {address}, хайрли тонг!',
                                'Тонг хайрли булсин, {address}',
                                'Хайрли тонг, {address} ☀️',
                                'Сизга хам хайрли тонг, {address}',
                                'Хайрли тонг, кунингиз яхши утсин',
                                'Хайрли тонг, {address}! 🙂',
                                'Хайрли тонг, {address}',
                            ],
                            'respectful' => [
                                'Хайрли тонг, {address}!',
                                'Хайрли тонг!',
                                'Ассалому алайкум, {address}, хайрли тонг!',
                                'Тонг хайрли булсин, {address}',
                                'Хайрли тонг, {address} ☀️',
                                'Сизга хам хайрли тонг, {address}',
                                'Хайрли тонг, кунингиз яхши утсин',
                                'Хайрли тонг, {address}! 🙂',
                                'Хайрли тонг, {address}',
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
                                'Хайрли кун, {address}!',
                                'Хайрли кун!',
                                'Ассалому алайкум, {address}, хайрли кун!',
                                'Сизга хам хайрли кун, {address}',
                                'Хайрли кун, {address} 🙂',
                                'Кунингиз хайрли утсин, {address}',
                                'Хайрли кун, {address}',
                            ],
                            'respectful' => [
                                'Хайрли кун, {address}!',
                                'Хайрли кун!',
                                'Ассалому алайкум, {address}, хайрли кун!',
                                'Сизга хам хайрли кун, {address}',
                                'Хайрли кун, {address} 🙂',
                                'Кунингиз хайрли утсин, {address}',
                                'Хайрли кун, {address}',
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
                                'Приветствую, {address}, добрый день!',
                            ],
                            'respectful' => [
                                'Добрый день, {address}!',
                                'Добрый день!',
                                'И вам добрый день, {address}!',
                                'Добрый день, {address} 🙂',
                                'Добрый день, хорошего дня!',
                                'День добрый, {address}',
                                'Добрый, {address}!',
                                'Приветствую, {address}, добрый день!',
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
                                'Хайрли кеч, {address}!',
                                'Хайрли кеч!',
                                'Сизга хам хайрли кеч, {address}',
                                'Хайрли кеч, {address} 🙂',
                                'Кечингиз хайрли булсин, {address}',
                                'Хайрли кеч, {address}',
                                'Ассалому алайкум, {address}, хайрли кеч!',
                            ],
                            'respectful' => [
                                'Хайрли кеч, {address}!',
                                'Хайрли кеч!',
                                'Сизга хам хайрли кеч, {address}',
                                'Хайрли кеч, {address} 🙂',
                                'Кечингиз хайрли булсин, {address}',
                                'Хайрли кеч, {address}',
                                'Ассалому алайкум, {address}, хайрли кеч!',
                            ],
                        ],
                        'ru' => [
                            'plain' => [
                                'Добрый вечер, {address}!',
                                'Добрый вечер!',
                                'И вам добрый вечер, {address}!',
                                'Добрый вечер, {address} 🙂',
                                'Вечер добрый, {address}',
                                'Добрый, {address}!',
                                'Добрый вечер, {address}! Хорошего вечера',
                                'Приветствую, {address}, добрый вечер!',
                            ],
                            'respectful' => [
                                'Добрый вечер, {address}!',
                                'Добрый вечер!',
                                'И вам добрый вечер, {address}!',
                                'Добрый вечер, {address} 🙂',
                                'Вечер добрый, {address}',
                                'Добрый, {address}!',
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
                                'Ва алайкум ассалом, {address}!',
                                'Ва алайкум ассалом!',
                                'Ваалайкум ассалом, {address}',
                                'Ва алайкум ассалом, {address} 🙂',
                                'Ва алайкум ассалом, хайрли кун!',
                                'Валейкум ассалом, {address}',
                                'Ваалайкум ассалом!',
                                'Ва алайкум ассалом, {address} 🤝',
                            ],
                            'respectful' => [
                                'Ва алайкум ассалом, {address}!',
                                'Ва алайкум ассалом!',
                                'Ваалайкум ассалом, {address}',
                                'Ва алайкум ассалом, {address} 🙂',
                                'Ва алайкум ассалом, хайрли кун!',
                                'Валейкум ассалом, {address}',
                                'Ваалайкум ассалом!',
                                'Ва алайкум ассалом, {address} 🤝',
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
                                'Салом, {address}!',
                                'Салом!',
                                'Салом, {address} 🙂',
                                'Ассалому алайкум, {address}!',
                                'Салом, {address}, хайрли кун',
                                'Салом-салом, {address}!',
                                'Салом, {address} 👋',
                                'Ассалому алайкум!',
                            ],
                            'respectful' => [
                                'Ассалому алайкум, {address}!',
                                'Ассалому алайкум!',
                                'Ассалому алайкум, {address} 🙂',
                                'Ва алайкум ассалом, {address}!',
                                'Ассалому алайкум, {address}, хайрли кун!',
                                'Ассалому алайкум, {address} 🤝',
                                'Ассалому алайкум, {address}, кунингиз хайрли утсин',
                                'Ассалому алейкум, {address}',
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
                        'Ассалому алайкум, {address}!',
                        'Ассалому алайкум!',
                        'Ассалому алайкум, {address} 🙂',
                        'Ассалому алайкум, {address}, хайрли кун!',
                        'Ассалому алайкум, {address} 🤝',
                        'Ассалому алайкум, кунингиз хайрли утсин!',
                        'Ассалому алейкум, {address}',
                        'Салом, {address}!',
                        'Салом, {address} 👋',
                    ],
                    'respectful' => [
                        'Ассалому алайкум, {address}!',
                        'Ассалому алайкум!',
                        'Ассалому алайкум, {address} 🙂',
                        'Ассалому алайкум, {address}, хайрли кун!',
                        'Ассалому алайкум, {address} 🤝',
                        'Ассалому алайкум, кунингиз хайрли утсин!',
                        'Ассалому алейкум, {address}',
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
                        'Бу хам',
                        'Буниси хам',
                        'Бу хам бор',
                        'Буни хам унутманг',
                        'Мана бу хам',
                        'Бунга хам каранг',
                        'Бу хам шунака',
                        'Яна биттаси',
                        'Буни хам, {address}',
                        'Бу хам уша',
                    ],
                    'respectful' => [
                        'Бу хам, {address}',
                        'Буниси хам, илтимос',
                        'Буни хам унутманг, {address}',
                        'Мана бу хам',
                        'Бунга хам караб куйинг, илтимос',
                        'Бу хам, илтимос',
                        'Яна биттаси, {address}',
                        'Бунисини хам куриб куйинг',
                        'Бу хам шу каторда',
                        'Буни хам, илтимос, {address}',
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
                        'Булар хам',
                        'Булари хам',
                        'Мана булар хам',
                        'Буларни хам унутманг',
                        'Буларга хам каранг',
                        'Яна бир нечтаси',
                        'Булар хам шунака',
                        'Буларни хам, {address}',
                        'Булар хам бор',
                        'Мана яна',
                    ],
                    'respectful' => [
                        'Булар хам, {address}',
                        'Буларни хам унутманг, илтимос',
                        'Мана булар хам, {address}',
                        'Буларга хам караб куйинг, илтимос',
                        'Булар хам, илтимос',
                        'Яна бир нечтаси, {address}',
                        'Буларни хам куриб куйинг',
                        'Булар хам шу каторда',
                        'Буларни хам, илтимос, {address}',
                        'Мана яна, {address}',
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
