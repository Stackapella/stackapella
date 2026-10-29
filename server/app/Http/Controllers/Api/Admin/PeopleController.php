<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Admin\People\ManagePerson;
use App\Actions\Billing\StoreTesters;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\People\ListPeopleRequest;
use App\Http\Requests\Admin\People\ReasonRequest;
use App\Http\Requests\Admin\People\RecoverRequest;
use App\Http\Requests\Admin\People\RefundPlusRequest;
use App\Http\Requests\Admin\People\TrustRequest;
use App\Http\Requests\Admin\StoreTesterRequest;
use App\Http\Resources\Admin\AccountAdminResource;
use App\Models\User;
use App\Services\Billing\BillingGateway;
use App\Support\Http\CursorList;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * admin.people.*: finding someone, and the admin's actions on their account.
 */
final class PeopleController extends Controller
{
    public function index(ListPeopleRequest $request): JsonResponse
    {
        $q = trim((string) $request->validated('q'));
        $like = '%'.addcslashes($q, '%_\\').'%';

        $page = AccountAdminResource::query()
            ->when($q !== '', fn ($query) => $query->where(fn ($where) => $where->where('email', 'like', $like)->orWhere('handle', 'like', $like)->orWhere('name', 'like', $like)))
            ->when($request->validated('suspended') !== null, fn ($query) => $request->boolean('suspended') ? $query->whereNotNull('suspended_at') : $query->whereNull('suspended_at'))
            ->when($request->validated('adult') !== null, fn ($query) => $request->boolean('adult') ? $query->whereNotNull('adult_confirmed_at') : $query->whereNull('adult_confirmed_at')->whereNotNull('birth_year'))
            ->when(is_string($country = $request->validated('country')), fn ($query) => $query->where('country', strtoupper((string) $country)))
            ->when($request->validated('role') === 'tester', fn ($query) => $query->whereNotNull('store_tester_at'))
            ->when($request->validated('role') === 'singer', fn ($query) => $query->whereExists(fn ($q) => $q->selectRaw('1')->from('beta_singers')->whereColumn('beta_singers.user_id', 'users.id')))
            ->when($request->validated('plan') !== null, fn ($query) => $request->validated('plan') === 'plus' ? $query->where(self::plus(...)) : $query->whereNot(self::plus(...)))
            ->when($request->validated('sort') === 'active', fn ($query) => $query->orderByDesc('last_active_at'))
            ->orderByDesc('users.id')
            ->cursorPaginate(50);

        return CursorList::response($page, AccountAdminResource::class, $request);
    }

    /**
     * Plus now, as the database has it (the person page asks the billing state for the source):
     * a demo or coach's Plus, an invoice or a store's day still to come, or a Stripe subscription
     * that's running or still in its paid-for time.
     *
     * @param  Builder<User>  $query
     */
    private static function plus(Builder $query): void
    {
        $now = now();
        $query->where('plan', 'plus')->orWhereNotNull('coach_active_at')->orWhere('plus_paid_until', '>', $now)->orWhere('store_plus_until', '>', $now)
            ->orWhereExists(fn ($q) => $q->selectRaw('1')->from('subscriptions')->whereColumn('subscriptions.user_id', 'users.id')->where('type', 'default')
                ->where(fn ($s) => $s->whereIn('stripe_status', ['active', 'trialing', 'past_due'])->whereNull('ends_at')->orWhere('ends_at', '>', $now)));
    }

    /**
     * How many people last signed in from each country, most first; counts only.
     */
    public function countries(): JsonResponse
    {
        $rows = User::query()->selectRaw('country, count(*) as people')->groupBy('country')->orderByDesc('people')->get();

        return response()->json(['data' => $rows->map(static fn (User $row): array => ['country' => $row->country, 'people' => (int) $row->getAttribute('people')])->values()->all()]);
    }

    public function show(User $person): AccountAdminResource
    {
        return $this->fresh($person);
    }

    /**
     * Their latest Plus payment through Stripe, what a refund comes from: read only when asked, as
     * it asks Stripe twice.
     */
    public function payment(User $person, BillingGateway $billing): JsonResponse
    {
        $payment = $billing->latestPayment($person);

        return response()->json(['data' => ['payment' => $payment === null ? null : [
            'amountCents' => $payment['cents'],
            'refundedCents' => $payment['refunded'],
            'currency' => $payment['currency'],
            'paidAt' => Carbon::createFromTimestamp($payment['paidAt'])->utc()->toIso8601ZuluString(),
        ]]]);
    }

    public function suspend(ReasonRequest $request, User $person, ManagePerson $manage, #[CurrentUser] User $admin): AccountAdminResource
    {
        $manage->suspend($person, $admin, (string) $request->validated('reason'), $request->ip());

        return $this->fresh($person);
    }

    public function refund(RefundPlusRequest $request, User $person, ManagePerson $manage, #[CurrentUser] User $admin): JsonResponse
    {
        return response()->json(['data' => $manage->refundPlus($person, $admin, (int) $request->validated('amountCents'), (string) $request->validated('note'), $request->ip())]);
    }

    public function unsuspend(ReasonRequest $request, User $person, ManagePerson $manage, #[CurrentUser] User $admin): AccountAdminResource
    {
        $manage->unsuspend($person, $admin, (string) $request->validated('reason'), $request->ip());

        return $this->fresh($person);
    }

    public function trust(TrustRequest $request, User $person, ManagePerson $manage, #[CurrentUser] User $admin): AccountAdminResource
    {
        $level = $request->validated('level');
        $manage->setTrust($person, $admin, $level !== null ? (int) $level : null, (string) $request->validated('note'), $request->ip());

        return $this->fresh($person);
    }

    public function storeTester(StoreTesterRequest $request, User $person, StoreTesters $testers, #[CurrentUser] User $admin): AccountAdminResource
    {
        $testers->set($person, $request->boolean('tester'), $admin, is_string($note = $request->validated('note')) ? $note : null, $request->ip());

        return $this->fresh($person);
    }

    /**
     * admin.storeTesters.list: the accounts marked as store testers, most recent first.
     */
    public function storeTesters(): JsonResponse
    {
        return response()->json(['data' => User::query()->whereNotNull('store_tester_at')->latest('store_tester_at')->get()->map(fn (User $user): array => [
            'id' => $user->sqid(),
            'name' => $user->name,
            'email' => $user->email,
            'since' => $user->store_tester_at?->utc()->toIso8601ZuluString(),
        ])->values()->all()]);
    }

    public function recover(RecoverRequest $request, User $person, ManagePerson $manage, #[CurrentUser] User $admin): AccountAdminResource
    {
        $manage->recover($person, $admin, (string) $request->validated('email'), (string) $request->validated('reason'), $request->ip());

        return $this->fresh($person);
    }

    private function fresh(User $person): AccountAdminResource
    {
        return (new AccountAdminResource(AccountAdminResource::query()->whereKey($person->id)->firstOrFail()))->detailed();
    }
}
