<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\Actions\Projects\Owned;
use App\Exceptions\Refusal;
use App\Models\Group;
use App\Models\GroupAssignment;
use App\Models\GroupMember;
use App\Models\GroupSong;
use App\Models\Template;
use App\Models\User;
use App\Support\Accounts\Age;
use App\Support\Ids;
use App\Support\Usage\Plans;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Choir groups (contract/api.md, "Choir groups"): an adult account with Plus makes a group, members join free by its
 * one link, and the director shares songs and assigns parts (docs/pricing-plan.md, Hillel 2026-11-12: "you can learn
 * alone for free but to team up becomes more serious"; whoever starts the group needs Plus, everyone invited joins
 * free). When the director's Plus ends the group pauses: everyone keeps what they have, nobody new joins and nothing
 * new is shared or assigned, until it's back.
 */
final class ManageGroups
{
    public function __construct(private readonly Plans $plans) {}

    public function create(User $user, string $name, bool $adult = false): Group
    {
        return DB::transaction(function () use ($user, $name, $adult): Group {
            Owned::lockAccount($user);
            $this->ensureAdult($user, $adult);

            if (! $this->plans->mayStartShared($user)) {
                throw new Refusal(403, 'plus_required');
            }

            if (Group::query()->where('director_id', $user->id)->count() >= (int) config('usage.groups_per_director')) {
                throw new Refusal(403, 'group_limit');
            }

            $group = new Group(['name' => trim($name)]);
            $group->forceFill(['director_id' => $user->id])->save();
            $director = new GroupMember(['role' => GroupMember::DIRECTOR]);
            $director->forceFill(['group_id' => $group->id, 'user_id' => $user->id])->save();

            return $group;
        });
    }

    /**
     * A group the person directs or sings in; anyone else finds nothing.
     */
    public function find(User $user, string $sqid): Group
    {
        return Group::query()->with('director')->whereSqid($sqid)->whereHas('members', fn ($query) => $query->where('user_id', $user->id))->first()
            ?? throw new Refusal(404, 'not_found');
    }

    /**
     * The director's alone; a member is told so.
     */
    public function directed(User $user, string $sqid): Group
    {
        $group = $this->find($user, $sqid);

        if ($group->director_id !== $user->id) {
            throw new Refusal(403, 'director_only');
        }

        return $group;
    }

    /**
     * Paused: the director's Plus has ended (worked out on read, so it opens again the moment Plus is back).
     */
    public function readOnly(Group $group): bool
    {
        $director = $group->relationLoaded('director') ? $group->director : $group->director()->first();

        return $director !== null && ! $this->plans->sharedOpen($director);
    }

    /**
     * The director's, for a change that shares something new: refused while the group is paused, so the director
     * renews Plus (members are never asked).
     */
    private function directedOpen(User $user, string $sqid): Group
    {
        $group = $this->directed($user, $sqid);

        if ($this->readOnly($group)) {
            throw new Refusal(403, 'plus_required');
        }

        return $group;
    }

    /**
     * The director's changes to the group: a new name, the next rehearsal (null clears it), or both.
     *
     * @param  array{name?: string, rehearsal?: array{date: string, weekly: bool}|null}  $changes
     */
    public function update(User $user, string $sqid, array $changes): Group
    {
        $group = $this->directed($user, $sqid);

        if (array_key_exists('name', $changes)) {
            $group->forceFill(['name' => trim($changes['name'])]);
        }

        if (array_key_exists('rehearsal', $changes)) {
            $group->forceFill(['rehearsal_on' => $changes['rehearsal']['date'] ?? null, 'rehearsal_weekly' => $changes['rehearsal']['weekly'] ?? false]);
        }

        $group->save();

        return $group;
    }

    public function delete(User $user, string $sqid): void
    {
        $group = $this->directed($user, $sqid);
        $group->delete();
    }

    /**
     * A new join link; the old one stops working. It's found by its hash, and kept (encrypted)
     * for the director to copy again.
     */
    public function newLink(User $user, string $sqid): string
    {
        $group = $this->directedOpen($user, $sqid);
        $this->rotateLink($group);

        return (string) $group->linkUrl();
    }

    private function rotateLink(Group $group): void
    {
        $token = Str::random(24);
        $group->forceFill(['link_hash' => hash('sha256', $token), 'link_token' => $token, 'link_created_at' => now()])->save();
    }

    public function byLink(string $token): Group
    {
        return Group::query()->with('director')->where('link_hash', hash('sha256', $token))->first() ?? throw new Refusal(404, 'not_found');
    }

