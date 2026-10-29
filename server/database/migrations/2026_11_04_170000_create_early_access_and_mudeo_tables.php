<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Telling mudeo's users (docs/mudeo-transition-plan.md): the secret early-access link (one open
    // at a time; replaced ones stay, closed, for the counts), which accounts came in through it,
    // and the list Hillel emails by hand in batches. New tables and a nullable column: safe live.
    public function up(): void
    {
        Schema::create('early_access_links', function (Blueprint $table) {
            $table->id();
            $table->string('label', 20);
            $table->char('code_hash', 64)->unique();
            // The code itself, encrypted, so the admin can show the link again to paste it.
            $table->text('code');
            $table->unsignedInteger('cap');
            $table->unsignedInteger('joined')->default(0);
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('early_access_link_id')->nullable()->after('ref')->constrained()->nullOnDelete();
        });

        Schema::create('mudeo_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('count');
            $table->timestamp('sent_at');
            $table->timestamp('undone_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('mudeo_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('name')->nullable();
            $table->unsignedBigInteger('mudeo_id')->nullable();
            $table->timestamp('mudeo_created_at')->nullable();
            $table->timestamp('mudeo_updated_at')->nullable();
            $table->foreignId('mudeo_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opted_out_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->timestamps();

            $table->index(['sent_at', 'mudeo_updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mudeo_contacts');
        Schema::dropIfExists('mudeo_batches');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('early_access_link_id');
        });
        Schema::dropIfExists('early_access_links');
    }
};
