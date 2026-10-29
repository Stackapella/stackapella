<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Flag;
use App\Models\FlagUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The switches for an attack and the features that ship dark (config/flags.php), read from the
 * `flags` table so changing one never needs a deploy. Scoped per request: the table is read at
 * most once per request or job.
 */
final class Flags
{
    /** @var array<string, string>|null */
    private ?array $values = null;

    /**
     * Every flag with its definition, value and stored row (null if it was never changed), in the
     * order of config/flags.php, for the admin.
     *
     * @return list<array{name: string, definition: array{kind: string, values: list<string>, pauses?: string}, value: string, row: Flag|null}>
     */
    public function all(): array
    {
        $rows = Flag::query()->with('updater')->get()->keyBy('name');
        $this->values = $rows->map(static fn (Flag $flag): string => $flag->value)->all();

        return array_values(array_map(
            fn (string $name): array => ['name' => $name, 'definition' => $this->definition($name), 'value' => $this->value($name), 'row' => $rows->get($name)],
            array_keys((array) config('flags')),
        ));
    }

    public function exists(string $name): bool
    {
        return array_key_exists($name, (array) config('flags'));
    }

    /**
     * The flag's value: one of its `values`, and the first ("off") when it was never set.
     */
    public function value(string $name): string
    {
        $definition = $this->definition($name);
        $this->values ??= Flag::query()->pluck('value', 'name')->map(static fn (mixed $v): string => (string) $v)->all();
        $value = $this->values[$name] ?? null;

        return $value !== null && in_array($value, $definition['values'], true) ? $value : $definition['values'][0];
    }

    public function on(string $name): bool
    {
        return $this->value($name) !== $this->definition($name)['values'][0];
    }

    /**
     * A dark feature: on for everyone, or turned on for this person first.
     */
    public function onFor(string $name, ?User $user): bool
    {
        if ($this->on($name)) {
            return true;
        }

        return $user !== null && FlagUser::query()->where('flag', $name)->where('user_id', $user->getKey())->exists();
    }

    /**
     * The switch that pauses a feature (`pauses` in config/flags.php), if it's on.
     */
    public function pausing(string $feature): ?string
    {
        /** @var array<string, array{kind: string, values: list<string>, pauses?: string}> $all */
        $all = (array) config('flags');

        foreach ($all as $name => $definition) {
            if (($definition['pauses'] ?? null) === $feature && $this->on($name)) {
                return $name;
            }
        }

        return null;
    }

    /**
     * Change a flag, audit-logged with the reason. Callers in admin check sudo and the admin role.
     */
    public function set(string $name, string $value, ?User $actor, string $reason, ?string $ip = null): void
    {
        $definition = $this->definition($name);

        if (! in_array($value, $definition['values'], true)) {
            throw new InvalidArgumentException("[{$value}] isn't a value of the flag [{$name}].");
        }

        $before = $this->value($name);

        DB::transaction(function () use ($name, $value, $actor, $reason, $before, $ip): void {
            // One statement, so two changes at the same moment can't both insert the row.
            Flag::query()->upsert(
                [['name' => $name, 'value' => $value, 'reason' => $reason, 'updated_by' => $actor?->getKey(), 'created_at' => now(), 'updated_at' => now()]],
                ['name'],
                ['value', 'reason', 'updated_by', 'updated_at'],
            );
            AuditLog::record('flag.'.$name, null, $actor, before: ['value' => $before], after: ['value' => $value], reason: $reason, ip: $ip);
        });

        if ($this->values !== null) {
            $this->values[$name] = $value;
        }
    }

    /**
     * @return array{kind: string, values: list<string>, pauses?: string}
     */
    public function definition(string $name): array
    {
        /** @var array<string, array{kind: string, values: list<string>, pauses?: string}> $all */
        $all = (array) config('flags');

        // Looked up by key, never by config('flags.'.$name): a dotted name would reach inside one.
        return $all[$name] ?? throw new InvalidArgumentException("No flag named [{$name}] in config/flags.php.");
    }
}
