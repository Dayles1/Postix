<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client checks: CRM penalties for a request that sat in its status too long.
 *
 * A bot posts them into a watched chat ("⚠️ Штраф по запросу #…",
 * "🆘 Повторное отправление штрафа №N …"). Each one is forwarded to the
 * person responsible - an operator or a sales manager - followed by one
 * comment per batch whose tone grows with the repeat number and with how
 * often that person has been fined lately.
 *
 * Kept apart from telegram_driver_checks: the operator stats, monitoring and
 * export count every row there as a driver check.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_client_checks', function (Blueprint $table) {
            $table->id();

            $table->bigInteger('telegram_chat_id');
            $table->bigInteger('telegram_message_id');

            $table->text('message_text')->nullable();
            $table->json('telegram_raw')->nullable();

            /*
             * Everything the parser read; the columns below are the parts
             * that are queried.
             */
            $table->json('parsed')->nullable();

            $table->string('request_number', 32)->nullable();
            $table->unsignedSmallInteger('repeat_number')->default(1);
            $table->string('crm_status')->nullable();
            $table->timestamp('status_since')->nullable();

            /*
             * As written in "Ответственный (Operation|Sales): ФИО".
             */
            $table->string('responsible_role', 16)->nullable();
            $table->string('responsible_name')->nullable();

            $table->foreignId('operation_user_id')
                ->nullable()
                ->constrained('operation_users')
                ->nullOnDelete();

            /*
             * The escalation worked out for this penalty. The comment itself
             * is stored on the batch's last penalty only, the one it was
             * sent after.
             */
            $table->unsignedTinyInteger('level')->default(0);
            $table->json('metrics')->nullable();
            $table->unsignedTinyInteger('comment_level')->nullable();
            $table->unsignedSmallInteger('phrase_index')->nullable();
            $table->text('comment')->nullable();
            $table->unsignedSmallInteger('batch_count')->nullable();

            $table->string('status', 16)->default('pending');
            $table->string('reason', 64)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('error')->nullable();

            /*
             * Two steps: forwarded as soon as it arrives, commented once the
             * batch is over. A retry resumes where it stopped.
             */
            $table->string('peer')->nullable();
            $table->timestamp('forwarded_at')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            $table->unique(
                ['telegram_chat_id', 'telegram_message_id'],
                'telegram_client_checks_message_unique',
            );

            $table->index(
                ['operation_user_id', 'created_at'],
                'telegram_client_checks_operator_created_index',
            );

            $table->index('request_number', 'telegram_client_checks_request_index');

            $table->index('status', 'telegram_client_checks_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_client_checks');
    }
};
