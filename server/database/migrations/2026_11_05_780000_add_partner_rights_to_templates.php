<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A partner's song given by Admin (docs/partner-songs.md, gap 1): the rights record from the
 * partner's written confirmation, kept with the song (who confirmed, the words they confirmed, and
 * where that confirmation is: their email), as an import's own confirmation is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table): void {
            $table->string('rights_confirmed_by', 160)->nullable()->after('rights_statement');
            $table->text('rights_wording')->nullable()->after('rights_confirmed_by');
            $table->string('rights_source', 500)->nullable()->after('rights_wording');
        });
    }

    public function down(): void
    {
        Schema::table('templates', fn (Blueprint $table) => $table->dropColumn(['rights_confirmed_by', 'rights_wording', 'rights_source']));
    }
};
