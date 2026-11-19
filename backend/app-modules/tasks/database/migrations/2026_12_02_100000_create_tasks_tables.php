<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_columns', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('edition_id')->constrained('editions')->cascadeOnDelete();
            $table->string('name', 60);
            $table->unsignedSmallInteger('position');
            $table->boolean('is_done')->default(false);
            $table->timestamps();

            $table->index(['edition_id', 'position']);
        });

        Schema::create('tasks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('edition_id')->constrained('editions')->cascadeOnDelete();
            $table->foreignUuid('task_column_id')->constrained('task_columns')->restrictOnDelete();
            $table->unsignedInteger('position');
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->uuid('assignee_id')->nullable();
            $table->date('due_on')->nullable();
            $table->string('link_type', 24)->nullable();
            $table->uuid('link_id')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('due_reminded_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->index(['edition_id', 'task_column_id', 'position']);
            $table->index(['edition_id', 'assignee_id']);
            $table->index(['edition_id', 'due_on']);
            $table->index(['edition_id', 'link_type', 'link_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('task_columns');
    }
};
