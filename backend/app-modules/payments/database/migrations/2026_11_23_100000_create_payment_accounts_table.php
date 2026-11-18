<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_accounts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('edition_id')->constrained('editions')->cascadeOnDelete();
            $table->string('driver', 32);
            $table->string('environment', 16);
            $table->text('credentials');
            $table->string('credentials_hint', 8);
            $table->string('holder_type', 16);
            $table->string('holder_name', 120);
            $table->string('holder_document_masked', 24);
            $table->json('methods');
            $table->unsignedTinyInteger('max_installments')->default(1);
            $table->unsignedSmallInteger('pix_expiration_minutes')->default(30);
            $table->string('webhook_token', 64)->unique();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('webhook_verified_at')->nullable();
            $table->timestamp('retired_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['edition_id', 'retired_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_accounts');
    }
};
