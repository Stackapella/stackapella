<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Books;

use App\Actions\Books\BookConnections;
use App\Actions\Books\Books;
use App\Actions\Coaching\CoachSetup;
use App\Http\Controllers\Controller;
use App\Http\Requests\Books\ConnectBooksRequest;
use App\Http\Requests\Books\ListRecordsRequest;
use App\Http\Requests\Books\UpdateBooksRequest;
use App\Models\InvoiceNinjaConnection;
use App\Models\InvoiceNinjaRecord;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * invoiceNinja.*: a coach's own books in Invoice Ninja (docs/invoice-ninja-integration-plan.md).
 */
final class BooksController extends Controller
{
    public function __construct(private readonly BookConnections $connections, private readonly CoachSetup $setup) {}

    public function show(#[CurrentUser] User $user): JsonResponse
    {
        $this->setup->profile($user);
        $connection = Books::coachBooks($user->id);

        return response()->json(['data' => $connection !== null ? BookConnections::view($connection) : null]);
    }

    public function connect(ConnectBooksRequest $request, #[CurrentUser] User $user): JsonResponse
    {
        $this->setup->profile($user);
        $url = $request->validated('url');

        return response()->json(['data' => BookConnections::view($this->connections->connect($user, (string) $request->validated('token'), is_string($url) ? $url : null))]);
    }

    public function update(UpdateBooksRequest $request, #[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => BookConnections::view($this->connections->setEmail($user, $request->boolean('sendEmail')))]);
    }

    public function destroy(#[CurrentUser] User $user): Response
    {
        $this->connections->disconnect($user);

        return response()->noContent();
    }

    public function records(ListRecordsRequest $request, #[CurrentUser] User $user): JsonResponse
    {
        return self::page($this->connections->mine($user), $request);
    }

    public function retry(string $record, #[CurrentUser] User $user): JsonResponse
    {
        return self::retryIn($this->connections->mine($user), $record);
    }

    public static function page(InvoiceNinjaConnection $connection, ListRecordsRequest $request): JsonResponse
    {
        $status = $request->validated('status');
        $page = BookConnections::rows($connection, is_string($status) ? $status : null);

        return response()->json([
            'data' => collect($page->items())->map(fn (InvoiceNinjaRecord $r): array => BookConnections::record($r))->values()->all(),
            'meta' => ['nextCursor' => $page->nextCursor()?->encode()],
        ]);
    }

    public static function retryIn(InvoiceNinjaConnection $connection, string $sqid): JsonResponse
    {
        return response()->json(['data' => BookConnections::record(BookConnections::retryIn($connection, $sqid))]);
    }
}
