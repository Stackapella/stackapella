<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The blocklist (after Event Schedule's): emails, email domains, IPs and ranges, and card
 * fingerprints, each with a reason and an optional expiry. Checked at sign-up, sign-in, Checkout
 * and the public forms.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocks', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->string('value', 255);
            $table->string('reason', 500);
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['type', 'value']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocks');
    }
};
