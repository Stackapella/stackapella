<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Hillel's decision (docs/decisions.md, "beta singers have accounts"): a singer signs in like
    // everyone else, and the invite link attaches their singer record to the account once. The
    // beta session (its epoch and codes) goes, and so does the credit's own name: it's the
    // account's name now.
    public function up(): void
    {
        Schema::table('beta_singers', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->unique()->after('id')->constrained()->nullOnDelete();
            $table->timestamp('accepted_at')->nullable()->after('user_id');
            $table->dropColumn(['session_epoch', 'credit_name']);
        });

        Schema::dropIfExists('beta_session_codes');

        // The invite a sign-up carried, accepted on its own once the address is verified.
        Schema::table('users', function (Blueprint $table) {
            $table->char('pending_invite_hash', 64)->nullable()->after('ref');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('pending_invite_hash');
        });

        Schema::create('beta_session_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('beta_singer_id')->constrained()->cascadeOnDelete();
            $table->char('code_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::table('beta_singers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn('accepted_at');
            $table->unsignedInteger('session_epoch')->default(1);
            $table->string('credit_name', 80)->nullable();
        });
    }
};
