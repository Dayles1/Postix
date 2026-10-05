<?php

/*
|--------------------------------------------------------------------------
| Client checks (CRM penalties)
|--------------------------------------------------------------------------
|
| A bot posts a penalty when a request sits in its status too long. The
| listener forwards it to the person responsible and, once the batch is
| over, sends that person one comment from the account it runs on.
|
| The level is the higher of two:
|  - by the bot's own repeat number ("Повторное отправление штрафа №N");
|  - by the person's history: penalties in the last hour, today, this week,
|    and how soon after the previous batch this one came.
|
*/

return [

    /*
     * The bot posts penalties in bursts. A person's batch is considered over
     * when no new penalty for them has arrived for this many seconds; only
     * then is the comment sent, once for the whole batch.
     */
    'batch_quiet_seconds' => 20,

    /*
     * Lowest repeat number for each level ("№1" is the first penalty).
     */
    'repeat_levels' => [
        3 => 7,
        2 => 4,
        1 => 2,
    ],

    /*
     * How far back the history is read, days ("week" below).
     */
    'history_days' => 7,

    /*
     * History conditions per level, any one is enough. Checked from the
     * highest level down; no match is level 0.
     *  - hour:          penalties in the last 60 minutes
     *  - today:         penalties since midnight (app timezone)
     *  - week:          penalties within history_days
     *  - repeat_within: minutes since the previous batch, at most
     */
    'levels' => [
        3 => ['today' => 8, 'hour' => 5],
        2 => ['today' => 5, 'repeat_within' => 30],
        1 => ['today' => 3, 'week' => 8],
    ],

    /*
     * Failed steps are retried this many times in total, and only while the
     * penalty is younger than retry_minutes.
     */
    'max_attempts' => 3,

    'retry_minutes' => 30,

    /*
     * Added under the phrase when the batch holds more than one penalty.
     */
    'batch_line' => 'Штрафов сейчас: <b>{batch_count}</b>',

    /*
     * One phrase per batch, never the one this person got last time on the
     * same level. Telegram HTML is allowed.
     *
     * Placeholders: {name}, {request}, {repeat_number}, {batch_count},
     * {hour_count}, {today_count}, {week_count}.
     */
    'phrases' => [
        
    ],

];
