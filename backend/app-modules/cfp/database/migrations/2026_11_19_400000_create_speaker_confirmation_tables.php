<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cfp_settings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('confirmation_days')->default(7);
        });

        Schema::create('speaker_confirmations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('edition_id')->constrained('editions')->cascadeOnDelete();
            $table->foreignUuid('proposal_id')->unique()->constrained('proposals')->cascadeOnDelete();
            $table->string('status', 16)->index();
            $table->timestamp('deadline_at');
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->string('reason', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('speaker_logistics', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('edition_id')->constrained('editions')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('origin_city')->nullable();
            $table->char('origin_state', 2)->nullable();
            $table->boolean('needs_travel')->default(false);
            $table->boolean('needs_lodging')->default(false);
            $table->boolean('travel_paid_by_organization')->default(false);
            $table->boolean('lodging_paid_by_organization')->default(false);
            $table->text('organization_notes')->nullable();
            $table->timestamps();

            $table->unique(['edition_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('speaker_logistics');
        Schema::dropIfExists('speaker_confirmations');

        Schema::table('cfp_settings', function (Blueprint $table): void {
            $table->dropColumn('confirmation_days');
        });
    }
};
