<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('speaker_confirmations', function (Blueprint $table): void {
            $table->timestamp('attended_at')->nullable()->after('reminded_at');
        });
    }

    public function down(): void
    {
        Schema::table('speaker_confirmations', function (Blueprint $table): void {
            $table->dropColumn('attended_at');
        });
    }
};
