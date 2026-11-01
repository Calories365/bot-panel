<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A question of the 1C helper bot is gathered in batches: the question
     * itself, then a batch for each follow-up. A closed batch becomes a job for
     * the worker on the 1C server. The bot remembers the messages it sent about
     * a question, so that a reply to any of them finds the question.
     */
    public function up(): void
    {
        Schema::create('onec_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('chat_id');
            $table->string('title')->nullable();
            $table->text('summary')->nullable();
            $table->json('entities')->nullable();
            $table->string('session_id')->nullable();
            $table->timestamp('files_deleted_at')->nullable();
            $table->timestamps();

            $table->index(['bot_id', 'chat_id']);
        });

        Schema::create('onec_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('onec_questions')->cascadeOnDelete();
            $table->string('kind', 16);
            $table->string('status', 16);
            $table->bigInteger('service_message_id')->nullable();
            $table->timestamp('service_edited_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('onec_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('onec_batches')->cascadeOnDelete();
            $table->bigInteger('telegram_message_id');
            $table->string('type', 16);
            $table->text('text')->nullable();
            $table->string('author')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->boolean('forwarded')->default(false);
            $table->string('media_group_id')->nullable();
            $table->boolean('is_screen')->default(false);
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_mime')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamp('file_deleted_at')->nullable();
            $table->timestamps();

            $table->unique(['batch_id', 'telegram_message_id']);
        });

        Schema::create('onec_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('onec_questions')->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained('onec_batches')->cascadeOnDelete();
            $table->string('kind', 16);
            $table->string('status', 16);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('heartbeat_at')->nullable();
            $table->timestamp('note_shown_at')->nullable();
            $table->timestamp('queued_alert_sent_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('session_id')->nullable();
            $table->longText('for_user')->nullable();
            $table->longText('for_boss')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('onec_bot_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('onec_questions')->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('onec_batches')->cascadeOnDelete();
            $table->bigInteger('chat_id');
            $table->bigInteger('message_id');
            $table->timestamps();

            $table->index(['chat_id', 'message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onec_bot_messages');
        Schema::dropIfExists('onec_jobs');
        Schema::dropIfExists('onec_messages');
        Schema::dropIfExists('onec_batches');
        Schema::dropIfExists('onec_questions');
    }
};
