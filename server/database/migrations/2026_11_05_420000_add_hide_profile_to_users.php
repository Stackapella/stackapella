<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hide profile (docs/hide-profile-plan.md): shown to others as "Anonymous", with no handle, picture
 * or profile page, while still sharing and recording.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('hide_profile')->default(false)->after('public_profile_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('hide_profile');
        });
    }
};
