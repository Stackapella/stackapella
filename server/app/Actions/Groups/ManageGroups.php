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
 * Choir groups (contract/api.md, "Choir groups"): a director with Plus makes a group, members join
 * by its one link, and the director shares songs and assigns parts. If the director's Plus ends,
 * the group stays but takes nothing new.
 */
final class ManageGroups
{
    public function __construct(private readonly Plans $plans) {}

    public function create(User $user, string $name, bool $adult = false): Group
    {
        return DB::transaction(function () use ($user, $name, $adult): Group {
            Owned::lockAccount($user);
            $this->ensureAdult($user, $adult);

            if (! $this->plans->isPlus($user)) {
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
    public function directed(User $user, string $sqid, bool $needsPlus = false): Group
    {
        $group = $this->find($user, $sqid);

        if ($group->director_id !== $user->id) {
            throw new Refusal(403, 'director_only');
        }

        if ($needsPlus && $this->readOnly($group)) {
            throw new Refusal(403, 'plus_required');
        }

        return $group;
    }

    public function readOnly(Group $group): bool
    {
        return ! $this->plans->isPlus($group->director);
    }

    public function rename(User $user, string $sqid, string $name): Group
    {
        $group = $this->directed($user, $sqid);
        $group->forceFill(['name' => trim($name)])->save();

        return $group;
    }

    public function delete(User $user, string $sqid): void
    {
        $group = $this->directed($user, $sqid);
        $members = GroupMember::query()->where('group_id', $group->id)->pluck('user_id')->all();
        $group->delete();
        $this->refresh($members);
    }

    /**
     * Group songs keep members' Plus-song projects editable: when that changes, their devices
     * hear about it in their next pull (Plans::readOnlyChanged), as at a downgrade.
     *
     * @param  iterable<int|string>  $userIds
     */
    public function refresh(iterable $userIds): void
    {
        foreach (User::query()->with('subscriptions')->whereKey(collect($userIds)->all())->get() as $member) {
            if (! $this->plans->isPlus($member)) {
                $this->plans->readOnlyChanged($member);
            }
        }
    }

    /**
     * A new join link; the old one stops working. It's found by its hash, and kept (encrypted)
     * for the director to copy again.
     */
    public function newLink(User $user, string $sqid): string
    {
        $group = $this->directed($user, $sqid, needsPlus: true);
        $token = Str::random(24);
        $group->forceFill(['link_hash' => hash('sha256', $token), 'link_token' => $token, 'link_created_at' => now()])->save();

        return (string) $group->linkUrl();
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

            if ($this->readOnly($group)) {
                throw new Refusal(403, 'plus_required');
            }

            $this->ensureAdult($user, $adult);

            if (GroupMember::query()->where('group_id', $group->id)->where('role', GroupMember::MEMBER)->count() >= (int) config('usage.group_members')) {
                throw new Refusal(403, 'group_full');
            }

            $member = new GroupMember(['role' => GroupMember::MEMBER]);
            $member->forceFill(['group_id' => $group->id, 'user_id' => $user->id])->save();
            $this->refresh([$user->id]);

            return $group;
        });
    }

    /**
     * The director removes a member, or a member leaves. The director can't leave their own group
     * (they delete it instead).
     */
    public function removeMember(User $user, string $sqid, string $memberSqid): void
    {
        $group = $this->find($user, $sqid);
        $memberId = Ids::decode($memberSqid);

        if ($memberId === $group->director_id || ($memberId !== $user->id && $group->director_id !== $user->id)) {
            throw new Refusal(403, 'director_only');
        }

        DB::transaction(function () use ($group, $memberId): void {
            GroupAssignment::query()->where('user_id', $memberId ?? 0)->whereIn('group_song_id', GroupSong::query()->where('group_id', $group->id)->select('id'))->delete();
            GroupMember::query()->where('group_id', $group->id)->where('user_id', $memberId ?? 0)->delete();
        });

        $this->refresh([$memberId ?? 0]);
    }

    /**
     * Share one of our songs, or one of the director's own templates.
     */
    public function addSong(User $user, string $sqid, string $templateId): Group
    {
        $group = $this->directed($user, $sqid, needsPlus: true);
        $template = Template::query()->where('slug', $templateId)->whereNull('deleted_at')->where(fn ($query) => $query->whereNull('owner_id')->orWhere('owner_id', $user->id))->whereNotNull('current_version_id')->first()
            ?? throw new Refusal(404, 'not_found');

        GroupSong::query()->firstOrCreate(['group_id' => $group->id, 'template_id' => $template->id]);
        $this->refresh(GroupMember::query()->where('group_id', $group->id)->pluck('user_id'));

        return $group;
    }

    public function removeSong(User $user, string $sqid, string $templateId): void
    {
        $group = $this->directed($user, $sqid);
        GroupSong::query()->where('group_id', $group->id)->whereHas('template', fn ($query) => $query->where('slug', $templateId))->delete();
        $this->refresh(GroupMember::query()->where('group_id', $group->id)->pluck('user_id'));
    }

    /**
     * Who sings which part of one song: the whole list, replacing the last.
     *
     * @param  list<array{memberId: string, partKey: string}>  $assignments
     */
    public function assign(User $user, string $sqid, string $templateId, array $assignments): Group
    {
        $group = $this->directed($user, $sqid, needsPlus: true);
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
