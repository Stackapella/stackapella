<?php

declare(strict_types=1);

namespace App\Actions\Outreach;

use App\Actions\Admin\People\ManagePerson;
use App\Http\Requests\Admin\Outreach\OutreachRules;
use App\Models\AuditLog;
use App\Models\OutreachMessage;
use App\Models\OutreachSuppression;
use App\Models\OutreachTarget;
use App\Models\User;
use App\Services\Mail\CloudflareTransport;
use App\Support\Outreach\Outreach;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * The organizations (docs/outreach-plan.md, slice 1): one added, one changed, or the research list
 * imported. A contact must come with the page it was published on; a suppressed address is never
 * imported. Admin only, audited.
 */
final class ManageTargets
{
    /**
     * The research file's columns, as the import takes them: an organization's fields, and whether
     * its contact was seen on the page today (`verified: false` keeps it "to research").
     *
     * @return array<string, list<mixed>>
     */
    public static function importRules(): array
    {
        return [
            ...OutreachRules::target(partial: false),
            'verified' => ['sometimes', 'boolean'],
            // The ?ref= tag its drafts' links already carry (o- and at most 14 more); made from the name otherwise.
            // Too long or malformed, it's replaced by a short one made here (never refused).
            'ref' => ['sometimes', 'string', 'max:64'],
            // Written ahead (Hillel's partner offer: marketing drafts each one), reviewed before Send.
            'draft' => ['sometimes', 'array'],
            'draft.subject' => ['required_with:draft', 'string', 'max:200'],
            'draft.body' => ['required_with:draft', 'string', 'max:10000'],
            'followUp' => ['sometimes', 'array'],
            'followUp.subject' => ['required_with:followUp', 'string', 'max:200'],
            'followUp.body' => ['required_with:followUp', 'string', 'max:10000'],
        ];
    }

    /**
     * @param  array{name: string, group: string, country?: string|null, reach?: string|null, fit?: string|null, contact?: string|null, source?: string|null, angle: string, notes?: string|null, ref?: string}  $fields
     */
    public function create(array $fields, User $admin, ?string $ip = null): OutreachTarget
    {
        $target = new OutreachTarget($this->columns($fields));
        $given = $fields['ref'] ?? null;
        $ref = is_string($given) && Outreach::validRef($given) && ! OutreachTarget::query()->where('ref', $given)->exists() ? $given : Outreach::newRef($fields['name']);
        $target->forceFill(['ref' => $ref, 'status' => $this->readiness($target)])->save();
        Cache::forget(Outreach::REFS_CACHE);
        AuditLog::record('outreach.target_added', $target, $admin, after: ['name' => $target->name, 'ref' => $target->ref], ip: $ip);

        return $target;
    }

    /**
     * @param  array<string, mixed>  $fields  name, group, country, reach, fit, contact, source, angle, notes, status, outcome, outcomeNote
     */
    public function update(OutreachTarget $target, array $fields, User $admin, ?string $ip = null): OutreachTarget
    {
        $before = $this->audited($target);
        $target->fill($this->columns($fields));

        if (array_key_exists('status', $fields) && is_string($fields['status'])) {
            $target->status = $fields['status'];
        }

        if (array_key_exists('outcome', $fields)) {
            $target->outcome = is_string($fields['outcome']) ? $fields['outcome'] : null;
        }

        // A "no" is forever: the organization and its address.
        if ($target->outcome === 'declined') {
            $target->status = 'do_not_contact';
        }

        if ($target->status === 'do_not_contact' && $target->contact_email !== null) {
            OutreachSuppression::record($target->contact_email, $target->outcome === 'declined' ? 'no_thanks' : 'manual', $target->id, by: $admin->id);
        }

        if (in_array($target->status, ['to_research', 'ready'], true)) {
            $target->status = $this->readiness($target);
        }

        $target->save();
        AuditLog::record('outreach.target_changed', $target, $admin, before: $before, after: $this->audited($target), ip: $ip);

        return $target;
    }

    /**
     * What the audit log keeps of a change: the contact address only as a fingerprint.
     *
     * @return array<string, mixed>
     */
    private function audited(OutreachTarget $target): array
    {
        return [...$target->only(['status', 'outcome', 'contact_form_url']), 'contact_email' => ManagePerson::fingerprint($target->contact_email)];
    }

