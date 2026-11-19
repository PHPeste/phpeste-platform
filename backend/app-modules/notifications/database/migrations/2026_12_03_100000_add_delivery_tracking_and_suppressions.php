<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_deliveries', function (Blueprint $table): void {
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->timestamp('complained_at')->nullable();
            $table->string('provider_detail', 500)->nullable();

            $table->index('provider_message_id');
        });

        // Endereço que não recebe mais nada da edição (nem transacional) até a
        // organização liberar: bounce permanente ou reclamação de spam.
        Schema::create('email_suppressions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('edition_id')->constrained('editions')->cascadeOnDelete();
            $table->string('email');
            $table->string('reason', 16);
            $table->string('detail', 500)->nullable();
            $table->uuid('email_delivery_id')->nullable();
            $table->timestamps();

            $table->unique(['edition_id', 'email']);
        });

        // Caixa de entrada dos webhooks do Resend, um registro por svix-id:
        // a mesma entrega repetida não tem efeito duas vezes.
        Schema::create('email_webhook_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('svix_id', 100)->unique();
            $table->string('type', 40);
            $table->string('provider_message_id', 100)->nullable();
            $table->uuid('edition_id')->nullable()->index();
            $table->string('outcome', 16);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_webhook_events');
        Schema::dropIfExists('email_suppressions');

        Schema::table('email_deliveries', function (Blueprint $table): void {
            $table->dropIndex(['provider_message_id']);
            $table->dropColumn(['delivered_at', 'bounced_at', 'complained_at', 'provider_detail']);
        });
    }
};
