<?php

declare(strict_types=1);

namespace App\Services\InvoiceNinja;

use App\Models\InvoiceNinjaConnection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * An Invoice Ninja company as its API behaves, with nothing leaving the server: for the tests and,
 * locally (`INVOICE_NINJA_FAKE=true`), the demo; never in production. A token starting "in-" is
 * accepted. What it holds is kept in the cache, per token, so it lasts between requests locally.
 *
 * Tests steer it: `failNext()` makes the next call fail as Invoice Ninja would, and
 * `loseAnswerOfNext()` makes it do the work but fail to say so (a timeout after the fact).
 *
 * @phpstan-type Company array{clients: array<string, array{name: string, email: string, currency: string}>, invoices: array<string, array{clientId: string, poNumber: string, lines: list<array{description: string, cents: int}>, emailed: bool, date: string}>, payments: array<string, array{clientId: string, invoiceId: string, cents: int, refunded: int, reference: string, completed?: bool}>, vendors: array<string, string>, expenses: array<string, array{vendorId: string, cents: int, currency: string, reference: string, note: string}>, webhooks: array<string, array{url: string, secret: string}>}
 */
final class FakeInvoiceNinja implements InvoiceNinja
{
    private const KEY = 'fake-invoice-ninja';

    public function company(InvoiceNinjaConnection $connection): array
    {
        $this->step($connection);

        return ['id' => 'Co'.substr(hash('sha256', $connection->token), 0, 6), 'name' => str_contains($connection->token, 'platform') ? 'Stackapella' : 'Sarah\'s Studio'];
    }

    public function findClient(InvoiceNinjaConnection $connection, string $email, string $currency): ?string
    {
        $this->step($connection);

        foreach ($this->of($connection)['clients'] as $id => $client) {
            if ($client['email'] === $email && $client['currency'] === strtolower($currency)) {
                return $id;
            }
        }

        return null;
    }

    public function createClient(InvoiceNinjaConnection $connection, string $name, string $email, string $currency): string
    {
        return $this->make($connection, 'clients', ['name' => $name, 'email' => $email, 'currency' => strtolower($currency)]);
    }

    public function findInvoice(InvoiceNinjaConnection $connection, string $poNumber): ?array
    {
        $this->step($connection);
        $company = $this->of($connection);

        foreach ($company['invoices'] as $id => $invoice) {
            if ($invoice['poNumber'] === $poNumber) {
                $payment = collect($company['payments'])->filter(fn (array $p): bool => $p['invoiceId'] === $id)->keys()->first();

                return ['id' => $id, 'paymentId' => is_string($payment) ? $payment : null];
            }
        }

        return null;
    }

    public function createInvoice(InvoiceNinjaConnection $connection, string $clientId, array $lines, string $poNumber, string $date, bool $sendEmail): string
    {
        return $this->make($connection, 'invoices', ['clientId' => $clientId, 'poNumber' => $poNumber, 'lines' => $lines, 'emailed' => $sendEmail, 'date' => $date]);
    }

    public function createPayment(InvoiceNinjaConnection $connection, string $clientId, string $invoiceId, int $cents, string $date, string $reference): string
    {
        return $this->make($connection, 'payments', ['clientId' => $clientId, 'invoiceId' => $invoiceId, 'cents' => $cents, 'refunded' => 0, 'reference' => $reference]);
    }

    public function refundedOn(InvoiceNinjaConnection $connection, string $paymentId): int
    {
        $this->step($connection);

        return (int) ($this->of($connection)['payments'][$paymentId]['refunded'] ?? 0);
    }

    public function refund(InvoiceNinjaConnection $connection, string $paymentId, string $invoiceId, int $cents, string $date): void
    {
        $lost = false;
        $this->step($connection, function (array &$company) use ($paymentId, $cents): void {
            if (! isset($company['payments'][$paymentId])) {
                throw new InvoiceNinjaError(InvoiceNinjaError::REFUSED, 'No such payment.');
            }

            $company['payments'][$paymentId]['refunded'] += $cents;
        }, $lost, 'refunds');

        if ($lost) {
            throw new InvoiceNinjaError(InvoiceNinjaError::UNREACHABLE, 'Timed out (fake).');
        }
    }

    public function vendor(InvoiceNinjaConnection $connection, string $name): string
    {
        $this->step($connection);
        $found = array_search($name, $this->of($connection)['vendors'], true);

        return is_string($found) ? $found : $this->make($connection, 'vendors', $name);
    }

    public function findExpense(InvoiceNinjaConnection $connection, string $reference): ?string
    {
        $this->step($connection);

        foreach ($this->of($connection)['expenses'] as $id => $expense) {
            if ($expense['reference'] === $reference) {
                return $id;
            }
        }

        return null;
    }

    public function createExpense(InvoiceNinjaConnection $connection, string $vendorId, int $cents, string $currency, string $date, string $note, string $reference): string
    {
        return $this->make($connection, 'expenses', ['vendorId' => $vendorId, 'cents' => $cents, 'currency' => strtolower($currency), 'reference' => $reference, 'note' => $note]);
    }

    public function payment(InvoiceNinjaConnection $connection, string $paymentId): ?array
    {
        $this->step($connection);
        $payment = $this->of($connection)['payments'][$paymentId] ?? null;

        return $payment !== null ? ['id' => $paymentId, 'cents' => $payment['cents'], 'invoiceIds' => [$payment['invoiceId']], 'isDeleted' => false] : null;
    }

