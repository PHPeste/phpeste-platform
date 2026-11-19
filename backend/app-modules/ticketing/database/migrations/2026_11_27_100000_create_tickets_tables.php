<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('edition_id')->constrained('editions')->cascadeOnDelete();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUuid('order_item_id')->unique()->constrained('order_items')->cascadeOnDelete();
            $table->foreignUuid('ticket_type_id')->constrained('ticket_types')->restrictOnDelete();
            $table->string('code', 16);
            $table->string('status', 16);
            $table->unsignedInteger('qr_version')->default(1);
            $table->string('holder_name', 120);
            $table->string('holder_email');
            $table->foreignUuid('holder_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('badge_name', 60)->nullable();
            $table->string('company', 120)->nullable();
            $table->string('tshirt_size', 8)->nullable();
            $table->text('accessibility')->nullable();
            $table->timestamp('issued_at');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();

            $table->unique(['edition_id', 'code']);
            $table->index(['edition_id', 'status']);
            $table->index(['edition_id', 'holder_email']);
        });

        Schema::create('ticket_transfers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('edition_id')->constrained('editions')->cascadeOnDelete();
            $table->foreignUuid('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->string('from_name', 120);
            $table->string('from_email');
            $table->string('to_name', 120);
            $table->string('to_email');
            $table->foreignUuid('transferred_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 16);
            $table->timestamps();

            $table->index(['edition_id', 'ticket_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_transfers');
        Schema::dropIfExists('tickets');
    }
};
