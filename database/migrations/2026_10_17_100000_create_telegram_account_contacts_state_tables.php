<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * What the accounts really hold, next to what the database says.
 *
 * telegram_account_contact_links - "this account has this person in its
 * Telegram contacts", as last read from the account (or as the sync saw it
 * added). Many accounts may hold the same person; telegram_account_contacts
 * stays the one-owner table the sync claims people with.
 *
 * telegram_account_contact_states - per account: how many contacts it had
 * when it was last read, when, and whether a read or a clean-up is queued,
 * running or failed. Kept apart from the links so "the database has 0, the
 * account has 800" can be said after the database was cleaned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_account_contact_links', function (Blueprint $table) {
            $table->id();

            $table->foreignId('telegram_account_id')
                ->constrained('telegram_accounts')
                ->cascadeOnDelete();

            $table->foreignId('scraped_user_id')
                ->constrained('telegram_scraped_users')
                ->cascadeOnDelete();

            /* One id per read: what a later read does not carry again is gone from the account. */
            $table->string('sync_token', 36)->nullable();

            $table->timestamp('synced_at')->nullable();

            $table->unique(['telegram_account_id', 'scraped_user_id'], 'tg_acc_contact_link_unique');
            $table->index('scraped_user_id');
        });

        Schema::create('telegram_account_contact_states', function (Blueprint $table) {
            $table->id();

            $table->foreignId('telegram_account_id')
                ->unique()
                ->constrained('telegram_accounts')
                ->cascadeOnDelete();

            /* idle | queued | running | failed */
            $table->string('status', 12)->default('idle');

            /* import | clear_account: what is queued or running */
            $table->string('action', 16)->nullable();

            /* How many contacts the account had when it was last read or cleaned. */
            $table->unsignedInteger('in_account')->default(0);

            $table->timestamp('synced_at')->nullable();
            $table->timestamp('cleared_account_at')->nullable();
            $table->timestamp('cleared_db_at')->nullable();

            $table->text('last_error')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_account_contact_states');
        Schema::dropIfExists('telegram_account_contact_links');
    }
};
