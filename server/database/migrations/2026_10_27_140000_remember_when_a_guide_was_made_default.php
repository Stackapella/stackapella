<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Hillel's decision (docs/decisions.md, published versions): when a part's default goes, the
    // version he made default most recently takes over, so his choices are remembered.
    public function up(): void
    {
        Schema::table('template_guides', function (Blueprint $table) {
            $table->timestamp('defaulted_at')->nullable()->after('is_default');
        });
    }

    public function down(): void
    {
        Schema::table('template_guides', function (Blueprint $table) {
            $table->dropColumn('defaulted_at');
        });
    }
};
