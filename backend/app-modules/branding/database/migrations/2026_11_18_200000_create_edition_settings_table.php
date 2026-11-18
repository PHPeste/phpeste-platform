<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edition_settings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('edition_id')->unique()->constrained('editions')->cascadeOnDelete();
            $table->json('theme_draft');
            $table->json('theme_published')->nullable();
            $table->json('theme_previous')->nullable();
            $table->timestamp('theme_published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edition_settings');
    }
};
