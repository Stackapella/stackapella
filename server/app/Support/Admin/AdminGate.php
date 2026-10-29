<?php

declare(strict_types=1);

namespace App\Support\Admin;

use App\Enums\AdminRole;
use App\Models\User;
use Illuminate\Contracts\Session\Session;

/**
 * Who is an admin or an editor: three independent checks, all required (iaw's AdminGate).
 *
 * 1. users.is_admin (or is_editor) is true in the database;
 * 2. the user's google_id is listed in ADMIN_GOOGLE_IDS (or EDITOR_GOOGLE_IDS);
 * 3. at a fresh Google sign-in in THIS session, Google confirmed the email (email_verified) and it
 *    is listed in ADMIN_EMAILS (or EDITOR_EMAILS). The email Google returned is kept in the
 *    session; users.email is never used for this.
 *
 * A remember-me restore never sets the session values in check 3, so it never grants admin.
 * Admins also count as editors.
 */
final class AdminGate
{
    public const SESSION_EMAIL = 'auth.google_verified_email';

    public const SESSION_FRESH = 'auth.fresh_google_login';

    public const SESSION_STARTED_AT = 'admin.started_at';

    public const SESSION_LAST_SEEN_AT = 'admin.last_seen_at';

    public const SESSION_UA = 'admin.ua';

    public function isAdmin(?User $user, ?Session $session): bool
    {
        if ($user === null || $session === null || ! $user->is_admin || $user->isSuspended()) {
            return false;
        }

        return $this->listed($user, $session, 'admin');
    }

    public function isEditor(?User $user, ?Session $session): bool
    {
        if ($this->isAdmin($user, $session)) {
            return true;
        }

        if ($user === null || $session === null || ! $user->is_editor || $user->isSuspended()) {
            return false;
        }

        return $this->listed($user, $session, 'editor');
    }

    public function role(?User $user, ?Session $session): ?AdminRole
    {
        return match (true) {
            $this->isAdmin($user, $session) => AdminRole::Admin,
            $this->isEditor($user, $session) => AdminRole::Editor,
            default => null,
        };
    }

    /**
     * Could this person be an admin or editor once signed in freshly? (Checks 1 and 2, for the
     * sign-in page; check 3 needs Google.)
     */
    public function mayBecomeAdminOrEditor(User $user): bool
    {
        $googleId = strtolower((string) $user->google_id);

        return ($user->is_admin && in_array($googleId, (array) config('admin.admin_google_ids'), true))
            || ($user->is_editor && in_array($googleId, (array) config('admin.editor_google_ids'), true));
    }

    /**
     * The admin session's own limits: started (by EnsureAdmin) in this browser, not idle for
     * longer than admin.idle_timeout and younger than admin.max_lifetime.
     */
    public function sessionIsFresh(Session $session, string $userAgentHash): bool
    {
        $startedAt = $session->get(self::SESSION_STARTED_AT);

        if ($startedAt === null || $session->get(self::SESSION_UA) !== $userAgentHash) {
            return false;
        }

        $now = now()->getTimestamp();
        $lastSeen = (int) $session->get(self::SESSION_LAST_SEEN_AT, $now);

        return $now - $lastSeen <= (int) config('admin.idle_timeout') * 60
            && $now - (int) $startedAt <= (int) config('admin.max_lifetime') * 60;
    }

    public static function userAgentHash(?string $userAgent): string
    {
        return hash('sha256', (string) $userAgent);
    }

    private function listed(User $user, Session $session, string $role): bool
    {
        $googleIds = (array) config("admin.{$role}_google_ids");
        $emails = (array) config("admin.{$role}_emails");

        if (! in_array(strtolower((string) $user->google_id), $googleIds, true)) {
            return false;
        }

        if ($session->get(self::SESSION_FRESH) !== true) {
            return false;
        }

        $email = strtolower((string) $session->get(self::SESSION_EMAIL));

        return $email !== '' && in_array($email, $emails, true);
    }
}
