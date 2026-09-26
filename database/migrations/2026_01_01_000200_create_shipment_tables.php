<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quotes, shipments, parcels, the append-only event timeline, and invoices.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->constrained();
            $table->foreignId('origin_zone_id')->constrained('service_zones');
            $table->foreignId('destination_zone_id')->constrained('service_zones');
            $table->foreignId('pricing_rule_id')->constrained('pricing_rules');
            $table->json('inputs');
            $table->json('breakdown');
            $table->decimal('total', 12, 2);
            $table->char('currency', 3);
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32)->unique();
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax', 12, 2);
            $table->decimal('total', 12, 2);
            $table->char('currency', 3);
            // draft | issued | paid | void
            $table->string('status', 20)->default('issued')->index();
            $table->timestamp('issued_at')->nullable();
            $table->date('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_number', 20)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('quote_id')->nullable();
            $table->foreignId('service_id')->constrained();
            $table->foreignId('origin_zone_id')->constrained('service_zones');
            $table->foreignId('destination_zone_id')->constrained('service_zones');
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();

            // Sender / recipient snapshots (kept even if saved addresses change).
            $table->string('sender_name');
            $table->string('sender_phone', 32);
            $table->string('sender_email')->nullable();
            $table->string('pickup_address');
            $table->string('pickup_city', 100);
            $table->string('pickup_state', 100);
            $table->string('recipient_name');
            $table->string('recipient_phone', 32);
            $table->string('recipient_email')->nullable();
            $table->string('delivery_address');
            $table->string('delivery_city', 100);
            $table->string('delivery_state', 100);
            $table->text('pickup_instructions')->nullable();
            $table->text('delivery_instructions')->nullable();

            $table->string('package_description');
            $table->string('package_category', 40);
            $table->unsignedSmallInteger('parcel_count')->default(1);
            $table->decimal('chargeable_weight_kg', 10, 3);
            $table->decimal('declared_value', 12, 2)->default(0);
            $table->boolean('insured')->default(false);
            $table->json('special_handling')->nullable();
            $table->boolean('pickup_requested')->default(true);
            $table->date('pickup_date')->nullable();
            // morning | afternoon | evening
            $table->string('pickup_window', 20)->nullable();

            // online | cod | invoice
            $table->string('payment_method', 20);
            $table->json('price_breakdown');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax', 12, 2);
            $table->decimal('total', 12, 2);
            $table->char('currency', 3);
            // Cash to collect from recipient on delivery (COD), separate from the shipping charge.
            $table->decimal('cod_amount', 12, 2)->default(0);

            $table->string('status', 32)->index();
            $table->timestamp('status_changed_at')->nullable();
            $table->date('estimated_delivery_from')->nullable();
            $table->date('estimated_delivery_to')->nullable();

            // Guest access (hashed) so guests can return to checkout / their shipment page.
            $table->string('guest_token_hash', 64)->nullable();
            // Optional recipient one-time delivery code (hashed).
            $table->string('delivery_code_hash', 255)->nullable();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->timestamp('terms_accepted_at');
            $table->timestamp('booked_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['business_id', 'created_at']);
            $table->index('created_at');
        });

        Schema::create('shipment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('description')->nullable();
            $table->decimal('weight_kg', 8, 3);
            $table->decimal('length_cm', 8, 2)->nullable();
            $table->decimal('width_cm', 8, 2)->nullable();
            $table->decimal('height_cm', 8, 2)->nullable();
            $table->decimal('declared_value', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('shipment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32);
            $table->string('previous_status', 32)->nullable();
            $table->timestamp('occurred_at')->index();
            $table->string('location')->nullable();
            $table->string('public_description');
            // Never shown to customers or the public.
            $table->text('internal_note')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            // customer | admin | dispatcher | rider | system | integration
            $table->string('source', 20);
            $table->unsignedBigInteger('delivery_proof_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['shipment_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        foreach (['shipment_events', 'shipment_items', 'shipments', 'invoices', 'quotes'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
