<?php

declare(strict_types=1);

namespace App\Actions\Templates;

use App\Actions\Ideas\Ideas;
use App\Actions\Projects\Owned;
use App\Enums\TemplateAccess;
use App\Exceptions\Refusal;
use App\Models\AuditLog;
use App\Models\GroupSong;
use App\Models\Template;
use App\Models\TemplateRecording;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Support\Contract\SchemaChecker;
use App\Support\Moderation\TextFilter;
use App\Support\Templates\Catalogue;
use App\Support\Templates\Imported;
use App\Support\Templates\SheetImport;
use App\Support\Templates\TemplateRules;
use App\Support\Trust\TrustLevel;
use App\Support\Usage\Plans;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The templates people make (contract/api.md, "Your own templates"): saved with the UUID their
 * device gave them, one immutable version per save, private or unlisted, within the plan's limit.
 */
final class OwnTemplates
{
    public const MAX_BYTES = 512 * 1024;

    public function __construct(private readonly Plans $plans, private readonly TrustLevel $trust, private readonly TemplateRules $rules) {}

    /**
     * templates.save: make the template, or add a version (the same version again, with the same
     * JSON, answers it as it is).
     *
     * A public template's new version is saved with the licensing declaration again (N3), and a
     * first save may name the template it remixes (`parentId`).
     *
     * @param  array<string, mixed>  $data  the template JSON
     * @param  array{song: string, source?: string|null, ownWork: bool}|null  $declaration
     */
    public function save(User $user, string $uuid, array $data, ?string $visibility, ?bool $libraryOptIn, ?array $declaration = null, ?string $parentId = null, bool $imported = false, ?string $copiedFrom = null): Template
    {
        $this->check($uuid, $data);
        // A copy of an imported song is imported too, with the original's rights confirmation.
        $import = $imported ? ['imported_at' => now(), 'rights_confirmed_at' => now(), 'rights_statement' => (string) config('site.import_rights_version')] : null;

        if ($copiedFrom !== null) {
            $original = $this->openable($user, $copiedFrom);

            // Someone else's imported song (one a choir group shares) stays theirs: only its owner
            // copies it, under the rights they confirmed.
            if ($original->isImported() && $original->owner_id !== $user->id) {
                Imported::refuse();
            }

            // Never the partner's named confirmation: that marks a song we gave (Template::isGiven), and a copy is the
            // person's own, counted toward their plan.
            $import ??= $original->isImported() ? ['imported_at' => $original->imported_at, 'rights_confirmed_at' => $original->rights_confirmed_at, 'rights_statement' => $original->rights_statement] : null;
        }

        // A new song from sheet music only while import is on (staff try it before), never by
        // calling the API around the app's switch (docs/sheet-import-legal.md).
        $existing = Template::query()->where('slug', $uuid)->where('owner_id', $user->id)->whereNotNull('imported_at')->exists();

        if ($imported && $copiedFrom === null && ! $existing && ! app(SheetImport::class)->allowed($user)) {
            throw new Refusal(403, 'import_closed');
        }

        // A repeat infringer (the copyright policy) makes no new songs from sheet music.
        if ($import !== null && Imported::takedowns($user) >= Imported::REPEAT_LIMIT && ! Template::query()->where('slug', $uuid)->where('owner_id', $user->id)->whereNotNull('imported_at')->exists()) {
            throw new Refusal(403, 'import_blocked');
        }

        try {
            return DB::transaction(fn (): Template => $this->store($user, $uuid, $data, $visibility, $libraryOptIn, $declaration, $parentId, $import));
        } catch (UniqueConstraintViolationException) {
            // The same template sent twice at once: the other request made it.
            return DB::transaction(fn (): Template => $this->store($user, $uuid, $data, $visibility, $libraryOptIn, $declaration, $parentId, $import));
        }
    }

    /**
     * A partner's song, given by Admin (docs/partner-songs.md, gap 1): the template the arranger
     * prepared, put into the owner's account as an imported, private song with the rights record
     * from the partner's written confirmation. It then follows sheet import's guardrails (private to
     * its owner and their groups, reportable, taken down on a notice) whether or not import is on,
     * and the owner shares it with their group themselves. Audited.
     *
     * @param  array<string, mixed>  $data  the template JSON
     * @param  array{confirmedBy: string, confirmedAt: string, wording: string, source: string}  $rights
     */
    public function give(User $owner, array $data, array $rights, User $admin, ?string $ip = null): Template
    {
        // A new song of its own: its id is ours, whatever the arranger's file named it.
        $uuid = (string) Str::uuid();
        $data = [...$data, 'id' => $uuid];
        $this->check($uuid, $data);

        // The account it's for has shown it's theirs (its email confirmed), as the partner's records name it.
        if ($owner->email_verified_at === null) {
            throw new Refusal(403, 'email_unverified');
        }

        if (Imported::takedowns($owner) >= Imported::REPEAT_LIMIT) {
            throw new Refusal(403, 'import_blocked');
        }

        $import = [
            'imported_at' => now(),
            'rights_confirmed_at' => Carbon::parse($rights['confirmedAt'])->utc(),
            'rights_statement' => 'partner',
            'rights_confirmed_by' => $rights['confirmedBy'],
            'rights_wording' => $rights['wording'],
            'rights_source' => $rights['source'],
        ];
        $template = DB::transaction(fn (): Template => $this->store($owner, $uuid, $data, 'private', false, null, null, $import));
        AuditLog::record('templates.given', $template, $admin, after: ['owner' => $owner->sqid(), 'title' => $template->title, 'confirmedBy' => $rights['confirmedBy']], ip: $ip);

        return $template;
    }