    public function paidOn(InvoiceNinjaConnection $connection, string $invoiceId): int
    {
        $this->step($connection);

        return (int) collect($this->of($connection)['payments'])->filter(fn (array $p): bool => $p['invoiceId'] === $invoiceId && ($p['completed'] ?? true))->sum(fn (array $p): int => $p['cents'] - $p['refunded']);
    }

    public function createWebhook(InvoiceNinjaConnection $connection, string $url, string $secret): string
    {
        return $this->make($connection, 'webhooks', ['url' => $url, 'secret' => $secret]);
    }

    public function deleteWebhook(InvoiceNinjaConnection $connection, string $id): void
    {
        $this->step($connection, function (array &$company) use ($id): void {
            unset($company['webhooks'][$id]);
        });
    }

    /**
     * The customer pays an invoice (as on Invoice Ninja's portal): the payment's id, for the
     * webhook a test then delivers.
     */
    public function pay(InvoiceNinjaConnection $connection, string $invoiceId, int $cents, bool $completed = true): string
    {
        $clientId = (string) ($this->of($connection)['invoices'][$invoiceId]['clientId'] ?? '');

        return $this->make($connection, 'payments', ['clientId' => $clientId, 'invoiceId' => $invoiceId, 'cents' => $cents, 'refunded' => 0, 'reference' => 'portal', 'completed' => $completed]);
    }

    /** A pending payment (a bank transfer) clears. */
    public function complete(InvoiceNinjaConnection $connection, string $paymentId): void
    {
        $this->step($connection, function (array &$company) use ($paymentId): void {
            $company['payments'][$paymentId]['completed'] = true;
        });
    }

    /** The next call fails as Invoice Ninja would (`unreachable`, `bad_token`...). */
    public function failNext(string $reason): void
    {
        $this->update(function (array &$state) use ($reason): void {
            $state['fail'][] = $reason;
        });
    }

    /** The next call that makes something (of that kind: `payments`, `invoices`...) makes it, then fails to say so. */
    public function loseAnswerOfNext(string $collection = '*'): void
    {
        $this->update(function (array &$state) use ($collection): void {
            $state['lose'] = $collection;
        });
    }

    /**
     * What a company holds (by its connection's token).
     *
     * @return Company
     */
    public function of(InvoiceNinjaConnection $connection): array
    {
        $company = (array) ($this->state()['companies'][hash('sha256', $connection->token)] ?? []);

        /** @var Company $full */
        $full = [...['clients' => [], 'invoices' => [], 'payments' => [], 'vendors' => [], 'expenses' => [], 'webhooks' => []], ...$company];

        return $full;
    }

    /**
     * @param  array<string, mixed>|string  $item
     */
    private function make(InvoiceNinjaConnection $connection, string $collection, array|string $item): string
    {
        $id = ucfirst(substr($collection, 0, 2)).Str::random(8);
        $lose = false;

        $this->step($connection, function (array &$company) use ($collection, $id, $item): void {
            $company[$collection][$id] = $item;
        }, $lose, $collection);

        if ($lose) {
            throw new InvoiceNinjaError(InvoiceNinjaError::UNREACHABLE, 'Timed out (fake).');
        }

        return $id;
    }

    /**
     * @param  (callable(array<string, mixed>): void)|null  $change
     */
    private function step(InvoiceNinjaConnection $connection, ?callable $change = null, bool &$lost = false, ?string $collection = null): void
    {
        $fail = null;
        $this->update(function (array &$state) use ($connection, $change, &$fail, &$lost, $collection): void {
            $fail = array_shift($state['fail']);

            if ($fail !== null) {
                return;
            }

            if (! str_starts_with(trim($connection->token), 'in-')) {
                $fail = InvoiceNinjaError::BAD_TOKEN;

                return;
            }

            if ($change !== null) {
                $key = hash('sha256', $connection->token);
                $company = (array) ($state['companies'][$key] ?? []);
                $company = [...['clients' => [], 'invoices' => [], 'payments' => [], 'vendors' => [], 'expenses' => [], 'webhooks' => []], ...$company];
                $change($company);
                $state['companies'][$key] = $company;
                if ($collection !== null && ($state['lose'] === '*' || $state['lose'] === $collection)) {
                    $lost = true;
                    $state['lose'] = false;
                }
            }
        });

        if ($fail !== null) {
            throw new InvoiceNinjaError($fail);
        }
    }

    /**
     * @return array{companies: array<string, mixed>, fail: list<string>, lose: string|false}
     */
    private function state(): array
    {
        /** @var array{companies: array<string, mixed>, fail: list<string>, lose: string|false} $state */
        $state = [...['companies' => [], 'fail' => [], 'lose' => false], ...(array) Cache::get(self::KEY, [])];

        return $state;
    }

    /**
     * @param  callable(array{companies: array<string, mixed>, fail: list<string>, lose: string|false}): void  $change
     */
    private function update(callable $change): void
    {
        Cache::lock(self::KEY.'-lock', 5)->block(5, function () use ($change): void {
            $state = $this->state();
            $change($state);
            Cache::put(self::KEY, $state, now()->addDays(30));
        });
    }
}
