<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A choir group's next rehearsal, as its director sets it: a plain date and whether it repeats
 * weekly. The next one is worked out on read (App\Support\Groups\RehearsalDates), so nothing is
 * scheduled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->date('rehearsal_on')->nullable()->after('link_created_at');
            $table->boolean('rehearsal_weekly')->default(false)->after('rehearsal_on');
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn(['rehearsal_on', 'rehearsal_weekly']);
        });
    }
};
