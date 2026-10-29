<?php

declare(strict_types=1);

namespace App\Actions\Books;

use App\Exceptions\Refusal;
use App\Jobs\BackfillBooks;
use App\Models\AuditLog;
use App\Models\InvoiceNinjaConnection;
use App\Models\InvoiceNinjaRecord;
use App\Models\User;
use App\Services\InvoiceNinja\HttpInvoiceNinja;
use App\Services\InvoiceNinja\InvoiceNinja;
use App\Services\InvoiceNinja\InvoiceNinjaError;
use App\Support\Http\PublicAddress;
use App\Support\Ids;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Connecting Invoice Ninja (docs/invoice-ninja-integration-plan.md): a coach's, or Stackapella's
 * own (`$owner` null, Hillel in admin). The token is checked at once by reading the company; a
 * selfhosted address must be public. Connecting a coach's books records their last 12 months
 * once; reconnecting tries again what waited. Stackapella's own gets the payment webhook that
 * paying by invoice needs. Disconnecting is best-effort at Invoice Ninja and never blocks.
 */
final class BookConnections
{
    public function __construct(private readonly InvoiceNinja $ninja) {}

    public function connect(?User $owner, string $token, ?string $url, ?User $admin = null, ?string $ip = null): InvoiceNinjaConnection
    {
        $url = $url !== null && trim($url) !== '' ? trim($url) : null;
        $base = HttpInvoiceNinja::normalizeApiUrl($url);

        if ($base === false || ($url !== null && ! str_starts_with($base, HttpInvoiceNinja::HOSTED.'/') && ! PublicAddress::allowed($base))) {
            throw ValidationException::withMessages(['url' => __('books.reasons.not_allowed')]);
        }

        $probe = (new InvoiceNinjaConnection)->forceFill(['api_url' => $url, 'token' => trim($token)]);

        try {
            $company = $this->ninja->company($probe);
        } catch (InvoiceNinjaError $e) {
            throw ValidationException::withMessages([$e->reason === InvoiceNinjaError::BAD_TOKEN ? 'token' : 'url' => __('books.reasons.'.$e->reason)]);
        }

        $connection = InvoiceNinjaConnection::query()->firstOrNew(['owner_key' => InvoiceNinjaConnection::ownerKey($owner)]);
        $connection->forceFill([
            'user_id' => $owner?->id,
            'api_url' => $url,
            'token' => trim($token),
            'company_id' => $company['id'],
            'company_name' => mb_substr($company['name'] !== '' ? $company['name'] : 'Invoice Ninja', 0, 190),
            'send_email' => (bool) $connection->send_email,
            'status' => InvoiceNinjaConnection::ACTIVE,
            'last_error' => null,
        ])->save();

        if ($owner === null) {
            $this->webhook($connection);
            AuditLog::record('books.connected', null, $admin, after: ['company' => $connection->company_name], ip: $ip);
        } elseif ($connection->backfilled_at === null) {
            BackfillBooks::dispatch($connection->id);
        }

        Books::retryAll($connection);

        return $connection;
    }

    public function setEmail(User $coach, bool $sendEmail): InvoiceNinjaConnection
    {
        $connection = $this->mine($coach);
        $connection->forceFill(['send_email' => $sendEmail])->save();

        return $connection;
    }

    public function disconnect(?User $owner, ?User $admin = null, ?string $ip = null): void
    {
        $connection = $owner !== null ? $this->mine($owner) : (Books::platformBooks() ?? throw new Refusal(404, 'not_found'));

        // Plus invoices still out would never be seen paid: they're settled (or cancelled) first.
        if ($owner === null && InvoiceNinjaRecord::query()->where('connection_id', $connection->id)->where('kind', InvoiceNinjaRecord::PLUS_INVOICE)->whereIn('status', [InvoiceNinjaRecord::PENDING, InvoiceNinjaRecord::RECORDING, InvoiceNinjaRecord::FAILED, InvoiceNinjaRecord::SENT])->exists()) {
            throw new Refusal(409, 'plus_invoices_open');
        }

        if ($connection->webhook_id !== null && $connection->status === InvoiceNinjaConnection::ACTIVE) {
            try {
                $this->ninja->deleteWebhook($connection, $connection->webhook_id);
            } catch (InvoiceNinjaError $e) {
                // ES's lesson: an install out of reach must never leave someone stuck "connected".
                report($e);
            }
        }

        $connection->delete();

        if ($owner === null) {
            AuditLog::record('books.disconnected', null, $admin, before: ['company' => $connection->company_name], ip: $ip);
        }
    }

    public function mine(User $coach): InvoiceNinjaConnection
    {
        return Books::coachBooks($coach->id) ?? throw new Refusal(404, 'not_found');
    }

