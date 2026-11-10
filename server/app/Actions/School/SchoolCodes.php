<?php

declare(strict_types=1);

namespace App\Actions\School;

use App\Exceptions\Refusal;
use App\Mail\Account\ClassCodesReady;
use App\Models\AuditLog;
use App\Models\OutreachTarget;
use App\Models\SchoolClass;
use App\Models\SchoolRedemption;
use App\Models\SchoolTeacher;
use App\Models\User;
use App\Support\Flags;
use App\Support\Gallery\Gallery;
use App\Support\Stats\ReportDay;
use App\Support\Usage\Plans;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * The school offer (docs/school-offer-plan.md): a teacher's class codes, and a student's free year
 * of Plus from one. Teacher-led, with no groups: the teacher sees seats used, never who.
 */
final class SchoolCodes
{
    /** A code takes students for this many days after it's made. */
    public const OPEN_DAYS = 120;

    /** A student's free year. */
    public const YEAR_DAYS = 365;

    public const MAX_SEATS = 60;

    /** At most this many open codes, and open seats, per teacher at a time. */
    public const OPEN_CODES = 5;

    public const OPEN_SEATS = 150;

    public function __construct(private readonly Flags $flags, private readonly Plans $plans) {}

    public function ensureOpen(): void
    {
        if (! $this->flags->on('schools')) {
            throw new Refusal(404, 'not_found');
        }
    }

    /**
     * The teacher's own codes, newest first, each with its seats used.
     *
     * @return Collection<int, SchoolClass>
     */
    public function mine(User $teacher): Collection
    {
        $this->ensureOpen();

        return SchoolClass::query()->where('teacher_id', $teacher->id)->with('teacherRecord')->withCount(['redemptions as today_count' => fn ($q) => $q->where('created_at', '>=', ReportDay::start(ReportDay::today()))])->latest('id')->get();
    }

    public function create(User $teacher, string $name, int $seats): SchoolClass
    {
        $this->ensureOpen();
        $this->ensureTeacher($teacher);

        return DB::transaction(function () use ($teacher, $name, $seats): SchoolClass {
            // One at a time per teacher, so two quick taps can't pass the limits together.
            User::query()->whereKey($teacher->id)->lockForUpdate()->first();
            // A teacher waits for Admin's approval before their codes work (Hillel, 2026-11-01).
            $record = SchoolTeacher::query()->firstOrCreate(['user_id' => $teacher->id], ['seat_allowance' => SchoolTeacher::ALLOWANCE]);
            $open = SchoolClass::query()->where('teacher_id', $teacher->id)->whereNull('closed_at')->where('expires_at', '>', now())->get(['id', 'seats']);

            if ($open->count() >= self::OPEN_CODES || $open->sum('seats') + $seats > self::OPEN_SEATS) {
                throw new Refusal(409, 'school_too_many_codes');
            }

            if (self::seatsTaken($teacher->id) + $seats > $record->seat_allowance) {
                throw new Refusal(409, 'school_seat_allowance');
            }

            for ($try = 0; ; $try++) {
                try {
                    $class = new SchoolClass;
                    $class->forceFill(['teacher_id' => $teacher->id, 'name' => $name, 'code' => self::newCode(), 'seats' => $seats, 'expires_at' => now()->addDays(self::OPEN_DAYS)])->save();

                    return $class->refresh()->load('teacherRecord');
                } catch (QueryException $e) {
                    // A clash with an existing code (31^8 of them): draw again, a few times at most.
                    if ($try >= 4 || ! str_contains($e->getMessage(), 'Duplicate')) {
                        throw $e;
                    }
                }
            }
        });
    }

    /** A partner code's seats at most (a partner's membership), and its life in days. */
    public const PARTNER_SEATS = 100000;

    public const PARTNER_DAYS = 365;

