<?php

/*
|--------------------------------------------------------------------------
| Join group
|--------------------------------------------------------------------------
*/

return [

    /*
     * The cities a keyword marked "with cities" is searched together with:
     * Telegram's global search returns a few dozen groups a query, and
     * "yuk Samarqand" finds other groups than "yuk". Written the ways people
     * name their groups - Uzbek Latin, Uzbek Cyrillic, Russian.
     */
    'places' => [
        'Toshkent', 'Samarqand', 'Buxoro', 'Andijon', "Farg'ona", 'Fargona', 'Namangan', 'Qarshi', 'Termiz',
        'Urganch', 'Xorazm', 'Navoiy', 'Jizzax', 'Guliston', 'Nukus', "Qo'qon", 'Denov', 'Shahrisabz',
        'Тошкент', 'Самарканд', 'Бухоро', 'Андижон', 'Фаргона', 'Наманган', 'Карши', 'Термиз', 'Хоразм',
        'Ташкент', 'Узбекистан', 'Москва', 'Россия', 'Санкт-Петербург', 'Казань', 'Екатеринбург',
        'Новосибирск', 'Краснодар', 'Казахстан', 'Алматы', 'Rossiya', "Qozog'iston", 'Uzbekistan',
    ],

    /*
     * What our topic is made of: the beginnings of the words a logistics
     * chat is full of (a word counts when it starts with one). A group is
     * on topic when its title has one, or enough of its messages do. The
     * words of the panel's keywords count too, so a keyword added there
     * widens the topic by itself.
     */
    'topic_roots' => [
        'yuk', 'юк', 'gruz', 'груз', 'logist', 'логист', 'fura', 'фура', 'фуры', 'perevoz', 'перевоз',
        'dostav', 'достав', 'tashish', 'ташиш', 'tonna', 'тонн', 'isuzu', 'исузу', 'kamaz', 'камаз',
        'tent', 'тент', 'refrij', 'рефриж', 'pricep', 'прицеп', 'dalnoboy', 'дальнобо', 'cargo', 'truck',
        'mashina', 'машина', 'moshina', 'мошина', 'shafyor', 'шофер', 'haydovchi', 'хайдовчи', 'zaprav',
        'yo\'nalish', 'йуналиш', 'reys', 'рейс', 'kuzov', 'кузов', 'labo', 'лабо', 'damas', 'дамас',
        'porter', 'портер', 'gazel', 'газел', 'sprinter', 'volvo', 'вольво',
    ],

];
