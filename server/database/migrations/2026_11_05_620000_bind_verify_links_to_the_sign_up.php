<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A confirm-your-email link is bound to the browser or app that signed up (the hash of its secret):
 * opened anywhere else, the account starts fresh, so a stranger who signed up with someone's address
 * never keeps a way in (the security review of 2026-11-01).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_tokens', function (Blueprint $table): void {
            $table->char('browser_hash', 64)->nullable()->after('token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('email_tokens', fn (Blueprint $table) => $table->dropColumn('browser_hash'));
    }
};
