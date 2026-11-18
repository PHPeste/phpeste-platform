<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticketing_settings', function (Blueprint $table): void {
            $table->unsignedSmallInteger('reservation_minutes')->default(30);
            $table->unsignedSmallInteger('max_tickets_per_buyer')->nullable();
            $table->unsignedSmallInteger('gateway_fee_basis_points')->default(0);
            $table->unsignedInteger('order_sequence')->default(0);
        });

        Schema::table('ticket_types', function (Blueprint $table): void {
            $table->boolean('pass_fee_to_buyer')->default(false);
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('edition_id')->constrained('editions')->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('status', 24);
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('buyer_name', 120);
            $table->string('buyer_email');
            $table->text('buyer_document')->nullable();
            $table->string('buyer_document_hash', 64)->nullable();
            $table->unsignedInteger('subtotal_cents');
            $table->unsignedInteger('discount_cents')->default(0);
            $table->unsignedInteger('fee_cents')->default(0);
            $table->unsignedInteger('total_cents');
            $table->foreignUuid('coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
            $table->string('coupon_code', 40)->nullable();
            $table->foreignUuid('coupon_redemption_id')->nullable()->constrained('coupon_redemptions')->nullOnDelete();
            $table->text('access_token');
            $table->string('idempotency_key', 100);
            $table->string('request_hash', 64);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->unique(['edition_id', 'number']);
            $table->unique(['edition_id', 'idempotency_key']);
            $table->index(['edition_id', 'status', 'expires_at']);
            $table->index(['edition_id', 'buyer_email']);
            $table->index(['edition_id', 'buyer_document_hash']);
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('edition_id')->constrained('editions')->cascadeOnDelete();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUuid('ticket_type_id')->constrained('ticket_types')->restrictOnDelete();
            $table->foreignUuid('ticket_batch_id')->constrained('ticket_batches')->restrictOnDelete();
            $table->string('ticket_type_name', 120);
            $table->string('batch_name', 80);
            $table->unsignedInteger('unit_price_cents');
            $table->unsignedInteger('discount_cents')->default(0);
            $table->unsignedInteger('fee_cents')->default(0);
            $table->unsignedInteger('total_cents');
            $table->string('attendee_name', 120);
            $table->string('attendee_email');
            $table->json('answers')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');

        Schema::table('ticket_types', function (Blueprint $table): void {
            $table->dropColumn('pass_fee_to_buyer');
        });

        Schema::table('ticketing_settings', function (Blueprint $table): void {
            $table->dropColumn(['reservation_minutes', 'max_tickets_per_buyer', 'gateway_fee_basis_points', 'order_sequence']);
        });
    }
};
