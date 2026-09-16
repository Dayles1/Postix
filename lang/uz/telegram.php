<?php

declare(strict_types=1);

return [

    'operation_users' => [

        'title' => 'Operatorlar',

        'description' => 'Telegram foydalanuvchilari va haydovchilar tekshiruvi statistikasi',

        'telegram' => 'Telegram',

        'refresh' => 'Yangilash',

        'loading' => 'Yuklanmoqda...',

        'open_user' => 'Foydalanuvchini ochish',

        'per_page' => 'Sahifada',

        'filters' => [

            'title' => 'Filtrlar',

            'description' => 'Operatorlarni qidirish va saralash',

            'reset' => 'Tozalash',

            'search' => 'Qidiruv',

            'search_placeholder' => 'Ism, username, Telegram ID...',

            'telegram_id' => 'Telegram ID',

            'telegram_id_placeholder' => 'Telegram ID kiriting',

            'username' => 'Username',

            'username_placeholder' => '@username',

            'sort' => 'Saralash',

            'created' => 'Yaratilgan sana',

            'updated' => 'Yangilangan sana',

            'name' => 'Ism',

            'drivers' => 'Haydovchilar',

            'checks' => 'Tekshiruvlar',

            'confirmed' => 'Tasdiqlangan',

            'not_confirmed' => 'Tasdiqlanmagan',

            'pending' => 'Kutilmoqda',

            'match_rate' => 'Moslik darajasi',

            'average_score' => 'O‘rtacha ball',

            'last_check' => 'Oxirgi tekshiruv',

            'ascending' => 'O‘sish tartibida',

            'descending' => 'Kamayish tartibida',

            'apply' => 'Qidirish',

            'more_filters' => 'Ko‘proq filtr',

            'less_filters' => 'Filtrlarni yashirish',

            'status' => 'Tekshiruv holati',

            'status_all' => 'Har qanday holat',

            'confirmed' => 'Tasdiqlangan',

            'not_confirmed' => 'Tasdiqlanmagan',

            'pending' => 'Kutilmoqda',

            'processing' => 'Jarayonda',

            'period' => 'Davr',

            'period_today' => 'Bugun',

            'period_week' => '7 kun',

            'period_month' => '30 kun',

            'period_custom' => 'Boshqa davr',

            'period_all' => 'Barcha vaqt',

            'period_from' => 'Dan',

            'period_to' => 'Gacha',

            'score_range' => 'Moslik bali',

            'score_from' => 'Dan',

            'score_to' => 'Gacha',

            'has_telegram' => 'Telegram mavjud',

            'has_driver' => 'Haydovchi mavjud',

            'any' => 'Muhim emas',

            'yes' => 'Ha',

            'no' => 'Yo‘q',
        ],

        'export' => [

            'title' => 'Excelga eksport',

            'operators' => 'Operatorlar bo‘yicha jamlanma',

            'details' => 'Tekshiruvlar bo‘yicha batafsil',

            'hint' => 'Eksport joriy filtr va davrni hisobga oladi.',
        ],

        'stats' => [

            'users' => 'Operatorlar',

            'drivers' => 'Haydovchilar',

            'checks' => 'Tekshiruvlar',

            'avg_match' => 'O‘rtacha moslik',
        ],

        'table' => [

            'user' => 'Operator',

            'telegram' => 'Telegram',

            'drivers' => 'Haydovchilar',

            'checks' => 'Tekshiruvlar',

            'result' => 'Natija',

            'match' => 'Moslik',

            'score' => 'Ball',

            'id' => 'ID',

            'best' => 'Eng yaxshi',

            'last_check' => 'Oxirgi tekshiruv',

            'no_username' => 'Username mavjud emas',

            'no_telegram_id' => 'Telegram ID mavjud emas',
        ],

        'result' => [

            'confirmed' => 'Tasdiqlangan',

            'not_confirmed' => 'Tasdiqlanmagan',

            'pending' => 'Kutilmoqda',

            'processing' => 'Jarayonda',
        ],

        'empty' => [

            'title' => 'Operatorlar topilmadi',

            'description' => 'Filtr yoki qidiruv so‘rovini o‘zgartirib ko‘ring.',
        ],

        'pagination' => [

            'showing' => 'Ko‘rsatilmoqda',

            'page' => 'Sahifa',

            'of' => 'dan',

            'previous' => 'Oldingi',

            'next' => 'Keyingi',
        ],

        'errors' => [

            'load_failed' => 'Operatorlarni yuklashda xatolik yuz berdi',

            'unknown' => 'Noma’lum xatolik yuz berdi',
        ],

        'dates' => [

            'never' => 'Hali yo‘q',
        ],
    ],


    /*
    |--------------------------------------------------------------------------
    | Operation User
    |--------------------------------------------------------------------------
    */

    'operation_user' => [

        'title' => 'Operator',

        'back' => 'Operatorlar',

        'refresh' => 'Yangilash',

        'loading' => 'Yuklanmoqda...',

        'id' => 'ID',

        'no_username' => 'Username mavjud emas',

        'unknown' => 'Noma’lum',


        /*
        |--------------------------------------------------------------------------
        | Operator stats
        |--------------------------------------------------------------------------
        */

        'stats' => [

            'drivers' => 'Haydovchilar',

            'checks' => 'Tekshiruvlar',

            'confirmed' => 'Tasdiqlangan',

            'not_confirmed' => 'Tasdiqlanmagan',

            'pending' => 'Kutilmoqda',

            'processing' => 'Jarayonda',

            'match_rate' => 'Moslik darajasi',
        ],


        /*
        |--------------------------------------------------------------------------
        | Driver filters
        |--------------------------------------------------------------------------
        */

        'filters' => [

            'title' => 'Filtrlar',

            'all' => 'Barcha vaqt',

            'last_week' => '7 kun',

            'last_month' => '30 kun',

            'from' => 'Dan',

            'to' => 'Gacha',

            'clear' => 'Tozalash',

            'status' => 'Holat',

            'status_all' => 'Har qanday holat',

            'confirmed' => 'Tasdiqlangan',

            'not_confirmed' => 'Tasdiqlanmagan',

            'pending' => 'Kutilmoqda',

            'processing' => 'Jarayonda',
        ],

        'export' => [

            'button' => 'Excelga eksport',

            'hint' => 'Eksport joriy filtr va davrni hisobga oladi.',
        ],


        /*
        |--------------------------------------------------------------------------
        | Drivers
        |--------------------------------------------------------------------------
        */

        'drivers' => [

            'title' => 'Haydovchilar',

            'per_page' => 'Sahifada',

            'phones' => 'telefon',

            'checks' => 'tekshiruv',

            'show_phones' => 'Telefonlarni ko‘rsatish',

            'hide_phones' => 'Telefonlarni yashirish',

            'no_drivers' => 'Haydovchilar mavjud emas',

            'no_drivers_description' => 'Ushbu operatorga hali haydovchilar biriktirilmagan.',

            'no_resolved_phones' => 'Bog‘langan telefon raqamlari mavjud emas',

            'no_username' => 'Username mavjud emas',

            'unknown_date' => 'Sana noma’lum',


            /*
            |--------------------------------------------------------------------------
            | Driver statuses
            |--------------------------------------------------------------------------
            */

            'confirmed' => 'Tasdiqlangan',

            'confirmed_description' => 'Haydovchi ma’lumotlari tasdiqlangan.',

            'not_confirmed' => 'Tasdiqlanmagan',

            'not_confirmed_description' => 'Haydovchi ma’lumotlari tasdiqlanmagan.',

            'pending' => 'Kutilmoqda',

            'pending_description' => 'Haydovchi tasdiqlanishini kutmoqda.',

            'unknown_description' => 'Haydovchi holati noma’lum.',
        ],


        /*
        |--------------------------------------------------------------------------
        | Pagination
        |--------------------------------------------------------------------------
        */

        'pagination' => [

            'page' => 'Sahifa',

            'of' => 'dan',

            'previous' => 'Oldingi',

            'next' => 'Keyingi',
        ],


        /*
        |--------------------------------------------------------------------------
        | Errors
        |--------------------------------------------------------------------------
        */

        'errors' => [

            'load_operator' => 'Operatorni yuklashda xatolik yuz berdi',

            'load_drivers' => 'Haydovchilarni yuklashda xatolik yuz berdi',

            'unknown' => 'Noma’lum xatolik yuz berdi',
        ],
    ],


    /*
    |--------------------------------------------------------------------------
    | Drivers (list page)
    |--------------------------------------------------------------------------
    */

    'drivers' => [

        'title' => 'Haydovchilar',

        'description' => 'Barcha operatorlar bo‘yicha Telegram orqali tekshirilgan haydovchilar',

        'refresh' => 'Yangilash',

        'loading' => 'Yuklanmoqda...',

        'filters' => [

            'title' => 'Filtrlar',

            'description' => 'Haydovchilarni qidirish va saralash',

            'reset' => 'Tozalash',

            'search' => 'Qidiruv',

            'search_placeholder' => 'Haydovchi ismi...',

            'sort' => 'Saralash',

            'created' => 'Yaratilgan sana',

            'name' => 'Ism',

            'checks' => 'Tekshiruvlar',

            'confirmed' => 'Tasdiqlangan',

            'not_confirmed' => 'Tasdiqlanmagan',

            'best_match_score' => 'Eng yaxshi ball',

            'last_check' => 'Oxirgi tekshiruv',

            'ascending' => 'O‘sish tartibida',

            'descending' => 'Kamayish tartibida',

            'apply' => 'Qidirish',

            'status' => 'Haydovchi holati',

            'status_all' => 'Har qanday holat',

            'confirmed_status' => 'Tasdiqlangan',

            'not_confirmed_status' => 'Tasdiqlanmagan',

            'pending_status' => 'Kutilmoqda',

            'check_status' => 'Tekshiruv holati',

            'period' => 'Davr',

            'period_today' => 'Bugun',

            'period_week' => '7 kun',

            'period_month' => '30 kun',

            'period_custom' => 'Boshqa davr',

            'period_all' => 'Barcha vaqt',

            'period_from' => 'Dan',

            'period_to' => 'Gacha',

            'score_range' => 'Moslik bali',

            'score_from' => 'Dan',

            'score_to' => 'Gacha',
        ],

        'stats' => [

            'total' => 'Haydovchilar',

            'checks' => 'Tekshiruvlar',

            'confirmed' => 'Tasdiqlangan',

            'avg_match' => 'O‘rtacha moslik',
        ],

        'table' => [

            'driver' => 'Haydovchi',

            'operator' => 'Operator',

            'status' => 'Holat',

            'checks' => 'Tekshiruvlar',

            'result' => 'Natija',

            'score' => 'Ball',

            'last_check' => 'Oxirgi tekshiruv',

            'phones' => 'Telefonlar',

            'id' => 'ID',

            'no_operator' => 'Operator yo‘q',
        ],

        'status' => [

            'confirmed' => 'Tasdiqlangan',

            'not_confirmed' => 'Tasdiqlanmagan',

            'pending' => 'Kutilmoqda',

            'unknown' => 'Noma’lum',
        ],

        'export' => [

            'button' => 'Excelga eksport',

            'hint' => 'Eksport joriy filtr va davrni hisobga oladi.',
        ],

        'empty' => [

            'title' => 'Haydovchilar topilmadi',

            'description' => 'Filtr yoki qidiruv so‘rovini o‘zgartirib ko‘ring.',
        ],

        'pagination' => [

            'showing' => 'Ko‘rsatilmoqda',

            'page' => 'Sahifa',

            'of' => 'dan',

            'previous' => 'Oldingi',

            'next' => 'Keyingi',
        ],

        'errors' => [

            'load_failed' => 'Haydovchilarni yuklashda xatolik yuz berdi',

            'unknown' => 'Noma’lum xatolik yuz berdi',
        ],

        'dates' => [

            'never' => 'Hali yo‘q',
        ],
    ],


    /*
    |--------------------------------------------------------------------------
    | Resolved phones (list page)
    |--------------------------------------------------------------------------
    */

    'resolved_phones' => [

        'title' => 'Telegram raqamlari',

        'description' => 'Telegram orqali aniqlangan barcha telefon raqamlari va tekshiruv natijalari',

        'refresh' => 'Yangilash',

        'loading' => 'Yuklanmoqda...',

        'filters' => [

            'title' => 'Filtrlar',

            'description' => 'Aniqlangan raqamlarni qidirish va saralash',

            'reset' => 'Tozalash',

            'search' => 'Qidiruv',

            'search_placeholder' => 'Telefon, username, ism...',

            'sort' => 'Saralash',

            'created' => 'Yaratilgan sana',

            'resolved' => 'Aniqlangan sana',

            'phone' => 'Telefon',

            'checks' => 'Tekshiruvlar',

            'confirmed' => 'Tasdiqlangan',

            'not_confirmed' => 'Tasdiqlanmagan',

            'ascending' => 'O‘sish tartibida',

            'descending' => 'Kamayish tartibida',

            'apply' => 'Qidirish',

            'has_username' => 'Username mavjud',

            'has_driver' => 'Haydovchiga bog‘langan',

            'any' => 'Muhim emas',

            'yes' => 'Ha',

            'no' => 'Yo‘q',

            'stale' => 'Eskirgan (> 7 kun)',

            'period' => 'Davr',

            'period_today' => 'Bugun',

            'period_week' => '7 kun',

            'period_month' => '30 kun',

            'period_custom' => 'Boshqa davr',

            'period_all' => 'Barcha vaqt',

            'period_from' => 'Dan',

            'period_to' => 'Gacha',
        ],

        'stats' => [

            'total' => 'Raqamlar',

            'with_username' => 'Username bilan',

            'with_driver' => 'Haydovchiga bog‘langan',

            'checks' => 'Tekshiruvlar',
        ],

        'table' => [

            'phone' => 'Telefon',

            'telegram' => 'Telegram',

            'driver' => 'Haydovchi',

            'operator' => 'Operator',

            'account' => 'Aniqlovchi akkaunt',

            'resolved_at' => 'Aniqlangan',

            'checks' => 'Tekshiruvlar',

            'result' => 'Natija',

            'id' => 'ID',

            'no_username' => 'Username mavjud emas',

            'no_driver' => 'Haydovchi yo‘q',
        ],

        'export' => [

            'button' => 'Excelga eksport',

            'hint' => 'Eksport joriy filtr va davrni hisobga oladi.',
        ],

        'empty' => [

            'title' => 'Raqamlar topilmadi',

            'description' => 'Filtr yoki qidiruv so‘rovini o‘zgartirib ko‘ring.',
        ],

        'pagination' => [

            'showing' => 'Ko‘rsatilmoqda',

            'page' => 'Sahifa',

            'of' => 'dan',

            'previous' => 'Oldingi',

            'next' => 'Keyingi',
        ],

        'errors' => [

            'load_failed' => 'Raqamlarni yuklashda xatolik yuz berdi',

            'unknown' => 'Noma’lum xatolik yuz berdi',
        ],

        'dates' => [

            'never' => 'Hali yo‘q',
        ],
    ],
];