<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('audit_entries', 'audit_logs');

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->renameColumn('details', 'after');
        });

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->json('after')->nullable()->change();
            $table->json('before')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('request_id', 64)->nullable();
            $table->index(['edition_id', 'action']);
            $table->index(['actor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropIndex(['edition_id', 'action']);
            $table->dropIndex(['actor_id', 'created_at']);
            $table->dropColumn(['before', 'ip', 'request_id']);
        });

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->renameColumn('after', 'details');
        });

        Schema::rename('audit_logs', 'audit_entries');
    }
};
