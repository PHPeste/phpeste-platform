<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('editions', function (Blueprint $table): void {
            $table->string('site_mode', 16)->default('platform')->after('timezone');
            $table->string('external_site_url')->nullable()->after('site_mode');
            $table->string('privacy_contact_email')->nullable()->after('external_site_url');
            $table->string('privacy_policy_url')->nullable()->after('privacy_contact_email');
            $table->boolean('anonymize_after_event')->default(false)->after('privacy_policy_url');
        });
    }

    public function down(): void
    {
        Schema::table('editions', function (Blueprint $table): void {
            $table->dropColumn(['site_mode', 'external_site_url', 'privacy_contact_email', 'privacy_policy_url', 'anonymize_after_event']);
        });
    }
};
