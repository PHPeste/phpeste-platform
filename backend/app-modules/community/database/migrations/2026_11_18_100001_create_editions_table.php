<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('community_id')->constrained('communities')->restrictOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('city')->nullable();
            $table->char('state', 2)->nullable();
            $table->string('timezone', 64)->default('America/Fortaleza');
            $table->string('status', 32)->default('draft')->index();
            $table->timestamps();

            $table->unique(['community_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editions');
    }
};
