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

        'duration' => [
            'd' => 'kun',
            'h' => 'soat',
            'm' => 'daq',
            's' => 's',
        ],
    ],

    'menu' => [
        'groups' => [
            'check' => 'Haydovchi tekshiruvi',
            'settings' => 'Sozlamalar',
            'monitoring' => 'Monitoring',
        ],
        'items' => [
            'operation_users' => 'Operatorlar statistikasi',
            'drivers' => 'Haydovchilar',
            'resolved_phones' => 'Telegram raqamlari',
            'penalties' => 'CRM jarimalari',
            'operators' => 'Operatorlar',
            'sales' => 'Sales',
            'penalty_settings' => 'Jarima sozlamalari',
            'chats' => 'Chatlar',
            'sessions' => 'Telegram sessiyalari',
            'watchdog' => 'Watchdog',
            'queue' => 'Navbat',
        ],
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
            'role' => 'Rol',
            'role_all' => 'Hammasi',
        ],

        'roles' => [
            'operation' => 'Operation',
            'sales' => 'Sales',
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
            'penalties' => 'Jarimalar',
            'last_penalty' => 'Oxirgi jarima',
            'no_penalties' => 'Jarima bo\'lmagan',
            'open_penalties' => 'Jarimalarni ochish',
            'style' => 'Qanday yozamiz',
            'respectful' => 'Hurmat bilan',
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
            'role' => 'Rol',
            'role_hint' => 'Rol odam qaysi sahifada ko\'rinishini belgilaydi. Ism bo\'yicha moslashtirish umumiy: bitta odam ikkalasida bo\'la olmaydi.',
            'save' => 'Saqlash',
            'cancel' => 'Bekor qilish',
            'saving' => 'Saqlanmoqda...',
            'delete' => 'O\'chirish',
            'delete_hint' => 'Faqat haydovchisi, tekshiruvi va jarimasi yo\'q odamni o\'chirish mumkin.',
            'language' => 'Xabarlar tili',
            'language_auto' => 'Rol bo\'yicha: :language',
            'language_hint' => 'Jarima izohlari qaysi tilda yuboriladi.',
            'respectful' => 'Hurmat bilan',
            'respectful_hint' => 'Yoshi kattalar uchun: jarima izohlari hurmatli variantda yuboriladi.',
        ],

        'validation' => [
            'duplicate_name' => 'Bunday ismli operator allaqachon mavjud',
            'username_format' => 'Username lotin harflari, raqamlar va «_» dan iborat, 5–32 belgi bo\'lishi kerak',
        ],

        'messages' => [
            'created' => 'Operator qo\'shildi',
            'updated' => 'O\'zgarishlar saqlandi',
            'moved' => 'Saqlandi va «:role» ga o\'tkazildi',
            'deleted' => 'O\'chirildi',
        ],

        'deleted' => 'Operator o\'chirildi',

        'errors' => [
            'title' => 'Xato',
            'load' => 'Operatorlarni yuklab bo\'lmadi',
            'save' => 'Operatorni saqlab bo\'lmadi',
            'has_history' => 'Bu odamda haydovchilar, tekshiruvlar yoki jarimalar bor - o\'chirib bo\'lmaydi, aks holda tarix egasiz qoladi. Lichkaga yuborishni o\'chiring.',
            'dm_last_error' => 'Oxirgi yuborishdagi xato',
            'delete' => 'O\'chirib bo\'lmadi',
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

        'tabs' => [
            'operation' => 'Operatorlar',
            'sales' => 'Sales',
        ],

        'pages' => [
            'operation' => [
                'title' => 'Operatorlar',
                'description' => 'Haydovchi tekshiruvi guruhlaridagi operatorlar: hisobotlar ularning lichkasiga ham yuboriladi. «Ответственный (Operation)» bo\'lgan CRM jarimalari ham shu yerga keladi.',
                'create' => 'Operator qo\'shish',
                'edit_title' => 'Operatorni tahrirlash',
                'total' => 'Jami operatorlar',
                'name_hint' => 'Guruh xabaridagi «Пользователь:» qatori bilan bir xil bo\'lishi kerak',
                'dm_enabled_hint' => 'Guruhdagi hisobot nusxasi va CRM jarimalari operatorga shaxsiy xabar bilan yuboriladi',
                'empty_title' => 'Operatorlar yo\'q',
                'empty_description' => 'Operatorlar guruh xabarlaridan avtomatik yaratiladi. Qo\'lda ham qo\'shish mumkin.',
            ],
            'sales' => [
                'title' => 'Sales',
                'description' => 'CRM jarimalaridagi sales-menejerlar («Ответственный (Sales)»): jarima ularning lichkasiga izoh bilan birga yuboriladi.',
                'create' => 'Sales qo\'shish',
                'edit_title' => 'Salesni tahrirlash',
                'total' => 'Jami sales',
                'name_hint' => 'Jarimadagi «Ответственный (Sales):» qatoridagi ism bilan bir xil bo\'lishi kerak',
                'dm_enabled_hint' => 'CRM jarimalari lichkaga izoh bilan birga yuboriladi',
                'empty_title' => 'Hozircha sales yo\'q',
                'empty_description' => 'Sales CRM jarimalaridan avtomatik yaratiladi. Telegramini darrov kiritish uchun qo\'lda ham qo\'shsa bo\'ladi.',
            ],
        ],
        'confirm' => [
            'delete_title' => 'O\'chirilsinmi?',
            'delete_text' => 'Bu amalni qaytarib bo\'lmaydi.',
            'delete' => 'O\'chirish',
            'cancel' => 'Bekor qilish',
        ],
    ],

    'penalties' => [
        'title' => 'CRM jarimalari',
        'description' => 'CRM boti statusda qotib qolgan so\'rovlar uchun yozgan jarimalar: kimga yuborildi, qanday izoh ketdi va yetib bordimi.',
        'search_placeholder' => 'So\'rov raqami, ism yoki @username',
        'settings' => [
            'title' => 'Jarimalarni yuborish',
            'enabled' => 'Jarimalarni yuborish',
            'enabled_hint' => 'Jarima mas\'ulning lichkasiga forward qilinadi. O\'chirilgan bo\'lsa hech narsa ketmaydi: na forward, na izoh. Jarimalar baribir yoziladi va hisoblanadi.',
            'comments' => 'Jarimadan keyingi izoh',
            'comments_hint' => 'Har bir jarimalar to\'plamidan keyin bitta izoh xabari ketadi. O\'chirilgan bo\'lsa faqat forward, qo\'shimcha xabarsiz.',
            'on' => 'Yoq',
            'off' => 'O\'ch',
            'saved' => 'Sozlama saqlandi',
            'failed' => 'Sozlamani saqlab bo\'lmadi',
            'off_banner' => 'Jarimalarni yuborish o\'chirilgan: yangi jarimalar yoziladi, lekin hech kimga ketmaydi. Qayta yoqilganda eskilari yuborilmaydi.',
            'comments_off_banner' => 'Izohlar o\'chirilgan: jarimalar qo\'shimcha xabarsiz forward qilinadi.',
            'operation_enabled' => 'Operatorlarga',
            'operation_enabled_hint' => '«Ответственный (Operation)» bo\'lgan jarimalar',
            'sales_enabled' => 'Sales\'ga',
            'sales_enabled_hint' => '«Ответственный (Sales)» bo\'lgan jarimalar',
            'by_role' => 'Kimga yuboriladi',
        ],
        'off_link' => 'Sozlamalarda yoqish',
        'delivery_on' => 'Yuborish yoqiq',
        'delivery_off' => 'Yuborish o\'chiq',
        'comments_off' => 'izohsiz',
        'level_n' => ':n-daraja',
        'open_settings' => 'Sozlamalar',
        'stats' => [
            'total' => 'Jami jarimalar',
            'sent' => 'Yetkazildi',
            'in_progress' => 'Jarayonda',
            'failed' => 'Xato',
            'skipped' => 'O\'tkazib yuborildi',
            'critical' => 'Eng yuqori daraja',
            'people' => 'Odamlar',
        ],
        'tabs' => [
            'all' => 'Hammasi',
            'operation' => 'Operation',
            'sales' => 'Sales',
        ],
        'filters' => [
            'period' => 'Davr',
            'period_all' => 'Butun davr',
            'period_today' => 'Bugun',
            'period_week' => '7 kun',
            'period_month' => '30 kun',
            'period_custom' => 'O\'z davri',
            'period_from' => 'Dan',
            'period_to' => 'Gacha',
            'role' => 'Rol',
            'status' => 'Status',
            'status_all' => 'Hammasi',
            'level' => 'Daraja',
            'level_all' => 'Hammasi',
            'person' => 'Odam',
            'person_clear' => 'Hammasini ko\'rsatish',
        ],
        'statuses' => [
            'pending' => 'Kutilmoqda',
            'forwarded' => 'Yuborildi, izoh kutilmoqda',
            'sent' => 'Yetkazildi',
            'failed' => 'Xato',
            'skipped' => 'O\'tkazib yuborildi',
        ],
        'reasons' => [
            'outside_hours' => 'Ish vaqtidan tashqari keldi - yuborilmadi',
            'responsible_missing' => 'Jarimada mas\'ul ko\'rsatilmagan',
            'responsible_unreachable' => 'Mas\'ulning Telegrami yo\'q yoki lichkaga yuborish o\'chirilgan',
            'forward_failed' => 'Telegram forwardni qabul qilmadi',
            'comment_failed' => 'Izoh yuborilmadi',
            'disabled' => 'Jarimalarni yuborish o\'chirilgan edi',
            'comment_disabled' => 'Izohsiz yuborildi: izohlar o\'chirilgan',
            'role_disabled' => 'Bu rol uchun yuborish o\'chirilgan',
            'level_off' => 'Bu darajada bu rolga hech narsa yuborilmaydi',
            'forward_only' => 'Faqat forward: bu darajada izoh kerak emas',
        ],
        'levels' => [
            '0' => 'Xotirjam',
            '1' => 'Eslatma',
            '2' => 'Qattiq',
            '3' => 'Juda qattiq',
        ],
        'table' => [
            'request' => 'So\'rov',
            'responsible' => 'Mas\'ul',
            'repeat' => 'Takror',
            'level' => 'Daraja',
            'status' => 'Status',
            'created' => 'Kelgan',
            'actions' => 'Amallar',
            'details' => 'Batafsil',
            'no_responsible' => 'Ko\'rsatilmagan',
            'crm' => 'CRMda ochish',
        ],
        'detail' => [
            'title' => 'So\'rov bo\'yicha jarima',
            'message' => 'Bot xabari',
            'comment' => 'Izoh',
            'comment_on_last' => 'Izoh har bir to\'plamga bitta yuboriladi - u to\'plamning eng kuchli jarimasida saqlanadi.',
            'batch' => 'To\'plamdagi jarimalar',
            'metrics' => 'Odam tarixi',
            'hour_count' => 'Oxirgi soat',
            'today_count' => 'Bugun',
            'week_count' => 'Shu hafta',
            'minutes_since_last' => 'Oldingi to\'plamdan beri, daqiqa',
            'crm_status' => 'CRMdagi status',
            'time_in_status' => 'Statusda turibdi',
            'attempts' => 'Urinishlar',
            'error' => 'Xato',
            'peer' => 'Qayerga yuborildi',
            'forwarded_at' => 'Yuborildi',
            'sent_at' => 'Izoh',
            'retry_hint' => 'Xatolar jarima yangi ekan avtomatik qayta yuboriladi. Odamning Telegrami bo\'lmasa, uni odamlar sahifasida kiriting.',
            'variant' => 'Qaysi matn',
            'variant_value' => ':language, :tone',
        ],
        'empty' => [
            'title' => 'Jarimalar yo\'q',
            'description' => 'CRM boti kuzatiladigan chatga yozganda jarimalar shu yerda paydo bo\'ladi.',
        ],
        'errors' => [
            'title' => 'Xato',
            'load' => 'Jarimalarni yuklab bo\'lmadi',
        ],
    ],


    'penalty_settings' => [
        'title' => 'Jarima sozlamalari',
        'description' => 'Bitta so\'rov bo\'yicha 1-, 2-, 3- va keyingi jarimalarda odamga nima yozish. Raqam bot xabaridan olinadi. Matnlar ikki tilda, oddiy va hurmatli - yoshi kattalar uchun.',
        'back' => 'Jarimalarga qaytish',
        'sections' => [
            'hours' => 'Ish vaqti',
            'delivery' => 'Yuborish',
            'levels' => 'Jarima raqami bo\'yicha matnlar',
            'levels_hint' => '«⚠️ Штраф по запросу» - birinchi marta, «🆘 Повторное отправление штрафа №N» - N-marta. Oxirgi daraja undan keyingilar uchun ham ishlaydi.',
            'timing' => 'Vaqt va qayta urinishlar',
            'placeholders' => 'O\'zgaruvchilar',
            'placeholders_hint' => 'Oxirgi tahrirlangan iboraga qo\'yish uchun bosing.',
            'language' => 'Matnlar tili',
            'how' => 'Matn qanday tanlanadi',
            'how_text' => 'Til va ohang odamning kartasidan olinadi: sales uchun standart rus tili, operatorlar uchun o\'zbek tili. «Hurmat bilan» ham o\'sha yerda yoqiladi. Bo\'sh variant eng yaqini bilan almashtiriladi: boshqa ohang, pastki daraja, oxirgi chora sifatida boshqa til.',
        ],
        'hours' => [
            'hint' => 'Bu vaqtdan tashqari kelgan jarimalar yoziladi, lekin hech kimga yuborilmaydi. Toshkent vaqti.',
            'from' => 'Dan',
            'to' => 'Gacha',
            'always' => 'Kun bo\'yi',
            'clear' => 'Cheklovsiz',
        ],
        'languages' => [
            'uz' => 'O\'zbekcha',
            'ru' => 'Русский',
        ],
        'tones' => [
            'plain' => 'Oddiy',
            'plain_hint' => 'Tengdosh va yoshlarga',
            'respectful' => 'Hurmat bilan',
            'respectful_hint' => 'Yoshi kattalarga',
        ],
        'level' => [
            'title' => ':n-daraja',
            'name' => 'Nomi',
            'name_placeholder' => 'Masalan: Ikkinchi marta',
            'from' => 'Qaysi jarimadan',
            'from_hint' => '«Повторное отправление штрафа №N» dagi raqam',
            'first' => 'Birinchi jarima',
            'from_summary' => '№:n-jarima',
            'from_summary_last' => '№:n-jarima va keyingilar',
            'add_phrase' => 'Ibora',
            'remove_phrase' => 'Iborani o\'chirish',
            'add' => 'Daraja qo\'shish',
            'remove' => 'Darajani o\'chirish',
            'empty' => 'Bo\'sh - :fallback olinadi',
            'fallback_tone' => 'oddiy variant',
            'fallback_lower' => 'pastki daraja matni',
            'phrases_count' => ':n ta ibora',
            'random_hint' => 'Ibora bir nechta bo\'lsa, tasodifiysi tanlanadi, lekin odam oldin olganidan boshqasi.',
        ],
        'timing' => [
            'batch_quiet_seconds' => 'Takrorni kutish, son',
            'batch_quiet_seconds_hint' => 'Shu vaqt ichida bitta odamga kelgan jarimalarga, turli so\'rovlar bo\'lsa ham, bitta izoh ketadi - eng kuchlisiniki (kattaroq raqam).',
            'max_attempts' => 'Yuborish urinishlari',
            'max_attempts_hint' => 'Telegram rad etsa, necha marta urinish.',
            'retry_minutes' => 'Qayta urinish muddati, daq',
            'retry_minutes_hint' => 'Shundan keyin jarima eskirgan hisoblanadi va yuborilmaydi.',
        ],
        'placeholders' => [
            'name' => 'Odamning ismi',
            'request' => 'So\'rov raqami',
            'repeat_number' => 'Jarima raqami',
            'status_limit' => 'Statusga berilgan vaqt (2 ч / 2 soat)',
            'time_in_status' => 'Statusda turgan vaqt',
            'crm_status' => 'CRMdagi status',
        ],
        'actions' => [
            'save' => 'Saqlash',
            'saving' => 'Saqlanmoqda...',
            'discard' => 'O\'zgarishlarni bekor qilish',
            'reset' => 'Standartga qaytarish',
            'reset_confirm' => 'Standart sozlamalarga qaytarish uchun yana bir marta bosing',
            'dirty' => 'Saqlanmagan o\'zgarishlar bor',
            'customised' => 'O\'z sozlamalari',
            'default' => 'Standart sozlamalar',
        ],
        'messages' => [
            'saved' => 'Jarima sozlamalari saqlandi',
            'reset' => 'Standart sozlamalar qaytarildi',
            'failed' => 'Saqlab bo\'lmadi',
            'leave' => 'O\'zgarishlar saqlanmagan. Sahifadan chiqasizmi?',
        ],
        'validation' => [
            'first_level_phrase' => 'Birinchi darajada kamida bitta ibora bo\'lishi kerak',
            'from_order' => ':level-daraja kamida №:min-jarimadan boshlanishi kerak',
            'fix' => 'Belgilangan maydonlarni to\'g\'rilang',
        ],
        'roles' => [
            'operation' => 'Operatorlar',
            'sales' => 'Sales',
        ],
        'roles_hint' => 'Operatorlar va sales uchun darajalar, matnlar va yuborish qoidalari alohida.',
        'modes' => [
            'all' => 'Forward + izoh',
            'forward' => 'Faqat forward',
            'off' => 'Yuborilmaydi',
        ],
        'mode_hint' => 'Shu darajada bu rolga nima ketadi',
        'copy_from' => '«:role» dan nusxa olish',
        'copy_confirm' => 'Yana bosing: «:role» darajalari bularning o\'rniga qo\'yiladi',
        'copied' => '«:role» dan nusxa olindi. Saqlashni unutmang.',
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

    'sessions' => [

        'title' => 'Telegram sessiyalari',

        'description' => 'Listener va raqam bo\'yicha qidiruv ishlaydigan barcha MadelineProto akkauntlari: kirish, tekshirish, chiqish.',

        'notice' => 'MadelineProto faqat konsoldan ishlaydi, shuning uchun har bir amal fon buyrug\'ini ishga tushiradi. Holat bir necha soniyada o\'zi yangilanadi.',

        'create' => 'Akkaunt qo\'shish',

        'search_placeholder' => 'Telefon, ism, @username yoki Telegram ID',

        'primary' => 'Listener',

        'primary_hint' => 'Bu akkaunt chatlarni tinglaydi (TELEGRAM_DRIVER_CHECK_ACCOUNT_ID)',

        'stats' => [
            'authorized' => 'Faol',
            'pending' => 'Kirish tugallanmagan',
            'problem' => 'Muammolar',
            'logged_out' => 'Chiqqan',
        ],

        'filters' => [
            'state' => 'Holat',
            'state_all' => 'Hammasi',
        ],

        'table' => [
            'account' => 'Akkaunt',
            'phone' => 'Telefon',
            'status' => 'Holat',
            'processes' => 'Jarayonlar',
            'last_checked' => 'Tekshirilgan',
            'authorized_at' => 'Kirgan sana',
            'actions' => 'Amallar',
        ],

        'fields' => [
            'telegram_id' => 'Telegram ID',
            'name' => 'Ism',
            'username' => 'Username',
            'session_file' => 'Sessiya fayli',
            'session_file_yes' => 'Diskda bor',
            'session_file_no' => 'Diskda yo\'q',
        ],

        'state' => [
            'listening' => 'Chatlarni tinglayapti',
            'stopped' => 'Listener to\'xtagan',
            'active' => 'Faol',
            'warning' => 'Faol, xato bor',
            'no_file' => 'Sessiya fayli yo\'q',
            'sending_code' => 'Kod yuborilmoqda',
            'awaiting_code' => 'Kod kutilmoqda',
            'verifying' => 'Tekshirilmoqda',
            'awaiting_password' => '2FA parol kutilmoqda',
            'checking' => 'Tekshirilmoqda',
            'logging_out' => 'Chiqilmoqda...',
            'stale' => 'Qotib qoldi',
            'code_invalid' => 'Kod noto\'g\'ri',
            'failed' => 'Kirish xatosi',
            'revoked' => 'Sessiya bekor qilingan',
            'logged_out' => 'Chiqqan',
            'new' => 'Avtorizatsiya qilinmagan',
        ],

        'state_hint' => [
            'listening' => 'Listener ishlayapti va shu sessiyani ushlab turibdi. Uni paneldan tekshirib bo\'lmaydi: profil listener har safar ishga tushganda yangilanadi.',
            'stopped' => 'Sessiya avtorizatsiyadan o\'tgan, lekin listener ishlamayapti. Chatlardagi xabarlar qayta ishlanmaydi.',
            'active' => 'Sessiya ishlayapti va jarayonlar uchun ochiq.',
            'warning' => 'Sessiya avtorizatsiyadan o\'tgan, lekin oxirgi so\'rov xato qaytardi. Tirikligini bilish uchun "Tekshirish"ni bosing.',
            'no_file' => 'Bazada akkaunt avtorizatsiyadan o\'tgan, lekin diskda sessiya fayli yo\'q: har qanday jarayon unda yiqiladi. Chiqib, qayta kiring.',
            'sending_code' => 'Fon buyrug\'i Telegramdan kod so\'ramoqda.',
            'awaiting_code' => 'Telegram kodni yubordi. Davom etish uchun uni kiriting.',
            'verifying' => 'Fon buyrug\'i kiritilgan ma\'lumotni tekshirmoqda.',
            'awaiting_password' => 'Akkauntda ikki bosqichli tekshiruv yoqilgan. Bulutli parol kerak.',
            'checking' => 'Fon buyrug\'i sessiya tirikligini Telegramdan so\'ramoqda.',
            'logging_out' => 'Fon buyrug\'i Telegramdagi sessiyani yakunlab, faylni o\'chirmoqda.',
            'stale' => 'Fon buyrug\'i javob bermadi. Ehtimol u yiqilgan - amalni qaytarish mumkin.',
            'code_invalid' => 'Kod mos kelmadi. Telegram shu kod bilan qayta urinishga ruxsat bermaydi: yangi kod so\'rang.',
            'failed' => 'Kirish muvaffaqiyatsiz. Qaytadan boshlash mumkin.',
            'revoked' => 'Telegram bu sessiyani endi qabul qilmaydi: u telefonda yakunlangan yoki akkaunt bloklangan. Qayta kiring.',
            'logged_out' => 'Sessiya yakunlangan. Akkauntga qayta kirish yoki uni o\'chirish mumkin.',
            'new' => 'Kirish hali boshlanmagan.',
        ],

        'processes' => [
            'names' => [
                'resolver_phone' => 'Raqam bo\'yicha qidiruv',
                'send_message' => 'Xabar yuborish',
                'driver_check' => 'Haydovchilarni tekshirish',
            ],
            'states' => [
                'ready' => 'Tayyor',
                'busy' => 'Band',
                'stuck' => 'Qotib qoldi',
                'failing' => 'Xatolar bor',
                'disabled' => 'O\'chirilgan',
            ],
            'none' => 'Ishlatilmagan',
            'none_hint' => 'Hali hech bir jarayon bu akkauntni olmagan. Jarayon uni birinchi marta tanlaganda qator paydo bo\'ladi.',
            'successes' => 'Muvaffaqiyatli',
            'failures' => 'Xatolar',
            'streak' => 'Ketma-ket',
            'disabled_reason' => 'Sabab',
            'stuck_hint' => ':time band deb belgilangan va bo\'shamagan. Belgini olib tashlash uchun jarayonni o\'chirib, qayta yoqing.',
            'enable' => 'Yoqish',
            'disable' => 'O\'chirish',
            'disabled_manually' => 'Panelda qo\'lda o\'chirilgan',
        ],

        'actions' => [
            'continue' => 'Kirishni davom ettirish',
            'login_again' => 'Qayta kirish',
            'check' => 'Tekshirish',
            'logout' => 'Chiqish',
            'delete' => 'O\'chirish',
        ],

        'login' => [
            'title' => 'Yangi akkaunt',
            'steps' => [
                'phone' => 'Telefon',
                'code' => 'Kod',
                'password' => '2FA parol',
            ],
            'phone' => 'Telefon raqami',
            'phone_hint' => 'Xalqaro formatda, mamlakat kodi bilan',
            'send_code' => 'Kod olish',
            'sending' => 'Kod so\'ralmoqda...',
            'verifying' => 'Tekshirilmoqda...',
            'waiting_hint' => 'Buyruq fonda ishlayapti. Oynani yopish mumkin - kirish ro\'yxatdan davom etadi.',
            'code' => 'Telegramdagi kod',
            'code_hint' => 'Shu raqamdagi Telegram ilovasiga (yoki SMS orqali) keladi',
            'code_warning' => 'Kodni hech kimga, hatto "Saqlanganlar"ga ham yubormang: Telegram uni darhol bekor qiladi.',
            'password' => 'Bulutli parol',
            'password_hint' => 'Shu akkauntning ikki bosqichli tekshiruv paroli',
            'hint' => 'Eslatma',
            'verify' => 'Tasdiqlash',
            'resend' => 'Kodni qayta yuborish',
            'done' => 'Akkaunt avtorizatsiyadan o\'tdi',
        ],

        'confirm' => [
            'logout_title' => 'Akkauntdan chiqilsinmi?',
            'logout_text' => 'Telegramdagi sessiya yakunlanadi, sessiya fayli o\'chiriladi. Jarayonlar bu akkauntdan foydalanmay qo\'yadi.',
            'logout_primary' => 'Bu listener akkaunti: chiqqandan keyin haydovchilarni tekshirish chatlardan xabar olmay qo\'yadi.',
            'delete_title' => 'Akkaunt o\'chirilsinmi?',
            'delete_text' => 'Yozuv va jarayonlar statistikasi o\'chiriladi. Akkauntda tirik sessiya yo\'q, Telegramda hech narsa o\'zgarmaydi.',
        ],

        'messages' => [
            'authorized' => 'Akkaunt avtorizatsiyadan o\'tdi',
            'check_started' => 'Tekshiruv boshlandi',
            'logout_started' => 'Chiqish boshlandi',
            'deleted' => 'Akkaunt o\'chirildi',
            'process_enabled' => 'Jarayon yoqildi',
            'process_disabled' => 'Jarayon o\'chirildi',
        ],

        'validation' => [
            'phone' => 'Raqamni xalqaro formatda kiriting, masalan +998901234567',
            'code' => 'Kod faqat raqamlardan iborat',
        ],

        'state_errors' => [
            'listener_running' => 'Hozir bu sessiyada listener ishlayapti. Uning profili listener ishga tushganda yangilanadi.',
            'process_busy' => 'Akkaunt hozir jarayon bilan band. U bo\'shaganda qayta urinib ko\'ring.',
            'already_authorized' => 'Bu akkaunt allaqachon avtorizatsiyadan o\'tgan.',
            'busy' => 'Akkaunt bilan fon buyrug\'i ishlayapti. Biroz kuting.',
            'not_waiting_code' => 'Akkaunt hozir kod kutmayapti. Sahifani yangilang.',
            'not_waiting_password' => 'Akkaunt hozir parol kutmayapti. Sahifani yangilang.',
            'not_authorized' => 'Akkaunt avtorizatsiyadan o\'tmagan.',
            'still_authorized' => 'Avval akkauntdan chiqing, keyin o\'chiring.',
        ],

        'telegram_errors' => [
            'PHONE_CODE_INVALID' => 'Kod noto\'g\'ri. Yangisini so\'rang.',
            'PHONE_CODE_EXPIRED' => 'Kod eskirgan. Yangisini so\'rang.',
            'PASSWORD_HASH_INVALID' => 'Parol noto\'g\'ri. Yana urinib ko\'ring.',
            'PASSWORD_EXPIRED' => 'Parol fon buyrug\'iga yetib bormadi. Uni qayta kiriting.',
            'PHONE_NUMBER_INVALID' => 'Telegram bu raqamni qabul qilmaydi.',
            'PHONE_NUMBER_BANNED' => 'Raqam Telegramda bloklangan.',
            'PHONE_NUMBER_FLOOD' => 'Bu raqam bilan juda ko\'p urinish bo\'ldi. Keyinroq urinib ko\'ring.',
            'FLOOD_WAIT' => 'Telegram keyingi urinishdan oldin kutishni so\'ramoqda.',
            'FLOOD_WAIT_SECONDS' => 'Telegram :seconds soniya kutishni so\'ramoqda.',
            'AUTH_KEY_UNREGISTERED' => 'Sessiya Telegramda yakunlangan.',
            'SESSION_REVOKED' => 'Sessiya Telegramda yakunlangan.',
            'USER_DEACTIVATED' => 'Akkaunt o\'chirilgan yoki bloklangan.',
            'SESSION_NOT_FOUND' => 'Sessiya fayli diskda topilmadi.',
            'NOT_LOGGED_IN' => 'Diskdagi sessiya avtorizatsiyadan o\'tmagan.',
            'ACCOUNT_NOT_REGISTERED' => 'Bu raqamda Telegram ro\'yxatdan o\'tmagan.',
        ],

        'errors' => [
            'title' => 'Xato',
            'load' => 'Sessiyalarni yuklab bo\'lmadi',
            'load_failed' => 'Sessiyalarni yuklab bo\'lmadi',
            'action' => 'Amalni bajarib bo\'lmadi',
        ],

        'empty' => [
            'title' => 'Hozircha akkauntlar yo\'q',
            'description' => 'Telegram akkaunt qo\'shing: listener va raqam bo\'yicha qidiruv shunda ishlaydi.',
        ],
    ],

    'watchdog' => [

        'title' => 'Watchdog',

        'description' => 'Haydovchilarni tekshirish listeneri watchdog nazoratida: ishlayaptimi, qaysi akkauntda va oxirgi marta nima qildi.',

        'actions' => [
            'start' => 'Ishga tushirish',
            'restart' => 'Listenerni qayta ishga tushirish',
            'restart_short' => 'Qayta yoqish',
            'stop' => 'To\'xtatish',
        ],

        'state' => [
            'ok' => 'Ishlayapti',
            'unsupervised' => 'Listener nazoratsiz',
            'restarting' => 'Listener qayta ishga tushmoqda',
            'down' => 'To\'xtagan',
            'misconfigured' => 'Asosiy akkaunt tayyor emas',
            'unknown' => 'Holat noma\'lum',
        ],

        'state_hint' => [
            'ok' => 'Watchdog listenerni nazorat qilyapti, asosiy akkaunt avtorizatsiyadan o\'tgan.',
            'unsupervised' => 'Listener ishlayapti, lekin watchdog ishga tushmagan: listener yiqilsa, uni hech kim ko\'tarmaydi. "Ishga tushirish"ni bosing.',
            'restarting' => 'Watchdog ishlayapti, lekin listener hozir ishlamayapti: u hozirgina yiqilgan yoki keyingi urinishdan oldin pauzani kutyapti.',
            'down' => 'Na watchdog, na listener ishlayapti. Chatlardagi xabarlar qayta ishlanmaydi.',
            'misconfigured' => 'TELEGRAM_DRIVER_CHECK_ACCOUNT_ID berilmagan, akkaunt topilmadi yoki avtorizatsiyadan o\'tmagan. Uni "Telegram sessiyalari" sahifasida tekshiring.',
            'unknown' => 'Veb-server storage/app/telegram dagi lock-fayllarni o\'qiy olmayapti.',
        ],

        'processes' => [
            'title' => 'Jarayonlar',
            'watchdog' => 'Watchdog',
            'watchdog_hint' => 'Listenerni ishga tushiradi va yiqilgandan keyin ko\'taradi',
            'spare' => 'Zaxira watchdog',
            'spare_hint' => 'Kutib turadi va asosiysi yiqilsa, nazoratni o\'z qo\'liga oladi',
            'listener' => 'Listener',
            'listener_hint' => 'telegram:start-loop: asosiy akkaunt nomidan chatlarni tinglaydi',
            'running' => 'Ishlayapti',
            'stopped' => 'Ishlamayapti',
            'unknown' => 'Noma\'lum',
            'pid' => 'PID',
        ],

        'account' => [
            'title' => 'Asosiy akkaunt',
            'missing' => 'Akkaunt berilmagan',
            'missing_hint' => '.env da TELEGRAM_DRIVER_CHECK_ACCOUNT_ID ni ko\'rsating.',
            'not_found' => 'TELEGRAM_DRIVER_CHECK_ACCOUNT_ID dagi akkaunt topilmadi',
            'authorized' => 'Avtorizatsiyadan o\'tgan',
            'not_authorized' => 'Avtorizatsiyadan o\'tmagan',
            'session_missing' => 'Sessiya fayli diskda yo\'q',
            'status' => 'Holat',
            'open_sessions' => 'Sessiyalar',
        ],

        'activity' => [
            'title' => 'Faollik',
            'last_check' => 'Oxirgi xabar',
            'last_report' => 'Oxirgi hisobot',
            'checks_today' => 'Bugungi tekshiruvlar',
            'pending' => 'Jarayonda',
        ],

        'worker' => [
            'title' => 'Navbat workeri',
            'alive' => 'Javob beryapti',
            'dead' => 'Javob bermayapti',
            'dead_hint' => '"Ishga tushirish" navbatga vazifa qo\'yadi. Worker javob bermaguncha, uni hech kim bajarmaydi.',
            'start_queued' => 'Navbatda ishga tushirish kutyapti: :count',
            'open_queue' => 'Navbat',
        ],

        'restart' => [
            'title' => 'Oxirgi yiqilish',
            'reason' => 'Sabab',
            'exit_code' => 'Chiqish kodi',
            'uptime' => 'Ishladi',
            'restarts' => 'Qayta ishga tushirish №',
            'at' => 'Qachon',
            'detail' => 'Tafsilotlar',
            'marker' => 'Listener muammo haqida xabar berdi',
        ],

        'signals_unavailable' => 'Qayta ishga tushirish va to\'xtatish faqat watchdog ishlaydigan Linux serverda ishlaydi.',

        'auto_refresh' => 'Har 5 soniyada yangilanadi',

        'confirm' => [
            'restart_title' => 'Listener qayta ishga tushirilsinmi?',
            'restart_text' => 'Listener SIGTERM oladi va to\'g\'ri yakunlanadi, watchdog bir necha soniyada yangisini ishga tushiradi. Bu vaqtda xabarlar qayta ishlanmaydi.',
            'stop_title' => 'Watchdog va listener to\'xtatilsinmi?',
            'stop_text' => 'Watchdog listenerni to\'xtatadi va o\'zi ham yakunlanadi. Qayta ishga tushirilmaguncha haydovchilarni tekshirish ishlamaydi.',
            'confirm' => 'Ha, davom etish',
        ],

        'messages' => [
            'restart_queued' => 'Qayta ishga tushirish navbat workeriga topshirildi, listener bir necha soniyada qayta ishga tushadi',
            'stop_queued' => 'To\'xtatish navbat workeriga topshirildi, watchdog bir necha soniyada to\'xtaydi',
            'start_queued' => 'Watchdogni ishga tushirish navbatga qo\'yildi',
            'restart_sent' => 'Listener qayta ishga tushmoqda',
            'stop_sent' => 'Watchdog to\'xtamoqda',
        ],

        'errors' => [
            'title' => 'Xato',
            'load_failed' => 'Holatni olib bo\'lmadi',
            'action' => 'Amalni bajarib bo\'lmadi',
            'no_watchdog' => 'Watchdog ishlamayapti: to\'xtatilgan listenerni hech kim ko\'tarmaydi. Avval watchdogni ishga tushiring.',
            'not_running' => 'Jarayon ishlamayapti.',
            'pid_unknown' => 'Jarayon PID sini tasdiqlab bo\'lmadi. Signal yuborilmadi.',
            'signal_failed' => 'Signal yuborilmadi: uni yuborayotgan jarayonda watchdogga huquq yo\'q. Ehtimol, watchdog boshqa foydalanuvchi nomidan qo\'lda ishga tushirilgan.',
        ],
    ],

    'queue' => [

        'title' => 'Navbat',

        'description' => 'Panelning fon vazifalari: bot, resolver, akkauntlarga kirish, xabar yuborish. Hammasi telegram navbati orqali o\'tishi kerak.',

        'worker' => [
            'alive' => 'Worker ishlayapti',
            'dead' => 'Worker javob bermayapti',
            'never' => 'Worker hali bir marta ham javob bermagan',
            'dead_hint' => 'Vazifalar yig\'ilib qolyapti va bajarilmayapti. Serverda tekshiring: php artisan queue:work --queue=telegram',
            'never_hint' => 'Worker yangi kod bilan qayta ishga tushganda puls paydo bo\'ladi (php artisan queue:restart).',
            'pulse' => 'Oxirgi puls',
            'listens' => 'Tinglaydi',
            'last_processed' => 'Oxirgi bajarilgan',
            'last_failed' => 'Oxirgi yiqilgan',
        ],

        'stats' => [
            'ready' => 'Tayyor',
            'delayed' => 'Kechiktirilgan',
            'reserved' => 'Bajarilmoqda',
            'failed' => 'Yiqilgan',
            'failed_day' => 'bir kunda: :count',
        ],

        'queues' => [
            'title' => 'Navbatlar',
            'queue' => 'Navbat',
            'stuck' => 'Qotib qolgan',
            'oldest' => 'Eng uzoq kutgan',
            'main' => 'Asosiy',
            'not_listened' => 'Hech kim tinglamaydi',
            'not_listened_hint' => 'Bu navbatdagi vazifalarni hech kim bajarmaydi. Ularni telegram ga o\'tkazing.',
            'move' => 'telegram ga o\'tkazish',
        ],

        'classes' => [
            'title' => 'Bajarilishini kutayotganlar',
            'job' => 'Vazifa',
            'total' => 'Jami',
            'empty' => 'Navbat bo\'sh',
        ],

        'tabs' => [
            'pending' => 'Kutayotganlar',
            'failed' => 'Yiqilganlar',
        ],

        'filters' => [
            'search' => 'Vazifa nomi',
            'search_failed' => 'Vazifa yoki xato matni',
            'queue' => 'Navbat',
            'queue_all' => 'Barcha navbatlar',
            'state' => 'Holat',
            'state_all' => 'Hammasi',
        ],

        'state' => [
            'ready' => 'Tayyor',
            'delayed' => 'Kechiktirilgan',
            'reserved' => 'Bajarilmoqda',
            'stuck' => 'Qotib qolgan',
        ],

        'table' => [
            'job' => 'Vazifa',
            'queue' => 'Navbat',
            'state' => 'Holat',
            'attempts' => 'Urinishlar',
            'created' => 'Qo\'yilgan',
            'available' => 'Ishga tushadi',
            'error' => 'Xato',
            'failed_at' => 'Yiqilgan',
            'actions' => 'Amallar',
        ],

        'actions' => [
            'retry' => 'Qayta urinish',
            'retry_all' => 'Hammasini qayta',
            'delete' => 'O\'chirish',
            'flush' => 'Hammasini o\'chirish',
            'details' => 'Batafsil',
        ],

        'detail' => [
            'title' => 'Yiqilgan vazifa',
            'exception' => 'Xato',
            'uuid' => 'UUID',
        ],

        'confirm' => [
            'move_title' => 'Vazifalar telegram ga o\'tkazilsinmi?',
            'move_text' => '":queue" navbatidagi barcha kutayotgan vazifalar telegram ga o\'tadi va worker tomonidan bajariladi.',
            'retry_all_title' => 'Barcha yiqilgan vazifalar qayta ishga tushirilsinmi?',
            'retry_all_text' => 'Barcha yiqilgan vazifalar telegram navbatiga qaytadi. Ma\'lumotdagi xato sabab yiqilgan vazifa, ehtimol, yana yiqiladi.',
            'delete_title' => 'Yiqilgan vazifa o\'chirilsinmi?',
            'delete_text' => 'Vazifa qayta ishga tushirilmasdan o\'chiriladi.',
            'flush_title' => 'Barcha yiqilgan vazifalar o\'chirilsinmi?',
            'flush_text' => 'Barcha yiqilgan vazifalar qayta ishga tushirilmasdan o\'chiriladi. Buni bekor qilib bo\'lmaydi.',
            'confirm' => 'Ha, davom etish',
        ],

        'messages' => [
            'retried' => 'Navbatga qaytarildi: :count',
            'deleted' => 'O\'chirildi: :count',
            'moved' => 'telegram ga o\'tkazildi: :count',
        ],

        'errors' => [
            'title' => 'Xato',
            'load_failed' => 'Navbatni yuklab bo\'lmadi',
            'action' => 'Amalni bajarib bo\'lmadi',
            'retry' => 'Vazifani tiklab bo\'lmadi: :error',
        ],

        'empty' => [
            'pending_title' => 'Kutayotgan vazifalar yo\'q',
            'pending_description' => 'Navbatga qo\'yilgan hamma narsa bajarib bo\'lingan.',
            'failed_title' => 'Yiqilgan vazifalar yo\'q',
            'failed_description' => 'Barcha vazifalar xatosiz bajarildi.',
        ],

        'auto_refresh' => 'Umumiy ma\'lumot har 10 soniyada yangilanadi',
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
            'phone' => 'Telefon',

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
