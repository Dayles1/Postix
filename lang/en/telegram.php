<?php

declare(strict_types=1);

return [

    'operation_users' => [

        'title' => 'Operators',

        'description' => 'Statistics of Telegram users and driver verification',

        'telegram' => 'Telegram',

        'refresh' => 'Refresh',

        'loading' => 'Loading...',

        'open_user' => 'Open user',

        'per_page' => 'Per page',

        'filters' => [

            'title' => 'Filters',

            'description' => 'Search and sort operators',

            'reset' => 'Clear',

            'search' => 'Search',

            'search_placeholder' => 'Name, username, Telegram ID...',

            'telegram_id' => 'Telegram ID',

            'telegram_id_placeholder' => 'Enter Telegram ID',

            'username' => 'Username',

            'username_placeholder' => '@username',

            'sort' => 'Sort',

            'created' => 'Created date',

            'updated' => 'Updated date',

            'name' => 'Name',

            'drivers' => 'Drivers',

            'checks' => 'Checks',

            'confirmed' => 'Confirmed',

            'not_confirmed' => 'Not confirmed',

            'pending' => 'Pending',

            'match_rate' => 'Match rate',

            'average_score' => 'Average score',

            'last_check' => 'Last check',

            'ascending' => 'Ascending',

            'descending' => 'Descending',

            'apply' => 'Search',
        ],

        'stats' => [

            'users' => 'Operators',

            'drivers' => 'Drivers',

            'checks' => 'Checks',

            'avg_match' => 'Average match',
        ],

        'table' => [

            'user' => 'Operator',

            'telegram' => 'Telegram',

            'drivers' => 'Drivers',

            'checks' => 'Checks',

            'result' => 'Result',

            'match' => 'Match',

            'score' => 'Score',

            'id' => 'ID',

            'best' => 'Best',

            'last_check' => 'Last check',

            'no_username' => 'Username not available',

            'no_telegram_id' => 'Telegram ID not available',
        ],

        'result' => [

            'confirmed' => 'Confirmed',

            'not_confirmed' => 'Not confirmed',

            'pending' => 'Pending',

            'processing' => 'Processing',
        ],

        'empty' => [

            'title' => 'No operators found',

            'description' => 'Try changing the filter or search query.',
        ],

        'pagination' => [

            'showing' => 'Showing',

            'page' => 'Page',

            'of' => 'of',

            'previous' => 'Previous',

            'next' => 'Next',
        ],

        'errors' => [

            'load_failed' => 'Failed to load operators',

            'unknown' => 'An unknown error occurred',
        ],

        'dates' => [

            'never' => 'Never',
        ],
    ],


    /*
    |--------------------------------------------------------------------------
    | Operation User
    |--------------------------------------------------------------------------
    */

    'operation_user' => [

        'title' => 'Operator',

        'back' => 'Operators',

        'refresh' => 'Refresh',

        'loading' => 'Loading...',

        'id' => 'ID',

        'no_username' => 'Username not available',

        'unknown' => 'Unknown',


        /*
        |--------------------------------------------------------------------------
        | Operator stats
        |--------------------------------------------------------------------------
        */

        'stats' => [

            'drivers' => 'Drivers',

            'checks' => 'Checks',

            'confirmed' => 'Confirmed',

            'not_confirmed' => 'Not confirmed',

            'pending' => 'Pending',

            'processing' => 'Processing',

            'match_rate' => 'Match rate',
        ],


        /*
        |--------------------------------------------------------------------------
        | Driver filters
        |--------------------------------------------------------------------------
        */

        'filters' => [

            'title' => 'Filters',

            'all' => 'All',

            'last_week' => 'Last week',

            'last_month' => 'Last month',

            'from' => 'From',

            'to' => 'To',

            'clear' => 'Clear',
        ],


        /*
        |--------------------------------------------------------------------------
        | Drivers
        |--------------------------------------------------------------------------
        */

        'drivers' => [

            'title' => 'Drivers',

            'per_page' => 'Per page',

            'phones' => 'phone',

            'checks' => 'check',

            'show_phones' => 'Show phones',

            'hide_phones' => 'Hide phones',

            'no_drivers' => 'No drivers available',

            'no_drivers_description' => 'No drivers have been assigned to this operator yet.',

            'no_resolved_phones' => 'No linked phone numbers available',

            'no_username' => 'Username not available',

            'unknown_date' => 'Date unknown',


            /*
            |--------------------------------------------------------------------------
            | Driver statuses
            |--------------------------------------------------------------------------
            */

            'confirmed' => 'Confirmed',

            'confirmed_description' => 'Driver information has been confirmed.',

            'not_confirmed' => 'Not confirmed',

            'not_confirmed_description' => 'Driver information has not been confirmed.',

            'pending' => 'Pending',

            'pending_description' => 'Waiting for the driver to be confirmed.',

            'unknown_description' => 'Driver status is unknown.',
        ],


        /*
        |--------------------------------------------------------------------------
        | Pagination
        |--------------------------------------------------------------------------
        */

        'pagination' => [

            'page' => 'Page',

            'of' => 'of',

            'previous' => 'Previous',

            'next' => 'Next',
        ],


        /*
        |--------------------------------------------------------------------------
        | Errors
        |--------------------------------------------------------------------------
        */

        'errors' => [

            'load_operator' => 'Failed to load operator',

            'load_drivers' => 'Failed to load drivers',

            'unknown' => 'An unknown error occurred',
        ],
    ],
];
