<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AiVoice;
use App\Models\AuditLog;
use App\Support\Ids;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Records an AI voice's singer's written consent (docs/ai-voices-build-plan.md, Hillel's gate): the day they agreed, the
 * consent text's version, whether a payment is noted, and whether they chose to be named on the label. Only then can what it sings leave Admin (with ai_voices on).
 * A withdrawn voice can't be consented again: a voice coming back is a new voice.
 */
final class RecordAiVoiceConsent extends Command
{
    protected $signature = 'app:ai-voice:consent {voice : its id} {--date= : the day they agreed, YYYY-MM-DD} {--consent-version= : the consent text\'s version they agreed to} {--paid : a payment to them is noted} {--credited : they chose to be named on the label ("AI voice based on <name>\'s singing"); left out, the plain AI label}';

    protected $description = 'Record an AI voice\'s singer\'s consent';

    public function handle(): int
    {
        $voice = AiVoice::query()->find(Ids::decode((string) $this->argument('voice')) ?? 0);
        $version = trim((string) $this->option('consent-version'));

        try {
            $date = Carbon::createFromFormat('!Y-m-d', (string) $this->option('date'));
        } catch (Throwable) {
            $date = null;
        }

        if ($voice === null) {
            $this->error('No such voice.');

            return self::FAILURE;
        }

        if ($voice->beta_singer_id !== null) {
            $this->error('That voice was licensed in the app: its singer manages it there.');

            return self::FAILURE;
        }

        if (! $voice->isActive()) {
            $this->error('That voice is withdrawn: add a new one instead.');

            return self::FAILURE;
        }

        if (! $date instanceof Carbon || $date->isFuture() || $version === '' || mb_strlen($version) > 40) {
            $this->error('Give --date (YYYY-MM-DD, not in the future) and --consent-version (1 to 40 characters).');

            return self::FAILURE;
        }

        $before = ['date' => $voice->consent_date?->toDateString(), 'version' => $voice->consent_version, 'paid' => $voice->payment_noted, 'credited' => $voice->credited];
        $after = ['date' => $date->toDateString(), 'version' => $version, 'paid' => (bool) $this->option('paid'), 'credited' => (bool) $this->option('credited')];
        $voice->forceFill(['consent_date' => $after['date'], 'consent_version' => $version, 'payment_noted' => $after['paid'], 'credited' => $after['credited']])->save();
        AuditLog::record('ai.voice_consent', $voice, null, before: $before, after: $after);
        $this->info("Consent recorded for {$voice->name} ({$voice->sqid()}).");

        return self::SUCCESS;
    }
}
