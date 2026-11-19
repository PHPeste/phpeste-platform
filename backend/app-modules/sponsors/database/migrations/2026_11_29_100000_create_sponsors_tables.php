<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsor_settings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('edition_id')->unique()->constrained('editions')->cascadeOnDelete();
            $table->unsignedBigInteger('goal_cents')->nullable();
            $table->timestamps();
        });

        Schema::create('sponsor_tiers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('edition_id')->constrained('editions')->cascadeOnDelete();
            $table->string('name', 80);
            $table->unsignedBigInteger('price_cents');
            $table->unsignedSmallInteger('slots')->nullable();
            $table->json('benefits');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['edition_id', 'name']);
        });

        Schema::create('sponsors', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('edition_id')->constrained('editions')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('website')->nullable();
            $table->string('contact_name', 120)->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['edition_id', 'name']);
        });

        Schema::create('sponsorships', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('edition_id')->constrained('editions')->cascadeOnDelete();
            $table->foreignUuid('sponsor_id')->constrained('sponsors')->cascadeOnDelete();
            $table->foreignUuid('sponsor_tier_id')->nullable()->constrained('sponsor_tiers')->nullOnDelete();
            $table->string('stage', 16);
            $table->unsignedBigInteger('value_cents');
            $table->uuid('owner_id')->nullable();
            $table->string('lost_reason', 500)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('lost_at')->nullable();
            $table->timestamps();

            $table->index(['edition_id', 'stage']);
            $table->index(['edition_id', 'sponsor_tier_id', 'stage']);
        });

        Schema::create('sponsorship_payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('edition_id')->constrained('editions')->cascadeOnDelete();
            $table->foreignUuid('sponsorship_id')->constrained('sponsorships')->cascadeOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->date('paid_on');
            $table->string('note', 500)->nullable();
            $table->uuid('recorded_by')->nullable();
            $table->timestamps();

            $table->index(['edition_id', 'sponsorship_id']);
        });

        Schema::create('sponsor_deliverables', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('edition_id')->constrained('editions')->cascadeOnDelete();
            $table->foreignUuid('sponsorship_id')->constrained('sponsorships')->cascadeOnDelete();
            $table->string('title', 200);
            $table->uuid('owner_id')->nullable();
            $table->date('due_on')->nullable();
            $table->string('status', 16);
            $table->timestamp('done_at')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['edition_id', 'status', 'due_on']);
        });

        Schema::create('sponsor_contacts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('edition_id')->constrained('editions')->cascadeOnDelete();
            $table->foreignUuid('sponsor_id')->constrained('sponsors')->cascadeOnDelete();
            $table->uuid('user_id')->nullable();
            $table->string('channel', 16);
            $table->timestamp('happened_at');
            $table->text('summary');
            $table->timestamps();

            $table->index(['edition_id', 'sponsor_id', 'happened_at']);
        });

        Schema::create('sponsor_attachments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('edition_id')->constrained('editions')->cascadeOnDelete();
            $table->foreignUuid('sponsor_id')->constrained('sponsors')->cascadeOnDelete();
            $table->string('kind', 16);
            $table->string('path');
            $table->string('original_name', 191);
            $table->string('mime_type', 100);
            $table->unsignedInteger('size_bytes');
            $table->uuid('uploaded_by')->nullable();
            $table->timestamps();

            $table->index(['edition_id', 'sponsor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsor_attachments');
        Schema::dropIfExists('sponsor_contacts');
        Schema::dropIfExists('sponsor_deliverables');
        Schema::dropIfExists('sponsorship_payments');
        Schema::dropIfExists('sponsorships');
        Schema::dropIfExists('sponsors');
        Schema::dropIfExists('sponsor_tiers');
        Schema::dropIfExists('sponsor_settings');
    }
};
