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
                    'plain' => ['Javob kutyapman, {address}', 'Men bularni shunchaki yozmayman, {address}'],
                    'respectful' => ['Iltimos, {address}'],
                ],
                'ru' => [
                    'plain' => ['Жду ответа, {address}'],
                    'respectful' => ['Пожалуйста, {address}'],
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
                        'uz' => ['plain' => ['Xayrli tong, {address}!'], 'respectful' => ['Xayrli tong, {address}!']],
                        'ru' => ['plain' => ['Доброе утро, {address}!'], 'respectful' => ['Доброе утро, {address}!']],
                    ],
                ],
                [
                    'name' => 'День',
                    'keywords' => ['добрый день', 'xayrli kun', 'hayrli kun', 'хайрли кун'],
                    'answers' => [
                        'uz' => ['plain' => ['Xayrli kun, {address}!'], 'respectful' => ['Xayrli kun, {address}!']],
                        'ru' => ['plain' => ['Добрый день, {address}!'], 'respectful' => ['Добрый день, {address}!']],
                    ],
                ],
                [
                    'name' => 'Вечер',
                    'keywords' => ['добрый вечер', 'xayrli kech', 'hayrli kech', 'хайрли кеч'],
                    'answers' => [
                        'uz' => ['plain' => ['Xayrli kech, {address}!'], 'respectful' => ['Xayrli kech, {address}!']],
                        'ru' => ['plain' => ['Добрый вечер, {address}!'], 'respectful' => ['Добрый вечер, {address}!']],
                    ],
                ],
                [
                    'name' => 'Ассалому алайкум',
                    'keywords' => [
                        'assalomu alaykum', 'assalomu aleykum', 'assalamu alaykum', 'assalamu aleykum',
                        'ассалому алайкум', 'ассалому алейкум', 'ассаламу алайкум', 'ассаламу алейкум',
                        'salom alaykum', 'salam aleykum', 'салом алайкум', 'салам алейкум',
                        'assalom', 'ассалом', 'ассалам',
                    ],
                    'answers' => [
                        'uz' => ['plain' => ['Va alaykum assalom, {address}!'], 'respectful' => ['Va alaykum assalom, {address}!']],
                        'ru' => ['plain' => ['Ва алейкум ассалам, {address}!'], 'respectful' => ['Ва алейкум ассалам, {address}!']],
                    ],
                ],
                [
                    'name' => 'Салом',
                    'keywords' => ['здравствуйте', 'здраствуйте', 'здравствуй', 'приветствую', 'привет', 'salom', 'salam', 'салом', 'салам', 'hello'],
                    'answers' => [
                        'uz' => ['plain' => ['Salom, {address}!'], 'respectful' => ['Assalomu alaykum, {address}!']],
                        'ru' => ['plain' => ['Привет, {address}!'], 'respectful' => ['Здравствуйте, {address}!']],
                    ],
                ],
            ],
        ],
    ],

];
