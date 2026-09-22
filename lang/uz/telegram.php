<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Umumiy panel elementlari
    |--------------------------------------------------------------------------
    |
    | Driver-check komponentlari ishlatadigan matnlar: filtrlar, saralash,
    | sahifalash, bo'sh va xato holatlari. Bitta nusxa - hamma sahifada bir xil.
    |
    */

    'ui' => [

        'filters' => 'Filtrlar',
        'filters_show' => 'Ko\'proq filtr',
        'filters_hide' => 'Filtrlarni yashirish',
        'filters_active' => 'Faol filtrlar',
        'reset' => 'Tozalash',
        'apply' => 'Qo\'llash',
        'search' => 'Qidirish',
        'search_clear' => 'Qidiruvni tozalash',
        'sort' => 'Saralash',
        'sort_asc' => 'O\'sish bo\'yicha',
        'sort_desc' => 'Kamayish bo\'yicha',
        'order' => 'Tartib',
        'per_page' => 'Sahifada',
        'showing' => 'Ko\'rsatilmoqda',
        'of' => 'dan',
        'page' => 'Sahifa',
        'previous' => 'Oldingi',
        'next' => 'Keyingi',
        'loading' => 'Yuklanmoqda...',
        'refresh' => 'Yangilash',
        'retry' => 'Qayta urinish',
        'copy' => 'Nusxalash',
        'copied' => 'Nusxalandi',
        'open' => 'Ochish',
        'close' => 'Yopish',
        'cancel' => 'Bekor qilish',
        'save' => 'Saqlash',
        'saving' => 'Saqlanmoqda...',
        'created' => 'Yaratilgan sana',
        'period' => 'Davr',
        'period_custom' => 'Boshqa oraliq',
        'details' => 'Batafsil',
        'error' => 'Xatolik',
        'never' => 'Hech qachon',
        'show_more' => 'Ko\'proq',
        'show_less' => 'Yig\'ish',
        'results' => 'ta natija',
    ],

    'operators' => [

        'title' => 'Operatorlarni boshqarish',

        'description' => 'Operatorlarni Telegram bilan qo\'lda bog\'lash: hisobotlar ularning shaxsiy chatiga ham yuboriladi',

        'refresh' => 'Yangilash',

        'loading' => 'Yuklanmoqda...',

        'create' => 'Operator qo\'shish',

        'search_placeholder' => 'Ism, @username yoki Telegram ID',

        'per_page' => 'Sahifada',

        'stats' => [
            'total' => 'Jami operatorlar',
            'linked' => 'Telegram bog\'langan',
            'dm_enabled' => 'Lichkaga oladi',
            'failing' => 'Yuborishda xato',
        ],

        'filters' => [
            'title' => 'Filtrlar',
            'reset' => 'Tozalash',
            'dm' => 'Shaxsiy xabarlar',
            'dm_all' => 'Hammasi',
            'dm_on' => 'Yoqilgan',
            'dm_off' => 'O\'chirilgan',
            'linked' => 'Telegram',
            'linked_all' => 'Hammasi',
            'linked_yes' => 'Bog\'langan',
            'linked_no' => 'Bog\'lanmagan',
        ],

        'table' => [
            'operator' => 'Operator',
            'telegram' => 'Telegram',
            'dm' => 'Lichkaga',
            'drivers' => 'Haydovchilar',
            'checks' => 'Tekshiruvlar',
            'last_sent' => 'Oxirgi yuborilgan',
            'actions' => 'Amallar',
            'no_username' => 'Username yo\'q',
            'no_id' => 'ID yo\'q',
            'never' => 'Yuborilmagan',
            'dm_on' => 'Yoqilgan',
            'dm_off' => 'O\'chirilgan',
            'dm_unreachable' => 'Kontakt yo\'q',
            'edit' => 'Tahrirlash',
        ],

        'form' => [
            'create_title' => 'Yangi operator',
            'edit_title' => 'Operatorni tahrirlash',
            'name' => 'Operator ismi',
            'name_hint' => 'Guruh xabaridagi «Пользователь:» qatori bilan bir xil bo\'lishi kerak',
            'name_normalized' => 'Moslashtirish kaliti',
            'telegram_username' => 'Telegram username',
            'telegram_username_hint' => '«@» belgisisiz. Birinchi navbatda ishlatiladi — akkaunt operatorni hali tanimasa ham ishlaydi',
            'telegram_id' => 'Telegram ID',
            'telegram_id_hint' => 'Zaxira variant: akkaunt bu foydalanuvchini allaqachon ko\'rgan bo\'lsagina ishlaydi',
            'dm_enabled' => 'Hisobotlarni lichkaga yuborish',
            'dm_enabled_hint' => 'Guruhga ketgan hisobot nusxasi operatorga shaxsiy xabar bilan yuboriladi',
            'save' => 'Saqlash',
            'cancel' => 'Bekor qilish',
            'saving' => 'Saqlanmoqda...',
        ],

        'validation' => [
            'duplicate_name' => 'Bunday ismli operator allaqachon mavjud',
            'username_format' => 'Username lotin harflari, raqamlar va «_» dan iborat, 5–32 belgi bo\'lishi kerak',
        ],

        'messages' => [
            'created' => 'Operator qo\'shildi',
            'updated' => 'O\'zgarishlar saqlandi',
        ],

        'deleted' => 'Operator o\'chirildi',

        'errors' => [
            'title' => 'Xato',
            'load' => 'Operatorlarni yuklab bo\'lmadi',
            'save' => 'Operatorni saqlab bo\'lmadi',
            'has_history' => 'Bu operatorda haydovchilar yoki tekshiruvlar bor — o\'chirib bo\'lmaydi.',
            'dm_last_error' => 'Oxirgi yuborishdagi xato',
        ],

        'empty' => [
            'title' => 'Operatorlar yo\'q',
            'description' => 'Operatorlar guruh xabarlaridan avtomatik yaratiladi. Qo\'lda ham qo\'shish mumkin.',
        ],

        'pagination' => [
            'showing' => 'Ko\'rsatilmoqda',
            'to' => '—',
            'of' => 'dan',
            'previous' => 'Oldingi',
            'next' => 'Keyingi',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Kuzatiladigan chatlar
    |--------------------------------------------------------------------------
    |
    | Listener tinglaydigan guruhlar. Ro'yxatda bitta chat, bir nechtasi yoki
    | umuman bo'lmasligi mumkin; ro'yxatni panel emas, listener o'qiydi.
    |
    */

    'chats' => [

        'title' => 'Kuzatiladigan chatlar',

        'description' => 'Haydovchi tekshiruvi tinglaydigan guruhlar. Bitta, bir nechta yoki umuman yo\'q.',

        'notice' => 'Havolani listener o\'zi aniqlaydi, shuning uchun yangi chat bir daqiqagacha vaqt oladi.',

        'create' => 'Chat qo\'shish',

        'search_placeholder' => 'Havola, nom yoki chat ID',

        'stats' => [
            'total' => 'Chatlar',
            'active' => 'Yoqilgan',
            'watching' => 'Kuzatilmoqda',
            'failing' => 'Xatolar',
        ],

        'filters' => [
            'status' => 'Holat',
            'status_all' => 'Barchasi',
            'status_active' => 'Yoqilgan',
            'status_inactive' => 'To\'xtatilgan',
            'resolved' => 'Aniqlash',
            'resolved_all' => 'Barchasi',
            'resolved_yes' => 'Aniqlangan',
            'resolved_no' => 'Kutilmoqda',
        ],

        'table' => [
            'chat' => 'Chat',
            'peer' => 'Chat ID',
            'status' => 'Holat',
            'checks' => 'Tekshiruvlar',
            'last_message' => 'Oxirgi xabar',
            'actions' => 'Amallar',
            'no_link' => 'ID orqali qo\'shilgan',
            'no_id' => 'Hali aniqlanmagan',
            'never' => 'Hali yo\'q',
            'edit' => 'Tahrirlash',
            'source_env' => '.env dan',
        ],

        'status' => [
            'watching' => 'Kuzatilmoqda',
            'pending' => 'Aniqlanmoqda',
            'failed' => 'Xato',
            'paused' => 'To\'xtatilgan',
        ],

        'form' => [
            'create_title' => 'Yangi chat',
            'edit_title' => 'Chatni tahrirlash',
            'chat' => 'Havola yoki chat ID',
            'chat_hint' => 'Taklif havolasi (t.me/+...), @username yoki raqamli ID (-100...)',
            'title' => 'Nomi',
            'title_hint' => 'Faqat panel uchun. Bo\'sh qoldirilsa, listener Telegramdan oladi',
            'is_active' => 'Bu chatni kuzatish',
            'is_active_hint' => 'O\'chirilsa, listener guruhni e\'tiborsiz qoldiradi, lekin unutmaydi',
            'save' => 'Saqlash',
            'cancel' => 'Bekor qilish',
            'saving' => 'Saqlanmoqda...',
            'delete' => 'O\'chirish',
        ],

        'validation' => [
            'format' => 't.me havolasi, @username yoki raqamli chat ID kiriting',
            'duplicate' => 'Bu chat ro\'yxatda allaqachon bor',
        ],

        'confirm' => [
            'delete_title' => 'Bu chat o\'chirilsinmi?',
            'delete_text' => 'Listener uni tinglashni to\'xtatadi. Yaratilgan tekshiruvlar saqlanib qoladi.',
            'delete' => 'O\'chirish',
            'cancel' => 'Bekor qilish',
        ],

        'messages' => [
            'created' => 'Chat qo\'shildi',
            'updated' => 'O\'zgarishlar saqlandi',
            'deleted' => 'Chat o\'chirildi',
        ],

        'deleted' => 'Chat o\'chirildi',

        'errors' => [
            'title' => 'Xato',
            'load' => 'Chatlarni yuklab bo\'lmadi',
            'load_failed' => 'Chatlarni yuklab bo\'lmadi',
            'save' => 'Chatni saqlab bo\'lmadi',
            'delete' => 'Chatni o\'chirib bo\'lmadi',
            'resolve' => 'Aniqlash xatosi',
        ],

        'empty' => [
            'title' => 'Hozircha chat yo\'q',
            'description' => 'Xabarlari tekshiriladigan guruhni qo\'shing. Usiz listener ishlaydi, lekin jim turadi.',
        ],
    ],

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
