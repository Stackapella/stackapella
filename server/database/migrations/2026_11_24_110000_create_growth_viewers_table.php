<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Growth viewers (App\Support\Admin\GrowthViewers): an advisor's address, granted from a console, and the Google account
 * that first signed in with it, kept from then on.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('growth_viewers')) {
            return;
        }

        Schema::create('growth_viewers', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->unique();
            $table->string('google_id')->nullable();
            $table->timestamp('granted_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('growth_viewers');
    }
};
