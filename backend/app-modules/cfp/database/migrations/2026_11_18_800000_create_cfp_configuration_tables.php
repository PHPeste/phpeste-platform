<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cfp_settings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('edition_id')->unique()->constrained('editions')->cascadeOnDelete();
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->timestamp('results_at')->nullable();
            $table->boolean('anonymous_review')->default(true);
            $table->boolean('require_nordeste_quota')->default(true);
            $table->unsignedTinyInteger('nordeste_quota_percent')->default(50);
            $table->unsignedTinyInteger('proposals_per_person')->default(3);
            $table->string('announced_status', 16)->default('draft');
            $table->timestamps();
        });

        Schema::create('cfp_tracks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('edition_id')->constrained('editions')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('slots')->default(0);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['edition_id', 'name']);
        });

        Schema::create('cfp_criteria', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('edition_id')->constrained('editions')->cascadeOnDelete();
            $table->string('name');
            $table->string('description', 500)->nullable();
            $table->unsignedTinyInteger('weight')->default(1);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['edition_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cfp_criteria');
        Schema::dropIfExists('cfp_tracks');
        Schema::dropIfExists('cfp_settings');
    }
};
