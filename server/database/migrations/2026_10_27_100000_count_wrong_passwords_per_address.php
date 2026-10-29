<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Wrong passwords are counted per address in the rate limiter (App\Support\Accounts\PasswordFailures),
    // for addresses with no account too, so these per-account columns go.
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['failed_sign_ins', 'sign_in_locked_until']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('failed_sign_ins')->default(0);
            $table->timestamp('sign_in_locked_until')->nullable();
        });
    }
};
