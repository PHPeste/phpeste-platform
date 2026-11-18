<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edition_clones', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('source_edition_id')->constrained('editions')->cascadeOnDelete();
            $table->foreignUuid('target_edition_id')->nullable()->constrained('editions')->nullOnDelete();
            $table->foreignUuid('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->json('parts');
            $table->string('status', 16)->default('queued');
            $table->text('error')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edition_clones');
    }
};
