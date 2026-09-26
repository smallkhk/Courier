<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riders, assignments, locations, delivery attempts and proofs, cash on delivery.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rider_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('service_zone_id')->nullable()->constrained('service_zones')->nullOnDelete();
            $table->string('vehicle_type', 30)->default('motorcycle');
            $table->string('plate_number', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('on_duty')->default(false);
            $table->timestamp('on_duty_since')->nullable();
            $table->unsignedSmallInteger('max_active_assignments')->default(15);
            $table->timestamp('location_consent_at')->nullable();
            $table->boolean('location_sharing')->default(false);
            $table->decimal('last_lat', 10, 7)->nullable();
            $table->decimal('last_lng', 10, 7)->nullable();
            $table->decimal('last_accuracy_m', 10, 2)->nullable();
            $table->timestamp('last_location_at')->nullable();
            $table->timestamps();
        });

        Schema::create('rider_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rider_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            // pickup | delivery
            $table->string('leg', 20)->default('delivery');
            // assigned | accepted | declined | completed | reassigned | cancelled
            $table->string('status', 20)->default('assigned')->index();
            $table->timestamp('assigned_at');
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index(['rider_id', 'status']);
            $table->index(['shipment_id', 'status']);
        });

        Schema::create('rider_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->decimal('accuracy_m', 10, 2)->nullable();
            $table->timestamp('recorded_at');
            $table->timestamp('received_at');
            $table->timestamp('expires_at')->index();
            $table->index(['rider_id', 'recorded_at']);
        });

        Schema::create('delivery_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submitted_by')->constrained('users');
            $table->string('recipient_name');
            $table->timestamp('delivered_at');
            // Private storage paths (never under public/).
            $table->string('signature_path')->nullable();
            $table->string('photo_path')->nullable();
            $table->boolean('photo_consent')->default(false);
            $table->boolean('code_verified')->default(false);
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('delivery_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rider_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('attempted_at');
            // recipient_unavailable | address_issue | recipient_declined | access_issue | other
            $table->string('reason', 40);
            // Customer-safe note.
            $table->string('note')->nullable();
            $table->string('evidence_path')->nullable();
            // retry | contact_recipient | hold | return  (decided by operations)
            $table->string('resolution', 30)->nullable();
            $table->date('retry_on')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['resolution', 'created_at']);
        });

        Schema::create('cod_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('rider_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount_due', 12, 2);
            $table->decimal('amount_collected', 12, 2)->nullable();
            $table->char('currency', 3);
            // pending | collected | remitted | reconciled | discrepancy
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('collected_at')->nullable();
            $table->timestamp('remitted_at')->nullable();
            $table->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reconciled_at')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['cod_collections', 'delivery_attempts', 'delivery_proofs', 'rider_locations', 'rider_assignments', 'rider_profiles'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
