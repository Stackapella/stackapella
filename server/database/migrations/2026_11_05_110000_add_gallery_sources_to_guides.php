<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Guide voices from songs in the gallery (Hillel's call, "public songs' voices can become
    // guides"): a guide may come from a gallery harmony's part, credited to its singer's profile,
    // and goes when the song leaves the gallery. Nullable columns: safe on the live database.
    public function up(): void
    {
        Schema::table('template_guides', function (Blueprint $table) {
            $table->foreignId('harmony_id')->nullable()->after('beta_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('take_id')->nullable()->after('harmony_id')->constrained()->nullOnDelete();
            $table->foreignId('credit_user_id')->nullable()->after('take_id')->constrained('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('template_guides', function (Blueprint $table) {
            $table->dropConstrainedForeignId('credit_user_id');
            $table->dropConstrainedForeignId('take_id');
            $table->dropConstrainedForeignId('harmony_id');
        });
    }
};
