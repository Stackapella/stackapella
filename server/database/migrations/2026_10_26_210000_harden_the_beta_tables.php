<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fixes from M2's review:
 * - a link's sessions end when the link changes: the singer's session epoch, kept in each session;
 * - a guide's level never clips: the take's peak, measured when it's processed;
 * - a part is paid once, at the rate agreed when it was accepted: the amount, kept on the take;
 * - whether publishing worked, so a failed publish shows and can be tried again;
 * - in the database itself: one guide per take, and one default voice per part.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beta_singers', function (Blueprint $table) {
            $table->unsignedInteger('session_epoch')->default(1)->after('token_hash');
            $table->timestamp('forgotten_at')->nullable()->after('revoked_at');
        });

        Schema::table('beta_submissions', function (Blueprint $table) {
            $table->decimal('peak_dbfs', 6, 2)->nullable()->after('loudness_dbfs');
            $table->string('publish_status', 10)->default('none')->after('status');
            $table->unsignedInteger('payable_cents')->default(0)->after('paid_at');
        });

        Schema::table('template_guides', function (Blueprint $table) {
            $table->unique('beta_submission_id');
            // Virtual, not stored: MySQL refuses a stored column built on a cascading foreign key.
            $table->string('default_slot', 40)->nullable()->virtualAs("IF(is_default, CONCAT(template_version_id, ':', part_key), NULL)");
            $table->unique('default_slot');
        });
    }

    public function down(): void
    {
        Schema::table('template_guides', function (Blueprint $table) {
            $table->dropUnique(['default_slot']);
            $table->dropColumn('default_slot');
            // The foreign key needs an index of its own before the unique one goes.
            $table->index('beta_submission_id');
            $table->dropUnique(['beta_submission_id']);
        });

        Schema::table('beta_submissions', function (Blueprint $table) {
            $table->dropColumn(['peak_dbfs', 'publish_status', 'payable_cents']);
        });

        Schema::table('beta_singers', function (Blueprint $table) {
            $table->dropColumn(['session_epoch', 'forgotten_at']);
        });
    }
};
