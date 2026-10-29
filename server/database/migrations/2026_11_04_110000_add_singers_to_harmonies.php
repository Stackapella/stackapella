<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Who else sang on a harmony (their takes were in its mix), so each is credited, each agrees
    // before it's featured or in the gallery, and it comes down when their voice leaves. Existing
    // harmonies take them from their project's current takes; one with another singer loses any
    // yes to featuring or the gallery until they agree. A nullable column, safe on the live database.
    public function up(): void
    {
        Schema::table('harmonies', function (Blueprint $table) {
            $table->json('singer_ids')->nullable()->after('part_id');
        });

        DB::table('harmonies')->whereNotNull('project_id')->orderBy('id')->chunkById(200, function ($harmonies): void {
            foreach ($harmonies as $harmony) {
                $singers = DB::table('parts')->join('takes', 'takes.id', '=', 'parts.current_take_id')
                    ->where('parts.project_id', $harmony->project_id)
                    ->when($harmony->part_id !== null, fn ($query) => $query->where('parts.id', $harmony->part_id))
                    ->whereNotNull('takes.recorded_by')->where('takes.recorded_by', '!=', $harmony->user_id)
                    ->distinct()->orderBy('takes.recorded_by')->pluck('takes.recorded_by')->map(fn (mixed $id): int => (int) $id)->all();

                DB::table('harmonies')->where('id', $harmony->id)->update([
                    'singer_ids' => json_encode($singers),
                    ...($singers === [] ? [] : ['may_feature_at' => null, 'gallery_opt_in_at' => null]),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('harmonies', function (Blueprint $table) {
            $table->dropColumn('singer_ids');
        });
    }
};