    /**
     * The connection as the API shows it (contract/schemas/invoiceNinja.connection.json).
     *
     * @return array<string, mixed>
     */
    public static function view(InvoiceNinjaConnection $connection): array
    {
        $counts = InvoiceNinjaRecord::query()->where('connection_id', $connection->id)->whereIn('status', [InvoiceNinjaRecord::PENDING, InvoiceNinjaRecord::RECORDING, InvoiceNinjaRecord::FAILED, InvoiceNinjaRecord::RECORDED])
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            'companyName' => $connection->company_name,
            'url' => $connection->api_url,
            'sendEmail' => $connection->send_email,
            'status' => $connection->status,
            'problem' => $connection->last_error !== null ? __('books.reasons.'.$connection->last_error) : null,
            'problemCode' => $connection->last_error,
            'recorded' => (int) ($counts[InvoiceNinjaRecord::RECORDED] ?? 0),
            'waiting' => (int) ($counts[InvoiceNinjaRecord::PENDING] ?? 0) + (int) ($counts[InvoiceNinjaRecord::RECORDING] ?? 0),
            'notRecorded' => (int) ($counts[InvoiceNinjaRecord::FAILED] ?? 0),
            'connectedAt' => $connection->created_at?->utc()->toIso8601ZuluString(),
        ];
    }

    /**
     * A connection's rows, newest first: not recorded (the default), waiting, recorded, or all.
     *
     * @return CursorPaginator<int, InvoiceNinjaRecord>
     */
    public static function rows(InvoiceNinjaConnection $connection, ?string $status): CursorPaginator
    {
        return InvoiceNinjaRecord::query()->where('connection_id', $connection->id)
            ->when(match ($status ?? 'not_recorded') {
                'not_recorded' => [InvoiceNinjaRecord::FAILED],
                'waiting' => [InvoiceNinjaRecord::PENDING, InvoiceNinjaRecord::RECORDING],
                'recorded' => [InvoiceNinjaRecord::RECORDED, InvoiceNinjaRecord::SENT, InvoiceNinjaRecord::PAID],
                default => null,
            }, fn ($query, array $statuses) => $query->whereIn('status', $statuses))
            ->orderByDesc('id')->cursorPaginate(50);
    }

    /**
     * Try one row again now: `409 reconnect_required` while the token is refused, `409
     * record_state` unless it's waiting or failed.
     */
    public static function retryIn(InvoiceNinjaConnection $connection, string $sqid): InvoiceNinjaRecord
    {
        $record = InvoiceNinjaRecord::query()->where('connection_id', $connection->id)->whereKey(Ids::decode($sqid) ?? 0)->first() ?? throw new Refusal(404, 'not_found');

        if ($connection->status !== InvoiceNinjaConnection::ACTIVE) {
            throw new Refusal(409, 'reconnect_required');
        }

        if (! in_array($record->status, [InvoiceNinjaRecord::FAILED, InvoiceNinjaRecord::PENDING], true)) {
            throw new Refusal(409, 'record_state');
        }

        Books::retry($record);

        return $record->refresh();
    }

    /**
     * A row as the "not recorded" list shows it.
     *
     * @return array<string, mixed>
     */
    public static function record(InvoiceNinjaRecord $record): array
    {
        return [
            'id' => $record->sqid(),
            'kind' => $record->kind,
            'description' => (string) ($record->data['description'] ?? ($record->kind === InvoiceNinjaRecord::REFUND ? __('books.line.refund') : '')),
            'amountCents' => $record->cents,
            'currency' => $record->currency,
            'status' => $record->status,
            'problem' => $record->error !== null ? __('books.reasons.'.$record->error) : null,
            'problemCode' => $record->error,
            'attempts' => (int) $record->attempts,
            'createdAt' => $record->created_at?->utc()->toIso8601ZuluString(),
        ];
    }

    private function webhook(InvoiceNinjaConnection $connection): void
    {
        if ($connection->webhook_id !== null) {
            try {
                $this->ninja->deleteWebhook($connection, $connection->webhook_id);
            } catch (InvoiceNinjaError $e) {
                report($e);
            }
        }

        $secret = Str::random(40);

        try {
            $id = $this->ninja->createWebhook($connection, route('webhooks.invoice-ninja', ['connection' => $connection->sqid()]), $secret);
            $connection->forceFill(['webhook_id' => $id, 'webhook_secret' => $secret])->save();
        } catch (InvoiceNinjaError $e) {
            // Recording works without it; only paying by invoice waits (admin shows it).
            report($e);
            $connection->forceFill(['webhook_id' => null, 'webhook_secret' => null])->save();
        }
    }
}
