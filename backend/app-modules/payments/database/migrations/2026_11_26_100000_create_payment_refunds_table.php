<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_charges', function (Blueprint $table): void {
            $table->unsignedInteger('refunded_cents')->default(0);
        });

        Schema::create('payment_refunds', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('edition_id')->constrained('editions')->cascadeOnDelete();
            $table->foreignUuid('payment_charge_id')->constrained('payment_charges')->restrictOnDelete();
            $table->foreignUuid('payment_account_id')->constrained('payment_accounts')->restrictOnDelete();
            $table->uuid('reference');
            $table->string('gateway_refund_id', 100)->nullable();
            $table->unsignedInteger('amount_cents');
            $table->string('status', 16);
            $table->string('reason', 32);
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->string('failure_reason', 300)->nullable();
            $table->uuid('requested_by')->nullable();
            $table->uuid('settled_by')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['edition_id', 'reference']);
            $table->index(['edition_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_refunds');

        Schema::table('payment_charges', function (Blueprint $table): void {
            $table->dropColumn('refunded_cents');
        });
    }
};
