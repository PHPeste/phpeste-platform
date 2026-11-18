<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('edition_id')->constrained('editions')->cascadeOnDelete();
            $table->string('template', 64);
            $table->string('subject');
            $table->text('body');
            $table->timestamps();

            $table->unique(['edition_id', 'template']);
        });

        Schema::create('email_deliveries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('edition_id')->constrained('editions')->cascadeOnDelete();
            $table->string('template', 64);
            $table->string('to_email');
            $table->string('to_name');
            $table->json('variables');
            $table->json('content')->nullable();
            $table->string('action_url', 2_048)->nullable();
            $table->string('status', 16)->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->boolean('is_test')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['edition_id', 'template', 'status']);
        });

        Schema::create('email_unsubscribes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('edition_id')->constrained('editions')->cascadeOnDelete();
            $table->string('email');
            $table->timestamps();

            $table->unique(['edition_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_unsubscribes');
        Schema::dropIfExists('email_deliveries');
        Schema::dropIfExists('email_templates');
    }
};
