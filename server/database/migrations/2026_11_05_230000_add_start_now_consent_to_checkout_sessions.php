<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The EU, EEA and UK consent to start Plus at once (App\Support\Billing\Withdrawal), kept with the
 * checkout it was given for: when, the words' version, and the country it was needed in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkout_sessions', function (Blueprint $table): void {
            $table->char('country', 2)->nullable()->after('trial');
            $table->timestamp('start_now_consent_at')->nullable()->after('country');
            $table->unsignedSmallInteger('start_now_consent_version')->nullable()->after('start_now_consent_at');
        });
    }

    public function down(): void
    {
        Schema::table('checkout_sessions', function (Blueprint $table): void {
            $table->dropColumn(['country', 'start_now_consent_at', 'start_now_consent_version']);
        });
    }
};
