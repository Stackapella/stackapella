<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // When a lesson was to end when it was bought. A lesson counts as done (and its coach is paid)
    // only once both this and its end now have passed, so a move into the past, or a webhook that
    // says so, can't pay a coach for a lesson that hasn't happened. Nullable, safe on the live database.
    public function up(): void
    {
        Schema::table('lesson_bookings', function (Blueprint $table) {
            $table->timestamp('agreed_ends_at')->nullable()->after('ends_at');
        });

        DB::table('lesson_bookings')->update(['agreed_ends_at' => DB::raw('ends_at')]);
    }

    public function down(): void
    {
        Schema::table('lesson_bookings', function (Blueprint $table) {
            $table->dropColumn('agreed_ends_at');
        });
    }
};