    public function update(User $user, string $uuid, ?string $visibility, ?bool $libraryOptIn): Template
    {
        return DB::transaction(function () use ($user, $uuid, $visibility, $libraryOptIn): Template {
            Owned::lockAccount($user);
            $template = $this->mine($user, $uuid, lock: true);

            if ($template->isImported() && (in_array($visibility, ['unlisted', 'public'], true) || $libraryOptIn === true)) {
                Imported::refuse();
            }

            // One past the free limit after a downgrade stays where it was: it isn't shared further.
            if ($visibility === 'unlisted' && $template->visibility !== 'unlisted' && $this->plans->templateReadOnly($user, $template)) {
                throw new Refusal(403, 'template_limit');
            }

            // Settings, not the song: which templates stay editable after a downgrade goes by when
            // the song last changed, so this doesn't move it.
            $template->timestamps = false;
            $template->forceFill(array_filter(['visibility' => $visibility, 'library_opt_in' => $libraryOptIn], static fn (mixed $value): bool => $value !== null));

            // Made private or unlisted: out of the library, and off the Featured shelf.
            if ($visibility !== null && $template->isDirty('visibility') && $template->getOriginal('visibility') === 'public') {
                $template->forceFill(['featured_at' => null]);
            }

            $template->save();
            $template->timestamps = true;

            return $template;
        });
    }

    /**
     * Gone for everyone with the link and every group; projects made from it keep their copy.
     */
    public function delete(User $user, string $uuid): void
    {
        [$keys, $id] = DB::transaction(function () use ($user, $uuid): array {
            $template = Template::query()->where('slug', $uuid)->where('owner_id', $user->id)->whereNull('deleted_at')->lockForUpdate()->first();

            if ($template === null) {
                return [[], null];
            }

            $template->forceFill(['deleted_at' => now()])->save();
            GroupSong::query()->where('template_id', $template->id)->delete();
            $recordings = TemplateRecording::query()->where('template_id', $template->id)->get();
            TemplateRecording::query()->where('template_id', $template->id)->delete();

            return [$recordings->flatMap(fn (TemplateRecording $r): array => array_values(array_filter([$r->file_key, $r->upload_key])))->all(), $template->id];
        });

        Storage::disk('private')->delete($keys);

        // Its idea goes too, with every voice on it (after the template's own change is saved).
        if ($id !== null) {
            app(Ideas::class)->removeForTemplates([$id]);
        }
    }

