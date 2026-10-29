<?php

declare(strict_types=1);

namespace App\Services\InvoiceNinja;

use App\Models\InvoiceNinjaConnection;

/**
 * What Stackapella asks of an Invoice Ninja company (docs/invoice-ninja-integration-plan.md), in
 * whole cents. The real one is HttpInvoiceNinja (a port of Event Schedule's
 * app/Utils/InvoiceNinja.php); FakeInvoiceNinja stands in for the tests and, locally, the demo.
 * Failures throw InvoiceNinjaError. Every "find" lets a retry find what an earlier try made.
 *
 * @phpstan-type Line array{description: string, cents: int}
 */
interface InvoiceNinja
{
    /**
     * @return array{id: string, name: string}
     */
    public function company(InvoiceNinjaConnection $connection): array;

    public function findClient(InvoiceNinjaConnection $connection, string $email, string $currency): ?string;

    public function createClient(InvoiceNinjaConnection $connection, string $name, string $email, string $currency): string;

    /**
     * An invoice by our reference (its `po_number`), with the payment applied to it, if any.
     *
     * @return array{id: string, paymentId: string|null}|null
     */
    public function findInvoice(InvoiceNinjaConnection $connection, string $poNumber): ?array;

    /**
     * @param  list<Line>  $lines
     */
    public function createInvoice(InvoiceNinjaConnection $connection, string $clientId, array $lines, string $poNumber, string $date, bool $sendEmail): string;

    public function createPayment(InvoiceNinjaConnection $connection, string $clientId, string $invoiceId, int $cents, string $date, string $reference): string;

    /**
     * What has been refunded on a payment so far.
     */
    public function refundedOn(InvoiceNinjaConnection $connection, string $paymentId): int;

    public function refund(InvoiceNinjaConnection $connection, string $paymentId, string $invoiceId, int $cents, string $date): void;

    public function vendor(InvoiceNinjaConnection $connection, string $name): string;

    public function findExpense(InvoiceNinjaConnection $connection, string $reference): ?string;

    public function createExpense(InvoiceNinjaConnection $connection, string $vendorId, int $cents, string $currency, string $date, string $note, string $reference): string;

    /**
     * A payment as Invoice Ninja has it (for paying by invoice: never the webhook's word).
     *
     * @return array{id: string, cents: int, invoiceIds: list<string>, isDeleted: bool}|null
     */
    public function payment(InvoiceNinjaConnection $connection, string $paymentId): ?array;

    /**
     * How much of an invoice has been paid by payments that completed (not pending, failed,
     * voided or refunded), as Invoice Ninja has it.
     */
    public function paidOn(InvoiceNinjaConnection $connection, string $invoiceId): int;

    public function createWebhook(InvoiceNinjaConnection $connection, string $url, string $secret): string;

    public function deleteWebhook(InvoiceNinjaConnection $connection, string $id): void;
}
