<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Books\BookConnections;
use App\Actions\Books\Books;
use App\Actions\Books\PlusInvoices;
use App\Exceptions\Refusal;
use App\Http\Controllers\Api\Books\BooksController as CoachBooks;
use App\Http\Controllers\Controller;
use App\Http\Requests\Books\ConnectBooksRequest;
use App\Http\Requests\Books\CreatePlusInvoiceRequest;
use App\Http\Requests\Books\ListRecordsRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * admin.books.*: Stackapella's own books in Invoice Ninja, and Plus paid by invoice.
 */
final class BooksController extends Controller
{
    public function __construct(private readonly BookConnections $connections) {}

    public function show(): JsonResponse
    {
        $connection = Books::platformBooks();

        return response()->json(['data' => $connection !== null ? [...BookConnections::view($connection), 'paymentsWebhook' => $connection->webhook_id !== null] : null]);
    }

    public function connect(ConnectBooksRequest $request, #[CurrentUser] User $admin): JsonResponse
    {
        $url = $request->validated('url');
        $connection = $this->connections->connect(null, (string) $request->validated('token'), is_string($url) ? $url : null, $admin, $request->ip());

        return response()->json(['data' => [...BookConnections::view($connection), 'paymentsWebhook' => $connection->webhook_id !== null]]);
    }

    public function destroy(Request $request, #[CurrentUser] User $admin): Response
    {
        $this->connections->disconnect(null, $admin, $request->ip());

        return response()->noContent();
    }

    public function records(ListRecordsRequest $request): JsonResponse
    {
        return CoachBooks::page(Books::platformBooks() ?? throw new Refusal(404, 'not_found'), $request);
    }

    public function retry(Request $request, string $record, #[CurrentUser] User $admin): JsonResponse
    {
        $retried = CoachBooks::retryIn(Books::platformBooks() ?? throw new Refusal(404, 'not_found'), $record);
        AuditLog::record('books.retried', null, $admin, after: ['record' => $record], ip: $request->ip());

        return $retried;
    }

    public function invoice(CreatePlusInvoiceRequest $request, #[CurrentUser] User $admin, PlusInvoices $invoices): JsonResponse
    {
        $record = $invoices->create($admin, (string) $request->validated('personId'), (int) $request->validated('amountCents'), (string) $request->validated('currency'), (int) $request->validated('months'), $request->ip());

        return response()->json(['data' => BookConnections::record($record)], 201);
    }
}