    public function join(User $user, string $token, bool $adult = false): Group
    {
        return DB::transaction(function () use ($user, $token, $adult): Group {
            $found = $this->byLink($token);
            $group = Group::query()->whereKey($found->id)->lockForUpdate()->firstOrFail()->setRelation('director', $found->director);

            if (GroupMember::query()->where('group_id', $group->id)->where('user_id', $user->id)->exists()) {
                return $group;
            }

            $this->ensureAdult($user, $adult);

            // A paused group takes nobody new: a calm note for the singer, nothing to buy (its director renews).
            if ($this->readOnly($group)) {
                throw new Refusal(403, 'shared_paused');
            }

            if (GroupMember::query()->where('group_id', $group->id)->where('role', GroupMember::MEMBER)->count() >= (int) config('usage.group_members')) {
                throw new Refusal(403, 'group_full');
            }

            $member = new GroupMember(['role' => GroupMember::MEMBER]);
            $member->forceFill(['group_id' => $group->id, 'user_id' => $user->id])->save();

            return $group;
        });
    }

    /**
     * The director removes a member, or a member leaves. The director can't leave their own group
     * (they delete it instead). A member removed gets a new link made, so the old one can't bring
     * them straight back; the director sees and copies the new one as before.
     */
    public function removeMember(User $user, string $sqid, string $memberSqid): void
    {
        $group = $this->find($user, $sqid);
        $memberId = Ids::decode($memberSqid);

        if ($memberId === $group->director_id || ($memberId !== $user->id && $group->director_id !== $user->id)) {
            throw new Refusal(403, 'director_only');
        }

        DB::transaction(function () use ($group, $memberId, $user): void {
            GroupAssignment::query()->where('user_id', $memberId ?? 0)->whereIn('group_song_id', GroupSong::query()->where('group_id', $group->id)->select('id'))->delete();
            $removed = GroupMember::query()->where('group_id', $group->id)->where('user_id', $memberId ?? 0)->delete() > 0;

            if ($removed && $memberId !== $user->id && $group->link_hash !== null) {
                $this->rotateLink($group);
            }
        });

    }

    /**
     * Share one of our songs, or one of the director's own templates.
     */
    public function addSong(User $user, string $sqid, string $templateId): Group
    {
        $group = $this->directedOpen($user, $sqid);
        $template = Template::query()->where('slug', $templateId)->whereNull('deleted_at')->where(fn ($query) => $query->whereNull('owner_id')->orWhere('owner_id', $user->id))->whereNotNull('current_version_id')->first()
            ?? throw new Refusal(404, 'not_found');

        GroupSong::query()->firstOrCreate(['group_id' => $group->id, 'template_id' => $template->id]);

        return $group;
    }

    public function removeSong(User $user, string $sqid, string $templateId): void
    {
        $group = $this->directed($user, $sqid);
        GroupSong::query()->where('group_id', $group->id)->whereHas('template', fn ($query) => $query->where('slug', $templateId))->delete();
    }

    /**
     * Who sings which part of one song: the whole list, replacing the last.
     *
     * @param  list<array{memberId: string, partKey: string}>  $assignments
     */
    public function assign(User $user, string $sqid, string $templateId, array $assignments): Group
    {
        $group = $this->directedOpen($user, $sqid);
        $song = GroupSong::query()->with('template.currentVersion')->where('group_id', $group->id)->whereHas('template', fn ($query) => $query->where('slug', $templateId))->first()
            ?? throw new Refusal(404, 'not_found');

        $parts = array_column((array) ($song->template->currentVersion->data['parts'] ?? []), 'key');
        $members = GroupMember::query()->where('group_id', $group->id)->pluck('user_id')->map(fn (mixed $id): int => (int) $id)->all();
        $rows = [];

        foreach ($assignments as $i => $assignment) {
            $memberId = Ids::decode($assignment['memberId']);

            if ($memberId === null || ! in_array($memberId, $members, true)) {
                throw ValidationException::withMessages(["assignments.{$i}.memberId" => __('validation.group_member')]);
            }

            if (! in_array($assignment['partKey'], $parts, true)) {
                throw ValidationException::withMessages(["assignments.{$i}.partKey" => __('validation.template_part')]);
            }

            if (isset($rows[$memberId])) {
                throw ValidationException::withMessages(["assignments.{$i}.memberId" => __('validation.group_one_part')]);
            }

            $rows[$memberId] = ['group_song_id' => $song->id, 'user_id' => $memberId, 'part_key' => $assignment['partKey'], 'created_at' => now(), 'updated_at' => now()];
        }

        DB::transaction(function () use ($song, $rows): void {
            GroupAssignment::query()->where('group_song_id', $song->id)->delete();
            GroupAssignment::query()->insert(array_values($rows));
        });

        return $group;
    }

    /**
     * Choir groups are 18 and over until Hillel decides how minors take part: asked once, kept on
     * the account (`adult: true` on joining or making a group).
     */
    private function ensureAdult(User $user, bool $adult): void
    {
        if ((int) config('usage.group_min_age') < 18 || $user->adult_confirmed_at !== null) {
            return;
        }

        // Their birth month and year say younger: no "I'm 18" changes that.
        Age::ensureMayClaimAdult($user);

        if (! $adult) {
            throw new Refusal(403, 'adult_required');
        }

        $user->forceFill(['adult_confirmed_at' => now()])->save();
    }
}
