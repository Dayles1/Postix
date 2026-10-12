<?php

/*
|--------------------------------------------------------------------------
| File routes
|--------------------------------------------------------------------------
|
| The notifications bot sends its reports every morning as files
| ("Zitic — 2026-10-09", "qozoq_2026-10-09_..._tekshirilgan.xlsx"). A file
| from one of these chats is forwarded to the groups its kind is set to
| on the "Fayllar" page (Driver check -> Fayllar). The chat must also be
| on the watched chats list, otherwise the listener never sees it.
|
| Comma separated Telegram ids. The default is @zanjeer_notifications_bot,
| @E_Ombor_Bot and the "Test" group, where files are sent by hand to try
| the routes out.
|
*/

return [

    'source_chat_ids' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('FILE_ROUTES_SOURCE_CHAT_IDS', '7465833893,8325658417,-1003659088185')),
    ))),

];