    /**
     * The research list: each row added, or filling the gaps of the same organization (name and
     * country) when it's still being researched. A row with a problem is left out and said why.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{added: int, updated: int, skipped: list<array{row: int, name: string|null, reason: string}>}
     */
    public function import(array $rows, User $admin, ?string $ip = null): array
    {
        $added = 0;
        $updated = 0;
        $skipped = [];

        DB::transaction(function () use ($rows, $admin, &$added, &$updated, &$skipped): void {
            foreach ($rows as $i => $row) {
                $name = is_string($row['name'] ?? null) ? $row['name'] : null;
                $check = Validator::make($row, self::importRules());

                if ($check->fails()) {
                    $skipped[] = ['row' => $i + 1, 'name' => $name, 'reason' => (string) $check->errors()->first()];

                    continue;
                }

                /** @var array{name: string, group: string, country?: string|null, reach?: string|null, fit?: string|null, contact?: string|null, source?: string|null, angle: string} $row */
                $columns = $this->columns($row);

                $email = $columns['contact_email'] ?? null;

                if (OutreachSuppression::blocks(is_string($email) ? $email : null)) {
                    $skipped[] = ['row' => $i + 1, 'name' => $name, 'reason' => __('outreach.import.suppressed')];

                    continue;
                }

                $country = isset($columns['country']) ? $columns['country'] : null;
                $existing = OutreachTarget::query()->where('name', $row['name'])->where('country', $country)->first();

                $unverified = ($row['verified'] ?? true) === false;

                if ($existing === null) {
                    $made = $this->create($row, $admin);

                    $this->drafts($made, $row);

                    if ($unverified) {
                        $made->forceFill(['status' => 'to_research', 'notes' => trim(__('outreach.import.unverified').' '.$made->notes)])->save();
                    }

                    $added++;

                    continue;
                }

                // Anything not emailed yet takes the newer research and drafts (a fact check's fixes).
                if (! in_array($existing->status, ['to_research', 'ready', 'drafted'], true) || $existing->messages()->whereNotNull('sent_at')->exists()) {
                    $skipped[] = ['row' => $i + 1, 'name' => $name, 'reason' => __('outreach.import.in_progress')];

                    continue;
                }

                $existing->fill(array_filter($columns, static fn (mixed $v): bool => $v !== null && $v !== ''));
                $existing->status = $unverified ? 'to_research' : ($existing->status === 'drafted' ? 'drafted' : $this->readiness($existing));
                $existing->save();
                $this->drafts($existing, $row);
                $updated++;
            }
        });

        AuditLog::record('outreach.imported', null, $admin, after: ['added' => $added, 'updated' => $updated, 'skipped' => count($skipped)], ip: $ip);

        return ['added' => $added, 'updated' => $updated, 'skipped' => $skipped];
    }

    /**
     * A row's drafts written ahead: its first email and its follow-up, each replacing an unsent one
     * of its kind. Never sent from here: Hillel reviews each and presses Send.
     *
     * @param  array<string, mixed>  $row
     */
    private function drafts(OutreachTarget $target, array $row): void
    {
        foreach (['draft' => OutreachMessage::FIRST, 'followUp' => OutreachMessage::FOLLOW_UP] as $key => $kind) {
            $draft = $row[$key] ?? null;

            if (! is_array($draft) || $target->messages()->where('kind', $kind)->whereNotNull('sent_at')->exists()) {
                continue;
            }

            $target->messages()->where('kind', $kind)->where('status', 'draft')->delete();
            $message = new OutreachMessage(['kind' => $kind, 'subject' => CloudflareTransport::oneLine((string) $draft['subject']), 'body' => (string) $draft['body']]);
            $message->forceFill(['outreach_target_id' => $target->id, 'status' => 'draft'])->save();
        }

        if (isset($row['draft']) && $target->status === 'ready') {
            $target->forceFill(['status' => 'drafted'])->save();
        }
    }

    /**
     * Ready once there's a published contact (an address or a form) and the page it came from.
     */
    private function readiness(OutreachTarget $target): string
    {
        return ($target->contact_email !== null || $target->contact_form_url !== null) && $target->source_url !== null ? 'ready' : 'to_research';
    }

    /**
     * The request's or the file's words as columns: a contact is an address or a form's URL.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, string|list<string>|null>
     */
    private function columns(array $fields): array
    {
        $columns = [];
        $map = ['name' => 'name', 'group' => 'group', 'reach' => 'reach', 'fit' => 'fit', 'angle' => 'angle', 'notes' => 'notes', 'outcomeNote' => 'outcome_note', 'source' => 'source_url', 'sendBy' => 'send_by', 'followUpNotBefore' => 'follow_up_not_before', 'sendNotBefore' => 'send_not_before', 'timingNote' => 'timing_note'];

        foreach ($map as $key => $column) {
            if (array_key_exists($key, $fields)) {
                $value = is_string($fields[$key]) ? trim($fields[$key]) : null;
                $columns[$column] = $value === '' ? null : $value;
            }
        }

        if (array_key_exists('group', $columns) && $columns['group'] !== null) {
            $columns['group'] = strtolower($columns['group']);
        }

        if (array_key_exists('country', $fields)) {
            $columns['country'] = is_string($fields['country']) && $fields['country'] !== '' ? strtoupper($fields['country']) : null;
        }

        if (array_key_exists('research', $fields)) {
            $research = is_array($fields['research']) ? $fields['research'] : [];
            $note = is_string($research['note'] ?? null) ? trim($research['note']) : '';
            $links = array_values(array_filter((array) ($research['links'] ?? []), 'is_string'));
            $columns['research_note'] = $note !== '' ? $note : null;
            $columns['research_links'] = $links !== [] ? $links : null;
        }

        if (array_key_exists('contact', $fields)) {
            $contact = is_string($fields['contact']) ? trim($fields['contact']) : '';
            $email = filter_var($contact, FILTER_VALIDATE_EMAIL) !== false;
            $columns['contact_email'] = $email ? strtolower($contact) : null;
            $columns['contact_form_url'] = ! $email && $contact !== '' ? $contact : null;
        }

        return $columns;
    }
}
