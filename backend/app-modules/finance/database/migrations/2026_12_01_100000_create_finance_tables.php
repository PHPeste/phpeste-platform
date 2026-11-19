<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_categories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('edition_id')->constrained('editions')->cascadeOnDelete();
            $table->string('name', 80);
            $table->unsignedBigInteger('budget_cents')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['edition_id', 'name']);
        });

        Schema::create('expenses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('edition_id')->constrained('editions')->cascadeOnDelete();
            $table->foreignUuid('finance_category_id')->constrained('finance_categories')->restrictOnDelete();
            $table->uuid('edition_supplier_id')->nullable();
            $table->string('description', 200);
            $table->unsignedBigInteger('amount_cents');
            $table->date('due_on')->nullable();
            $table->date('paid_on')->nullable();
            $table->string('status', 16);
            $table->string('receipt_path')->nullable();
            $table->string('receipt_name', 191)->nullable();
            $table->string('receipt_mime', 100)->nullable();
            $table->unsignedInteger('receipt_size')->nullable();
            $table->text('notes')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->index(['edition_id', 'status', 'due_on']);
            $table->index(['edition_id', 'finance_category_id']);
            $table->index(['edition_id', 'edition_supplier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('finance_categories');
    }
};
