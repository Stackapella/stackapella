<?php

declare(strict_types=1);

namespace Tests\Contract;

use App\Support\Contract\SchemaChecker;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * assertMatchesContract(): a response is what contract/api.md says the operation answers with this
 * status, its `code` is one listed for it (or a general one), and its JSON passes the schema.
 * Every operation's feature tests call it (ContractTest checks they do).
 */
trait AssertsContract
{
    private static ?SchemaChecker $schemaChecker = null;

    /**
     * @param  TestResponse<Response>  $response
     */
    protected function assertMatchesContract(TestResponse $response, string $operation, int $status): void
    {
        $operations = Contract::operations();
        $this->assertArrayHasKey($operation, $operations, "The operation [{$operation}] isn't in contract/api.md.");

        $this->assertSame($status, $response->getStatusCode(), "{$operation}: expected {$status}, got {$response->getStatusCode()}: ".mb_strimwidth((string) $response->getContent(), 0, 500, '...'));

        // CONTRACT_CASES_FILE=path: each answer checked is written down, to find the contract's
        // answers no test reaches (a sweep's tool; normal runs skip it).
        if (is_string($file = getenv('CONTRACT_CASES_FILE')) && $file !== '') {
            $code = json_decode((string) $response->getContent(), true)['code'] ?? '';
            file_put_contents($file, $operation.' '.$status.' '.(is_string($code) ? $code : '')."\n", FILE_APPEND | LOCK_EX);
        }

        if ($status === 204) {
            $this->assertSame('', (string) $response->getContent(), "{$operation}: a 204 has no body.");
            $this->assertNotEmpty(array_filter($operations[$operation]['cases'], static fn (array $case): bool => $case['status'] === 204), "{$operation}: contract/api.md has no 204 case.");

            return;
        }

        $body = json_decode((string) $response->getContent(), false);
        $this->assertNotNull($body, "{$operation} {$status}: the response isn't JSON.");

        $cases = array_values(array_filter($operations[$operation]['cases'], static fn (array $case): bool => $case['status'] === $status));
        $schema = $cases[0]['schema'] ?? null;

        if ($status >= 400) {
            $code = is_object($body) ? ($body->code ?? null) : null;
            $listed = array_values(array_filter(array_column($cases, 'code')));
            $allowed = [...$listed, ...(Contract::GENERAL[$status] ?? []), ...(str_starts_with($operation, 'admin.') ? (Contract::ADMIN[$status] ?? []) : []), ...(str_starts_with($operation, 'beta.') ? (Contract::BETA[$status] ?? []) : [])];

            $this->assertContains($code, $allowed, "{$operation} {$status}: the code \"{$code}\" isn't in contract/api.md for this operation (".implode(', ', $allowed).').');
            $schema = 'error.json';
        }

        $this->assertNotNull($schema, "{$operation}: contract/api.md has no {$status} case.");

        self::$schemaChecker ??= new SchemaChecker(Contract::directory().'/schemas');
        $errors = self::$schemaChecker->check($body, $schema);

        $this->assertSame([], $errors, "{$operation} {$status} doesn't match {$schema}:\n".implode("\n", $errors));
    }
}
