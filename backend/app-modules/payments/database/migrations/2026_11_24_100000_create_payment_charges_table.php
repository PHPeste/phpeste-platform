<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_charges', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('edition_id')->constrained('editions')->cascadeOnDelete();
            $table->foreignUuid('payment_account_id')->constrained('payment_accounts')->restrictOnDelete();
            $table->uuid('reference')->index();
            $table->string('driver', 32);
            $table->string('method', 16);
            $table->string('gateway_charge_id', 100);
            $table->string('status', 16);
            $table->unsignedInteger('amount_cents');
            $table->unsignedInteger('fee_cents')->nullable();
            $table->unsignedTinyInteger('installments')->default(1);
            $table->text('pix_copy_paste')->nullable();
            $table->mediumText('pix_qr_code')->nullable();
            $table->string('checkout_url', 500)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('failure_reason', 200)->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->unique(['payment_account_id', 'gateway_charge_id']);
            $table->index(['edition_id', 'reference', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_charges');
    }
};
