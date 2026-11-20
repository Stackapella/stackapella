<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AiVoice;
use App\Models\AuditLog;
use Illuminate\Console\Command;

/**
 * Adds an AI guide voice (docs/ai-voices-build-plan.md, phase 1: no create screen in Admin). Its guides stay in Admin
 * until its singer's consent is recorded (app:ai-voice:consent). Prints the id the worker renders with.
 */
final class AddAiVoice extends Command
{
    protected $signature = 'app:ai-voice:add {name : the label name Admin shows, e.g. "Alto A"} {--based-on= : whose singing it was learned from, as the label names them ("AI voice based on <name>\'s singing"); leave out for a voice not based on a named person}';

    protected $description = 'Add an AI guide voice (its consent is recorded separately)';

    public function handle(): int
    {
        $name = trim((string) $this->argument('name'));
        $basedOn = is_string($this->option('based-on')) ? trim($this->option('based-on')) : null;

        if ($name === '' || mb_strlen($name) > 80 || ($basedOn !== null && ($basedOn === '' || mb_strlen($basedOn) > 120))) {
            $this->error('The name is 1 to 80 characters; --based-on, if given, 1 to 120.');

            return self::FAILURE;
        }

        $voice = AiVoice::query()->create(['name' => $name, 'based_on' => $basedOn]);
        AuditLog::record('ai.voice_added', $voice, null, after: ['name' => $name, 'basedOn' => $basedOn]);
        $this->info("Added {$voice->name}: {$voice->sqid()}. Its guides stay in Admin until its consent is recorded (app:ai-voice:consent).");

        return self::SUCCESS;
    }
}
