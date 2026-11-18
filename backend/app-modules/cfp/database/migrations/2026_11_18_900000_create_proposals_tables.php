<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('edition_id')->constrained('editions')->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->foreignUuid('track_id')->nullable()->constrained('cfp_tracks')->nullOnDelete();
            $table->string('title', 90);
            $table->text('summary')->nullable();
            $table->text('audience')->nullable();
            $table->string('format', 16)->nullable();
            $table->string('level', 16)->nullable();
            $table->string('language', 8)->default('pt_BR');
            $table->string('status', 16)->default('draft')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();

            $table->unique(['edition_id', 'number']);
        });

        Schema::create('proposal_speakers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('edition_id')->constrained('editions')->cascadeOnDelete();
            $table->foreignUuid('proposal_id')->constrained('proposals')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_owner')->default(false);
            $table->timestamps();

            $table->unique(['proposal_id', 'user_id']);
            $table->index(['edition_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposal_speakers');
        Schema::dropIfExists('proposals');
    }
};
