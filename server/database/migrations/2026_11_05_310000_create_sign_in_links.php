<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sign-in links (auth.link.send and auth.link.open): a one-time link by email that signs in, or
 * makes the account for an address that has none. Only the token's hash is kept; what the link
 * carries (where to go after, a beta invite or early-access code) is encrypted. Kept a day.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sign_in_links', function (Blueprint $table): void {
            $table->id();
            $table->string('email');
            $table->char('token_hash', 64)->unique();
            $table->text('context');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['email', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sign_in_links');
    }
};
