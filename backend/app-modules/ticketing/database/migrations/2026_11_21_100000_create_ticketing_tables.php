<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticketing_settings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('edition_id')->unique()->constrained('editions')->cascadeOnDelete();
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedInteger('allocated')->default(0);
            $table->timestamps();
        });

        Schema::create('ticket_types', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('edition_id')->constrained('editions')->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('visibility', 16)->default('public');
            $table->string('access_code', 40)->nullable();
            $table->unsignedSmallInteger('min_per_order')->default(1);
            $table->unsignedSmallInteger('max_per_order')->default(10);
            $table->boolean('show_remaining')->default(false);
            $table->json('questions')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['edition_id', 'name']);
            $table->index(['edition_id', 'access_code']);
        });

        Schema::create('ticket_batches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('edition_id')->constrained('editions')->cascadeOnDelete();
            $table->foreignUuid('ticket_type_id')->constrained('ticket_types')->cascadeOnDelete();
            $table->string('name', 80);
            $table->unsignedSmallInteger('position')->default(0);
            $table->unsignedInteger('price_cents');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('allocated')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('on_sell_out', 16)->default('next');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['ticket_type_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_batches');
        Schema::dropIfExists('ticket_types');
        Schema::dropIfExists('ticketing_settings');
    }
};
