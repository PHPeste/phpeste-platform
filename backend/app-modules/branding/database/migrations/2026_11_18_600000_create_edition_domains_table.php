<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edition_domains', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('edition_id')->constrained('editions')->cascadeOnDelete();
            $table->string('host')->unique();
            $table->boolean('is_platform')->default(false);
            $table->boolean('is_primary')->default(false);
            $table->string('verification_token', 64);
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->string('last_check_error')->nullable();
            $table->timestamps();

            $table->index(['edition_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edition_domains');
    }
};
