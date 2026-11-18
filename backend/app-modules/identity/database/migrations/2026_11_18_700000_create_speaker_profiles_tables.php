<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('speaker_profiles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('display_name');
            $table->string('job_title')->nullable();
            $table->string('company')->nullable();
            $table->string('bio', 400)->nullable();
            $table->string('city')->nullable();
            $table->char('state', 2)->nullable();
            $table->json('links');
            $table->timestamps();
        });

        Schema::create('speaker_private_details', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->text('accessibility_needs')->nullable();
            $table->text('dietary_restrictions')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('speaker_private_details');
        Schema::dropIfExists('speaker_profiles');
    }
};
