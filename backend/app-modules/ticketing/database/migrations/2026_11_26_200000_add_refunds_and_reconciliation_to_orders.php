<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedInteger('refunded_cents')->default(0);
            $table->unsignedInteger('gateway_fee_cents')->nullable();
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->timestamp('cancelled_at')->nullable();
            $table->uuid('refund_id')->nullable();
        });

        Schema::create('reconciliation_issues', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('edition_id')->constrained('editions')->cascadeOnDelete();
            $table->string('kind', 32);
            $table->string('fingerprint', 191);
            $table->foreignUuid('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->uuid('payment_charge_id')->nullable();
            $table->json('details');
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('resolved_at')->nullable();
            $table->uuid('resolved_by')->nullable();
            $table->string('resolution', 500)->nullable();
            $table->timestamps();

            $table->unique(['edition_id', 'fingerprint']);
            $table->index(['edition_id', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_issues');

        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn(['cancelled_at', 'refund_id']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['refunded_cents', 'gateway_fee_cents']);
        });
    }
};
