<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public, read-mostly reads carry an ETag and are kept only by the viewer's browser, always
 * checked again; a repeat read with the ETag is a 304. Everything else stays no-store.
 */
final class RevalidateTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_public_read_revalidates_by_etag_and_a_personal_one_is_never_stored(): void
    {
        $first = $this->getJson('/api/billing/prices')->assertOk();
        $etag = (string) $first->headers->get('ETag');
        $this->assertNotSame('', $etag);
        $this->assertSame('no-cache, private', $first->headers->get('Cache-Control'));

        $again = $this->withHeaders(['If-None-Match' => $etag])->getJson('/api/billing/prices');
        $again->assertStatus(304);
        $this->assertSame('', $again->getContent());

        // A changed answer is a 200 with a new ETag.
        $this->withHeaders(['If-None-Match' => '"something-else"'])->getJson('/api/billing/prices')->assertOk();

        $this->flushHeaders();
        $config = $this->getJson('/api/auth/config')->assertOk();
        $this->assertNull($config->headers->get('ETag'));
        $this->assertSame('no-store, private', $config->headers->get('Cache-Control'));
    }
}
