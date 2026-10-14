<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Group members saved into the accounts' contacts, once a day.
 *
 * Three separate things on purpose: the groups to read, the people found in
 * them (one row per Telegram user, however many groups they sit in) and the
 * pivot that says which group they were seen in. Which account saved whom is
 * its own table, with the user as its unique key: that is what makes a person
 * land in exactly one account's contacts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_scrape_groups', function (Blueprint $table) {
            $table->id();

            $table->string('title')->nullable();

            /* t.me/name, @name or an invite link, as the accounts open it. */
            $table->string('link', 255)->unique();

            $table->bigInteger('telegram_chat_id')->nullable();

            $table->boolean('is_active')->default(true);

            $table->unsignedInteger('members_count')->default(0);

            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();

            $table->timestamps();
        });

        Schema::create('telegram_scraped_users', function (Blueprint $table) {
            $table->id();

            $table->bigInteger('telegram_user_id')->unique();

            $table->string('username')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();

            /* Only when Telegram shows it; most of the time it does not. */
            $table->string('phone', 32)->nullable();

            $table->boolean('is_bot')->default(false);

            $table->timestamps();
        });

        Schema::create('telegram_scrape_group_user', function (Blueprint $table) {
            $table->id();

            $table->foreignId('group_id')
                ->constrained('telegram_scrape_groups')
                ->cascadeOnDelete();

            $table->foreignId('scraped_user_id')
                ->constrained('telegram_scraped_users')
                ->cascadeOnDelete();

            $table->timestamp('seen_at')->nullable();

            $table->unique(['group_id', 'scraped_user_id']);
        });

        Schema::create('telegram_account_contacts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('telegram_account_id')
                ->constrained('telegram_accounts')
                ->cascadeOnDelete();

            /* Unique: one person is saved by one account, once. */
            $table->foreignId('scraped_user_id')
                ->unique()
                ->constrained('telegram_scraped_users')
                ->cascadeOnDelete();

            /* pending (claimed) | added | failed | skipped */
            $table->string('status', 16)->default('pending');

            $table->text('error')->nullable();
            $table->timestamp('added_at')->nullable();

            $table->timestamps();

            $table->index(['telegram_account_id', 'status']);
        });

        Schema::create('telegram_contact_sync_logs', function (Blueprint $table) {
            $table->id();

            /* One id per run, so a day can be read as a whole. */
            $table->string('run_id', 36)->index();

            $table->foreignId('telegram_account_id')
                ->nullable()
                ->constrained('telegram_accounts')
                ->nullOnDelete();

            $table->foreignId('group_id')
                ->nullable()
                ->constrained('telegram_scrape_groups')
                ->nullOnDelete();

            /* started | members | added | skipped | failed | flood | finished */
            $table->string('event', 24);

            $table->string('message', 500)->nullable();
            $table->json('context')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['created_at']);
            $table->index(['telegram_account_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_contact_sync_logs');
        Schema::dropIfExists('telegram_account_contacts');
        Schema::dropIfExists('telegram_scrape_group_user');
        Schema::dropIfExists('telegram_scraped_users');
        Schema::dropIfExists('telegram_scrape_groups');
    }
};
