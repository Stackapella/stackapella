<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sheet music import (Hillel: "please try your best to keep us out of any trouble"): an own song
 * made from a score the singer brought is marked, with when they confirmed they have the rights,
 * and it stays private to them and their choir groups. Live-safe: two nullable columns at the end.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table): void {
            $table->timestamp('imported_at')->nullable();
            $table->timestamp('rights_confirmed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table): void {
            $table->dropColumn(['imported_at', 'rights_confirmed_at']);
        });
    }
};
