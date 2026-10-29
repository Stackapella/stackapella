<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anonymous usage counts (config/counts.php): one number per day, kind, guest or account, and
 * country ('' when not known, so the key stays unique). Nothing that identifies anyone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_counts', function (Blueprint $table): void {
            $table->id();
            $table->date('day');
            $table->string('kind', 32);
            $table->string('audience', 8);
            $table->char('country', 2)->default('');
            $table->unsignedInteger('count')->default(0);
            $table->unique(['day', 'kind', 'audience', 'country']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_counts');
    }
};