    /**
     * A partner code (docs/outreach-plan.md, "The partner offer"): Admin's, for an approved partner
     * organization's members, with the seats it needs and no lifetime cap. Each member redeems once
     * for a year, under-18s under the school rules, and the partner learns only the seats used. Its
     * outreach organization (when there is one) becomes a partner. Audited.
     */
    public function createPartner(string $name, int $seats, int $days, ?OutreachTarget $target, User $admin, ?string $ip = null): SchoolClass
    {
        $this->ensureOpen();

        for ($try = 0; ; $try++) {
            try {
                $class = new SchoolClass;
                $class->forceFill(['kind' => SchoolClass::PARTNER, 'teacher_id' => null, 'outreach_target_id' => $target?->id, 'name' => $name, 'code' => self::newCode(), 'seats' => $seats, 'expires_at' => now()->addDays($days)])->save();

                break;
            } catch (QueryException $e) {
                if ($try >= 4 || ! str_contains($e->getMessage(), 'Duplicate')) {
                    throw $e;
                }
            }
        }

        $target?->forceFill(['status' => 'partner', 'outcome' => 'joined'])->save();
        AuditLog::record('school.partner_code', $class, $admin, after: ['name' => $name, 'seats' => $seats, 'days' => $days, 'target' => $target?->ref], ip: $ip);

        return $class->refresh();
    }

    /**
     * Admin changes a partner code's seats, never below those used. Audited.
     */
    public function partnerSeats(SchoolClass $class, int $seats, User $admin, ?string $reason = null): SchoolClass
    {
        if ($class->kind !== SchoolClass::PARTNER) {
            throw new Refusal(409, 'school_not_partner');
        }

        if ($seats < $class->used) {
            throw new Refusal(409, 'school_seats_below_used');
        }

        $before = $class->seats;
        $class->forceFill(['seats' => $seats])->save();
        AuditLog::record('school.partner_seats', $class, $admin, before: ['seats' => $before], after: ['seats' => $seats], reason: $reason);

        return $class;
    }

    /**
     * Renames it, changes its seats (never below the seats used), or closes it.
     */
    public function update(User $teacher, SchoolClass $class, ?string $name, ?int $seats, bool $close): SchoolClass
    {
        $this->ensureOpen();

        if ($class->teacher_id !== $teacher->id) {
            throw new Refusal(404, 'not_found');
        }

        return DB::transaction(function () use ($teacher, $class, $name, $seats, $close): SchoolClass {
            // The teacher's row first, as create() takes it, so seat changes and new codes can't pass
            // the open-seat limit together.
            User::query()->whereKey($teacher->id)->lockForUpdate()->first();
            $class = SchoolClass::query()->whereKey($class->id)->lockForUpdate()->firstOrFail();

            if ($seats !== null && $seats !== $class->seats) {
                // Against the count the teacher sees (as of the start of today), so the refusal can't
                // tell them who joined since; a code set below its live count just stops taking more.
                if ($seats < $class->usedForTeacher()) {
                    throw new Refusal(409, 'school_seats_below_used');
                }

                $others = SchoolClass::query()->where('teacher_id', $class->teacher_id)->whereKeyNot($class->id)->whereNull('closed_at')->where('expires_at', '>', now())->sum('seats');

                if ($class->closed_at === null && (int) $others + $seats > self::OPEN_SEATS) {
                    throw new Refusal(409, 'school_too_many_codes');
                }

                $allowance = SchoolTeacher::query()->where('user_id', $class->teacher_id)->value('seat_allowance') ?? SchoolTeacher::ALLOWANCE;

                if ($class->closed_at === null && self::seatsTaken((int) $class->teacher_id) - $class->allowanceTaken() + $seats > (int) $allowance) {
                    throw new Refusal(409, 'school_seat_allowance');
                }

                $class->seats = $seats;
            }

            if ($name !== null) {
                $class->name = $name;
            }

            if ($close && $class->closed_at === null) {
                $class->forceFill(['closed_at' => now(), 'closed_by' => 'teacher']);
            }

            $class->save();

            return $class->load('teacherRecord');
        });
    }

    /**
     * A student redeems a code: Plus free for a year from now, once per account.
     */
    public function redeem(User $student, string $code): SchoolRedemption
    {
        $this->ensureOpen();
        $code = SchoolClass::normalise($code);

        return DB::transaction(function () use ($student, $code): SchoolRedemption {
            // The student's row, then the class's (the seat count's lock): one student can't redeem two
            // codes at once, and two students can't take the last seat together.
            User::query()->whereKey($student->id)->lockForUpdate()->first();
            $class = strlen($code) === 8 ? SchoolClass::query()->with('teacherRecord')->where('code', $code)->lockForUpdate()->first() : null;
            $status = $class?->status();

            // A teacher not approved yet reads as no code at all.
            if ($class === null || in_array($status, ['closed', 'expired', 'pending'], true)) {
                throw new Refusal(404, 'school_code_unknown');
            }

            // A teacher's codes are for their students, never a year of Plus for themselves.
            if ($class->teacher_id === $student->id) {
                throw new Refusal(409, 'school_own_code');
            }

            if (SchoolRedemption::query()->where('user_id', $student->id)->exists()) {
                throw new Refusal(409, 'school_already_redeemed');
            }

            if ($this->plans->isPlus($student)) {
                throw new Refusal(409, 'school_already_plus');
            }

            if ($status === 'full') {
                throw new Refusal(409, 'school_code_full');
            }

            $until = now()->addDays(self::YEAR_DAYS);
            $redemption = new SchoolRedemption;
            $redemption->forceFill(['school_class_id' => $class->id, 'user_id' => $student->id, 'plus_until' => $until])->save();
            $class->increment('used');
            $student->forceFill(['school_plus_until' => $until])->save();

            return $redemption;
        });
    }

