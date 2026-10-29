<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Flags;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Turn a switch or dark feature on or off from a console (iaw's app:flag), audit-logged. The same
 * as the admin's switches, for when the admin itself can't be reached.
 */
final class SetFlag extends Command
{
    protected $signature = 'app:flag {name? : A flag from config/flags.php} {value? : One of its values} {--reason= : Why (required to change one)}';

    protected $description = 'Show the flags, or change one';

    public function handle(Flags $flags): int
    {
        $name = $this->argument('name');

        if (! is_string($name)) {
            $this->table(['Flag', 'Kind', 'Value', 'Values'], collect((array) config('flags'))->map(
                fn (array $definition, string $flag): array => [$flag, $definition['kind'], $flags->value($flag), implode(', ', $definition['values'])],
            )->values()->all());

            return self::SUCCESS;
        }

        $value = $this->argument('value');
        $reason = trim((string) $this->option('reason'));

        if (! is_string($value) || $reason === '') {
            $this->error('Give a value and a --reason.');

            return self::FAILURE;
        }

        try {
            $flags->set($name, $value, null, $reason.' (console)');
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("{$name} is now {$value}.");

        return self::SUCCESS;
    }
}
