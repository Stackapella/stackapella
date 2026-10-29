<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // From the review of N4 to N7: a choir voice held by reports (apart from one Hillel took out),
    // the trims a singer made (so the mix leaves out what they cut), and when a fill's original
    // owner was told of it. Nullable or defaulted columns: safe on the live database.
    public function up(): void
    {
        Schema::table('choir_voices', function (Blueprint $table) {
            $table->timestamp('held_at')->nullable()->after('in_mix_at');
            $table->unsignedInteger('trim_start_frames')->default(0)->after('offset_frames');
            $table->unsignedInteger('trim_end_frames')->default(0)->after('trim_start_frames');
        });

        Schema::table('harmonies', function (Blueprint $table) {
            $table->timestamp('fill_told_at')->nullable()->after('fill_part_key');
        });
    }

    public function down(): void
    {
        Schema::table('harmonies', function (Blueprint $table) {
            $table->dropColumn('fill_told_at');
        });

        Schema::table('choir_voices', function (Blueprint $table) {
            $table->dropColumn(['held_at', 'trim_start_frames', 'trim_end_frames']);
        });
    }
};
