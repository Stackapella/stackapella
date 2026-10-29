<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Open parts (N5; docs/more-features-plan.md, 5): a public harmony's parts left open for
    // others, the stems a filler sings with (copied from its mix's takes, kept with it), and a
    // fill's link to its original. Nullable or defaulted columns and a new table: safe live.
    public function up(): void
    {
        Schema::table('harmonies', function (Blueprint $table) {
            $table->json('open_parts')->nullable()->after('take_ids');
            $table->boolean('open_parts_guide')->default(true)->after('open_parts');
            $table->timestamp('stems_ready_at')->nullable()->after('open_parts_guide');
            $table->foreignId('parent_id')->nullable()->after('stems_ready_at')->constrained('harmonies')->nullOnDelete();
            $table->string('fill_part_key', 40)->nullable()->after('parent_id');
            $table->foreignId('featured_fill_id')->nullable()->after('fill_part_key')->constrained('harmonies')->nullOnDelete();
        });

        Schema::create('harmony_stems', function (Blueprint $table) {
            $table->id();
            $table->foreignId('harmony_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('part_key', 40);
            $table->string('file_key', 255);
            $table->unsignedBigInteger('stored_bytes')->default(0);
            $table->unsignedInteger('sample_rate');
            $table->unsignedInteger('frames');
            $table->integer('offset_frames')->default(0);
            $table->unsignedInteger('trim_start_frames')->default(0);
            $table->unsignedInteger('trim_end_frames')->default(0);
            $table->decimal('volume', 5, 3)->default(1);
            $table->decimal('pan', 4, 3)->default(0);
            // Its outline, copied from its take, so the filler's studio draws it.
            $table->unsignedInteger('frames_per_peak')->default(512);
            $table->json('peaks')->nullable();
            $table->timestamps();

            $table->index(['harmony_id', 'part_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('harmony_stems');

        Schema::table('harmonies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('featured_fill_id');
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn(['open_parts', 'open_parts_guide', 'stems_ready_at', 'fill_part_key']);
        });
    }
};
