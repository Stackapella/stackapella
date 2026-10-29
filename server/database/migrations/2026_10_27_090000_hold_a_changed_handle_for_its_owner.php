<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // A changed handle is held for a while for whoever let it go (they may take it back); a deleted
    // account's has no owner.
    public function up(): void
    {
        Schema::table('handle_holds', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('handle')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('handle_holds', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
