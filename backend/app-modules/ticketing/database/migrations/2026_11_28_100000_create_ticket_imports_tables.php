<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_imports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('edition_id')->constrained('editions')->cascadeOnDelete();
            $table->uuid('created_by')->nullable();
            $table->string('platform', 40);
            $table->string('status', 16);
            $table->string('file_name', 191);
            $table->string('file_path')->nullable();
            $table->string('delimiter', 1);
            $table->string('encoding', 16);
            $table->json('headers');
            $table->json('mapping')->nullable();
            $table->json('ticket_type_map')->nullable();
            $table->uuid('default_ticket_type_id')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedBigInteger('byte_offset')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->unsignedInteger('duplicate_rows')->default(0);
            $table->unsignedInteger('rejected_rows')->default(0);
            $table->string('failure', 500)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('emails_requested_at')->nullable();
            $table->uuid('emails_requested_by')->nullable();
            $table->timestamps();

            $table->index(['edition_id', 'status']);
        });

        Schema::create('ticket_import_rejections', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('edition_id')->constrained('editions')->cascadeOnDelete();
            $table->foreignUuid('ticket_import_id')->constrained('ticket_imports')->cascadeOnDelete();
            $table->unsignedInteger('line');
            $table->string('kind', 16);
            $table->string('reason', 255);
            $table->text('data');
            $table->timestamps();

            $table->unique(['ticket_import_id', 'line']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->string('source', 16)->default('checkout');
            $table->string('external_platform', 40)->nullable();
            $table->string('external_reference', 100)->nullable();
            $table->uuid('ticket_import_id')->nullable()->index();

            $table->unique(['edition_id', 'external_platform', 'external_reference']);
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->uuid('ticket_batch_id')->nullable()->change();
            $table->string('external_code', 100)->nullable();

            $table->unique(['edition_id', 'external_code']);
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropUnique(['edition_id', 'external_code']);
            $table->dropColumn('external_code');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique(['edition_id', 'external_platform', 'external_reference']);
            $table->dropIndex(['ticket_import_id']);
            $table->dropColumn(['source', 'external_platform', 'external_reference', 'ticket_import_id']);
        });

        Schema::dropIfExists('ticket_import_rejections');
        Schema::dropIfExists('ticket_imports');
    }
};
