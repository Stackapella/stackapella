<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // M10 (contract/api.md, "Books, posts and signing in with Event Schedule (M10)"): coaching's
    // payments as a ledger, Invoice Ninja connections and what's been recorded there, our social
    // posts, and a few nullable columns. New tables and nullable columns only, so it's safe on the
    // live database.
    public function up(): void
    {
        // Every coaching payment and refund, once each: the coach's books and our monthly
        // commission are both made from it.
        Schema::create('coaching_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('engagement_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('coach_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 8);
            $table->string('ref', 120)->unique();
            $table->string('sale_ref', 120)->nullable();
            $table->integer('cents');
            $table->integer('fee_cents');
            $table->char('currency', 3);
            $table->string('description');
            $table->string('stripe_charge')->nullable();
            // From before the ledger (a coach's backfill): their books only, never our commission.
            $table->boolean('backfilled')->default(false);
            $table->timestamp('paid_at');
            $table->timestamps();

            $table->index(['coach_id', 'paid_at']);
        });

        Schema::create('invoice_ninja_connections', function (Blueprint $table) {
            $table->id();
            // 'user:<id>' for a coach, 'platform' for Stackapella's own books: one each.
            $table->string('owner_key', 40)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('api_url')->nullable();
            $table->text('token');
            $table->string('company_id', 64);
            $table->string('company_name');
            $table->boolean('send_email')->default(false);
            $table->string('status', 16);
            $table->string('last_error', 32)->nullable();
            $table->string('webhook_id', 64)->nullable();
            $table->text('webhook_secret')->nullable();
            $table->timestamp('backfilled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('invoice_ninja_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('invoice_ninja_connections')->cascadeOnDelete();
            $table->string('kind', 16);
            $table->string('ref', 120);
            $table->string('status', 16);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('cents');
            $table->char('currency', 3);
            $table->json('data');
            $table->string('in_client_id', 64)->nullable();
            $table->string('in_invoice_id', 64)->nullable();
            $table->string('in_payment_id', 64)->nullable();
            $table->string('in_expense_id', 64)->nullable();
            $table->string('error', 32)->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('recorded_at')->nullable();
            $table->timestamps();

            $table->unique(['connection_id', 'kind', 'ref']);
            $table->index(['connection_id', 'status']);
            $table->index(['kind', 'in_invoice_id']);
        });

        Schema::create('social_posts', function (Blueprint $table) {
            $table->id();
            $table->string('postable_type', 40);
            $table->unsignedBigInteger('postable_id');
            // Why: a new song, the song of the month (with its month), a featured harmony.
            $table->string('occasion', 40);
            $table->string('network', 16);
            $table->string('status', 16);
            $table->string('remote_id')->nullable();
            $table->string('url')->nullable();
            $table->text('error')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->unique(['postable_type', 'postable_id', 'occasion', 'network']);
            $table->index(['status', 'created_at']);
        });

        Schema::table('harmonies', function (Blueprint $table) {
            // The owner's yes to our accounts featuring it.
            $table->timestamp('may_feature_at')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            // Sign in with Event Schedule: the ES account's id, never matched by email.
            $table->string('event_schedule_id', 64)->nullable()->unique();
            // Plus paid by invoice, until this time.
            $table->timestamp('plus_paid_until')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['event_schedule_id']);
            $table->dropColumn(['event_schedule_id', 'plus_paid_until']);
        });

        Schema::table('harmonies', function (Blueprint $table) {
            $table->dropColumn('may_feature_at');
        });

        Schema::dropIfExists('social_posts');
        Schema::dropIfExists('invoice_ninja_records');
        Schema::dropIfExists('invoice_ninja_connections');
        Schema::dropIfExists('coaching_payments');
    }
};
