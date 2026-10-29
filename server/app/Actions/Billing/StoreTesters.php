<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Models\AuditLog;
use App\Models\User;

/**
 * Store testers (docs/decisions.md, "Plus in the apps, after its review"): the accounts whose
 * sandbox App Store and Google Play purchases give Plus (App Review's demo account, TestFlight
 * testers). Set in admin or by `app:store:tester`, audit-logged, and their Plus recounted at once.
 */
final class StoreTesters
{
    public function __construct(private readonly StoreSubscriptions $stores) {}

    public function set(User $person, bool $tester, ?User $admin, ?string $note = null, ?string $ip = null): User
    {
        $before = $person->store_tester_at !== null;

        if ($before !== $tester) {
            $person->forceFill(['store_tester_at' => $tester ? now() : null])->save();
            AuditLog::record($tester ? 'billing.store_tester_on' : 'billing.store_tester_off', $person, $admin, reason: $note, ip: $ip);
            $this->stores->recount($person);
        }

        return $person;
    }
}
