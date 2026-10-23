<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Join group: the marked accounts find the groups of our topic by
 * themselves, join them safely and read who is in them.
 *
 * One set of groups and one set of people for the whole system, the contact
 * sync's: a group is a row of telegram_scrape_groups, a person a row of
 * telegram_scraped_users, and telegram_scrape_group_user says who is in
 * which group. What tells the two uses apart is the group's type:
 *   contacts - the contact sync reads it and saves its people as contacts;
 *   database - only who is in it is kept (what Join group finds).
 * A group Join group looks after has a join_status; the contact sync's own
 * groups have none.
 *
 * Join group's own tables: the words it searches by, the search queries
 * made of them, which account is in which group, and its log.
 */
return new class extends Migration
{
    /** What the accounts look for out of the box; the panel adds, the workers learn more. */
    private const KEYWORDS = [
        /* With the cities: the search gives a few dozen groups a query, "yuk Toshkent" finds others than "yuk". */
        ['yuk', true],
        ['gruz', true],
        ['юк', true],
        ['груз', true],
        ['логистика', true],
        ['logistika', true],
        ['fura', true],
        ['фура', true],
        /* On their own. */
        ['yuk bor', false],
        ['yuk tashish', false],
        ['yuklar', false],
        ['юк бор', false],
        ['юклар', false],
        ['грузы', false],
        ['грузоперевозки', false],
        ['перевозка грузов', false],
        ['gruzoperevozki', false],
        ['perevozki', false],
        ['дальнобойщики', false],
        ['dalnoboy', false],
        ['isuzu yuk', false],
        ['tent fura', false],
        ['refrijerator', false],
        ['рефрижератор', false],
    ];

    public function up(): void
    {
        Schema::create('telegram_join_keywords', function (Blueprint $table) {
            $table->id();

            $table->string('keyword', 100)->unique();

            /* Free text, only to sort the groups by: "Логистика", "Клиенты". */
            $table->string('topic', 60)->nullable();

            /* seed (came with the system) | manual (the panel) | learned (from the titles of good groups) */
            $table->string('source', 16)->default('manual');

            /* Searched with every city of config('group_join.places') too. */
            $table->boolean('expand')->default(false);

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });

        Schema::create('telegram_join_searches', function (Blueprint $table) {
            $table->id();

            $table->foreignId('keyword_id')
                ->constrained('telegram_join_keywords')
                ->cascadeOnDelete();

            /* What Telegram's global search is asked. */
            $table->string('query', 150)->unique();

            $table->timestamp('last_searched_at')->nullable();
            $table->unsignedInteger('found_count')->default(0);

            /* Taken by one account's worker until then. */
            $table->timestamp('locked_until')->nullable();

            $table->timestamps();

            $table->index(['last_searched_at']);
        });

        Schema::table('telegram_scrape_groups', function (Blueprint $table) {
            /* contacts (the contact sync's, every existing row) | database (only who is in it) */
            $table->string('type', 16)->default('contacts')->index();

            /* manual | search | link (a t.me link or @name in another group) | forward */
            $table->string('source', 16)->default('manual');

            /* The query, or the group the link was found in. */
            $table->string('found_via', 191)->nullable();

            $table->foreignId('keyword_id')
                ->nullable()
                ->constrained('telegram_join_keywords')
                ->nullOnDelete();

            /* null: not Join group's. new | approved | rejected | joined | dead */
            $table->string('join_status', 16)->nullable();

            /* What the last check read from its last 100 messages. */
            $table->timestamp('last_message_at')->nullable();
            $table->unsignedInteger('messages_day')->default(0);
            $table->unsignedInteger('messages_week')->default(0);
            $table->unsignedInteger('senders_week')->default(0);
            $table->decimal('per_day', 8, 1)->default(0);

            /* Percent of the letters that are Cyrillic or Latin; null: too little text to tell. */
            $table->unsignedTinyInteger('script_share')->nullable();

            /* Messages that speak of our topic (yuk, груз, fura ...). */
            $table->unsignedInteger('topic_hits')->default(0);

            /* The higher, the sooner it is joined. */
            $table->unsignedInteger('score')->default(0);

            $table->string('reject_reason', 32)->nullable();

            /* auto: the checks decide and may change their mind; manual: a person did. */
            $table->string('decided_by', 8)->default('auto');

            $table->timestamp('checked_at')->nullable();

            /* How many people were read from it the last time (last_synced_at is when). */
            $table->unsignedInteger('members_read')->default(0);

            /* Taken by one account's worker (a check, a members read) until then. */
            $table->timestamp('locked_until')->nullable();

            $table->index(['join_status', 'score']);
        });

        /* "Who is this @name / this phone" over a million rows. */
        Schema::table('telegram_scraped_users', function (Blueprint $table) {
            $table->index('username');
            $table->index('phone');
        });

        Schema::create('telegram_join_memberships', function (Blueprint $table) {
            $table->id();

            $table->foreignId('group_id')
                ->constrained('telegram_scrape_groups')
                ->cascadeOnDelete();

            $table->foreignId('telegram_account_id')
                ->constrained('telegram_accounts')
                ->cascadeOnDelete();

            /* joined | requested (the admins approve) | failed */
            $table->string('status', 16);

            $table->string('error', 255)->nullable();
            $table->timestamp('joined_at')->nullable();

            $table->timestamps();

            $table->unique(['group_id', 'telegram_account_id']);

            /* Named by hand: the generated name is longer than MySQL's 64 characters. */
            $table->index(['telegram_account_id', 'status', 'joined_at'], 'tg_join_memberships_account_status_joined');
        });

        Schema::create('telegram_join_logs', function (Blueprint $table) {
            $table->id();

            /* One id per worker run. */
            $table->string('run_id', 36)->index();

            $table->foreignId('telegram_account_id')
                ->nullable()
                ->constrained('telegram_accounts')
                ->nullOnDelete();

            $table->foreignId('group_id')
                ->nullable()
                ->constrained('telegram_scrape_groups')
                ->nullOnDelete();

            /* started | searched | found | checked | joined | requested | members | learned | failed | flood | disabled | full | dead | finished */
            $table->string('event', 24);

            $table->string('message', 500)->nullable();
            $table->json('context')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['created_at']);
            $table->index(['telegram_account_id', 'created_at']);
        });

        $now = now();

        DB::table('telegram_join_keywords')->insert(array_map(fn (array $row): array => [
            'keyword' => $row[0],
            'topic' => 'Логистика',
            'source' => 'seed',
            'expand' => $row[1],
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], self::KEYWORDS));
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_join_logs');
        Schema::dropIfExists('telegram_join_memberships');

        Schema::table('telegram_scraped_users', function (Blueprint $table) {
            $table->dropIndex(['username']);
            $table->dropIndex(['phone']);
        });

        Schema::table('telegram_scrape_groups', function (Blueprint $table) {
            $table->dropForeign(['keyword_id']);
            $table->dropIndex(['join_status', 'score']);
            $table->dropIndex(['type']);
            $table->dropColumn([
                'type', 'source', 'found_via', 'keyword_id', 'join_status', 'last_message_at', 'messages_day',
                'messages_week', 'senders_week', 'per_day', 'script_share', 'topic_hits', 'score', 'reject_reason',
                'decided_by', 'checked_at', 'members_read', 'locked_until',
            ]);
        });

        Schema::dropIfExists('telegram_join_searches');
        Schema::dropIfExists('telegram_join_keywords');
    }
};
