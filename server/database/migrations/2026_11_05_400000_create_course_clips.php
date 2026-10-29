<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Course clips (Hillel: "re-use the singer feature in admin to request singers to record these
 * missing parts"): the short real-voice clips a course step plays, defined by the courses in
 * web/course/plus/clips.json, recorded by beta singers like a part, and published once accepted.
 * An assignment, and so a submission, is a song part or a clip.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_clips', function (Blueprint $table): void {
            $table->id();
            // The courses' own id, `<course>.<name>`, kept forever once recorded.
            $table->string('slot', 80)->unique();
            $table->string('course_id', 40)->index();
            $table->string('lesson_id', 80)->nullable();
            $table->unsignedSmallInteger('step')->nullable();
            // What to sing, as clips.json says it (sing, pitch, seconds, style, voices, used).
            $table->json('brief');
            // Gone from clips.json: kept for what was recorded, never assigned again.
            $table->timestamp('retired_at')->nullable();
            // Published: the accepted take, levelled, on the media CDN.
            $table->foreignId('beta_submission_id')->nullable()->constrained()->nullOnDelete();
            $table->string('media_key')->nullable();
            $table->unsignedInteger('sample_rate')->nullable();
            $table->unsignedInteger('frames')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::table('beta_assignments', function (Blueprint $table): void {
            $table->unsignedBigInteger('template_id')->nullable()->change();
            $table->string('part_key', 20)->nullable()->change();
            $table->foreignId('course_clip_id')->nullable()->after('part_key')->constrained()->cascadeOnDelete();
            $table->unique(['beta_singer_id', 'course_clip_id']);
        });

        Schema::table('beta_submissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('template_version_id')->nullable()->change();
            $table->string('part_key', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('beta_assignments', function (Blueprint $table): void {
            $table->dropUnique(['beta_singer_id', 'course_clip_id']);
            $table->dropConstrainedForeignId('course_clip_id');
        });

        Schema::dropIfExists('course_clips');
    }
};
