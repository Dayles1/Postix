<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Shared panel chrome
    |--------------------------------------------------------------------------
    |
    | Labels used by the driver-check components: filters, sorting, paging,
    | empty and error states. One copy, so every page says the same thing.
    |
    */

    'ui' => [

        'filters' => 'Filters',
        'filters_show' => 'More filters',
        'filters_hide' => 'Hide filters',
        'filters_active' => 'Active filters',
        'reset' => 'Reset',
        'apply' => 'Apply',
        'search' => 'Search',
        'search_clear' => 'Clear search',
        'sort' => 'Sort',
        'sort_asc' => 'Ascending',
        'sort_desc' => 'Descending',
        'order' => 'Order',
        'per_page' => 'Per page',
        'showing' => 'Showing',
        'of' => 'of',
        'page' => 'Page',
        'previous' => 'Previous',
        'next' => 'Next',
        'loading' => 'Loading...',
        'refresh' => 'Refresh',
        'retry' => 'Try again',
        'copy' => 'Copy',
        'copied' => 'Copied',
        'open' => 'Open',
        'close' => 'Close',
        'cancel' => 'Cancel',
        'save' => 'Save',
        'saving' => 'Saving...',
        'created' => 'Created',
        'period' => 'Period',
        'period_custom' => 'Custom range',
        'details' => 'Details',
        'error' => 'Error',
        'never' => 'Never',
        'show_more' => 'Show more',
        'show_less' => 'Show less',
        'results' => 'results',
    ],

    'operators' => [

        'title' => 'Operator management',

        'description' => 'Link operators to Telegram by hand: reports are copied into their private chat',

        'refresh' => 'Refresh',

        'loading' => 'Loading...',

        'create' => 'Add operator',

        'search_placeholder' => 'Name, @username or Telegram ID',

        'per_page' => 'Per page',

        'stats' => [
            'total' => 'Operators',
            'active' => 'Active',
            'linked' => 'With Telegram',
            'dm_enabled' => 'Receive direct messages',
            'failing' => 'Delivery failing',
        ],

        'filters' => [
            'title' => 'Filters',
            'reset' => 'Reset',
            'status' => 'Status',
            'status_all' => 'All',
            'status_active' => 'Active',
            'status_inactive' => 'Inactive',
            'dm' => 'Direct messages',
            'dm_all' => 'All',
            'dm_on' => 'Enabled',
            'dm_off' => 'Disabled',
            'linked' => 'Telegram',
            'linked_all' => 'All',
            'linked_yes' => 'Linked',
            'linked_no' => 'Not linked',
        ],

        'table' => [
            'operator' => 'Operator',
            'telegram' => 'Telegram',
            'status' => 'Status',
            'dm' => 'Direct',
            'drivers' => 'Drivers',
            'checks' => 'Checks',
            'last_sent' => 'Last delivery',
            'actions' => 'Actions',
            'no_username' => 'No username',
            'no_id' => 'No ID',
            'never' => 'Never sent',
            'active' => 'Active',
            'inactive' => 'Inactive',
            'dm_on' => 'Enabled',
            'dm_off' => 'Disabled',
            'dm_unreachable' => 'No contact',
            'edit' => 'Edit',
            'delete' => 'Delete',
        ],

        'form' => [
            'create_title' => 'New operator',
            'edit_title' => 'Edit operator',
            'name' => 'Operator name',
            'name_hint' => 'Must match the "Пользователь:" line of the group message',
            'name_normalized' => 'Matching key',
            'telegram_username' => 'Telegram username',
            'telegram_username_hint' => 'Without the "@". Tried first - it works even if the account has never met the operator',
            'telegram_id' => 'Telegram ID',
            'telegram_id_hint' => 'Fallback: only resolves once the account has seen this user',
            'is_active' => 'Active',
            'is_active_hint' => 'An inactive operator receives nothing',
            'dm_enabled' => 'Send reports to the private chat',
            'dm_enabled_hint' => 'A copy of the group report is sent to the operator directly',
            'save' => 'Save',
            'cancel' => 'Cancel',
            'saving' => 'Saving...',
        ],

        'validation' => [
            'duplicate_name' => 'An operator with this name already exists',
            'username_format' => 'A username may contain latin letters, digits and "_", 5 to 32 characters',
        ],

        'confirm' => [
            'delete_title' => 'Delete this operator?',
            'delete_text' => 'This cannot be undone.',
            'delete' => 'Delete',
            'cancel' => 'Cancel',
        ],

        'messages' => [
            'created' => 'Operator added',
            'updated' => 'Changes saved',
            'deleted' => 'Operator deleted',
        ],

        'deleted' => 'Operator deleted',

        'errors' => [
            'title' => 'Error',
            'load' => 'Could not load the operators',
            'save' => 'Could not save the operator',
            'delete' => 'Could not delete the operator',
            'has_history' => 'This operator has drivers or checks and cannot be deleted. Deactivate it instead.',
            'dm_last_error' => 'Last delivery error',
        ],

        'empty' => [
            'title' => 'No operators yet',
            'description' => 'Operators are created automatically from group messages. You can also add one by hand.',
        ],

        'pagination' => [
            'showing' => 'Showing',
            'to' => '—',
            'of' => 'of',
            'previous' => 'Previous',
            'next' => 'Next',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Watched chats
    |--------------------------------------------------------------------------
    |
    | The groups the listener follows. The list may hold one chat, several or
    | none at all; it is read by the running listener, not by the panel.
    |
    */

    'chats' => [

        'title' => 'Watched chats',

        'description' => 'The groups the driver-check listener follows. None, one or several.',

        'notice' => 'Only the listener can resolve a link, so a new chat needs up to a minute before it is watched.',

        'create' => 'Add chat',

        'search_placeholder' => 'Link, name or chat ID',

        'stats' => [
            'total' => 'Chats',
            'active' => 'Enabled',
            'watching' => 'Watching',
            'failing' => 'Errors',
        ],

        'filters' => [
            'status' => 'Status',
            'status_all' => 'All',
            'status_active' => 'Enabled',
            'status_inactive' => 'Paused',
            'resolved' => 'Resolution',
            'resolved_all' => 'All',
            'resolved_yes' => 'Resolved',
            'resolved_no' => 'Pending',
        ],

        'table' => [
            'chat' => 'Chat',
            'peer' => 'Chat ID',
            'status' => 'Status',
            'checks' => 'Checks',
            'last_message' => 'Last message',
            'actions' => 'Actions',
            'no_link' => 'Added as an ID',
            'no_id' => 'Not resolved yet',
            'never' => 'Nothing yet',
            'edit' => 'Edit',
            'source_env' => 'From .env',
        ],

        'status' => [
            'watching' => 'Watching',
            'pending' => 'Resolving',
            'failed' => 'Error',
            'paused' => 'Paused',
        ],

        'form' => [
            'create_title' => 'New chat',
            'edit_title' => 'Edit chat',
            'chat' => 'Link or chat ID',
            'chat_hint' => 'An invite link (t.me/+...), a @username, or a numeric id (-100...)',
            'title' => 'Name',
            'title_hint' => 'Panel only. Left empty, the listener fills it in from Telegram',
            'is_active' => 'Watch this chat',
            'is_active_hint' => 'Turned off, the listener ignores the group without forgetting it',
            'save' => 'Save',
            'cancel' => 'Cancel',
            'saving' => 'Saving...',
            'delete' => 'Remove',
        ],

        'validation' => [
            'format' => 'Enter a t.me link, a @username or a numeric chat id',
            'duplicate' => 'This chat is already on the list',
        ],

        'confirm' => [
            'delete_title' => 'Remove this chat?',
            'delete_text' => 'The listener stops watching it. The checks it already produced are kept.',
            'delete' => 'Remove',
            'cancel' => 'Cancel',
        ],

        'messages' => [
            'created' => 'Chat added',
            'updated' => 'Changes saved',
            'deleted' => 'Chat removed',
        ],

        'deleted' => 'Chat removed',

        'errors' => [
            'title' => 'Error',
            'load' => 'Could not load the chats',
            'load_failed' => 'Could not load the chats',
            'save' => 'Could not save the chat',
            'delete' => 'Could not remove the chat',
            'resolve' => 'Resolution error',
        ],

        'empty' => [
            'title' => 'No chats yet',
            'description' => 'Add the group whose driver messages should be checked. Without one the listener runs but stays silent.',
        ],
    ],

    'sessions' => [

        'title' => 'Telegram sessions',

        'description' => 'Every MadelineProto account the listener and the phone lookup run on: log in, check, log out.',

        'notice' => 'MadelineProto only runs from the console, so every action starts a background command. The status updates by itself within a few seconds.',

        'create' => 'Add account',

        'search_placeholder' => 'Phone, name, @username or Telegram ID',

        'primary' => 'Listener',

        'primary_hint' => 'This account listens to the chats (TELEGRAM_DRIVER_CHECK_ACCOUNT_ID)',

        'stats' => [
            'authorized' => 'Active',
            'pending' => 'Login unfinished',
            'problem' => 'Problems',
            'logged_out' => 'Logged out',
        ],

        'filters' => [
            'state' => 'State',
            'state_all' => 'All',
        ],

        'table' => [
            'account' => 'Account',
            'phone' => 'Phone',
            'status' => 'Status',
            'processes' => 'Processes',
            'last_checked' => 'Checked',
            'authorized_at' => 'Logged in',
            'actions' => 'Actions',
        ],

        'fields' => [
            'telegram_id' => 'Telegram ID',
            'name' => 'Name',
            'username' => 'Username',
            'session_file' => 'Session file',
            'session_file_yes' => 'On disk',
            'session_file_no' => 'Not on disk',
        ],

        'state' => [
            'listening' => 'Listening to chats',
            'stopped' => 'Listener stopped',
            'active' => 'Active',
            'warning' => 'Active, with an error',
            'no_file' => 'Session file missing',
            'sending_code' => 'Sending code',
            'awaiting_code' => 'Waiting for code',
            'verifying' => 'Verifying',
            'awaiting_password' => 'Waiting for 2FA password',
            'checking' => 'Checking',
            'logging_out' => 'Logging out...',
            'stale' => 'Stuck',
            'code_invalid' => 'Wrong code',
            'failed' => 'Login failed',
            'revoked' => 'Session revoked',
            'logged_out' => 'Logged out',
            'new' => 'Not authorized',
        ],

        'state_hint' => [
            'listening' => 'The listener is running and holds this session. It cannot be checked from the panel: its profile is refreshed every time the listener starts.',
            'stopped' => 'The session is authorized, but the listener is not running. Messages from the chats are not processed.',
            'active' => 'The session works and is available to the processes.',
            'warning' => 'The session is authorized, but the last request returned an error. Press "Check" to find out whether it is still alive.',
            'no_file' => 'The account is authorized in the database, but its session file is not on disk: every process will fail on it. Log out and log in again.',
            'sending_code' => 'A background command is asking Telegram for the code.',
            'awaiting_code' => 'Telegram has sent the code. Enter it to continue.',
            'verifying' => 'A background command is checking what you entered.',
            'awaiting_password' => 'Two-step verification is on for this account. Its cloud password is needed.',
            'checking' => 'A background command is asking Telegram whether the session is alive.',
            'logging_out' => 'A background command is ending the session in Telegram and deleting the file.',
            'stale' => 'The background command never answered. It most likely crashed - the action can be repeated.',
            'code_invalid' => 'The code did not match. Telegram does not allow another try with the same login: request a new code.',
            'failed' => 'The login failed. You can start over.',
            'revoked' => 'Telegram no longer accepts this session: it was ended on the phone, or the account is banned. Log in again.',
            'logged_out' => 'The session has ended. The account can be logged in again or deleted.',
            'new' => 'No login has been started yet.',
        ],

        'processes' => [
            'names' => [
                'resolver_phone' => 'Phone lookup',
                'send_message' => 'Mailing',
                'driver_check' => 'Driver check',
            ],
            'states' => [
                'ready' => 'Ready',
                'busy' => 'Busy',
                'stuck' => 'Stuck',
                'failing' => 'Failing',
                'disabled' => 'Disabled',
            ],
            'none' => 'Not used yet',
            'none_hint' => 'No process has taken this account yet. A row appears the first time a process picks it.',
            'successes' => 'Successes',
            'failures' => 'Failures',
            'streak' => 'In a row',
            'disabled_reason' => 'Reason',
            'stuck_hint' => 'Marked busy :time and never released. Disable and enable the process to clear the flag.',
            'enable' => 'Enable',
            'disable' => 'Disable',
            'disabled_manually' => 'Disabled manually in the panel',
        ],

        'actions' => [
            'continue' => 'Continue login',
            'login_again' => 'Log in again',
            'check' => 'Check',
            'logout' => 'Log out',
            'delete' => 'Delete',
        ],

        'login' => [
            'title' => 'New account',
            'steps' => [
                'phone' => 'Phone',
                'code' => 'Code',
                'password' => '2FA password',
            ],
            'phone' => 'Phone number',
            'phone_hint' => 'International format, with the country code',
            'send_code' => 'Get code',
            'sending' => 'Requesting the code...',
            'verifying' => 'Verifying...',
            'waiting_hint' => 'The command runs in the background. You may close this window - the login continues from the list.',
            'code' => 'Code from Telegram',
            'code_hint' => 'Arrives in the Telegram app on this number (or by SMS)',
            'code_warning' => 'Do not forward the code to anyone, not even to Saved Messages: Telegram invalidates it at once.',
            'password' => 'Cloud password',
            'password_hint' => 'The two-step verification password of this account',
            'hint' => 'Hint',
            'verify' => 'Confirm',
            'resend' => 'Send the code again',
            'done' => 'Account authorized',
        ],

        'confirm' => [
            'logout_title' => 'Log out of this account?',
            'logout_text' => 'The session is ended in Telegram and its file deleted. Processes stop using this account.',
            'logout_primary' => 'This is the listener account: once logged out, the driver check stops receiving messages from the chats.',
            'delete_title' => 'Delete this account?',
            'delete_text' => 'The record and its process statistics are deleted. The account holds no live session, so nothing changes in Telegram.',
        ],

        'messages' => [
            'authorized' => 'Account authorized',
            'check_started' => 'Check started',
            'logout_started' => 'Logout started',
            'deleted' => 'Account deleted',
            'process_enabled' => 'Process enabled',
            'process_disabled' => 'Process disabled',
        ],

        'validation' => [
            'phone' => 'Enter the number in international format, e.g. +998901234567',
            'code' => 'The code consists of digits only',
        ],

        'state_errors' => [
            'listener_running' => 'The listener is running on this session right now. Its profile is refreshed when the listener starts.',
            'process_busy' => 'A process is using this account right now. Try again once it is free.',
            'already_authorized' => 'This account is already authorized.',
            'busy' => 'A background command is already working on this account. Please wait a moment.',
            'not_waiting_code' => 'The account is not waiting for a code. Refresh the page.',
            'not_waiting_password' => 'The account is not waiting for a password. Refresh the page.',
            'not_authorized' => 'The account is not authorized.',
            'still_authorized' => 'Log out of the account before deleting it.',
        ],

        'telegram_errors' => [
            'PHONE_CODE_INVALID' => 'Wrong code. Request a new one.',
            'PHONE_CODE_EXPIRED' => 'The code has expired. Request a new one.',
            'PASSWORD_HASH_INVALID' => 'Wrong password. Try again.',
            'PASSWORD_EXPIRED' => 'The password was not picked up in time. Enter it again.',
            'PHONE_NUMBER_INVALID' => 'Telegram does not accept this number.',
            'PHONE_NUMBER_BANNED' => 'This number is banned in Telegram.',
            'PHONE_NUMBER_FLOOD' => 'Too many login attempts for this number. Try later.',
            'FLOOD_WAIT' => 'Telegram asks to wait before the next attempt.',
            'FLOOD_WAIT_SECONDS' => 'Telegram asks to wait :seconds s.',
            'AUTH_KEY_UNREGISTERED' => 'The session was ended in Telegram.',
            'SESSION_REVOKED' => 'The session was ended in Telegram.',
            'USER_DEACTIVATED' => 'The account is deleted or banned.',
            'SESSION_NOT_FOUND' => 'The session file is not on disk.',
            'NOT_LOGGED_IN' => 'The session on disk is not logged in.',
            'ACCOUNT_NOT_REGISTERED' => 'No Telegram account is registered on this number.',
        ],

        'errors' => [
            'title' => 'Error',
            'load' => 'Could not load the sessions',
            'load_failed' => 'Could not load the sessions',
            'action' => 'Could not complete the action',
        ],

        'empty' => [
            'title' => 'No accounts yet',
            'description' => 'Add a Telegram account: the listener and the phone lookup run on it.',
        ],
    ],

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
