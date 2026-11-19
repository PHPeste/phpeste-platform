<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('edition_id')->constrained('editions')->cascadeOnDelete();
            $table->foreignUuid('payment_account_id')->constrained('payment_accounts')->restrictOnDelete();
            $table->string('driver', 32);
            $table->string('event_id', 191);
            $table->string('type', 100);
            $table->string('gateway_charge_id', 100)->nullable();
            $table->string('status', 16);
            $table->longText('payload');
            $table->string('result', 500)->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['payment_account_id', 'event_id']);
            $table->index(['status', 'next_attempt_at']);
            $table->index(['edition_id', 'status']);
        });

        Schema::table('payment_accounts', function (Blueprint $table): void {
            $table->unsignedInteger('webhook_rejections')->default(0);
            $table->timestamp('webhook_rejected_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payment_accounts', function (Blueprint $table): void {
            $table->dropColumn(['webhook_rejections', 'webhook_rejected_at']);
        });

        Schema::dropIfExists('webhook_events');
    }
};
