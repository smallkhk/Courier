<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coverage network: zones, services, branches, pricing rules, businesses, addresses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_zones', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('state', 100);
            // Cities / LGAs covered by this zone, used to validate addresses.
            $table->json('cities')->nullable();
            $table->boolean('is_remote')->default(false);
            $table->boolean('pickup_enabled')->default(true);
            $table->boolean('delivery_enabled')->default(true);
            $table->boolean('active')->default(true);
            $table->decimal('center_lat', 10, 7)->nullable();
            $table->decimal('center_lng', 10, 7)->nullable();
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            // Owner-configured commitments. NULL means no reliable estimate is offered.
            $table->unsignedSmallInteger('transit_days_min')->nullable();
            $table->unsignedSmallInteger('transit_days_max')->nullable();
            $table->decimal('max_weight_kg', 8, 2)->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('service_zone', function (Blueprint $table) {
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_zone_id')->constrained('service_zones')->cascadeOnDelete();
            $table->primary(['service_id', 'service_zone_id']);
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->foreignId('service_zone_id')->nullable()->constrained('service_zones')->nullOnDelete();
            $table->string('address');
            $table->string('city', 100);
            $table->string('state', 100);
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            // {"mon":"08:00-18:00", ... "sun":null}
            $table->json('opening_hours')->nullable();
            // [{"date":"2026-12-25","note":"Christmas"}]
            $table->json('holiday_closures')->nullable();
            $table->boolean('is_pickup_point')->default(true);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('registration_number', 64)->nullable();
            $table->string('email');
            $table->string('phone', 32)->nullable();
            $table->string('billing_email')->nullable();
            $table->string('billing_address')->nullable();
            // pending | approved | suspended | rejected
            $table->string('status', 20)->default('pending')->index();
            // prepaid | invoice
            $table->string('payment_terms', 20)->default('prepaid');
            $table->unsignedSmallInteger('invoice_due_days')->default(14);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('business_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // owner | admin | shipper | finance | viewer
            $table->string('role', 20)->default('shipper');
            $table->timestamps();
            $table->unique(['business_id', 'user_id']);
        });

        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            // NULL = applies to any zone.
            $table->foreignId('origin_zone_id')->nullable()->constrained('service_zones')->cascadeOnDelete();
            $table->foreignId('destination_zone_id')->nullable()->constrained('service_zones')->cascadeOnDelete();
            // NULL = public rate; set = negotiated business rate.
            $table->foreignId('business_id')->nullable()->constrained()->cascadeOnDelete();
            $table->char('currency', 3);
            $table->decimal('base_fee', 12, 2);
            $table->decimal('included_weight_kg', 8, 2)->default(0);
            $table->decimal('per_kg_fee', 12, 2)->default(0);
            $table->decimal('extra_parcel_fee', 12, 2)->default(0);
            $table->decimal('min_charge', 12, 2)->default(0);
            $table->decimal('remote_surcharge', 12, 2)->default(0);
            $table->decimal('pickup_surcharge', 12, 2)->default(0);
            $table->decimal('insurance_rate_percent', 6, 3)->default(0);
            $table->decimal('insurance_min_fee', 12, 2)->default(0);
            $table->decimal('discount_percent', 6, 3)->default(0);
            $table->decimal('tax_rate_percent', 6, 3)->default(0);
            $table->unsignedInteger('volumetric_divisor')->default(5000);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['service_id', 'active']);
        });

        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('business_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('label', 60);
            $table->string('contact_name');
            $table->string('phone', 32);
            $table->string('email')->nullable();
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('city', 100);
            $table->string('state', 100);
            $table->foreignId('service_zone_id')->nullable()->constrained('service_zones')->nullOnDelete();
            $table->string('landmark')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['addresses', 'pricing_rules', 'business_members', 'businesses', 'branches', 'service_zone', 'services', 'service_zones'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
