<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Accounts for everyone (roadmap M3, docs/backend-plan.md B): the profile, Sign in with Apple,
 * where people came from, held handles, the links in our emails, the browsers a person has signed
 * in from, and Download my data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('handle', 20)->nullable()->unique()->after('name');
            $table->string('apple_id')->nullable()->unique()->after('google_id');
            $table->string('avatar_key')->nullable()->after('password');
            $table->string('bio', 300)->nullable()->after('avatar_key');
            $table->string('x_handle', 15)->nullable()->after('bio');
            $table->string('bluesky_handle', 253)->nullable()->after('x_handle');
            $table->string('timezone', 64)->nullable()->after('locale');
            $table->timestamp('age_confirmed_at')->nullable()->after('timezone');
            // First-party attribution, never analytics (docs/go-to-market-plan.md).
            $table->string('ref', 40)->nullable()->after('age_confirmed_at');
            $table->string('heard_from', 20)->nullable()->after('ref');
            // Hillel's override of the computed trust level, with his note (docs/trust-and-safety-plan.md).
            $table->unsignedTinyInteger('trust_level_override')->nullable()->after('heard_from');
            $table->string('trust_note', 500)->nullable()->after('trust_level_override');
            $table->timestamp('trust_lowered_until')->nullable()->after('trust_note');
            $table->unsignedTinyInteger('failed_sign_ins')->default(0)->after('remember_token');
            $table->timestamp('sign_in_locked_until')->nullable()->after('failed_sign_ins');
        });

        // A deleted account's handle is held, so nobody can take it over (iaw's rule).
        Schema::create('handle_holds', function (Blueprint $table) {
            $table->id();
            $table->string('handle', 20)->unique();
            $table->timestamp('held_until')->nullable();
            $table->timestamps();
        });

        // The links in our emails: verifying an address, and confirming a new one. Hashed, one use.
        Schema::create('email_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('purpose', 20);
            $table->string('email');
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'purpose']);
        });

        // Browsers a person has signed in from (an HMAC of a cookie, never a fingerprint), so a
        // sign-in from a new one can be mentioned to them by email.
        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('device_hash', 64);
            $table->timestamp('last_seen_at');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'device_hash']);
        });

        Schema::create('data_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 12);
            $table->string('file_key')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_exports');
        Schema::dropIfExists('user_devices');
        Schema::dropIfExists('email_tokens');
        Schema::dropIfExists('handle_holds');

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['handle']);
            $table->dropUnique(['apple_id']);
            $table->dropColumn(['handle', 'apple_id', 'avatar_key', 'bio', 'x_handle', 'bluesky_handle', 'timezone', 'age_confirmed_at', 'ref', 'heard_from', 'trust_level_override', 'trust_note', 'trust_lowered_until', 'failed_sign_ins', 'sign_in_locked_until']);
        });
    }
};
