<?php

declare(strict_types=1);

namespace App\Actions\Templates;

use App\Actions\Ideas\Ideas;
use App\Actions\Projects\Owned;
use App\Enums\TemplateAccess;
use App\Exceptions\Refusal;
use App\Models\GroupSong;
use App\Models\Template;
use App\Models\TemplateRecording;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Support\Contract\SchemaChecker;
use App\Support\Moderation\TextFilter;
use App\Support\Templates\Catalogue;
use App\Support\Templates\TemplateRules;
use App\Support\Trust\TrustLevel;
use App\Support\Usage\Plans;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * The templates people make (contract/api.md, "Your own templates"): saved with the UUID their
 * device gave them, one immutable version per save, private or unlisted, within the plan's limit.
 */
final class OwnTemplates
{
    private const MAX_BYTES = 512 * 1024;

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
    public function save(User $user, string $uuid, array $data, ?string $visibility, ?bool $libraryOptIn, ?array $declaration = null, ?string $parentId = null): Template
    {
        $this->check($uuid, $data);

        try {
            return DB::transaction(fn (): Template => $this->store($user, $uuid, $data, $visibility, $libraryOptIn, $declaration, $parentId));
        } catch (UniqueConstraintViolationException) {
            // The same template sent twice at once: the other request made it.
            return DB::transaction(fn (): Template => $this->store($user, $uuid, $data, $visibility, $libraryOptIn, $declaration, $parentId));
        }
    }

    public function update(User $user, string $uuid, ?string $visibility, ?bool $libraryOptIn): Template
    {
        return DB::transaction(function () use ($user, $uuid, $visibility, $libraryOptIn): Template {
            Owned::lockAccount($user);
            $template = $this->mine($user, $uuid, lock: true);

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
     */
    private function store(User $user, string $uuid, array $data, ?string $visibility, ?bool $libraryOptIn, ?array $declaration, ?string $parentId): Template
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
