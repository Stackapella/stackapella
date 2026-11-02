<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sheet music import: which wording of the rights confirmation the singer agreed to (config
 * site.import_rights_version), kept with when. Live-safe: a nullable column at the end.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table): void {
            $table->string('rights_statement', 32)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table): void {
            $table->dropColumn('rights_statement');
        });
    }
};
