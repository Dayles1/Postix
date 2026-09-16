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

            'more_filters' => 'More filters',

            'less_filters' => 'Hide filters',

            'status' => 'Check status',

            'status_all' => 'Any status',

            'confirmed' => 'Confirmed',

            'not_confirmed' => 'Not confirmed',

            'pending' => 'Pending',

            'processing' => 'Processing',

            'period' => 'Period',

            'period_today' => 'Today',

            'period_week' => '7 days',

            'period_month' => '30 days',

            'period_custom' => 'Custom range',

            'period_all' => 'All time',

            'period_from' => 'From',

            'period_to' => 'To',

            'score_range' => 'Match score',

            'score_from' => 'From',

            'score_to' => 'To',

            'has_telegram' => 'Has Telegram',

            'has_driver' => 'Has driver',

            'any' => 'Any',

            'yes' => 'Yes',

            'no' => 'No',
        ],

        'export' => [

            'title' => 'Export to Excel',

            'operators' => 'Operators summary',

            'details' => 'Detailed checks',

            'hint' => 'Export respects the current filters and period.',
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

            'all' => 'All time',

            'last_week' => '7 days',

            'last_month' => '30 days',

            'from' => 'From',

            'to' => 'To',

            'clear' => 'Clear',

            'status' => 'Status',

            'status_all' => 'Any status',

            'confirmed' => 'Confirmed',

            'not_confirmed' => 'Not confirmed',

            'pending' => 'Pending',

            'processing' => 'Processing',
        ],

        'export' => [

            'button' => 'Export to Excel',

            'hint' => 'Export respects the current filters and period.',
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


    /*
    |--------------------------------------------------------------------------
    | Drivers (list page)
    |--------------------------------------------------------------------------
    */

    'drivers' => [

        'title' => 'Drivers',

        'description' => 'All drivers checked via Telegram, across all operators',

        'refresh' => 'Refresh',

        'loading' => 'Loading...',

        'filters' => [

            'title' => 'Filters',

            'description' => 'Search and sort drivers',

            'reset' => 'Clear',

            'search' => 'Search',

            'search_placeholder' => 'Driver name...',

            'sort' => 'Sort',

            'created' => 'Created date',

            'name' => 'Name',

            'checks' => 'Checks',

            'confirmed' => 'Confirmed',

            'not_confirmed' => 'Not confirmed',

            'best_match_score' => 'Best score',

            'last_check' => 'Last check',

            'ascending' => 'Ascending',

            'descending' => 'Descending',

            'apply' => 'Search',

            'status' => 'Driver status',

            'status_all' => 'Any status',

            'confirmed_status' => 'Confirmed',

            'not_confirmed_status' => 'Not confirmed',

            'pending_status' => 'Pending',

            'check_status' => 'Check status',

            'period' => 'Period',

            'period_today' => 'Today',

            'period_week' => '7 days',

            'period_month' => '30 days',

            'period_custom' => 'Custom range',

            'period_all' => 'All time',

            'period_from' => 'From',

            'period_to' => 'To',

            'score_range' => 'Match score',

            'score_from' => 'From',

            'score_to' => 'To',
        ],

        'stats' => [

            'total' => 'Drivers',

            'checks' => 'Checks',

            'confirmed' => 'Confirmed',

            'avg_match' => 'Average match',
        ],

        'table' => [

            'driver' => 'Driver',

            'operator' => 'Operator',

            'status' => 'Status',

            'checks' => 'Checks',

            'result' => 'Result',

            'score' => 'Score',

            'last_check' => 'Last check',

            'phones' => 'Phones',

            'id' => 'ID',

            'no_operator' => 'No operator',
        ],

        'status' => [

            'confirmed' => 'Confirmed',

            'not_confirmed' => 'Not confirmed',

            'pending' => 'Pending',

            'unknown' => 'Unknown',
        ],

        'export' => [

            'button' => 'Export to Excel',

            'hint' => 'Export respects the current filters and period.',
        ],

        'empty' => [

            'title' => 'No drivers found',

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

            'load_failed' => 'Failed to load drivers',

            'unknown' => 'An unknown error occurred',
        ],

        'dates' => [

            'never' => 'Never',
        ],
    ],


    /*
    |--------------------------------------------------------------------------
    | Resolved phones (list page)
    |--------------------------------------------------------------------------
    */

    'resolved_phones' => [

        'title' => 'Telegram numbers',

        'description' => 'All phone numbers resolved via Telegram, with their check results',

        'refresh' => 'Refresh',

        'loading' => 'Loading...',

        'filters' => [

            'title' => 'Filters',

            'description' => 'Search and sort resolved numbers',

            'reset' => 'Clear',

            'search' => 'Search',

            'search_placeholder' => 'Phone, username, name...',

            'sort' => 'Sort',

            'created' => 'Created date',

            'resolved' => 'Resolved date',

            'phone' => 'Phone',

            'checks' => 'Checks',

            'confirmed' => 'Confirmed',

            'not_confirmed' => 'Not confirmed',

            'ascending' => 'Ascending',

            'descending' => 'Descending',

            'apply' => 'Search',

            'has_username' => 'Has username',

            'has_driver' => 'Linked to driver',

            'any' => 'Any',

            'yes' => 'Yes',

            'no' => 'No',

            'stale' => 'Stale (> 7 days)',

            'period' => 'Period',

            'period_today' => 'Today',

            'period_week' => '7 days',

            'period_month' => '30 days',

            'period_custom' => 'Custom range',

            'period_all' => 'All time',

            'period_from' => 'From',

            'period_to' => 'To',
        ],

        'stats' => [

            'total' => 'Numbers',

            'with_username' => 'With username',

            'with_driver' => 'Linked to a driver',

            'checks' => 'Checks',
        ],

        'table' => [

            'phone' => 'Phone',

            'telegram' => 'Telegram',

            'driver' => 'Driver',

            'operator' => 'Operator',

            'account' => 'Resolver account',

            'resolved_at' => 'Resolved',

            'checks' => 'Checks',

            'result' => 'Result',

            'id' => 'ID',

            'no_username' => 'Username not available',

            'no_driver' => 'No driver',
        ],

        'export' => [

            'button' => 'Export to Excel',

            'hint' => 'Export respects the current filters and period.',
        ],

        'empty' => [

            'title' => 'No numbers found',

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

            'load_failed' => 'Failed to load numbers',

            'unknown' => 'An unknown error occurred',
        ],

        'dates' => [

            'never' => 'Never',
        ],
    ],
];
