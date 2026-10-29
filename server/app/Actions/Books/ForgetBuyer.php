<?php

declare(strict_types=1);

namespace App\Actions\Books;

use App\Models\CoachingPayment;
use App\Models\Engagement;
use App\Models\InvoiceNinjaRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * A buyer's account deleted: our money records keep the amounts and dates, without who it was
 * (docs/decisions.md). Their name leaves the coaching payments' lines, and their name and address
 * leave the book rows we keep (a coach's sale, its fee and refunds; their Plus by card), so one
 * not yet sent to Invoice Ninja goes without them. What Invoice Ninja already holds is the coach's
 * (or Stackapella's) own books. A coach's commission invoices, and Plus by invoice, are a
 * business's records and keep their name.
 */
final class ForgetBuyer
{
    public function handle(User $user): void
    {
        $deleted = __('coaching.deleted_account');
        $from = 'for '.$user->name;
        $to = 'for '.$deleted;
        $scrub = static fn (string $text): string => $user->name === '' ? $text : str_replace($from, $to, $text);

        DB::transaction(function () use ($user, $scrub, $deleted): void {
            $engagements = Engagement::query()->where('singer_id', $user->id)->pluck('coach_id', 'id');
            $payments = CoachingPayment::query()->whereIn('engagement_id', $engagements->keys()->all())->lockForUpdate()->get();

            foreach ($payments as $payment) {
                $payment->forceFill(['description' => $scrub((string) $payment->description)])->save();
            }

            $records = InvoiceNinjaRecord::query()->lockForUpdate()
                ->where(fn ($query) => $query->whereIn('ref', $payments->pluck('ref')->all())->orWhere(fn ($q) => $q->where('user_id', $user->id)->where('kind', InvoiceNinjaRecord::PLUS)))
                ->get();

            foreach ($records as $record) {
                $data = (array) $record->data;
                $data['name'] = $deleted;
                $data['email'] = 'deleted-'.$record->id.'@users.stackapella.invalid';
                $record->forceFill(['data' => self::scrubbed($data, $scrub), 'user_id' => null])->save();
            }

            // Their name in a coach's commission lines ("Take review: ..., for Ana (12.00 USD)").
            $commissions = InvoiceNinjaRecord::query()->lockForUpdate()->where('kind', InvoiceNinjaRecord::COMMISSION)->whereIn('user_id', $engagements->values()->unique()->all())->get();

            foreach ($commissions as $record) {
                $data = (array) $record->data;
                $data['lines'] = self::scrubbed((array) ($data['lines'] ?? []), $scrub);
                $record->forceFill(['data' => $data])->save();
            }
        });
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @param  callable(string): string  $scrub
     * @return array<array-key, mixed>
     */
    private static function scrubbed(array $data, callable $scrub): array
    {
        return array_map(static fn (mixed $value): mixed => match (true) {
            is_string($value) => $scrub($value),
            is_array($value) => self::scrubbed($value, $scrub),
            default => $value,
        }, $data);
    }
}
