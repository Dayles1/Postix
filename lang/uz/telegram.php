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

            'all' => 'Barchasi',

            'last_week' => 'O‘tgan hafta',

            'last_month' => 'O‘tgan oy',

            'from' => 'Dan',

            'to' => 'Gacha',

            'clear' => 'Tozalash',
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
];