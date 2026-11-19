<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cadastro global da comunidade: o mesmo fornecedor serve a várias
        // edições dela, mas nunca aparece para outra comunidade.
        Schema::create('suppliers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('community_id')->constrained('communities')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('name_key', 120);
            $table->string('service', 24);
            $table->string('description', 500)->nullable();
            $table->string('document', 14)->nullable();
            $table->string('website')->nullable();
            $table->string('city', 80)->nullable();
            $table->string('state', 2)->nullable();
            $table->timestamps();

            $table->unique(['community_id', 'name_key']);
            $table->index(['community_id', 'service']);
        });

        Schema::create('supplier_contacts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('role', 80)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->timestamps();
        });

        Schema::create('edition_suppliers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('edition_id')->constrained('editions')->cascadeOnDelete();
            $table->foreignUuid('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->string('status', 16);
            $table->uuid('owner_id')->nullable();
            $table->unsignedBigInteger('contracted_cents')->nullable();
            $table->uuid('accepted_quote_id')->nullable();
            $table->string('decline_reason', 500)->nullable();
            $table->timestamp('hired_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamps();

            $table->unique(['edition_id', 'supplier_id']);
            $table->index(['edition_id', 'status']);
        });

        Schema::create('supplier_quotes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('edition_id')->constrained('editions')->cascadeOnDelete();
            $table->foreignUuid('edition_supplier_id')->constrained('edition_suppliers')->cascadeOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->string('description', 500);
            $table->date('received_on');
            $table->date('valid_until')->nullable();
            $table->string('status', 16);
            $table->uuid('recorded_by')->nullable();
            $table->timestamps();

            $table->index(['edition_id', 'edition_supplier_id']);
        });

        Schema::create('supplier_attachments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('edition_id')->constrained('editions')->cascadeOnDelete();
            $table->foreignUuid('edition_supplier_id')->constrained('edition_suppliers')->cascadeOnDelete();
            $table->string('kind', 16);
            $table->string('path');
            $table->string('original_name', 191);
            $table->string('mime_type', 100);
            $table->unsignedInteger('size_bytes');
            $table->uuid('uploaded_by')->nullable();
            $table->timestamps();

            $table->index(['edition_id', 'edition_supplier_id']);
        });

        Schema::create('supplier_activities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('edition_id')->constrained('editions')->cascadeOnDelete();
            $table->foreignUuid('edition_supplier_id')->constrained('edition_suppliers')->cascadeOnDelete();
            $table->string('kind', 16);
            $table->string('channel', 16)->nullable();
            $table->text('summary');
            $table->uuid('user_id')->nullable();
            $table->timestamp('happened_at');
            $table->timestamps();

            $table->index(['edition_id', 'edition_supplier_id', 'happened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_activities');
        Schema::dropIfExists('supplier_attachments');
        Schema::dropIfExists('supplier_quotes');
        Schema::dropIfExists('edition_suppliers');
        Schema::dropIfExists('supplier_contacts');
        Schema::dropIfExists('suppliers');
    }
};