    public function mine(User $user, string $uuid, bool $lock = false): Template
    {
        return Template::query()->where('slug', $uuid)->where('owner_id', $user->id)->whereNull('deleted_at')->when($lock, fn ($query) => $query->lockForUpdate())->first()
            ?? throw new Refusal(404, 'not_found');
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{song: string, source?: string|null, ownWork: bool}|null  $declaration
     * @param  array{imported_at: mixed, rights_confirmed_at: mixed}|null  $import
     */
    private function store(User $user, string $uuid, array $data, ?string $visibility, ?bool $libraryOptIn, ?array $declaration, ?string $parentId, ?array $import = null): Template
    {
        Owned::lockAccount($user);

        if ($this->trust->of($user) === TrustLevel::NEW) {
            throw new Refusal(403, 'email_unverified');
        }

        $template = Template::query()->where('slug', $uuid)->lockForUpdate()->first();

        // Ours, someone else's, or deleted: not one this person can save.
        if ($template !== null && ($template->owner_id !== $user->id || $template->deleted_at !== null)) {
            throw new Refusal(404, 'not_found');
        }

        $sha = hash('sha256', (string) json_encode($data));
        $number = (int) $data['version'];

        if ($template !== null) {
            $existing = TemplateVersion::query()->where('template_id', $template->id)->where('version', $number)->first();

            if ($existing !== null) {
                return $existing->sha256 === $sha ? $template : throw new Refusal(409, 'version_exists');
            }

            if ($number < (int) TemplateVersion::query()->where('template_id', $template->id)->max('version')) {
                throw new Refusal(409, 'version_exists');
            }

            if ($this->plans->templateReadOnly($user, $template)) {
                throw new Refusal(403, 'template_read_only');
            }

            // Staying in the library: declared again. Leaving it: off the Featured shelf.
            if ($template->isPublic() && ($visibility === null || $visibility === 'public') && $declaration === null) {
                throw ValidationException::withMessages(['declaration' => __('validation.template_declaration')]);
            }
        } else {
            $limit = $this->plans->templateLimit($user);

            if ($limit !== null && $this->plans->countedTemplates($user)->count() >= $limit) {
                throw new Refusal(403, 'template_limit');
            }

            $template = new Template(['slug' => $uuid, 'access' => TemplateAccess::Free]);
            $template->forceFill(['owner_id' => $user->id, 'visibility' => $visibility ?? 'private', 'library_opt_in' => $libraryOptIn ?? false, 'parent_id' => $parentId !== null ? $this->parent($user, $parentId)->id : null]);
        }

        // Imported: marked once and for good (a later save never clears it), and never shared beyond its groups.
        if ($import !== null && ! $template->isImported()) {
            $template->forceFill($import);
        }

        if ($template->isImported() && (in_array($visibility ?? $template->visibility, ['unlisted', 'public'], true) || ($libraryOptIn ?? $template->library_opt_in) === true || $parentId !== null)) {
            Imported::refuse();
        }

        if ($template->isPublic() && $visibility !== null && $visibility !== 'public') {
            $template->forceFill(['featured_at' => null]);
        }

        $template->fill(['title' => mb_substr((string) $data['title'], 0, 160)]);
        $template->forceFill(array_filter(['visibility' => $visibility, 'library_opt_in' => $libraryOptIn], static fn (mixed $value): bool => $value !== null))->save();

        $version = new TemplateVersion(['version' => $number]);
        $version->forceFill(['template_id' => $template->id, 'reviewed' => (bool) $data['reviewed'], 'data' => $data, 'sha256' => $sha, 'declaration' => $template->isPublic() && $declaration !== null ? Library::declaration($declaration) : null])->save();
        $template->forceFill(['current_version_id' => $version->id, 'declaration' => $template->isPublic() && $declaration !== null ? Library::declaration($declaration) : $template->declaration])->save();
        Library::facets($template, $version);

        return $template;
    }

    /**
     * The template "Make a copy" copies: one of theirs, or one they may open (our songs they may
     * start, the library's, or a choir group's). Anything else is as if it weren't there.
     */
    private function openable(User $user, string $slug): Template
    {
        $original = Template::query()->where('slug', $slug)->whereNull('deleted_at')->first();
        $catalogue = app(Catalogue::class);
        $allowed = $original !== null && match (true) {
            $original->owner_id === $user->id => true,
            $original->hidden_at !== null => false,
            $original->isOfficial() => $catalogue->mayStart($original, $user),
            default => ($original->isPublic() && app(Library::class)->isOpen()) || $catalogue->inGroup($original, $user),
        };

        return $allowed ? $original : throw new Refusal(404, 'not_found');
    }

    /**
     * What a remix may come from: a public template (not hidden), or one of our songs the person
     * may start. Anything else is as if it weren't there.
     */
    private function parent(User $user, string $parentId): Template
    {
        $parent = Template::query()->where('slug', $parentId)->whereNull('deleted_at')->first();
        $allowed = $parent !== null && ($parent->isOfficial()
            ? app(Catalogue::class)->mayStart($parent, $user)
            : $parent->isPublic() && $parent->hidden_at === null && app(Library::class)->isOpen());

        return $allowed ? $parent : throw new Refusal(404, 'not_found');
    }

    /**
     * The template format (checked as the app checks it), its size, and no links in its words
     * around the music.
     *
     * @param  array<string, mixed>  $data
     */
    private function check(string $uuid, array $data): void
    {
        $json = (string) json_encode($data);

        if (strlen($json) > self::MAX_BYTES) {
            throw ValidationException::withMessages(['template' => __('validation.template_size', ['kb' => intdiv(self::MAX_BYTES, 1024)])]);
        }

        $problems = [...(new SchemaChecker((string) config('templates.schemas')))->check(json_decode($json, false), 'template.json'), ...$this->rules->problems($data)];

        if (($data['id'] ?? null) !== $uuid) {
            $problems[] = __('validation.template_id');
        }

        if ($problems !== []) {
            throw ValidationException::withMessages(['template' => $problems]);
        }

        $credits = array_filter((array) ($data['credits'] ?? []), 'is_string');
        foreach (['title' => (string) $data['title'], ...array_map(static fn (string $credit): string => $credit, $credits)] as $field => $text) {
            if (TextFilter::hasLink($text)) {
                throw ValidationException::withMessages([$field === 'title' ? 'template.title' : 'template.credits.'.$field => __('validation.no_links')]);
            }
        }
    }
}
