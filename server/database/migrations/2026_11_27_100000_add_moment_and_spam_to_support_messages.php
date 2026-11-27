<?php

declare(strict_types=1);

use App\Support\Feedback\DeviceDetails;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contact us, made easier (docs/feedback-plan.md): where a message was opened from (`moment`), and the Maybe spam
 * mark (a guest's message with two or more links while Turnstile was off). Messages kept before had the full user
 * agent as their device and browser: each is reduced to a device kind and a browser family, as new ones are.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('support_messages', 'moment')) {
            Schema::table('support_messages', function (Blueprint $table): void {
                $table->string('moment', 16)->nullable()->after('category');
                $table->boolean('maybe_spam')->default(false)->after('status');
            });
        }

        DB::table('support_messages')->whereNotNull('context')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                $context = json_decode((string) $row->context, true);

                if (! is_array($context)) {
                    continue;
                }

                $reduced = DeviceDetails::reduce($context);

                if ($reduced !== $context) {
                    DB::table('support_messages')->where('id', $row->id)->update(['context' => json_encode($reduced)]);
                }
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('support_messages', 'moment')) {
            Schema::table('support_messages', function (Blueprint $table): void {
                $table->dropColumn(['moment', 'maybe_spam']);
            });
        }
    }
};
