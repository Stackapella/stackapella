<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Plus bought in the apps (docs/app-purchases-plan.md, Hillel's option A): one row per App Store
 * or Google Play subscription, tied to an account. The account's store_plus_until is the latest
 * day a store gives it Plus (grace included), read where the plan is, as plus_paid_until is.
 * store_account_token is the UUID the apps set as Apple's appAccountToken and Google's
 * obfuscatedAccountId, so a purchase names its account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('store', 8);
            // Apple's originalTransactionId; for Google, the SHA-256 of the first purchase token.
            $table->string('original_id', 128);
            $table->text('purchase_token')->nullable();
            // Google's latest purchase token, hashed, to find the row a notification is about.
            $table->string('token_hash', 64)->nullable()->index();
            $table->string('product_id', 120);
            $table->string('interval', 8)->nullable();
            $table->string('status', 16);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('grace_until')->nullable();
            $table->boolean('will_renew')->default(true);
            $table->string('environment', 12);
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('last_event_at')->nullable();
            $table->timestamps();

            $table->unique(['store', 'original_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->uuid('store_account_token')->nullable()->unique()->after('stripe_id');
            $table->timestamp('store_plus_until')->nullable()->after('store_account_token');
        });

        // Every account has its token from the start (new ones get theirs when they're made).
        DB::table('users')->whereNull('store_account_token')->orderBy('id')->select('id')->eachById(static function (object $user): void {
            DB::table('users')->where('id', $user->id)->update(['store_account_token' => (string) Str::uuid()]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['store_account_token']);
            $table->dropColumn(['store_account_token', 'store_plus_until']);
        });

        Schema::dropIfExists('store_subscriptions');
    }
};
