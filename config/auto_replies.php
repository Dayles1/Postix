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
|       media               - gifs: [{file, name}] for every language;
|                             voices: {uz: [...], ru: [...]}, only in the
|                             person's own language. Files are uploaded in
|                             the panel. The answer is one random pick out
|                             of the texts, the GIFs and the voices.
|   silence                 - the nudge for someone who ignores the
|       penalties: enabled, after_penalties (this many in a row without a
|       word back from them), answers and media (as above). In the
|       penalties' working hours; the count starts over after it.
|
*/

return [

    'path' => env('AUTO_REPLIES_PATH', storage_path('app/telegram/auto-replies.json')),

    /*
     * The GIFs and voice messages uploaded in the panel (AutoReplyMedia).
     */
    'media_path' => env('AUTO_REPLIES_MEDIA_PATH', storage_path('app/telegram/auto-replies-media')),

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
    ],

];
