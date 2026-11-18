<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposals', function (Blueprint $table): void {
            $table->string('origin', 16)->default('cfp')->after('status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('proposals', function (Blueprint $table): void {
            $table->dropIndex(['origin']);
            $table->dropColumn('origin');
        });
    }
};