    /**
     * Admin: every code, newest first, with its teacher and seats used.
     *
     * @return Collection<int, SchoolClass>
     */
    public function all(): Collection
    {
        return SchoolClass::query()->with(['teacher:id,name,email', 'teacherRecord', 'partner'])->latest('id')->limit(500)->get();
    }

    public function close(SchoolClass $class, User $admin, ?string $reason = null): SchoolClass
    {
        if ($class->closed_at === null) {
            $class->forceFill(['closed_at' => now(), 'closed_by' => 'admin'])->save();
            AuditLog::record('school.code_closed', $class, $admin, after: ['code' => $class->code], reason: $reason);
        }

        return $class;
    }

    /**
     * Admin's End: a student's school year stops now (misuse). The seat stays used.
     */
    public function end(SchoolRedemption $redemption, User $admin, ?string $reason = null): SchoolRedemption
    {
        if ($redemption->ended_at === null) {
            DB::transaction(function () use ($redemption): void {
                $redemption->forceFill(['ended_at' => now()])->save();
                $redemption->user->forceFill(['school_plus_until' => null])->save();
            });
            AuditLog::record('school.year_ended', $redemption->user, $admin, after: ['class' => $redemption->school_class_id], reason: $reason);
        }

        return $redemption;
    }

    /**
     * Admin approves a teacher (or takes it back), or sets their seat allowance. Audited.
     */
    public function teacher(User $teacher, User $admin, ?bool $approved, ?int $allowance, ?string $reason): SchoolTeacher
    {
        $record = SchoolTeacher::query()->firstOrCreate(['user_id' => $teacher->id], ['seat_allowance' => SchoolTeacher::ALLOWANCE]);
        $before = ['approved' => $record->approved_at !== null, 'seatAllowance' => $record->seat_allowance];

        if ($approved !== null) {
            $record->forceFill($approved ? ['approved_at' => $record->approved_at ?? now(), 'approved_by' => $admin->id] : ['approved_at' => null, 'approved_by' => null]);
        }

        if ($allowance !== null) {
            $record->seat_allowance = $allowance;
        }

        // Approved for the first time: they hear once that their codes work.
        $tell = $record->approved_at !== null && $record->told_at === null && $teacher->email !== null && $teacher->email_verified_at !== null;

        if ($tell) {
            $record->told_at = now();
        }

        $record->save();

        if ($tell) {
            Mail::to((string) $teacher->email)->queue((new ClassCodesReady($teacher->name))->afterCommit());
        }

        AuditLog::record('school.teacher', $teacher, $admin, before: $before, after: ['approved' => $record->approved_at !== null, 'seatAllowance' => $record->seat_allowance], reason: $reason);

        return $record;
    }

    /** Seats a teacher's codes take of their allowance: all of an open code's, the used of a closed one's. */
    public static function seatsTaken(int $teacherId): int
    {
        return (int) SchoolClass::query()->where('teacher_id', $teacherId)->get(['seats', 'used', 'closed_at', 'expires_at'])->sum(fn (SchoolClass $c): int => $c->allowanceTaken());
    }

    private function ensureTeacher(User $teacher): void
    {
        if ($teacher->email_verified_at === null) {
            throw new Refusal(403, 'email_unverified');
        }

        Gallery::ensureAdult($teacher);
    }

    private static function newCode(): string
    {
        $code = '';

        for ($i = 0; $i < 8; $i++) {
            $code .= SchoolClass::ALPHABET[random_int(0, strlen(SchoolClass::ALPHABET) - 1)];
        }

        return $code;
    }
}
