<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // The mudeo email goes through the app now (docs/decisions.md, "telling mudeo's users"): a
    // batch says how it went (sent by the app or marked by hand, delivered, bounced, failed). New
    // columns with defaults: safe on the live database.
    public function up(): void
    {
        Schema::table('mudeo_batches', function (Blueprint $table) {
            $table->string('mode', 8)->default('manual')->after('count');
            $table->string('status', 8)->default('sent')->after('mode');
            $table->unsignedSmallInteger('delivered')->default(0)->after('status');
            $table->unsignedSmallInteger('bounced')->default(0)->after('delivered');
            $table->unsignedSmallInteger('failed')->default(0)->after('bounced');
            $table->string('error', 500)->nullable()->after('failed');
        });
    }

    public function down(): void
    {
        Schema::table('mudeo_batches', function (Blueprint $table) {
            $table->dropColumn(['mode', 'status', 'delivered', 'bounced', 'failed', 'error']);
        });
    }
};
