<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payments, refunds, notifications, support, audit, settings, website content, bulk imports.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            // paystack | sandbox
            $table->string('provider', 20);
            $table->string('reference', 64)->unique();
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3);
            // pending | successful | failed | cancelled | refunded
            $table->string('status', 20)->default('pending')->index();
            $table->string('provider_transaction_id', 64)->nullable();
            $table->string('channel', 30)->nullable();
            $table->string('payer_email')->nullable();
            $table->text('checkout_url')->nullable();
            $table->string('failure_reason')->nullable();
            // Safe metadata only. Never card numbers, CVVs or secrets.
            $table->json('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_shipment', function (Blueprint $table) {
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->primary(['payment_id', 'shipment_id']);
        });

        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20);
            $table->string('event_key', 128);
            $table->string('event_type', 64)->nullable();
            $table->string('reference', 64)->nullable();
            $table->string('outcome', 30)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'event_key']);
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained();
            $table->decimal('amount', 12, 2);
            $table->string('reason');
            $table->string('provider_reference', 64)->nullable();
            // pending | processed | failed
            $table->string('status', 20)->default('pending');
            $table->foreignId('requested_by')->constrained('users');
            $table->timestamp('processed_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('event', 50);
            // email | sms
            $table->string('channel', 10);
            $table->string('subject')->nullable();
            $table->text('body');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['event', 'channel']);
        });

        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shipment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 50);
            $table->string('channel', 10);
            $table->string('recipient');
            $table->string('subject')->nullable();
            $table->text('body');
            // queued | sent | failed | skipped
            $table->string('status', 20)->default('queued');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->string('provider', 30)->nullable();
            $table->string('provider_message_id', 128)->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamp('sent_at')->nullable();
            // Prevents duplicate sends when webhooks/status events are retried.
            $table->string('dedupe_key', 191)->unique();
            $table->timestamps();
            $table->index(['status', 'next_attempt_at']);
        });

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('contact_name');
            $table->string('contact_email');
            $table->string('contact_phone', 32)->nullable();
            $table->foreignId('shipment_id')->nullable()->constrained()->nullOnDelete();
            // delay | damaged | missing | payment | dispute | claim | general
            $table->string('category', 20);
            $table->string('subject');
            // open | in_progress | awaiting_customer | escalated | resolved | closed
            $table->string('status', 20)->default('open')->index();
            // low | normal | high | urgent
            $table->string('priority', 10)->default('normal');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->boolean('is_internal')->default(false);
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80)->index();
            $table->string('entity_type', 60)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('ip', 45)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->index();
            $table->index(['entity_type', 'entity_id']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('content_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('title');
            $table->longText('body');
            $table->timestamps();
        });

        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('question');
            $table->text('answer');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('bulk_imports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('filename');
            // previewed | confirmed | discarded
            $table->string('status', 20)->default('previewed');
            $table->json('rows');
            $table->unsignedInteger('valid_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['bulk_imports', 'faqs', 'content_blocks', 'settings', 'audit_logs', 'support_messages', 'support_tickets', 'notification_logs', 'notification_templates', 'refunds', 'payment_webhook_events', 'payment_shipment', 'payments'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
